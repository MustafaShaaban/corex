/**
 * Operations/Security and Access workflow browser evidence (spec 068: T153).
 *
 * Environment-gated: requires the Playwright global admin login plus a running WordPress site.
 */

const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

test( 'renders launch checklist login policy lockouts recovery and activity without console errors', async ( { page } ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-operations-security' );

	await expect( page.getByRole( 'heading', { name: 'CoreX Operations & Security' } ) ).toBeVisible();
	await expect( page.getByTestId( 'corex-security-center' ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'Production readiness' } ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'Protection settings' } ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'Lockouts' } ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'Recovery' } ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'Security activity' } ) ).toBeVisible();

	await page.getByLabel( 'Target mode' ).selectOption( 'production' );
	await expect( page.getByRole( 'dialog', { name: 'Production confirmation' } ) ).toBeVisible();
	await page.getByLabel( 'Type PRODUCTION' ).fill( 'PRODUCTION' );
	await expect( page.getByText( 'Typed confirmation is ready.' ) ).toBeVisible();

	// Recovery shows the command and nothing else (spec 069). It used to carry a "Mark command
	// reviewed" button that flipped a label and did nothing else — and this spec asserted that it
	// worked, which is how a control with no effect stayed shipped. Recovery runs from the CLI by
	// necessity: it exists for when the admin is unreachable, so no button here could perform it.
	// Scoped to the panel's own code block: the command also appears in the login-policy warning,
	// so an unscoped text match is ambiguous.
	await expect( page.locator( '.corex-security__panel code', { hasText: 'wp corex security reset-login' } ).first() ).toBeVisible();
	await expect( page.getByRole( 'button', { name: 'Mark command reviewed' } ) ).toHaveCount( 0 );

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'always says where the login is, and warns before hiding the default endpoints', async ( { page } ) => {
	// The owner has to leave this screen knowing where to sign in — saving hides wp-login.php and
	// wp-admin, and used to say nothing about what replaced them. The address reflects the SAVED
	// settings, so it is shown whether protection is on or off: it is always true.
	await page.goto( '/wp-admin/admin.php?page=corex-operations-security' );

	await expect( page.getByText( 'Sign in at:' ) ).toBeVisible();
	await expect( page.locator( '.corex-security__login-url a' ) ).toHaveAttribute( 'href', /^https?:\/\/.+/ );

	// The warning is about what saving WILL do, so it tracks the checkboxes, not the saved state.
	const enable = page.getByLabel( 'Enable failed-login protection' );
	const hide = page.getByLabel( 'Hide wp-login.php and wp-admin' );
	if ( ! ( await enable.isChecked() ) ) {
		await enable.check();
	}
	if ( ! ( await hide.isChecked() ) ) {
		await hide.check();
	}
	await expect( page.locator( '.corex-security__warning' ) ).toBeVisible();

	await enable.uncheck();
	await expect( page.locator( '.corex-security__warning' ) ).toHaveCount( 0 );
} );

test( 'creates a live access request through the localized Access REST workflow', async ( { page } ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-access&tab=matrix' );
	await expect( page.getByRole( 'heading', { name: 'CoreX Access & Abilities' } ) ).toBeVisible();
	await expect( page.locator( '#corex-access-app' ) ).toBeVisible();

	const result = await page.evaluate( async () => {
		return window.Corex.api.post(
			`${ window.corexAccess.restUrl }/requests`,
			{
				ability: 'corex_manage_forms',
				reason: 'Playwright request-access workflow evidence.',
			},
			{ nonce: window.corexAccess.nonce }
		);
	} );

	// AccessController wraps its payload under `data` (asserted by AccessControllerTest), and the
	// shared REST envelope adds its own `data`, so the created request lands at data.data.result.
	expect( result.envelope.ok ).toBe( true );
	expect( result.envelope.data.data.result.state ).toBe( 'completed' );
	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test.describe( 'a hidden endpoint is indistinguishable from a page that was never there', () => {
	// The whole point of hiding is that a probe learns nothing, so these must run signed OUT —
	// the project-wide storageState would authenticate them and skip the guard entirely.
	test.use( { storageState: { cookies: [], origins: [] } } );

	// Anything a probe could use to tell "handled" from "absent". The deprecation notice that
	// shipped (print_emoji_styles, printed into the body because WP_DEBUG_DISPLAY is on) is the
	// exact defect this locks; the rest are here so a future core deprecation cannot reintroduce
	// the same class of leak somewhere else.
	const DIAGNOSTIC = /Deprecated|Notice:|Warning:|Fatal error|is deprecated since version|Stack trace/;

	const CONTROL = '/corex-definitely-not-a-page/';

	test( 'the default login and admin endpoints 404 without leaking a diagnostic', async ( { request } ) => {
		const control = await request.get( CONTROL, { maxRedirects: 0 } );
		const login = await request.get( '/wp-login.php', { maxRedirects: 0 } );
		const admin = await request.get( '/wp-admin/', { maxRedirects: 0 } );

		expect( control.status(), 'control URL must genuinely 404' ).toBe( 404 );
		expect( login.status() ).toBe( 404 );
		expect( admin.status() ).toBe( 404 );

		for ( const [ name, response ] of [
			[ 'control', control ],
			[ 'wp-login.php', login ],
			[ 'wp-admin', admin ],
		] ) {
			const body = await response.text();
			expect( body, `${ name } leaked a PHP diagnostic into its body` ).not.toMatch( DIAGNOSTIC );
		}
	} );

	test( 'the hidden wp-login.php is byte-identical to a page that does not exist', async ( { request } ) => {
		// wp-login.php is the endpoint that actually identifies a hidden login, so this one has to
		// be exact. /wp-admin cannot be (see below).
		const control = await ( await request.get( CONTROL, { maxRedirects: 0 } ) ).text();
		const login = await ( await request.get( '/wp-login.php', { maxRedirects: 0 } ) ).text();

		expect( login ).toBe( control );
	} );

	test( 'the hidden admin 404 carries the same emoji styles a real 404 does', async ( { request } ) => {
		// Core unhooks its deprecated emoji shim via a branch on is_admin(). WP_ADMIN cannot be
		// unset, so on a hidden /wp-admin that unhook silently missed and the deprecated function
		// ran instead. The guard moves the shim to the hook core actually inspects, which both
		// silences the notice AND lets core enqueue the modern inline styles — so this block is
		// present in both responses. Removing the shim outright would pass the diagnostic test
		// above while making the two responses differ more, which is why it is asserted here.
		const control = await ( await request.get( CONTROL, { maxRedirects: 0 } ) ).text();
		const admin = await ( await request.get( '/wp-admin/', { maxRedirects: 0 } ) ).text();

		expect( control ).toContain( 'wp-emoji-styles-inline-css' );
		expect( admin ).toContain( 'wp-emoji-styles-inline-css' );
	} );
} );

test( 'contains Security and Access workspaces at mobile tablet desktop wide and RTL viewports', async ( { page } ) => {
	for ( const route of [ 'corex-operations-security', 'corex-access' ] ) {
		await page.goto( `/wp-admin/admin.php?page=${ route }` );
		for ( const width of [ 375, 768, 1024, 1440 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			const fits = await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth );
			expect( fits, `${ route } horizontal overflow at ${ width }px` ).toBe( true );
		}
		await page.locator( 'html' ).evaluate( ( root ) => root.setAttribute( 'dir', 'rtl' ) );
		await expect( page.locator( '.corex-admin' ) ).toHaveCSS( 'direction', 'rtl' );
	}
} );
