/**
 * Margin, padding and colour on the CoreX blocks (spec 136, User Story 1 scenario 6, FR-007 and
 * FR-008).
 *
 * The supports, over every `block.json` under `plugins/` and `addons/`, found by search:
 * - every block a person can insert supports a top and bottom margin (a block with `parent` or
 *   `ancestor`, or `inserter: false`, is not one a person inserts);
 * - the section blocks, stat and alert support padding by axis, never one physical side;
 * - the section blocks and stat support a background and a text colour, and no gradient or
 *   link colour.
 *
 * The styles, compiled from each one's `style.scss`: under `.has-text-color`, every element the
 * sheet colours takes the chosen colour (`color: inherit`), every inner surface it paints drops
 * its background so the text sits on the one it was chosen against, and every heading follows
 * its section (DECISIONS #635). Controls that paint their own fill keep it (KEEPS, below). The
 * block's root needs no rule: WordPress's colour classes are `!important` and a custom colour is
 * an inline style, so they beat the sheet as long as the sheet marks none of its colours
 * `!important`, which is checked too.
 */
const fs = require( 'fs' );
const path = require( 'path' );
// Sass and PostCSS come with @wordpress/scripts, which compiles these sheets for the build.
/* eslint-disable import/no-extraneous-dependencies */
const sass = require( 'sass' );
const postcss = require( 'postcss' );
/* eslint-enable import/no-extraneous-dependencies */
const { findFiles } = require( '../packages/build-tools/parity' );

const ROOT = path.resolve( __dirname, '..' );

/** FR-004's section blocks. */
const SECTIONS = [
	'hero',
	'cta',
	'team',
	'posts',
	'gallery',
	'tabs',
	'accordion',
	'carousel',
	'newsletter-signup',
	'jobs',
	'projects',
	'testimonial',
	'pricing',
].map( ( slug ) => `corex/${ slug }` );

const PADDED = [ ...SECTIONS, 'corex/stat', 'corex/alert' ];
const COLOURED = [ ...SECTIONS, 'corex/stat' ];
const PHYSICAL = [ 'top', 'right', 'bottom', 'left' ];

/**
 * The controls that paint their own fill and keep their colours inside a chosen text colour:
 * their text is drawn on their own background, not the section's. By compiled selector (Sass
 * drops the quotes of an attribute's value).
 */
const KEEPS = {
	'corex/hero': [ '.corex-hero__cta' ],
	'corex/cta': [ '.corex-cta__button' ],
	'corex/pricing': [ '.corex-pricing__cta' ],
	'corex/tabs': [
		// The tab shown: its own surface marks which one it is.
		'.corex-tabs:not(.is-editing) .corex-tabs__radio:checked + .corex-tabs__label',
	],
	'corex/carousel': [
		'.corex-carousel__arrow',
		'.corex-carousel__arrow:hover',
		'.corex-carousel__dot',
		'.corex-carousel__dot[aria-current=true]',
	],
	'corex/newsletter-signup': [
		'.corex-newsletter-signup__email',
		'.corex-newsletter-signup__submit',
		// A success or an error says so in its own colour.
		'.corex-newsletter-signup__status[data-state=success]',
		'.corex-newsletter-signup__status[data-state=error]',
	],
};

/** The list blocks print each title as a link, which the theme colours as a link. */
const TITLE_LINKS = [ 'corex/posts', 'corex/jobs', 'corex/projects' ];

const blocks = findFiles(
	[ path.join( ROOT, 'plugins' ), path.join( ROOT, 'addons' ) ],
	'block.json'
).map( ( file ) => ( {
	file,
	metadata: JSON.parse( fs.readFileSync( file, 'utf8' ) ),
} ) );

const byName = new Map(
	blocks.map( ( block ) => [ block.metadata.name, block ] )
);

/**
 * @param {Object} metadata A block's `block.json`.
 * @return {boolean} Whether a person can insert the block (WordPress's rules).
 */
function insertable( metadata ) {
	return (
		! metadata.parent &&
		! metadata.ancestor &&
		metadata.supports?.inserter !== false
	);
}

/**
 * The specificity of one selector, as `[ids, classes, elements]`. `:where()` counts nothing;
 * `:not()` and `:is()` count what they hold (enough for the selectors of these sheets).
 *
 * @param {string} selector One compiled selector.
 * @return {Array<number>} Its specificity.
 */
