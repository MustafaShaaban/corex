/**
 * Asking for access, in a browser, as somebody who actually lacks it (spec 079, US1/US2).
 *
 * The defect these tests exist for was not visible from an administrator's session and not visible
 * from the response body of any single request: the form's action pointed at a REST endpoint, so
 * pressing the button *navigated* the browser to a JSON document. Only a real submission by a real
 * subscriber shows it, which is why this file signs in as one rather than reusing the shared admin
 * storage state.
 */
const { test, expect } = require( '@playwright/test' );

const REQUESTER = process.env.COREX_REQUESTER_USER || 'corex-requester';
const REQUESTER_PASS =
	process.env.COREX_REQUESTER_PASS || 'CorexE2E!requester1';
const DENIED_SCREEN = '/wp-admin/admin.php?page=corex-forms';

/** Where a login form might be, in the order global-setup.js tries them. */
const LOGIN_PATHS = [
	process.env.COREX_LOGIN_PATH,
	'/wp-login.php',
	'/corex-login/',
].filter( Boolean );

/** Fields the old JSON page exposed. None of them belongs on a page a person reads. */
const INTERNAL_FIELDS = [
	'operation_id',
	'affected_ids',
	'audit_event_id',
	'"state"',
];

/**
 * Clear the requester's open requests, as an administrator, through the product's own decision route.
 *
 * Without this the spec passes once and then fails for seven days: the confirmation state is read
 * from the database on purpose, so a request left pending by an earlier run means the second run
 * never sees a form. A test that only works on a fresh install is a test that will be marked flaky
 * and disabled rather than believed.
 *
 * @param {import('@playwright/test').Page} page An admin-authenticated page.
 * @return {Promise<void>}
 */
async function clearPendingRequests( page ) {
	await page.goto( '/wp-admin/admin.php?page=corex-access' );

	// `corexAccess.nonce`, not `wpApiSettings.nonce`: the Access screen localizes the first
	// itself, so it is certainly there. The second depends on `wp-api-request` being enqueued,
	// and when it is not the value is `undefined`, the request 401s, and a cleanup written to
	// shrug that off leaves the suite failing for a reason nothing reports.
	const nonce = await page.evaluate(
		() => window.corexAccess && window.corexAccess.nonce
	);
	expect(
		nonce,
		'the Access screen must localize a REST nonce'
	).toBeTruthy();

	const listed = await page.request.get(
		'/wp-json/corex/v1/access/requests',
		{
			headers: { 'X-WP-Nonce': nonce },
		}
	);
	expect( listed.status(), 'pending requests must be listable' ).toBe( 200 );

	const body = await listed.json();
	// Only this requester's. Other specs create access requests of their own, and a cleanup that
	// denies everything pending would quietly break them — the same cross-file damage this file
	// suffered when it assumed the first row in the panel was its own.
	const pending = ( body?.data?.requests || [] ).filter(
		( request ) => request.requester === REQUESTER
	);

	for ( const request of pending ) {
		await page.request.post(
			`/wp-json/corex/v1/access/requests/${ request.id }/decision`,
			{
				headers: { 'X-WP-Nonce': nonce },
				data: {
					approved: false,
					note: 'Cleared by the spec 079 browser suite.',
				},
			}
		);
	}
}

/**
 * A browser signed in as the subscriber, in its own context so the shared admin session is untouched.
 *
 * @param {import('@playwright/test').Browser} browser The Playwright browser.
 * @param {string}                             baseURL Where the site is served.
 * @return {Promise<import('@playwright/test').Page>} The signed-in page.
 */
