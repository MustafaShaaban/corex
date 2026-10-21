/**
 * The measurements the quality checks take inside a page (spec 116).
 *
 * `installQualityHelpers` is handed to `page.addInitScript`, so it runs in the page before the
 * page's own scripts and leaves `window.__corexQuality`. Everything in it is plain DOM, written
 * to be read: each function returns plain objects a spec turns into findings.
 *
 * The scope is CoreX's own markup: inside `.corex-admin` on an admin screen (WordPress's menu and
 * toolbar are WordPress's), the whole document on a front-end page.
 */
function installQualityHelpers() {
	const CODE_TAGS =
		'code, kbd, samp, pre, script, style, noscript, template, textarea, svg';

	const textSignature = ( text ) =>
		text
			.replace( /\d+/g, '#' )
			.replace( /\s+/g, ' ' )
			.trim()
			.slice( 0, 120 );

	/**
	 * What an element is, never where it sits: tag, type, role, class names without digits.
	 * @param {Element} element An element of the page.
	 */
	const signature = ( element ) => {
		if ( ! element || element.nodeType !== 1 ) {
			return 'document';
		}

		const parts = [ element.tagName.toLowerCase() ];
		const type = element.getAttribute( 'type' );
		const role = element.getAttribute( 'role' );

		if ( type ) {
			parts.push( `[type=${ type }]` );
		}

		if ( role ) {
			parts.push( `[role=${ role }]` );
		}

		const classes = [ ...element.classList ]
			.filter(
				( name ) => ! /\d/.test( name ) && ! /^(is|has)-/.test( name )
			)
			.slice( 0, 3 );

		if ( classes.length > 0 ) {
			return parts.join( '' ) + '.' + classes.join( '.' );
		}

		return element.parentElement && element.parentElement !== document.body
			? `${ signature( element.parentElement ) } > ${ parts.join( '' ) }`
			: parts.join( '' );
	};

	const root = () =>
		document.querySelector( '.corex-admin' ) || document.body;

	// `checkVisibility` also answers false inside a closed <details>, whose content Chrome skips
	// laying out and reports at the origin, where it would seem to overlap everything else there.
	const isRendered = ( element ) => {
		const rect = element.getBoundingClientRect();

		return (
			element.checkVisibility( {
				contentVisibilityAuto: true,
				opacityProperty: true,
				visibilityProperty: true,
			} ) &&
			rect.width > 0 &&
			rect.height > 0 &&
			! element.closest( '[hidden], [aria-hidden="true"], [inert]' )
		);
	};

	/**
	 * Visually hidden for screen readers: one pixel, clipped. Not text a person sees.
	 * @param {Element} element An element of the page.
	 */
	const isScreenReaderOnly = ( element ) => {
		const rect = element.getBoundingClientRect();

		return (
			rect.width <= 2 ||
			rect.height <= 2 ||
			window.getComputedStyle( element ).clipPath === 'inset(50%)' ||
			window.getComputedStyle( element ).clip ===
				'rect(0px, 0px, 0px, 0px)'
		);
	};

	const scrollsItsOwnContent = ( element ) =>
		[ 'auto', 'scroll', 'hidden', 'clip' ].includes(
			window.getComputedStyle( element ).overflowX
		);

	/** The page scrolls sideways: by how much, and the deepest elements that stick out. */
	const overflow = () => {
		const page = document.documentElement;
		const width = page.clientWidth;
		const by = page.scrollWidth - width;
		const culprits = [];

		if ( by > 0 ) {
			for ( const element of document.body.querySelectorAll( '*' ) ) {
				const rect = element.getBoundingClientRect();
				const sticksOut = rect.right > width + 0.5 || rect.left < -0.5;

				if ( ! sticksOut || ! isRendered( element ) ) {
					continue;
				}

				let clipped = false;
				for (
					let parent = element.parentElement;
					parent && parent !== document.body;
					parent = parent.parentElement
				) {
					if ( scrollsItsOwnContent( parent ) ) {
						clipped = true;
						break;
					}
				}

				const childSticksOut = [ ...element.children ].some(
					( child ) => {
						const box = child.getBoundingClientRect();
						return box.right > width + 0.5 || box.left < -0.5;
					}
				);

				if ( ! clipped && ! childSticksOut ) {
					culprits.push( {
						signature: signature( element ),
						left: Math.round( rect.left ),
						right: Math.round( rect.right ),
					} );
				}
			}
		}

		return {
			by,
			width,
			scrollWidth: page.scrollWidth,
			culprits: culprits.slice( 0, 5 ),
		};
	};

	/** The visible text nodes in CoreX's markup, outside what is shown on purpose as code. */
	const visibleTexts = () => {
		const texts = [];
		const walker = document.createTreeWalker(
			root(),
			window.NodeFilter.SHOW_TEXT
		);

		for ( let node = walker.nextNode(); node; node = walker.nextNode() ) {
			const text = node.nodeValue.replace( /\s+/g, ' ' ).trim();
			const parent = node.parentElement;

			if ( text === '' || ! parent || parent.closest( CODE_TAGS ) ) {
				continue;
			}

			if ( ! isRendered( parent ) || isScreenReaderOnly( parent ) ) {
				continue;
			}

			texts.push( { text, element: parent } );
		}

		return texts;
	};

	const FILE_OR_HOST =
		/\.(com|org|net|io|dev|test|local|example|php|js|mjs|css|scss|json|zip|html?|pot?|mo|txt|csv|xml|pdf|png|jpe?g|gif|svg|webp|avif|md|ini|log|yml|yaml)$/i;

	/** Developer forms in visible text (US-NFR-06): what a person should never have to read. */
	const RAW_KEY_RULES = [
		{
			rule: 'snake-case-key',
			pattern: /\b[a-z][a-z0-9]*(?:_[a-z0-9]+)+\b/g,
		},
		// Each part two characters or more, so "e.g." and "i.e." are not keys.
		{
			rule: 'dotted-key',
			pattern: /\b[a-z][a-z0-9_]+(?:\.[a-z][a-z0-9_]+)+\b/g,
		},
		// The whole text is one lowercase word and a colon: "flow_owner:", not "Try a search:".
		{ rule: 'key-without-value', pattern: /^[a-z][a-z0-9_]*:$/g },
		{
			rule: 'machine-timestamp',
			pattern:
				/\b\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2})?(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?\b|\b1[6-9]\d{8}\b/g,
		},
	];

	const rawKeys = () => {
		const found = [];

		for ( const { text, element } of visibleTexts() ) {
			for ( const { rule, pattern } of RAW_KEY_RULES ) {
				for ( const match of text.matchAll( pattern ) ) {
					const value = match[ 0 ].trim();
					const around = text.slice(
						Math.max( 0, match.index - 1 ),
						match.index + match[ 0 ].length + 1
					);

					// An address, a file name, a domain or a version is not a key.
					if (
						rule === 'dotted-key' &&
						( FILE_OR_HOST.test( value ) || /[@/:]/.test( around ) )
					) {
						continue;
					}

					if ( rule === 'snake-case-key' && /@/.test( around ) ) {
						continue;
					}

					found.push( {
						rule,
						text: value,
						context: text.slice( 0, 160 ),
						signature: textSignature( value ),
						element: signature( element ),
					} );
				}
			}
		}

		return found;
	};

	/** Controls a thumb presses (US-NFR-04): submit, toggles, pagination, close, row actions. */
	const PRIMARY_CONTROLS = [
		'button[type="submit"]',
		'input[type="submit"]',
		'[aria-expanded]',
		'[aria-label*="close" i]',
		'.tablenav-pages a',
		'[class*="pagination"] a',
		'[class*="pagination"] button',
		'[class*="row-actions"] a',
		'[class*="row-actions"] button',
		'.wp-block-navigation__responsive-container-open',
		'.wp-block-navigation__responsive-container-close',
	].join( ', ' );

	const phone = () => {
		const width = document.documentElement.clientWidth;
		const scope = root();
		const tables = [];
		const targets = [];

		for ( const table of scope.querySelectorAll(
			'table, [role="table"], [role="grid"], [role="list"]'
		) ) {
			const rect = table.getBoundingClientRect();

			if ( isRendered( table ) && rect.width > width + 0.5 ) {
				tables.push( {
					signature: signature( table ),
					width: Math.round( rect.width ),
				} );
			}
		}

		for ( const control of scope.querySelectorAll( PRIMARY_CONTROLS ) ) {
			const rect = control.getBoundingClientRect();

			if (
				isRendered( control ) &&
				! isScreenReaderOnly( control ) &&
				( rect.width < 44 || rect.height < 44 )
			) {
				targets.push( {
					signature: signature( control ),
					size: `${ Math.round( rect.width ) }x${ Math.round( rect.height ) }`,
					name: (
						control.getAttribute( 'aria-label' ) ||
						control.textContent ||
						control.value ||
						''
					)
						.trim()
						.slice( 0, 60 ),
				} );
			}
		}

		// The rail (CX-SHL-04): at most one of its controls before the screen's heading.
		const heading = scope.querySelector( 'h1' );
		const rail = [
			...scope.querySelectorAll(
				'.corex-admin__nav a, .corex-admin__nav button'
			),
		].filter( isRendered );
		const railBeforeHeading = heading
			? rail.filter(
					( link ) =>
						link.getBoundingClientRect().top <
						heading.getBoundingClientRect().top
				).length
			: 0;

		return { tables, targets, railBeforeHeading };
	};

	/** Text at 200% (US-NFR-04): what is cut off, and text that lands on other text. */
	const textAt200 = () => {
		const page = document.documentElement;
		const before = page.style.fontSize;
		page.style.fontSize = '200%';

		const clipped = [];
		const boxes = [];

		for ( const { text, element } of visibleTexts() ) {
			const style = window.getComputedStyle( element );
			const cuts =
				( [ 'hidden', 'clip' ].includes( style.overflowX ) &&
					element.scrollWidth > element.clientWidth + 1 ) ||
				( [ 'hidden', 'clip' ].includes( style.overflowY ) &&
					element.scrollHeight > element.clientHeight + 1 );

			if ( cuts ) {
				clipped.push( {
					signature: signature( element ),
					text: text.slice( 0, 60 ),
				} );
			}

			boxes.push( {
				element,
				rect: element.getBoundingClientRect(),
				text,
			} );
		}

		const overlaps = [];

		for ( let a = 0; a < boxes.length; a++ ) {
			for ( let b = a + 1; b < boxes.length; b++ ) {
				const one = boxes[ a ];
				const two = boxes[ b ];

				if (
					one.element.contains( two.element ) ||
					two.element.contains( one.element )
				) {
					continue;
				}

				const across =
					Math.min( one.rect.right, two.rect.right ) -
					Math.max( one.rect.left, two.rect.left );
				const down =
					Math.min( one.rect.bottom, two.rect.bottom ) -
					Math.max( one.rect.top, two.rect.top );

				if ( across > 2 && down > 2 ) {
					overlaps.push( {
						signature: `${ signature( one.element ) } × ${ signature( two.element ) }`,
						text: `"${ one.text.slice( 0, 30 ) }" over "${ two.text.slice( 0, 30 ) }"`,
					} );
				}
			}
		}

		page.style.fontSize = before;

		return { clipped, overlaps: overlaps.slice( 0, 20 ) };
	};

	/** Animations that repeat forever while nothing is loading (US-NFR-07). */
	const restingAnimations = () =>
		document
			.getAnimations()
			.filter( ( animation ) => animation.playState === 'running' )
			.filter(
				( animation ) =>
					animation.effect?.getComputedTiming().iterations ===
					Infinity
			)
			.map( ( animation ) => animation.effect.target )
			.filter(
				( target ) =>
					target &&
					! target.closest(
						'[aria-busy="true"], [data-corex-state="loading"]'
					)
			)
			.map( ( target ) => ( { signature: signature( target ) } ) );

	/**
	 * An RGB triple and alpha from a computed colour, or null for one this cannot read.
	 * @param {string} value A computed colour.
	 */
	const parseColor = ( value ) => {
		const alpha = ( raw ) => {
			if ( raw === undefined ) {
				return 1;
			}
			return raw.endsWith( '%' )
				? parseFloat( raw ) / 100
				: parseFloat( raw );
		};

		const rgb = value.match(
			/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:\s*[,/]\s*([\d.]+%?))?\s*\)/
		);

		if ( rgb ) {
			return {
				r: +rgb[ 1 ],
				g: +rgb[ 2 ],
				b: +rgb[ 3 ],
				a: alpha( rgb[ 4 ] ),
			};
		}

		const srgb = value.match(
			/color\(srgb\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)(?:\s*\/\s*([\d.]+%?))?\s*\)/
		);

		if ( srgb ) {
			return {
				r: srgb[ 1 ] * 255,
				g: srgb[ 2 ] * 255,
				b: srgb[ 3 ] * 255,
				a: alpha( srgb[ 4 ] ),
			};
		}

		return null;
	};

	const luminance = ( { r, g, b } ) => {
		const channel = ( value ) => {
			const v = value / 255;
			return v <= 0.04045 ? v / 12.92 : ( ( v + 0.055 ) / 1.055 ) ** 2.4;
		};
		return (
			0.2126 * channel( r ) +
			0.7152 * channel( g ) +
			0.0722 * channel( b )
		);
	};

	const ratio = ( one, two ) => {
		const [ light, dark ] = [ luminance( one ), luminance( two ) ].sort(
			( x, y ) => y - x
		);
		return ( light + 0.05 ) / ( dark + 0.05 );
	};

	/**
	 * The colour painted behind an element: the nearest ancestor with an opaque background.
	 * @param {Element} element An element of the page.
	 */
	const backgroundBehind = ( element ) => {
		for (
			let node = element.parentElement;
			node;
			node = node.parentElement
		) {
			const color = parseColor(
				window.getComputedStyle( node ).backgroundColor
			);
			if ( color && color.a > 0.5 ) {
				return color;
			}
		}
		return { r: 255, g: 255, b: 255, a: 1 };
	};

	const indicatorOf = ( before, after ) => {
		if (
			after.outlineStyle !== 'none' &&
			parseFloat( after.outlineWidth ) >= 1
		) {
			return { kind: 'outline', color: after.outlineColor };
		}
		if (
			after.boxShadow !== 'none' &&
			after.boxShadow !== before.boxShadow
		) {
			return {
				kind: 'box-shadow',
				color:
					( after.boxShadow.match(
						/(rgba?\([^)]*\)|color\([^)]*\))/
					) || [] )[ 1 ] || '',
			};
		}
		if ( after.borderColor !== before.borderColor ) {
			return { kind: 'border', color: after.borderColor };
		}
		if ( after.backgroundColor !== before.backgroundColor ) {
			return { kind: 'background', color: after.backgroundColor };
		}
		if ( after.textDecorationLine !== before.textDecorationLine ) {
			return { kind: 'underline', color: after.color };
		}
		return null;
	};

	const FOCUS_STYLE = [
		'outlineStyle',
		'outlineWidth',
		'outlineColor',
		'boxShadow',
		'borderColor',
		'backgroundColor',
		'textDecorationLine',
		'color',
	];
	const styleOf = ( element ) => {
		const style = window.getComputedStyle( element );
		return Object.fromEntries(
			FOCUS_STYLE.map( ( name ) => [ name, style[ name ] ] )
		);
	};

	const TABBABLE =
		'a[href], button, input:not([type="hidden"]), select, textarea, summary, [tabindex]:not([tabindex="-1"]), [contenteditable="true"]';

	/**
	 * Every stop a keyboard reaches (US-NFR-03), in document order: is it visible when it has
	 * focus, and does focus show with 3:1 against what is behind it. Run after one real Tab
	 * press, so the browser treats the focus as keyboard focus (`:focus-visible`).
	 * @param {number} limit How many stops to take at most.
	 */
	const focusWalk = ( limit ) => {
		const found = [];
		const stops = [ ...root().querySelectorAll( TABBABLE ) ]
			.filter(
				( element ) => ! element.disabled && element.tabIndex >= 0
			)
			.filter( ( element ) =>
				element.checkVisibility( { visibilityProperty: true } )
			)
			.filter(
				( element ) =>
					! element.closest( '[inert], [aria-hidden="true"]' )
			)
			.slice( 0, limit );

		for ( const element of stops ) {
			const before = styleOf( element );
			element.focus();

			if ( element.ownerDocument.activeElement !== element ) {
				continue;
			}

			element.scrollIntoView( { block: 'center', inline: 'center' } );
			const after = styleOf( element );
			const rect = element.getBoundingClientRect();
			const name = (
				element.getAttribute( 'aria-label' ) ||
				element.textContent ||
				element.value ||
				element.name ||
				''
			)
				.trim()
				.replace( /\s+/g, ' ' )
				.slice( 0, 50 );

			if (
				rect.width < 2 ||
				rect.height < 2 ||
				Number( window.getComputedStyle( element ).opacity ) === 0
			) {
				found.push( {
					rule: 'focus-stop-invisible',
					signature: signature( element ),
					name,
				} );
			} else {
				const indicator = indicatorOf( before, after );
				const color = indicator ? parseColor( indicator.color ) : null;

				if ( ! indicator ) {
					found.push( {
						rule: 'no-focus-indicator',
						signature: signature( element ),
						name,
					} );
				} else if (
					color &&
					color.a > 0 &&
					ratio( color, backgroundBehind( element ) ) < 3
				) {
					found.push( {
						rule: 'focus-indicator-under-3-to-1',
						signature: signature( element ),
						name: `${ name } (${ indicator.kind } ${ ratio( color, backgroundBehind( element ) ).toFixed( 2 ) }:1)`,
					} );
				}
			}

			element.blur();
		}

		return { stops: stops.length, found };
	};

	window.__corexQuality = {
		signature,
		textSignature,
		isRendered,
		overflow,
		rawKeys,
		phone,
		textAt200,
		restingAnimations,
		focusWalk,
	};
}

module.exports = { installQualityHelpers };