function specificity( selector ) {
	let rest = selector.replace( /:where\((?:[^()]|\([^()]*\))*\)/g, ' ' );
	rest = rest.replace( /:(?:not|is)\(/g, ' ' ).replace( /\)/g, ' ' );
	const count = ( pattern ) => {
		const found = rest.match( pattern ) || [];
		rest = rest.replace( pattern, ' ' );

		return found.length;
	};
	const attributes = count( /\[[^\]]*\]/g );
	const pseudoElements = count( /::[a-z-]+/gi );
	const ids = count( /#[\w-]+/g );
	const classes = count( /\.[\w-]+/g ) + count( /:[a-z-]+/gi );
	const elements = count( /[a-z][a-z0-9-]*/gi );

	return [ ids, classes + attributes, elements + pseudoElements ];
}

/**
 * @param {Array<number>} a A specificity.
 * @param {Array<number>} b Another.
 * @return {number} Below 0 when a is lower, above 0 when higher, 0 when equal.
 */
function compare( a, b ) {
	return a[ 0 ] - b[ 0 ] || a[ 1 ] - b[ 1 ] || a[ 2 ] - b[ 2 ];
}

/**
 * @param {string} selector One compiled selector.
 * @return {string} Its last compound, after the last combinator.
 */
function lastCompound( selector ) {
	const parts = selector
		.replace( /\([^()]*\)/g, ( inner ) => inner.replace( /[\s>+~]/g, '_' ) )
		.split( /\s*[\s>+~]\s*/ );
	const last = parts[ parts.length - 1 ];

	return selector.slice( selector.length - last.length );
}

/**
 * The rules of a compiled sheet, one entry per selector, in order.
 *
 * @param {string} css Compiled CSS.
 * @return {Array<Object>} `{ selector, index, decls }`, `decls` by property.
 */
function rulesOf( css ) {
	const rules = [];
	postcss.parse( css ).walkRules( ( rule ) => {
		const decls = {};
		const important = [];
		rule.each( ( node ) => {
			if ( node.type === 'decl' ) {
				decls[ node.prop ] = node.value;
				if ( node.important ) {
					important.push( node.prop );
				}
			}
		} );
		rule.selectors.forEach( ( selector ) =>
			rules.push( {
				selector,
				index: rules.length,
				decls,
				important,
			} )
		);
	} );

	return rules;
}

/**
 * What a block's compiled sheet breaks of FR-008.
 *
 * @param {string}        name  The block's name (`corex/hero`).
 * @param {string}        css   Its compiled sheet.
 * @param {Array<string>} keeps The selectors of its controls that keep their colours.
 * @return {Array<string>} One line per problem, naming the block and the selector.
 */