async function signInAsRequester( browser, baseURL ) {
	// `browser.newContext()` does not inherit `use.baseURL` the way the `page` fixture does, so it
	// is passed through from the `baseURL` fixture — otherwise every relative URL below is an
	// invalid navigation, whatever the config says.
	const context = await browser.newContext( {
		storageState: undefined,
		baseURL,
	} );
	const page = await context.newPage();

	// The login page moves. `security-access.spec.js` turns login protection on, which makes
	// /wp-login.php answer the theme's 404 like any missing address — the feature working, not a
	// fault — and with ten workers that can happen while this file is running. `global-setup.js`
	// copes the same way for the administrator session; without it, sign-in fails silently here
	// and the failure surfaces several assertions later as a missing form.
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

		await page.fill( '#user_login', REQUESTER );
		await page.fill( '#user_pass', REQUESTER_PASS );

		// Bounded, and a timeout falls through to the next candidate rather than ending the test.
		// An unbounded `waitForNavigation()` inside a loop whose whole purpose is to *try* an
		// address spends the entire 60s test budget on the first one that half-answers — which is
		// what it did under ten parallel workers, reported as a timeout in a helper rather than as
		// "could not sign in".
		await Promise.all( [
			page.waitForNavigation( { timeout: 15000 } ).catch( () => {} ),
			page.click( '#wp-submit' ),
		] );
		signedIn = ! page.url().includes( 'wp-login.php' );

		if ( signedIn ) {
			break;
		}
	}

	expect( signedIn, `signed in as ${ REQUESTER }` ).toBe( true );

	return page;
}

