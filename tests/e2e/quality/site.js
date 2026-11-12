/**
 * The screens, templates and pages of the site under test, as `discover.js` read them.
 *
 * Read once per run: the config runs the discovery and leaves its answer in
 * `COREX_QUALITY_SITE`, which every worker inherits, so that a test list is the same in all of
 * them. Required outside a quality run, it runs the discovery itself.
 */
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

function discovered() {
	if ( ! process.env.COREX_QUALITY_SITE ) {
		process.env.COREX_QUALITY_SITE = execFileSync(
			process.execPath,
			[ path.join( __dirname, 'discover.js' ) ],
			{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'inherit' ] }
		);
	}

	return JSON.parse( process.env.COREX_QUALITY_SITE );
}

const site = discovered();

/** Widths every check uses (Definition of Done item 5). */
const PHONE = { width: 375, height: 812 };
const DESKTOP = { width: 1280, height: 900 };

/** The eight cells of the matrix, as the four a single load at one width can show. */
const APPEARANCES = [ 'light', 'dark' ];
const DIRECTIONS = [ 'ltr', 'rtl' ];

/** The four cells whose screenshots are kept for a person (US-NFR-01). */
const SCREENSHOT_CELLS = [
	'375-dark-rtl',
	'375-light-ltr',
	'1280-light-rtl',
	'1280-dark-ltr',
];

/**
 * A file name for a screen or template id.
 * @param {string} id A screen or template id.
 */
const fileNameOf = ( id ) => id.replace( /[^a-z0-9_-]+/gi, '-' );

module.exports = {
	site,
	PHONE,
	DESKTOP,
	APPEARANCES,
	DIRECTIONS,
	SCREENSHOT_CELLS,
	fileNameOf,
};
