/**
 * Padding and colour on the CoreX blocks (spec 136, User Story 1 scenario 6, FR-007 and FR-008).
 * That every block a person can insert supports a top and bottom margin is checked on WordPress's
 * registry, in `tests/Integration/Blocks/SpacingColourTest.php`, so the account block (registered
 * in code) and a child block with `parent` are counted.
 *
 * The supports, over every `block.json` under `plugins/` and `addons/`, found by search:
 * - the section blocks, stat and alert support padding by axis, never one physical side;
 * - the section blocks and stat support a background and a text colour, and no gradient or
 *   link colour;
 * - the six form and flow blocks gain the margin and nothing else.
 *
 * The styles, compiled from each one's `style.scss`. Every rule is written on
 * `.wp-block-corex-<slug>.has-text-color`: WordPress gives that class to the page's root and to
 * the editor's wrapper, and a block previewed by the server render sits inside that wrapper with
 * no colour class of its own (FR-006a). Under it, every element the sheet colours takes the chosen
 * colour (`color: inherit`), every inner surface it paints drops its background so the text sits
 * on the one it was chosen against, every heading follows its section (DECISIONS #635), and a link
 * takes the chosen colour as core's `p.has-text-color` does, but for the controls drawn as links.
 * Controls that paint their own fill keep it (KEEPS, below). The tab shown gets a mark that is
 * not a colour. A block whose text sits on its own root sets its text colour there, so
 * WordPress's contrast check reads it (ROOT_TEXT). The block's root needs no rule: WordPress's
 * colour classes are `!important` and a custom colour is an inline style, so they beat the sheet
 * as long as the sheet marks none of its colours `!important`, which is checked too.
 *
 * What the check does not see: a descendant the sheet does not colour by a class of the block
 * (it inherits, or takes the theme's colour), and colours set by any property but `color`,
 * `background` and `background-color` (`border-color`, `fill`, `outline-color`).
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
 * drops the quotes of an attribute's value). `links` are those drawn as an `a`, which the link
 * rule leaves out.
 */
const KEEPS = {
	'corex/hero': {
		rules: [ '.corex-hero__cta' ],
		links: [ '.corex-hero__cta' ],
	},
	'corex/cta': {
		rules: [ '.corex-cta__button' ],
		links: [ '.corex-cta__button' ],
	},
	'corex/pricing': {
		rules: [ '.corex-pricing__cta' ],
		links: [ '.corex-pricing__cta' ],
	},
	'corex/carousel': {
		rules: [
			'.corex-carousel__arrow',
			'.corex-carousel__arrow:hover',
			'.corex-carousel__dot',
			'.corex-carousel__dot[aria-current=true]',
		],
	},
	'corex/newsletter-signup': {
		rules: [
			'.corex-newsletter-signup__email',
			'.corex-newsletter-signup__submit',
		],
	},
};

/**
 * Elements printed only as the block's own root (a list with nothing in it): WordPress's colour
 * classes sit on them, and no rule under the root can reach them.
 */
const ROOT_ONLY = {
	'corex/posts': [ 'corex-posts__empty' ],
	'corex/jobs': [ 'corex-jobs__empty' ],
	'corex/projects': [ 'corex-projects__empty' ],
};

/**
 * The blocks whose text sits on their own root: the root sets the text colour, and these elements
 * inherit it (a heading has to, or the theme's heading colour wins). The card blocks are not
 * here: their text sits on their cards, not on the root.
 */
const ROOT_TEXT = {
	'corex/cta': [ 'corex-cta__title', 'corex-cta__text' ],
	'corex/hero': [ 'corex-hero__title' ],
	'corex/pricing': [ 'corex-pricing__plan', 'corex-pricing__features' ],
	'corex/testimonial': [ 'corex-testimonial__quote' ],
	'corex/stat': [ 'corex-stat__label', 'corex-stat__desc' ],
};

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
 * @param {string} name A block's name (`corex/hero`).
 * @return {string} Its slug (`hero`).
 */
