/**
 * Shared Playwright E2E helpers (spec 052): a console-error collector with a documented
 * allow-list. Admin authentication is handled once in `global-setup.js` (storageState),
 * so specs start already logged in and don't each re-run a flaky login.
 */
/**
 * Known, non-Corex console noise that must NOT fail the sweep (documented allow-list).
 * Keep this tiny and justified — the default is zero tolerated errors.
 */
const ALLOW_LIST = [
	/Failed to load resource: net::ERR_/i, // transient network/infra, not a code regression
	/favicon\.ico/i, // missing favicon on a bare dev install
];

/**
 * Attach console + pageerror listeners; returns the array of real errors (allow-list
 * filtered). Warnings are ignored — only errors fail the sweep (FR-004).
 */
function collectConsoleErrors( page ) {
	const errors = [];

	page.on( 'console', ( msg ) => {
		if ( msg.type() !== 'error' ) {
			return;
		}
		const text = msg.text();
		if ( ALLOW_LIST.some( ( re ) => re.test( text ) ) ) {
			return;
		}
		// Record where it came from. "Failed to load resource: 404" names no resource on its own,
		// which makes a failure report unactionable — you know something 404s, not what. Filtering
		// still happens on the raw text so the allow-list keeps matching.
		const { url } = msg.location();
		errors.push( url ? `${ text }  <- ${ url }` : text );
	} );

	page.on( 'pageerror', ( err ) => errors.push( err.message ) );

	return errors;
}

module.exports = { collectConsoleErrors };
