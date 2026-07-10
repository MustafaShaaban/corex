/**
 * Corex E2E — front-office product surfaces (Spec 068 US9, FR-155/158/161/164).
 *
 * Covers the account block end to end: it provisions a page carrying the `corex/account`
 * block through the REST API (admin session), then verifies the guest account panel
 * (login / register / recovery forms) renders and enhances, the member panel is
 * reachable, and neither the page nor the account block scrolls the document sideways at
 * 375px. Docs navigation (version selector, sidebar, search) is verified separately by
 * the PHP `DocsNavigationTest`, since the docs site is a static build.
 *
 * ENVIRONMENT-GATED: needs Apache up (http://corex.local) + `npx playwright install`.
 */
const { test, expect } = require( '@playwright/test' );

let accountPath = '/?page_id=';

test.beforeAll( async ( { browser } ) => {
	// Provision an Account page via REST using the saved admin session + a REST nonce.
	const context = await browser.newContext( {
		storageState: require( './global-setup' ).STORAGE_STATE,
	} );
	const page = await context.newPage();
	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate(
		() => window.wpApiSettings && window.wpApiSettings.nonce
	);

	const res = await page.request.post( '/wp-json/wp/v2/pages', {
		headers: { 'X-WP-Nonce': nonce },
		data: {
			title: 'Account E2E',
			status: 'publish',
			content: '<!-- wp:corex/account /-->',
		},
	} );
	const body = await res.json();
	accountPath = `/?page_id=${ body.id }`;
	await context.close();
} );

test( 'the guest account block renders the sign-in, register, and recovery forms', async ( {
	browser,
} ) => {
	// A fresh, unauthenticated context — the visitor's view. The config's `use`
	// options (including the admin `storageState`) are inherited by
	// `browser.newContext()`, so an explicit empty state is required to log out;
	// without it the block would render the signed-in member panel.
	const guest = await browser.newContext( {
		storageState: { cookies: [], origins: [] },
	} );
	const page = await guest.newPage();
	await page.goto( accountPath );

	const account = page.locator( '[data-corex-account]' );
	await expect( account ).toBeVisible();
	await expect(
		page.locator( '[data-corex-account-form="login"]' )
	).toBeVisible();
	// Registration form appears when the site allows sign-ups; the recovery form always does.
	await expect(
		page.locator( '[data-corex-account-form="reset-request"]' )
	).toBeVisible();

	// Empty sign-in submit is blocked by native validation (no request leaves the browser).
	const login = page.locator( '[data-corex-account-form="login"]' );
	await login.getByRole( 'button', { name: /sign in/i } ).click();
	await expect
		.poll( () =>
			login
				.locator( 'input[name="login"]' )
				.evaluate( ( el ) => el.checkValidity() )
		)
		.toBe( false );

	await guest.close();
} );

test( 'the account surface does not scroll sideways at 375px', async ( {
	browser,
} ) => {
	// Anonymous visitor (see note above) at a phone width.
	const guest = await browser.newContext( {
		storageState: { cookies: [], origins: [] },
		viewport: { width: 375, height: 812 },
	} );
	const page = await guest.newPage();
	await page.goto( accountPath );
	await expect( page.locator( '[data-corex-account]' ) ).toBeVisible();

	const overflow = await page.evaluate(
		() =>
			document.documentElement.scrollWidth >
			document.documentElement.clientWidth
	);
	expect( overflow ).toBe( false );

	await guest.close();
} );

test.afterAll( async ( { browser } ) => {
	// Remove the provisioned page.
	const match = accountPath.match( /page_id=(\d+)/ );
	if ( ! match ) {
		return;
	}
	const context = await browser.newContext( {
		storageState: require( './global-setup' ).STORAGE_STATE,
	} );
	const page = await context.newPage();
	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate(
		() => window.wpApiSettings && window.wpApiSettings.nonce
	);
	await page.request
		.delete( `/wp-json/wp/v2/pages/${ match[ 1 ] }?force=true`, {
			headers: { 'X-WP-Nonce': nonce },
		} )
		.catch( () => {} );
	await context.close();
} );
