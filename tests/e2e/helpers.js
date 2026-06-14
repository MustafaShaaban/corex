/**
 * Shared Playwright E2E helpers (spec 052): admin login + a console-error collector with a
 * documented allow-list. Credentials come from env (wp-env defaults), never hard-coded.
 */
const { expect } = require( '@playwright/test' );

const ADMIN_USER = process.env.COREX_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.COREX_ADMIN_PASS || 'password';

/**
 * Known, non-Corex console noise that must NOT fail the sweep (documented allow-list).
 * Keep this tiny and justified — the default is zero tolerated errors.
 */
const ALLOW_LIST = [
	/Failed to load resource: net::ERR_/i, // transient network/infra, not a code regression
	/favicon\.ico/i, // missing favicon on a bare dev install
];

async function login( page ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', ADMIN_USER );
	await page.fill( '#user_pass', ADMIN_PASS );
	await page.click( '#wp-submit' );
	await expect( page ).toHaveURL( /wp-admin/ );
}

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
		if ( ! ALLOW_LIST.some( ( re ) => re.test( text ) ) ) {
			errors.push( text );
		}
	} );

	page.on( 'pageerror', ( err ) => errors.push( err.message ) );

	return errors;
}

module.exports = { login, collectConsoleErrors, ADMIN_USER, ADMIN_PASS };
