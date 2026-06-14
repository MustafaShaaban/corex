/**
 * Playwright global setup (spec 052): authenticate ONCE against the running site and persist
 * the session to `storageState`, so every spec starts already-logged-in. This removes the
 * per-test login round-trip — and with it the WordPress cold-start "test cookie" first-login
 * race that otherwise flakes whichever spec happens to run first against a cold WP/OPcache.
 *
 * Credentials + base URL come from env (wp-env / WAMP defaults), never hard-coded.
 */
const { chromium, expect } = require( '@playwright/test' );
const path = require( 'path' );

const BASE_URL = process.env.COREX_BASE_URL || 'http://corex.local';
const ADMIN_USER = process.env.COREX_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.COREX_ADMIN_PASS || 'password';

const STORAGE_STATE = path.join( __dirname, '.auth', 'admin.json' );

module.exports = async () => {
	const browser = await chromium.launch();
	const page = await browser.newPage( { baseURL: BASE_URL } );

	// Retry a few times: the very first login against a cold WP can bounce back to the form
	// before the test cookie is honoured. Once it sticks, every spec reuses the saved state.
	let authed = false;
	for ( let attempt = 1; attempt <= 5 && ! authed; attempt++ ) {
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', ADMIN_USER );
		await page.fill( '#user_pass', ADMIN_PASS );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin|wp-login/, { timeout: 15_000 } ).catch( () => {} );
		// Confirm real authentication by loading an admin-only page and seeing the admin bar.
		await page.goto( '/wp-admin/' ).catch( () => {} );
		authed = await page.locator( '#wpadminbar' ).isVisible().catch( () => false );
	}

	expect( authed, 'global-setup could not authenticate the admin user' ).toBe( true );

	await page.context().storageState( { path: STORAGE_STATE } );
	await browser.close();
};

module.exports.STORAGE_STATE = STORAGE_STATE;
