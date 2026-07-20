/**
 * Playwright global setup (spec 052): authenticate ONCE against the running site and persist
 * the session to `storageState`, so every spec starts already-logged-in. This removes the
 * per-test login round-trip — and with it the WordPress cold-start "test cookie" first-login
 * race that otherwise flakes whichever spec happens to run first against a cold WP/OPcache.
 *
 * Credentials + base URL come from env (wp-env / WAMP defaults), never hard-coded.
 */
const { chromium, expect } = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );

const BASE_URL = process.env.COREX_BASE_URL || 'http://corex.local';
const ADMIN_USER = process.env.COREX_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.COREX_ADMIN_PASS || 'password';

/**
 * Where the login lives (spec 069).
 *
 * With login protection enabled, /wp-login.php returns the theme's 404 like any missing page — so
 * automation that assumes it cannot sign in. That is the feature working, not a fault, but this
 * setup has to cope: it tries the default endpoint first and falls back to the custom address.
 * Override with COREX_LOGIN_PATH when the site uses a different one.
 */
const LOGIN_PATHS = [
	process.env.COREX_LOGIN_PATH,
	'/wp-login.php',
	'/corex-login/',
].filter( Boolean );

const STORAGE_STATE = path.join( __dirname, '.auth', 'admin.json' );

/** Load a login form from whichever address actually serves one. */
async function openLoginForm( page ) {
	for ( const loginPath of LOGIN_PATHS ) {
		await page.goto( loginPath ).catch( () => {} );
		const visible = await page
			.locator( '#user_login' )
			.isVisible()
			.catch( () => false );

		if ( visible ) {
			return loginPath;
		}
	}

	return '';
}

module.exports = async () => {
	const browser = await chromium.launch();
	let context;
	let page;

	if ( fs.existsSync( STORAGE_STATE ) ) {
		context = await browser.newContext( {
			baseURL: BASE_URL,
			storageState: STORAGE_STATE,
		} );
		page = await context.newPage();
		await page.goto( '/wp-admin/' ).catch( () => {} );
		if (
			await page
				.locator( '#wpadminbar' )
				.isVisible()
				.catch( () => false )
		) {
			await context.storageState( { path: STORAGE_STATE } );
			await browser.close();
			return;
		}
		await context.close();
	}

	context = await browser.newContext( { baseURL: BASE_URL } );
	page = await context.newPage();

	// Retry a few times: the very first login against a cold WP can bounce back to the form
	// before the test cookie is honoured. Once it sticks, every spec reuses the saved state.
	let authed = false;
	let loginPath = '';
	for ( let attempt = 1; attempt <= 5 && ! authed; attempt++ ) {
		loginPath = await openLoginForm( page );
		if ( ! loginPath ) {
			continue;
		}

		await page.fill( '#user_login', ADMIN_USER );
		await page.fill( '#user_pass', ADMIN_PASS );
		await page.click( '#wp-submit' );
		await page.waitForLoadState( 'networkidle' ).catch( () => {} );
		// Confirm real authentication by loading an admin-only page and seeing the admin bar.
		await page.goto( '/wp-admin/' ).catch( () => {} );
		authed = await page
			.locator( '#wpadminbar' )
			.isVisible()
			.catch( () => false );
	}

	expect(
		authed,
		`global-setup could not authenticate the admin user. Tried: ${ LOGIN_PATHS.join(
			', '
		) }. If login protection is enabled with a different address, set COREX_LOGIN_PATH.`
	).toBe( true );

	await context.storageState( { path: STORAGE_STATE } );
	await browser.close();
};

module.exports.STORAGE_STATE = STORAGE_STATE;
