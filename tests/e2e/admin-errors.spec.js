/**
 * Every admin refusal is a CoreX page (spec 083).
 *
 * Spec 079 shipped a "unified admin error experience" whose unifying half was never built, and
 * nothing caught it, because the only browser test that touched a refusal visited exactly one URL:
 * `admin.php?page=corex-forms`. That URL is the entire scope of `AccessDeniedGate`, so the one test
 * covering the feature was the one test that could not fail.
 *
 * Two rules this file follows as a result.
 *
 * **The assertion is positive.** Spec 079's SC-005 asserts that WordPress's sentence "Sorry, you are
 * not allowed to access this page." is absent. Measured on the running install
 * (`specs/083-admin-error-surface/evidence/before/refusal-matrix.md`), WordPress uses several
 * different refusal sentences — "…not allowed to list users.", "…not allowed to manage options for
 * this site." — and every one is translated. So that assertion already passes on `users.php` today,
 * and would pass site-wide on an Arabic install, while every refusal stayed a white box. Asserting
 * that the response *is* a CoreX document is the form that cannot be satisfied by accident.
 *
 * **Both directions are checked.** A handler that brands everything is as wrong as one that brands
 * nothing; it would turn pages a viewer may legitimately open into errors. Every surface is opened
 * by somebody who must be refused AND by somebody who must not be.
 */
const { test, expect } = require( '@playwright/test' );

const SUBSCRIBER = process.env.COREX_REQUESTER_USER || 'corex-requester';
const SUBSCRIBER_PASS =
	process.env.COREX_REQUESTER_PASS || 'CorexE2E!requester1';
const EDITOR = process.env.COREX_EDITOR_USER || 'corex-editor';
const EDITOR_PASS = process.env.COREX_EDITOR_PASS || 'CorexE2E!editor1';

/** Where a login form might be, in the order global-setup.js tries them (spec 069). */
const LOGIN_PATHS = [
	process.env.COREX_LOGIN_PATH,
	'/wp-login.php',
	'/corex-login/',
].filter( Boolean );

/**
 * Admin addresses a subscriber cannot open, and the status each must answer with.
 *
 * The post-type row is the one that makes this a CoreX defect rather than a WordPress one:
 * `corex_job` comes from addons/corex-careers. It is a screen CoreX registers, and it carries no
 * `page` parameter, so it can never match the `corex-` prefix the old gate keys on.
 *
 * `edit.php?post_type=corex_project` is deliberately absent. The Portfolio kit is an add-on and is
 * inactive on some installs, where WordPress answers "Invalid post type." at 500 rather than
 * refusing anything — a row that means two different things depending on which add-ons happen to be
 * active is a row that makes the matrix lie.
 */
const REFUSED_FOR_A_SUBSCRIBER = [
	[ 'a CoreX screen', '/wp-admin/admin.php?page=corex-forms', 403 ],
	[
		'a CoreX address with no screen',
		'/wp-admin/admin.php?page=corex-nonexistent',
		404,
	],
	[
		'the CoreX Careers screen',
		'/wp-admin/edit.php?post_type=corex_job',
		403,
	],
	[ 'core Users', '/wp-admin/users.php', 403 ],
	[ 'core Plugins', '/wp-admin/plugins.php', 403 ],
	[ 'core Settings', '/wp-admin/options-general.php', 403 ],
	[ 'core Tools', '/wp-admin/tools.php', 403 ],
	[ 'core Media', '/wp-admin/upload.php', 403 ],
	[ 'core Posts', '/wp-admin/edit.php', 403 ],
];

/** What an editor may open. Branding any of these would be the opposite defect. */
const ALLOWED_FOR_AN_EDITOR = [
	[ 'core Posts', '/wp-admin/edit.php' ],
	[ 'core Media', '/wp-admin/upload.php' ],
	[ 'the CoreX Careers screen', '/wp-admin/edit.php?post_type=corex_job' ],
];