const slugOf = ( name ) => name.replace( /^corex\//, '' );

/**
 * @param {string} name A block's name.
 * @return {string} The selector every rule for a chosen text colour is written on.
 */
const chosenOf = ( name ) =>
	`.wp-block-corex-${ slugOf( name ) }.has-text-color`;

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
 * @param {Object} override A rule.
 * @param {Object} rule     Another, earlier or later in the same sheet.
 * @return {boolean} Whether the override wins the cascade over the rule.
 */
function wins( override, rule ) {
	const order = compare(
		specificity( override.selector ),
		specificity( rule.selector )
	);

	return order > 0 || ( order === 0 && override.index > rule.index );
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
 * @return {Array<Object>} `{ selector, index, decls, important }`, `decls` by property.
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
 * @param {Object} decls A rule's declarations.
 * @return {string|undefined} The background it sets.
 */
const backgroundOf = ( decls ) =>
	decls[ 'background-color' ] ?? decls.background;

/**
 * @param {string} compound A compound selector.
 * @param {string} slug     The block's slug.
 * @return {?string} The element class of the block it targets (`corex-hero__title`), or null. A
 *                   class inside `:where()`, `:not()` or `:is()` is not the one targeted.
 */
function elementClassOf( compound, slug ) {
	const match = compound
		.replace( /:(?:where|not|is)\((?:[^()]|\([^()]*\))*\)/g, '' )
		.match( new RegExp( `\\.(corex-${ slug }__[a-z0-9-]+)(?![\\w-])` ) );

	return match ? match[ 1 ] : null;
}

/**
 * What a block's compiled sheet breaks of FR-008.
 *
 * @param {string}        name            The block's name (`corex/hero`).
 * @param {string}        css             Its compiled sheet.
 * @param {Object}        [kept]          Its controls that keep their colours.
 * @param {Array<string>} [kept.rules]    Their rules, by compiled selector.
 * @param {Array<string>} [kept.links]    Those drawn as a link.
 * @param {Array<string>} [kept.rootOnly] Element classes printed only as the root.
 * @return {Array<string>} One line per problem, naming the block and the selector.
 */
function colourFailures(
	name,
	css,
	{ rules: keeps = [], links = [], rootOnly = [] } = {}
) {
	const slug = slugOf( name );
	const chosen = chosenOf( name );
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

	if (
		! overrides.some(
			( rule ) =>
				rule.selector ===
					`${ chosen } :where(h1, h2, h3, h4, h5, h6)` &&
				rule.decls.color === 'inherit'
		)
	) {
		failures.push(
			`${ name }: has no ${ chosen } :where(h1, h2, h3, h4, h5, h6) { color: inherit } rule, so a heading does not follow its section`
		);
	}

	const link = `${ chosen } a:where(:not(${ [
		'.wp-element-button',
		...links,
	].join( ', ' ) }))`;
	if (
		! overrides.some(
			( rule ) => rule.selector === link && rule.decls.color === 'inherit'
		)
	) {
		failures.push(
			`${ name }: has no ${ link } { color: inherit } rule, so a link keeps the theme's colour`
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
			if ( target === null || rootOnly.includes( target ) ) {
				return;
			}
			const wanted = [];
			if ( paints( rule.decls.color ) ) {
				wanted.push( [
					'color',
					( decls ) => decls.color === 'inherit',
					'its text does not take a chosen text colour',
				] );
			}
			if ( paints( backgroundOf( rule.decls ) ) ) {
				wanted.push( [
					'background',
					( decls ) => backgroundOf( decls ) === 'transparent',
					'its surface stays under a chosen text colour',
				] );
			}

			wanted.forEach( ( [ prop, yields, problem ] ) => {
				const beaten = overrides.some(
					( override ) =>
						elementClassOf(
							lastCompound( override.selector ),
							slug
						) === target &&
						yields( override.decls ) &&
						wins( override, rule )
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
 * @param {Object} block A block, as found above.
 * @return {string} Its compiled `style.scss`.
 */
function compiledSheet( block ) {
	const source = path.join( path.dirname( block.file ), 'style.scss' );

	return sass.compile( source, { style: 'expanded' } ).css;
}

/**
 * @param {string} name A block's name.
 * @return {Array<Object>} The rules of its compiled sheet.
 */
const rulesOfBlock = ( name ) => rulesOf( compiledSheet( byName.get( name ) ) );

describe( 'FR-007: padding', () => {
	test( 'finds the framework blocks, and every named block exists', () => {
		expect( blocks.length ).toBeGreaterThan( 0 );
		expect( PADDED.filter( ( name ) => ! byName.has( name ) ) ).toEqual(
			[]
		);
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

	test( "a chosen text colour reaches each one's headings, text and links, and its surfaces yield", () => {
		const failures = COLOURED.flatMap( ( name ) =>
			colourFailures( name, compiledSheet( byName.get( name ) ), {
				...KEEPS[ name ],
				rootOnly: ROOT_ONLY[ name ],
			} )
		);

		expect( failures ).toEqual( [] );
	} );

	test( 'every control kept is still in its sheet', () => {
		const stale = Object.entries( KEEPS ).flatMap(
			( [ name, { rules } ] ) => {
				const selectors = rulesOfBlock( name ).map(
					( rule ) => rule.selector
				);

				return rules
					.filter( ( keep ) => ! selectors.includes( keep ) )
					.map( ( keep ) => `${ name }: ${ keep }` );
			}
		);

		expect( stale ).toEqual( [] );
	} );

	test( 'the tab shown keeps a mark that is not a colour, a line under it in the chosen colour', () => {
		const shown = rulesOfBlock( 'corex/tabs' ).find(
			( rule ) =>
				rule.selector ===
				`${ chosenOf(
					'corex/tabs'
				) }:not(.is-editing) .corex-tabs__radio:checked + .corex-tabs__label`
		);

		expect( shown?.decls[ 'border-block-end-color' ] ).toBe(
			'currentcolor'
		);
		// Every tab keeps the line's room, so the one shown does not grow.
		expect(
			rulesOfBlock( 'corex/tabs' )
				.filter(
					( rule ) =>
						rule.selector ===
						`${ chosenOf( 'corex/tabs' ) } .corex-tabs__label`
				)
				.map( ( rule ) => rule.decls[ 'border-block-end' ] )
				.filter( Boolean )
		).toEqual( [
			expect.stringMatching(
				/^var\(--wp--custom--border--width--[a-z]+\) solid transparent$/
			),
		] );
	} );

	test( 'a block whose text sits on its own root sets its text colour there, and its text inherits it', () => {
		const wrong = Object.entries( ROOT_TEXT ).flatMap(
			( [ name, elements ] ) => {
				const rules = rulesOfBlock( name );
				const root = `.corex-${ slugOf( name ) }`;
				const problems = [];
				if (
					! rules.some(
						( rule ) =>
							rule.selector === root && paints( rule.decls.color )
					)
				) {
					problems.push( `${ name }: ${ root } sets no text colour` );
				}
				elements
					.filter(
						( element ) =>
							! rules.some(
								( rule ) =>
									rule.selector === `.${ element }` &&
									rule.decls.color === 'inherit'
							)
					)
					.forEach( ( element ) =>
						problems.push(
							`${ name }: .${ element } does not inherit the root's colour`
						)
					);

				return problems;
			}
		);

		expect( wrong ).toEqual( [] );
	} );
} );

describe( 'FR-008: the style check fails naming the block and the selector', () => {
	const probe = ( scss, kept ) =>
		colourFailures(
			'corex/probe',
			sass.compileString( scss, { style: 'expanded' } ).css,
			kept
		);
	const CHOSEN = '.wp-block-corex-probe.has-text-color';
	const SECTION = `
		${ CHOSEN } :where(h1, h2, h3, h4, h5, h6) { color: inherit; }
		${ CHOSEN } a:where(:not(.wp-element-button)) { color: inherit; }
	`;

	test( 'passes a sheet whose colours yield to a chosen text colour', () => {
		expect(
			probe( `
				.corex-probe { background-color: var(--a); }
				.corex-probe__title { color: var(--b); }
				.corex-probe__card { background-color: var(--c); }
				${ SECTION }
				${ CHOSEN } {
					.corex-probe__title { color: inherit; }
					.corex-probe__card { background-color: transparent; }
				}
			` )
		).toEqual( [] );
	} );

	test( 'a text colour, a surface, the headings and the links that do not yield', () => {
		expect(
			probe( `
				.corex-probe__title { color: var(--b); }
				.corex-probe__card { background: var(--c); }
			` )
		).toEqual( [
			`corex/probe: has no ${ CHOSEN } :where(h1, h2, h3, h4, h5, h6) { color: inherit } rule, so a heading does not follow its section`,
			`corex/probe: has no ${ CHOSEN } a:where(:not(.wp-element-button)) { color: inherit } rule, so a link keeps the theme's colour`,
			'corex/probe: .corex-probe__title sets color and its text does not take a chosen text colour',
			'corex/probe: .corex-probe__card sets background and its surface stays under a chosen text colour',
		] );
	} );

	test( 'a rule on the block class alone misses the editor wrapper and does not count', () => {
		expect(
			probe( `
				${ SECTION }
				.corex-probe__title { color: var(--b); }
				.corex-probe.has-text-color .corex-probe__title { color: inherit; }
			` )
		).toEqual( [
			'corex/probe: .corex-probe__title sets color and its text does not take a chosen text colour',
		] );
	} );

	test( 'an override that loses the cascade does not count', () => {
		expect(
			probe( `
				${ SECTION }
				${ CHOSEN } .corex-probe__title { color: inherit; }
				.corex-probe .corex-probe__x .corex-probe__x .corex-probe__title { color: var(--b); }
			` )
		).toEqual( [
			'corex/probe: .corex-probe .corex-probe__x .corex-probe__x .corex-probe__title sets color and its text does not take a chosen text colour',
		] );
	} );

	test( 'a control kept, a link control left out, a root-only element, and a colour marked !important', () => {
		expect(
			probe(
				`
				${ CHOSEN } :where(h1, h2, h3, h4, h5, h6) { color: inherit; }
				${ CHOSEN } a:where(:not(.wp-element-button, .corex-probe__button)) { color: inherit; }
				.corex-probe__button { color: var(--b); background: var(--c); }
				.corex-probe__empty { color: var(--b); }
				.corex-probe { background-color: var(--a) !important; }
			`,
				{
					rules: [ '.corex-probe__button' ],
					links: [ '.corex-probe__button' ],
					rootOnly: [ 'corex-probe__empty' ],
				}
			)
		).toEqual( [
			'corex/probe: .corex-probe marks a colour !important, which beats the one an editor chooses',
		] );
	} );

	test( 'a link rule that would recolour a control drawn as a link', () => {
		expect(
			probe(
				`
				${ SECTION }
				.corex-probe__button { color: var(--b); background: var(--c); }
			`,
				{
					rules: [ '.corex-probe__button' ],
					links: [ '.corex-probe__button' ],
				}
			)
		).toEqual( [
			`corex/probe: has no ${ CHOSEN } a:where(:not(.wp-element-button, .corex-probe__button)) { color: inherit } rule, so a link keeps the theme's colour`,
		] );
	} );
} );
