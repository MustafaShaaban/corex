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

	await page.getByRole( 'button', { name: 'Mark command reviewed' } ).click();
	await expect( page.getByText( 'Recovery guidance reviewed.' ) ).toBeVisible();

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
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