/** …and what it still may not. */
const REFUSED_FOR_AN_EDITOR = [
	[ 'core Plugins', '/wp-admin/plugins.php', 403 ],
	[ 'a CoreX screen', '/wp-admin/admin.php?page=corex-forms', 403 ],
];

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
	// newContext() does not inherit `use.baseURL` the way the `page` fixture does, so it is passed
	// through explicitly — otherwise every relative navigation below is invalid, whatever the
	// config says.
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

		// Bounded: an unbounded wait inside a loop whose purpose is to *try* an address spends the
		// whole test budget on the first one that half-answers.
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

/**
 * Assert that what the browser is looking at is a CoreX error document.
 *
 * Checks the frame, not the words. `StandalonePage::document()` sets the body class, and both of its
 * bodies — the short `notice()` and the wider denied surface — sit inside a `corex-standalone__card`.
 * Two markers rather than one, because the class alone survives a stylesheet that failed to load,
 * which is its own way of producing a white box (FR-010).
 *
 * Not the brand mark: `notice()` renders `.corex-standalone__mark`, but the denied surface renders
 * `.corex-denied__icon` instead. Asserting the mark would demand a design change this spec has no
 * reason to make.
 *
 * @param {import('@playwright/test').Page} page  The page to inspect.
 * @param {string}                          label What was opened, for the failure message.
 * @return {Promise<void>}
 */
async function expectCorexErrorDocument( page, label ) {
	const bodyClass =
		( await page.locator( 'body' ).getAttribute( 'class' ) ) || '';

	expect(
		bodyClass,
		`${ label } must render the CoreX standalone document, not WordPress's white box`
	).toContain( 'corex-standalone' );

	await expect(
		page.locator( '.corex-standalone__card' ),
		`${ label } must render CoreX content, not just a CoreX body class`
	).toBeAttached();
}

