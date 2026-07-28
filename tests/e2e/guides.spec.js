/**
 * The Guides screen and its help tabs (spec 084, US1 / US3 / US4).
 *
 * The registry is covered thoroughly in PHP; what only a browser can answer is whether the guides
 * reach a person — whether the screen renders, whether search actually narrows it, whether the
 * contextual help tab appears on the screen a guide claims, and whether somebody who cannot use a
 * feature is spared instructions for it.
 */
const { test, expect } = require( '@playwright/test' );

const EDITOR = process.env.COREX_EDITOR_USER || 'corex-editor';
const EDITOR_PASS = process.env.COREX_EDITOR_PASS || 'CorexE2E!editor1';

/** Where a login form might be, in the order global-setup.js tries them (spec 069). */
const LOGIN_PATHS = [
	process.env.COREX_LOGIN_PATH,
	'/wp-login.php',
	'/corex-login/',
].filter( Boolean );

const GUIDES = '/wp-admin/admin.php?page=corex-guides';

/**
 * Sign in as a named user, in a context of its own so the shared administrator session survives.
 *
 * @param {import('@playwright/test').Browser} browser  The Playwright browser.
 * @param {string}                             baseURL  Where the site is served.
 * @param {string}                             user     The login name.
 * @param {string}                             password The password.
 * @return {Promise<import('@playwright/test').Page>} The signed-in page.
 */
async function signInAs( browser, baseURL, user, password ) {
	const context = await browser.newContext( {
		storageState: undefined,
		baseURL,
	} );
	const page = await context.newPage();

	let signedIn = false;
	for ( const path of LOGIN_PATHS ) {
		await page.goto( path ).catch( () => {} );

		if (
			! ( await page
				.locator( '#user_login' )
				.isVisible()
				.catch( () => false ) )
		) {
			continue;
		}

		await page.fill( '#user_login', user );
		await page.fill( '#user_pass', password );
		await Promise.all( [
			page.waitForNavigation( { timeout: 15000 } ).catch( () => {} ),
			page.click( '#wp-submit' ),
		] );
		signedIn = ! page.url().includes( 'wp-login.php' );

		if ( signedIn ) {
			break;
		}
	}

	expect( signedIn, `signed in as ${ user }` ).toBe( true );

	return page;
}

test.describe( 'Guides', () => {
	test( 'lists the shipped guides, grouped, with their steps', async ( {
		page,
	} ) => {
		const response = await page.goto( GUIDES );
		expect( response.status() ).toBe( 200 );

		await expect(
			page.locator( '.corex-guides__guide' ).first()
		).toBeVisible();

		// More than one section, or the grouping is decoration.
		expect(
			await page.locator( '[data-corex-guide-section]' ).count()
		).toBeGreaterThan( 1 );

		// A topic opens to real numbered steps rather than a heading with nothing behind it.
		const topic = page
			.locator( '#guide-corex-publishing .corex-guides__topic' )
			.first();
		await topic.locator( 'summary' ).click();
		await expect(
			topic.locator( '.corex-guides__step-instruction' ).first()
		).toBeVisible();
	} );

	test( 'search narrows the list and says so when nothing matches', async ( {
		page,
	} ) => {
		await page.goto( GUIDES );

		const publishing = page.locator( '#guide-corex-publishing' );
		const submissions = page.locator( '#guide-corex-submissions' );

		await expect( publishing ).toBeVisible();
		await expect( submissions ).toBeVisible();

		// A word that is in one guide's steps and not in the other's title — proving search reads
		// the step text, which is the whole reason the search index is built server-side.
		await page.fill( '#corex-guides-search', 'category' );
		await expect( publishing ).toBeVisible();
		await expect( submissions ).toBeHidden();

		await page.fill( '#corex-guides-search', 'zzzznothing' );
		await expect(
			page.locator( '.corex-guides__no-results' )
		).toBeVisible();

		// A section heading standing above nothing reads as a section that failed to load.
		await expect(
			page.locator( '[data-corex-guide-section]:visible' )
		).toHaveCount( 0 );
	} );

	test( 'a guide reaches the screen it describes, through the Help tab', async ( {
		page,
	} ) => {
		// WordPress's contextual help is used nowhere else in this repo, so this asserts the whole
		// mechanism: the tab exists, it is ours, and it links back to the full guide.
		await page.goto( '/wp-admin/edit.php' );

		await page.locator( '#contextual-help-link' ).click();

		const tab = page.locator( '#tab-link-corex-guide-corex-publishing' );
		await expect( tab ).toBeVisible();

		await tab.locator( 'a' ).click();
		await expect(
			page.locator( '#tab-panel-corex-guide-corex-publishing' )
		).toContainText( 'Read the full guide' );
	} );

	test( 'somebody who cannot use a feature is not given instructions for it', async ( {
		browser,
		baseURL,
	} ) => {
		// An editor rather than a subscriber, for two reasons.
		//
		// The weaker one: `corex-requester` is signed in several times by
		// `access-request.spec.js`, and login protection locks an account out after enough
		// attempts from one address. Adding another sign-in here pushed that suite over the
		// threshold and failed it in CI — a test in one file breaking a test in another, reported
		// as "could not sign in" nowhere near the cause.
		//
		// The stronger one: an editor is the better assertion. A subscriber sees nothing, which
		// a registry that returned nothing at all would also satisfy. An editor must see exactly
		// the one guide whose capability they hold and none of the three they do not — so this
		// fails both if gating is too tight and if it is too loose.
		const page = await signInAs( browser, baseURL, EDITOR, EDITOR_PASS );

		const response = await page.goto( GUIDES );

		// The screen itself is open to anyone who can see the admin — refusing an editor the whole
		// screen would refuse them help for the parts they can use.
		expect( response.status() ).toBe( 200 );

		await expect( page.locator( '#guide-corex-publishing' ) ).toBeVisible();
		await expect( page.locator( '.corex-guides__guide' ) ).toHaveCount( 1 );

		for ( const gated of [
			'#guide-corex-submissions',
			'#guide-corex-email',
			'#guide-corex-performance',
		] ) {
			await expect( page.locator( gated ) ).toHaveCount( 0 );
		}

		await page.context().close();
	} );
} );