test.describe( 'Access request', () => {
	// `page` here carries the shared administrator session, which is exactly what the cleanup
	// needs — and it is the same route the Access screen's own Approve/Deny buttons call, so a
	// break in the decision workflow shows up here as well as in its own test.
	test.beforeEach( async ( { page } ) => {
		await clearPendingRequests( page );
	} );

	test( 'submitting the request keeps the browser in wp-admin and confirms what happened', async ( {
		browser,
		baseURL,
	} ) => {
		const page = await signInAsRequester( browser, baseURL );

		const denied = await page.goto( DENIED_SCREEN );

		expect( denied.status() ).toBe( 403 );
		await expect(
			page.locator( 'form.corex-denied__request' )
		).toBeVisible();

		// The form's action is the defect. Asserted before submitting, so a failure here names the
		// cause rather than the symptom.
		await expect(
			page.locator( 'form.corex-denied__request' )
		).toHaveAttribute( 'action', /admin-post\.php/ );

		await page.fill(
			'#corex-denied-request-reason',
			'I maintain the contact form and cannot open the screen.'
		);
		await Promise.all( [
			page.waitForNavigation(),
			page.click( 'form.corex-denied__request button[type="submit"]' ),
		] );

		// Before spec 079 this landed on /wp-json/corex/v1/access/requests rendering an operation
		// envelope — a *successful* request the requester had no way to recognise as one.
		expect( page.url() ).not.toContain( '/wp-json/' );
		expect( page.url() ).toContain( 'page=corex-forms' );

		await expect( page.locator( '.corex-denied__sent' ) ).toBeVisible();
		await expect(
			page.locator( 'form.corex-denied__request' )
		).toHaveCount( 0 );

		// It says when, in the product's own date language rather than an ISO string.
		await expect(
			page.locator( '.corex-denied__sent-when' )
		).toBeVisible();
		const when = await page
			.locator( '.corex-denied__sent-when' )
			.innerText();
		expect( when ).not.toMatch( /\d{4}-\d{2}-\d{2}T/ );

		const body = await page.locator( 'body' ).innerText();
		for ( const field of INTERNAL_FIELDS ) {
			expect( body ).not.toContain( field );
		}

		await page.context().close();
	} );

	test( 'refreshing after the request neither resubmits nor offers the form again', async ( {
		browser,
		baseURL,
	} ) => {
		const page = await signInAsRequester( browser, baseURL );

		await page.goto( DENIED_SCREEN );
		await page.fill(
			'#corex-denied-request-reason',
			'Second scenario, first submission.'
		);
		await Promise.all( [
			page.waitForNavigation(),
			page.click( 'form.corex-denied__request button[type="submit"]' ),
		] );
		await expect( page.locator( '.corex-denied__sent' ) ).toBeVisible();

		await page.reload();

		// Post/redirect/GET: the reload re-runs a GET, so there is no resubmission dialog and no
		// second request. The state survives because it is read from the database, not from a flag.
		await expect( page.locator( '.corex-denied__sent' ) ).toBeVisible();
		await expect(
			page.locator( 'form.corex-denied__request' )
		).toHaveCount( 0 );

		await page.context().close();
	} );

	test( 'an administrator can see the request and decide it', async ( {
		browser,
		baseURL,
		page,
	} ) => {
		const requester = await signInAsRequester( browser, baseURL );
		await requester.goto( DENIED_SCREEN );
		await requester.fill(
			'#corex-denied-request-reason',
			'I need Forms & Flows to publish the contact form.'
		);
		await Promise.all( [
			requester.waitForNavigation(),
			requester.click(
				'form.corex-denied__request button[type="submit"]'
			),
		] );
		await expect(
			requester.locator( '.corex-denied__sent' )
		).toBeVisible();

		// The other half of the workflow. Before spec 079 the REST route and the screen both
		// handed the panel a hardcoded empty array, so the request landed in a table no surface
		// read — while the requester was told an administrator would review it.
		// The landing tab has to say somebody is waiting, or the workflow depends on an
		// administrator happening to open the right tab.
		await page.goto( '/wp-admin/admin.php?page=corex-access' );
		await expect( page.locator( '.corex-access__waiting' ) ).toBeVisible();
		await expect(
			page
				.locator( '.corex-access__waiting' )
				.getByRole( 'link', { name: 'Review requests' } )
		).toHaveAttribute( 'href', /tab=matrix/ );

		// Opened directly rather than by following that link. Chromium in headless stops ticking
		// requestAnimationFrame on this page after a same-document tab navigation, and Playwright's
		// actionability check waits on two animation frames — so the click below would hang on a
		// paint quirk rather than on anything the product does. The href is asserted above, so the
		// link is still covered; what needs a real click is the decision.
		await page.goto( '/wp-admin/admin.php?page=corex-access&tab=matrix' );

		// The row for *this* requester. `.first()` was wrong: another spec creates its own access
		// request, and whichever is newest wins the position.
		const request = page
			.locator( '.corex-access__request' )
			.filter( { hasText: REQUESTER } );

		await expect( request ).toBeVisible();
		await expect( request ).toContainText( REQUESTER );
		await expect( request ).toContainText(
			'I need Forms & Flows to publish the contact form.'
		);
		// The date reads as a date, not as the ISO string the transport carries.
		await expect( request.locator( 'time' ) ).toBeVisible();
		await expect( request.locator( 'time' ) ).not.toContainText( 'T00:' );

		await request.getByRole( 'button', { name: 'Deny' } ).click();

		// Gone from the panel only because the server recorded the decision — and gone from the
		// requester's screen too, which is the proof it was a decision and not a UI state.
		await expect( request ).toHaveCount( 0 );

		await requester.reload();
		await expect(
			requester.locator( 'form.corex-denied__request' )
		).toBeVisible();

		await requester.context().close();
	} );

	test( 'a CoreX address with no screen behind it is a 404, not a denial', async ( {
		page,
	} ) => {
		// As an administrator, which is the case the defect report named: it used to answer 403 and
		// tell somebody who holds `manage_options` that their role does not, then offer a form to
		// request access to a screen that does not exist. Using the shared admin session also keeps
		// this assertion out of reach of the specs that move the login page and switch the site into
		// maintenance while this file is running.
		const response = await page.goto(
			'/wp-admin/admin.php?page=corex-nonexistent'
		);

		expect( response.status() ).toBe( 404 );
		await expect(
			page.locator( 'form.corex-denied__request' )
		).toHaveCount( 0 );
		await expect( page.locator( 'body' ) ).not.toContainText(
			'manage_options'
		);
	} );

	test( 'the denied surface fits a 375px viewport in RTL without scrolling sideways', async ( {
		browser,
		baseURL,
	} ) => {
		const page = await signInAsRequester( browser, baseURL );

		await page.setViewportSize( { width: 375, height: 800 } );
		await page.goto( DENIED_SCREEN );
		await page.evaluate( () => {
			document.documentElement.setAttribute( 'dir', 'rtl' );
		} );

		const overflow = await page.evaluate( () => {
			const el = document.documentElement;

			return el.scrollWidth - el.clientWidth;
		} );

		expect( overflow ).toBeLessThanOrEqual( 0 );

		await page.context().close();
	} );
} );