function colourFailures( name, css, keeps = [] ) {
	const slug = name.replace( /^corex\//, '' );
	const root = `.corex-${ slug }`;
	const chosen = `${ root }.has-text-color`;
	const rules = rulesOf( css );
	const failures = [];

	rules
		.filter( ( rule ) => rule.important.some( isColourProp ) )
		.forEach( ( rule ) =>
			failures.push(
				`${ name }: ${ rule.selector } marks a colour !important, which beats the one an editor chooses`
			)
		);

	const overrides = rules.filter( ( rule ) =>
		rule.selector.includes( chosen )
	);

	const hasHeadingRule = overrides.some(
		( rule ) =>
			/:where\(h1, h2, h3, h4, h5, h6\)$/.test( rule.selector ) &&
			rule.decls.color === 'inherit'
	);
	if ( ! hasHeadingRule ) {
		failures.push(
			`${ name }: has no ${ chosen } :where(h1, h2, h3, h4, h5, h6) { color: inherit } rule, so a heading does not follow its section`
		);
	}

	rules
		.filter(
			( rule ) =>
				! rule.selector.includes( '.has-text-color' ) &&
				! keeps.includes( rule.selector )
		)
		.forEach( ( rule ) => {
			const target = elementClassOf(
				lastCompound( rule.selector ),
				slug
			);
			if ( target === null ) {
				return;
			}
			const wanted = [];
			if ( paints( rule.decls.color ) ) {
				wanted.push( [
					'color',
					[ 'inherit' ],
					'its text does not take a chosen text colour',
				] );
			}
			const background =
				rule.decls[ 'background-color' ] ?? rule.decls.background;
			if ( paints( background ) ) {
				wanted.push( [
					'background',
					[ 'transparent' ],
					'its surface stays under a chosen text colour',
				] );
			}

			wanted.forEach( ( [ prop, values, problem ] ) => {
				const beaten = overrides.some(
					( override ) =>
						elementClassOf(
							lastCompound( override.selector ),
							slug
						) === target &&
						values.includes(
							prop === 'color'
								? override.decls.color
								: ( override.decls[ 'background-color' ] ??
										override.decls.background )
						) &&
						( compare(
							specificity( override.selector ),
							specificity( rule.selector )
						) > 0 ||
							( compare(
								specificity( override.selector ),
								specificity( rule.selector )
							) === 0 &&
								override.index > rule.index ) )
				);
				if ( ! beaten ) {
					failures.push(
						`${ name }: ${ rule.selector } sets ${ prop } and ${ problem }`
					);
				}
			} );
		} );

	return failures;
}

/**
 * @param {string} prop A property.
 * @return {boolean} Whether it sets a colour.
 */
function isColourProp( prop ) {
	return [ 'color', 'background', 'background-color' ].includes( prop );
}

/**
 * @param {string|undefined} value A colour value.
 * @return {boolean} Whether it paints a colour of the sheet's own.
 */
function paints( value ) {
	return (
		value !== undefined &&
		! [ 'inherit', 'transparent', 'currentcolor' ].includes(
			value.toLowerCase()
		)
	);
}

/**
 * @param {string} compound A compound selector.
 * @param {string} slug     The block's slug.
 * @return {?string} The element class of the block it targets (`corex-hero__title`), or null.
 */
function elementClassOf( compound, slug ) {
	const match = compound.match(
		new RegExp( `\\.(corex-${ slug }__[a-z0-9-]+)(?![\\w-])` )
	);

	return match ? match[ 1 ] : null;
}

/**
 * @param {Object} block A block, as found above.
 * @return {string} Its compiled `style.scss`.
 */
function compiledSheet( block ) {
	const source = path.join( path.dirname( block.file ), 'style.scss' );

	return sass.compile( source, { style: 'expanded' } ).css;
}

describe( 'FR-007: margin and padding', () => {
	test( 'finds the framework blocks, and every named block exists', () => {
		expect( blocks.length ).toBeGreaterThan( 0 );
		expect( PADDED.filter( ( name ) => ! byName.has( name ) ) ).toEqual(
			[]
		);
	} );

	test( 'every block a person can insert supports a top and bottom margin', () => {
		const missing = blocks
			.filter( ( { metadata } ) => insertable( metadata ) )
			.filter(
				( { metadata } ) =>
					JSON.stringify( metadata.supports?.spacing?.margin ) !==
					JSON.stringify( [ 'top', 'bottom' ] )
			)
			.map( ( { metadata } ) => metadata.name );

		expect( missing ).toEqual( [] );
	} );

	test( 'the section blocks, stat and alert support padding by axis', () => {
		const missing = PADDED.filter(
			( name ) =>
				JSON.stringify(
					byName.get( name )?.metadata.supports?.spacing?.padding
				) !== JSON.stringify( [ 'vertical', 'horizontal' ] )
		);

		expect( missing ).toEqual( [] );
	} );

	test( 'no block supports one physical side of padding alone', () => {
		const physical = blocks
			.filter( ( { metadata } ) => {
				const padding = metadata.supports?.spacing?.padding;

				return (
					padding === true ||
					( Array.isArray( padding ) &&
						padding.some( ( side ) => PHYSICAL.includes( side ) ) )
				);
			} )
			.map( ( { metadata } ) => metadata.name );

		expect( physical ).toEqual( [] );
	} );

	test( 'the form and flow blocks gain the margin and nothing else (FR-005)', () => {
		const forms = blocks.filter( ( { file } ) =>
			file.includes( `${ path.sep }corex-forms${ path.sep }` )
		);

		expect( forms ).toHaveLength( 6 );
		forms.forEach( ( { metadata } ) =>
			expect( metadata.supports ).toEqual( {
				html: false,
				className: false,
				anchor: true,
				spacing: { margin: [ 'top', 'bottom' ] },
			} )
		);
	} );
} );

describe( 'FR-008: background and text colour', () => {
	test( 'the section blocks and stat support a background and a text colour, no gradient or link', () => {
		const wrong = COLOURED.filter( ( name ) => {
			const color = byName.get( name )?.metadata.supports?.color;

			return (
				color?.background !== true ||
				color?.text !== true ||
				color?.gradients === true ||
				color?.link === true
			);
		} );

		expect( wrong ).toEqual( [] );
	} );

	test( "a chosen text colour reaches each one's headings and text, and its surfaces yield", () => {
		const failures = COLOURED.flatMap( ( name ) =>
			colourFailures(
				name,
				compiledSheet( byName.get( name ) ),
				KEEPS[ name ]
			)
		);

		expect( failures ).toEqual( [] );
	} );

	test( "a list block's title link takes a chosen text colour", () => {
		const missing = TITLE_LINKS.filter( ( name ) => {
			const slug = name.replace( /^corex\//, '' );
			const wanted = `.corex-${ slug }.has-text-color .corex-${ slug }__title a`;

			return ! rulesOf( compiledSheet( byName.get( name ) ) ).some(
				( rule ) =>
					rule.selector === wanted && rule.decls.color === 'inherit'
			);
		} );

		expect( missing ).toEqual( [] );
	} );

	test( 'every control kept is still in its sheet', () => {
		const stale = Object.entries( KEEPS ).flatMap( ( [ name, keeps ] ) => {
			const selectors = rulesOf(
				compiledSheet( byName.get( name ) )
			).map( ( rule ) => rule.selector );

			return keeps
				.filter( ( keep ) => ! selectors.includes( keep ) )
				.map( ( keep ) => `${ name }: ${ keep }` );
		} );

		expect( stale ).toEqual( [] );
	} );
} );

describe( 'FR-008: the style check fails naming the block and the selector', () => {
	const probe = ( scss, keeps ) =>
		colourFailures(
			'corex/probe',
			sass.compileString( scss, { style: 'expanded' } ).css,
			keeps
		);
	const HEADINGS =
		'.corex-probe.has-text-color :where(h1, h2, h3, h4, h5, h6) { color: inherit; }';

	test( 'passes a sheet whose colours yield to a chosen text colour', () => {
		expect(
			probe( `
				.corex-probe { background-color: var(--a); }
				.corex-probe__title { color: var(--b); }
				.corex-probe__card { background-color: var(--c); }
				${ HEADINGS }
				.corex-probe.has-text-color {
					.corex-probe__title { color: inherit; }
					.corex-probe__card { background-color: transparent; }
				}
			` )
		).toEqual( [] );
	} );

	test( 'a text colour, a surface and the headings that do not yield', () => {
		expect(
			probe( `
				.corex-probe__title { color: var(--b); }
				.corex-probe__card { background: var(--c); }
			` )
		).toEqual( [
			'corex/probe: has no .corex-probe.has-text-color :where(h1, h2, h3, h4, h5, h6) { color: inherit } rule, so a heading does not follow its section',
			'corex/probe: .corex-probe__title sets color and its text does not take a chosen text colour',
			'corex/probe: .corex-probe__card sets background and its surface stays under a chosen text colour',
		] );
	} );

	test( 'an override that loses the cascade does not count', () => {
		expect(
			probe( `
				${ HEADINGS }
				.corex-probe.has-text-color .corex-probe__title { color: inherit; }
				.corex-probe .corex-probe__x .corex-probe__title { color: var(--b); }
			` )
		).toEqual( [
			'corex/probe: .corex-probe .corex-probe__x .corex-probe__title sets color and its text does not take a chosen text colour',
		] );
	} );

	test( 'a control kept, and a colour marked !important', () => {
		expect(
			probe(
				`
				${ HEADINGS }
				.corex-probe__button { color: var(--b); background: var(--c); }
				.corex-probe { background-color: var(--a) !important; }
			`,
				[ '.corex-probe__button' ]
			)
		).toEqual( [
			'corex/probe: .corex-probe marks a colour !important, which beats the one an editor chooses',
		] );
	} );
} );