test.describe( 'Admin refusals', () => {
	/**
	 * One session per user for the whole file, rather than one per test.
	 *
	 * Not an optimisation. `security-access.spec.js` turns login protection on, and it locks an
	 * account out after repeated sign-ins from one address — so a file that signed in four times
	 * passed on its own and then failed at the fourth test in a full run, reported as "could not
	 * sign in" several assertions away from the cause. Two sign-ins total is well inside the
	 * threshold, and the sessions are read-only here so sharing them changes nothing else.
	 */
	let subscriberPage;
	let editorPage;

	test.beforeAll( async ( { browser } ) => {
		const baseURL = process.env.COREX_BASE_URL || 'http://corex.local';
		subscriberPage = await signInAs(
			browser,
			baseURL,
			SUBSCRIBER,
			SUBSCRIBER_PASS
		);
		editorPage = await signInAs( browser, baseURL, EDITOR, EDITOR_PASS );
	} );

	test.afterAll( async () => {
		await subscriberPage?.context().close();
		await editorPage?.context().close();
	} );

	test( 'every admin refusal a subscriber meets is a CoreX document', async () => {
		const page = subscriberPage;

		for ( const [ label, url, status ] of REFUSED_FOR_A_SUBSCRIBER ) {
			const response = await page.goto( url );

			expect( response.status(), `${ label } (${ url }) status` ).toBe(
				status
			);
			await expectCorexErrorDocument( page, `${ label } (${ url })` );
		}
	} );

	test( 'an administrator meets no error on any of them', async ( {
		page,
	} ) => {
		// The shared storage state is the administrator session. Every address the subscriber is
		// refused from, except the one that genuinely does not exist, must open normally.
		for ( const [ label, url ] of REFUSED_FOR_A_SUBSCRIBER ) {
			if ( url.includes( 'corex-nonexistent' ) ) {
				continue;
			}

			const response = await page.goto( url );

			expect( response.status(), `${ label } (${ url }) status` ).toBe(
				200
			);

			const bodyClass =
				( await page.locator( 'body' ).getAttribute( 'class' ) ) || '';
			expect(
				bodyClass,
				`${ label } (${ url }) must not be presented as an error to somebody who may open it`
			).not.toContain( 'corex-standalone' );
		}
	} );

	test( 'an editor is refused where it should be and nowhere else', async () => {
		const page = editorPage;

		for ( const [ label, url ] of ALLOWED_FOR_AN_EDITOR ) {
			const response = await page.goto( url );

			expect( response.status(), `${ label } (${ url }) status` ).toBe(
				200
			);

			const bodyClass =
				( await page.locator( 'body' ).getAttribute( 'class' ) ) || '';
			expect(
				bodyClass,
				`${ label } (${ url }) is open to an editor and must not be branded as an error`
			).not.toContain( 'corex-standalone' );
		}

		for ( const [ label, url, status ] of REFUSED_FOR_AN_EDITOR ) {
			const response = await page.goto( url );

			expect( response.status(), `${ label } (${ url }) status` ).toBe(
				status
			);
			await expectCorexErrorDocument( page, `${ label } (${ url })` );
		}
	} );

	test( 'an expired link is a CoreX page, not a white box', async () => {
		const page = subscriberPage;

		// The action has to be one this user may actually start, or the capability check refuses
		// first and the nonce is never reached — which is what `options.php?_wpnonce=deadbeef` does,
		// and why it is not the fixture here (evidence/before/refusal-matrix.md).
		await page.goto( '/wp-admin/profile.php' );
		await expect( page.locator( '#your-profile' ) ).toBeVisible();

		await page.evaluate( () => {
			document.querySelector( '#_wpnonce' ).value = 'expired';
		} );

		const [ response ] = await Promise.all( [
			page.waitForNavigation(),
			page.locator( '#submit' ).click(),
		] );

		expect( response.status(), 'an expired nonce is refused' ).toBe( 403 );
		await expectCorexErrorDocument( page, 'an expired profile update' );
	} );

	test( 'logging out is still a WordPress confirmation, not a CoreX error', async () => {
		const page = subscriberPage;

		// The address is taken from the admin bar rather than hard-coded. `security-access.spec.js`
		// turns login protection on, which moves the whole login endpoint to a custom path — so a
		// hard-coded `/wp-login.php?action=logout` passes this file in isolation and fails it in a
		// full run, having tested the theme's 404 instead of the logout flow.
		await page.goto( '/wp-admin/profile.php' );
		const logoutHref = await page
			.locator( '#wp-admin-bar-logout a' )
			.getAttribute( 'href' );
		expect(
			logoutHref,
			'the admin bar must offer a logout link'
		).toBeTruthy();

		// Dropping the nonce is what produces the prompt instead of actually logging out.
		// `wp_nonce_ays('log-out')` then reaches wp_die() at 403 exactly like a refusal — but it is
		// a confirmation, and branding it would present a normal, deliberate action as a failure
		// while dropping the only control on the page.
		await page.goto( logoutHref.replace( /&?_wpnonce=[^&]*/, '' ) );

		const bodyClass =
			( await page.locator( 'body' ).getAttribute( 'class' ) ) || '';
		expect(
			bodyClass,
			'the logout confirmation is not an error and must not be branded as one'
		).not.toContain( 'corex-standalone' );

		// Scoped to `#error-page`, which is the container WordPress's own wp_die handler renders.
		// An unscoped `a[href*="action=logout"]` also matches the admin bar's hidden Log Out item,
		// so it passed on any page that still had an admin bar — including the ones where the
		// prompt had not been reached at all.
		await expect( page.locator( '#wpadminbar' ) ).toHaveCount( 0 );
		await expect(
			page.locator( '#error-page a[href*="action=logout"]' ).first()
		).toBeVisible();
	} );

	test( 'a refused REST request is still JSON', async () => {
		const page = subscriberPage;

		// FR-002. wp_die() picks its handler by request type before any filter runs, so filtering
		// only `wp_die_handler` cannot reach a machine caller — but "cannot by construction" is a
		// claim about core's control flow, and this is the test that it is true of this install.
		const response = await page.request.get(
			'/wp-json/corex/v1/access/requests'
		);

		expect( response.status() ).toBeGreaterThanOrEqual( 400 );
		expect(
			response.headers()[ 'content-type' ] || '',
			'a refused REST request must stay a machine response'
		).toContain( 'application/json' );
	} );
} );
