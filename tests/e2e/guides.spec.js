/**
 * The Guides screen and its help tabs (spec 084, US1 / US3 / US4).
 *
 * The registry is covered thoroughly in PHP; what only a browser can answer is whether the guides
 * reach a person — whether the screen renders, whether search actually narrows it, whether the
 * contextual help tab appears on the screen a guide claims, and whether somebody who cannot use a
 * feature is spared instructions for it.
 */
const { test, expect } = require( '@playwright/test' );

// This file's *own* editor, not the one admin-errors.spec.js uses (spec 095). CoreX locks an
// account out after repeated logins from one address — correctly — and two spec files sharing one
// fixture trip it between them from a CI runner's single IP. The failure then lands in whichever
// file runs second, reported as "could not sign in", nowhere near the cause.
const EDITOR = process.env.COREX_GUIDES_EDITOR_USER || 'corex-guides-editor';
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

	test( 'a guide reaches the screen it describes, by linking to it', async ( {
		page,
	} ) => {
		// This asserted the contextual Help tab until spec 097 took that surface off CoreX admin.
		// The address a guide declares with `onScreen()` survived the removal, because it was always
		// the better half of the idea: the Guides screen renders it as a link, so somebody who has
		// just read the steps is one click from doing them.
		//
		// The publishing guide declares `edit.php`, which is where its steps are carried out.
		await page.goto( GUIDES );

		const link = page.locator(
			'#guide-corex-publishing .corex-guides__guide-screen a'
		);
		await expect( link ).toBeVisible();
		await expect( link ).toHaveAttribute( 'href', /\/wp-admin\/edit\.php/ );

		await link.click();
		await expect(
			page.getByRole( 'heading', { name: 'Posts', exact: true } )
		).toBeVisible();
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
		// a registry that returned nothing at all would also satisfy. An editor must see the
		// guides whose capabilities they hold and none of the ones they do not — so this fails
		// both if gating is too tight and if it is too loose.
		const page = await signInAs( browser, baseURL, EDITOR, EDITOR_PASS );

		const response = await page.goto( GUIDES );

		// The screen itself is open to anyone who can see the admin — refusing an editor the whole
		// screen would refuse them help for the parts they can use.
		expect( response.status() ).toBe( 200 );

		// Named, not counted. This asserted `toHaveCount( 1 )` until spec 094 added six guides,
		// and the failure it then produced said "expected 1, received 3" — which is a true
		// statement about a number and says nothing about whether gating works. Both halves are
		// now spelled out, so the message names the guide that leaked or vanished.
		for ( const visible of [
			// Orientation is gated on `read` deliberately: the reader who needs "what is this"
			// most is the one with the fewest permissions.
			'#guide-corex-orientation',
			'#guide-corex-publishing',
		] ) {
			await expect( page.locator( visible ) ).toBeVisible();
		}

		// `corex-forms-flows` is deliberately absent from this list. The `editor` *role* holds no
		// CoreX abilities, but a long-lived development install accumulates grants — the fixture
		// user here has `corex_manage_forms` from an earlier run of the Access specs, so the guide
		// correctly shows for them and asserting its absence would fail on install state rather
		// than on gating. Every capability below is one no grant in this repo hands an editor.
		for ( const gated of [
			'#guide-corex-submissions',
			'#guide-corex-email',
			'#guide-corex-performance',
			'#guide-corex-settings',
			'#guide-corex-setup',
		] ) {
			await expect( page.locator( gated ) ).toHaveCount( 0 );
		}

		await page.context().close();
	} );
} );
