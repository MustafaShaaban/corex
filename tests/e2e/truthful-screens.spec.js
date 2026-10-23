/**
 * Corex E2E — screens that tell the truth (spec 114, R0.3).
 *
 * The unit and integration suites hold each rule. This holds what a person reads: the
 * Overview and the Security Center agree with the site, the login address is called hidden
 * only when a visitor cannot reach the login form, a PageSpeed run that could not run says so
 * with no grade, the captcha test button is under the field it tests, and the login screen
 * offers no sign-in that does not exist.
 *
 * ENVIRONMENT-GATED: needs a running site at COREX_BASE_URL and `npx playwright install`.
 */
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

const SIGNED_OUT = { cookies: [], origins: [] };

/**
 * A row of the Security Center's operations summary, by its label.
 *
 * @param {Object} page  The signed-in Playwright page, on the Security Center.
 * @param {string} label The row's label.
 * @return {Promise<string>} The row's value as drawn.
 */
async function summaryValue( page, label ) {
	const row = page.locator( '.corex-opsec__summary-item' ).filter( {
		has: page.locator( '.corex-opsec__summary-label', {
			hasText: label,
		} ),
	} );

	return (
		await row.locator( '.corex-opsec__summary-value' ).innerText()
	).trim();
}

test.describe( 'the Overview and the Security Center say what is so', () => {
	test( 'the Overview names hardening as hardening and reads login protection from the Security Center', async ( {
		page,
	} ) => {
		const errors = collectConsoleErrors( page );

		await page.goto(
			'/wp-admin/admin.php?page=corex-operations-security&tab=overview'
		);
		const protection = await summaryValue( page, 'Login protection' );

		await page.goto( '/wp-admin/admin.php?page=corex-settings' );
		const overview = page.locator( '.corex-overview' );
		await expect(
			overview.locator( '.corex-overview__check-label', {
				hasText: 'Hardening checks',
			} )
		).toHaveCount( 1 );

		const login = overview
			.locator( '.corex-overview__integration' )
			.filter( { hasText: 'Login protection' } )
			.locator( '.corex-overview__integration-note' );
		// "Off" on both, or "On, …" against "On".
		await expect( login ).toHaveText(
			protection === 'Off' ? 'Off' : /^On, /
		);

		// The states that were drawn whatever was set.
		await expect( overview ).not.toContainText( 'Provider connected' );
		await expect( overview ).not.toContainText(
			'Captcha / honeypot ready'
		);

		expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual(
			[]
		);
	} );

	test( 'calls the default login hidden only when a visitor cannot reach the login form', async ( {
		page,
		browser,
	} ) => {
		await page.goto(
			'/wp-admin/admin.php?page=corex-operations-security&tab=overview'
		);
		const endpoints = await summaryValue( page, 'Default login endpoints' );

		const visitor = await browser.newContext( {
			storageState: SIGNED_OUT,
		} );
		const answer = await visitor.request.get( '/wp-login.php', {
			maxRedirects: 0,
		} );
		const servesTheForm = ( await answer.text() ).includes(
			'id="loginform"'
		);
		await visitor.close();

		// FR-009: whatever the site's settings, the row and the address agree.
		expect( servesTheForm, `the row says "${ endpoints }"` ).toBe(
			! endpoints.startsWith( 'Hidden' )
		);
	} );
} );

test.describe( 'a PageSpeed run that could not run', () => {
	test( 'says so, with the reason and Retry, and no grade or score', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=corex-insights' );
		const card = page.locator( '.corex-insight-card', {
			has: page.getByRole( 'heading', { name: 'Performance' } ),
		} );
		const run = card.getByRole( 'button' );
		await expect( run ).toBeEnabled();

		// The suite's site is on a local address, which PageSpeed cannot reach: the run stops
		// before any request leaves.
		await run.click();

		await expect( card ).toHaveClass( /is-failed/ );
		await expect( card ).toContainText( 'Could not run' );
		await expect( card ).toContainText(
			'PageSpeed cannot reach a local site'
		);
		await expect(
			card.locator( '.corex-insight-card__grade' )
		).toHaveCount( 0 );
		await expect( card.getByRole( 'button' ) ).toHaveText( 'Retry' );
	} );
} );

test.describe( 'the captcha test button', () => {
	test( 'sits under the secret key field when keys are saved, and its answer is announced', async ( {
		page,
	} ) => {
		// Keys answered for this page's requests only (fixture corex-e2e-captcha-keys.php).
		await page.setExtraHTTPHeaders( { 'X-Corex-E2E-Captcha': 'keys' } );
		// The provider is not asked: its answer is the route's, given here.
		await page.route( '**/captcha/test**', ( route ) =>
			route.fulfill( {
				status: 400,
				contentType: 'application/json',
				body: JSON.stringify( {
					ok: false,
					error: 'invalid_keys',
					message:
						'Wrong key: the provider refused the secret key. Copy both keys again from the provider.',
				} ),
			} )
		);
		await page.goto(
			'/wp-admin/admin.php?page=corex-settings-config&corex_tab=captcha'
		);

		const cell = page.locator( 'td', {
			has: page.locator( '#captcha_secret' ),
		} );
		const button = cell.getByRole( 'button', {
			name: 'Test verification',
		} );
		await expect( button ).toBeVisible();

		await button.focus();
		await page.keyboard.press( 'Enter' );

		const answer = cell.locator( '.corex-captcha-test__result' );
		await expect( answer ).toHaveAttribute( 'aria-live', 'polite' );
		await expect( answer ).toContainText( 'Wrong key' );
	} );

	test( 'is not offered without saved keys, and the field says what is needed', async ( {
		page,
	} ) => {
		await page.setExtraHTTPHeaders( { 'X-Corex-E2E-Captcha': 'provider' } );
		await page.goto(
			'/wp-admin/admin.php?page=corex-settings-config&corex_tab=captcha'
		);

		const cell = page.locator( 'td', {
			has: page.locator( '#captcha_secret' ),
		} );
		await expect(
			cell.locator( '.corex-captcha-test__needed' )
		).toHaveText(
			'Save a site key and a secret key for this provider, then test them here.'
		);
		await expect(
			page.getByRole( 'button', { name: 'Test verification' } )
		).toHaveCount( 0 );
	} );
} );

test.describe( 'the login screen', () => {
	test.use( { storageState: SIGNED_OUT } );

	test( 'offers no single sign-on button and no "or" divider', async ( {
		page,
	} ) => {
		await page.goto( '/wp-login.php' );
		test.skip(
			( await page.locator( '#loginform' ).count() ) === 0,
			'this site hides its default login address'
		);

		await expect( page.locator( '.corex-login__subtitle' ) ).toBeVisible();
		await expect(
			page.locator( '.corex-login__sso, .corex-login__divider' )
		).toHaveCount( 0 );
		await expect( page.locator( 'body' ) ).not.toContainText( 'SSO' );
		// Every button on the page belongs to a form WordPress draws itself.
		await expect(
			page.locator(
				'button:not(form button), input[type="submit"]:not(form input)'
			)
		).toHaveCount( 0 );
	} );
} );
