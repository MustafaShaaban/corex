/**
 * The CoreX theme's colours as the browser computes them, read from `theme/theme.json` and
 * `theme/styles/dark.json`, for the specs that measure the theme in both of its styles
 * (spec 134: `section-bands.spec.js`, `front-page-hero.spec.js`).
 *
 * The Dark variation is a choice of the whole site, and the other spec files of a run read the
 * front end at the same time, so it is not saved: `showDarkVariation()` gives the page the
 * variation's palette and custom colours as the same custom properties WordPress prints for a
 * chosen variation.
 */
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const ROOT = path.join( __dirname, '..', '..' );

/**
 * WordPress's name for a custom key: a capital starts a word (`onAccent` is `on-accent`).
 *
 * @param {string} key A key of `settings.custom.color`.
 * @return {string} The name in the custom property.
 */
const kebab = ( key ) =>
	key.replace( /[A-Z]/g, ( letter ) => `-${ letter.toLowerCase() }` );

/**
 * A `#rrggbb` colour as `getComputedStyle` writes it; any other value as it is.
 *
 * @param {string} value A colour from a theme file.
 * @return {string} The computed form.
 */
const rgb = ( value ) => {
	if ( ! /^#[0-9a-f]{6}$/i.test( value ) ) {
		return value;
	}
	const channels = [ 1, 3, 5 ].map( ( at ) =>
		parseInt( value.slice( at, at + 2 ), 16 )
	);

	return `rgb(${ channels.join( ', ' ) })`;
};

/**
 * A theme file's colours: palette slugs by slug, custom colours by the name WordPress prints.
 *
 * @param {string} file The theme file, from the repository root.
 * @return {{preset: Object<string,string>, custom: Object<string,string>, settings: Object}} Colours.
 */
function themeColours( file ) {
	const { settings } = JSON.parse(
		fs.readFileSync( path.join( ROOT, file ), 'utf8' )
	);

	return {
		preset: Object.fromEntries(
			settings.color.palette.map( ( { slug, color } ) => [
				slug,
				rgb( color ),
			] )
		),
		custom: Object.fromEntries(
			Object.entries( settings.custom.color ).map( ( [ key, value ] ) => [
				kebab( key ),
				rgb( value ),
			] )
		),
		settings,
	};
}

/** The two styles a site can choose, by the name a test prints. */
const MODES = {
	default: themeColours( 'theme/theme.json' ),
	dark: themeColours( 'theme/styles/dark.json' ),
};

/**
 * Resolves once the colour transitions a change started have ended. A style read in the same task
 * as the change is the value from before it.
 *
 * @param {import('@playwright/test').Page} page The page.
 */
async function settle( page ) {
	await page.evaluate( async () => {
		await new Promise( ( drawn ) => window.requestAnimationFrame( drawn ) );
		await Promise.all(
			document
				.getAnimations()
				.filter(
					( animation ) => animation instanceof window.CSSTransition
				)
				.map( ( animation ) => animation.finished.catch( () => {} ) )
		);
	} );
}

/**
 * Draws the loaded page in the Dark variation's colours.
 *
 * @param {import('@playwright/test').Page} page The page.
 */
async function showDarkVariation( page ) {
	const { settings } = MODES.dark;
	const properties = [
		...settings.color.palette.map(
			( { slug, color } ) => `--wp--preset--color--${ slug }: ${ color };`
		),
		...Object.entries( settings.custom.color ).map(
			( [ key, value ] ) =>
				`--wp--custom--color--${ kebab( key ) }: ${ value };`
		),
		// theme.json defines the focus colour as `accent`; the page resolves it where it is declared.
		'--wp--custom--focus--color: var(--wp--preset--color--accent);',
	];

	await page.addStyleTag( {
		content: `:root { ${ properties.join( ' ' ) } }`,
	} );
	await settle( page );
}

module.exports = { MODES, settle, showDarkVariation, themeColours };
