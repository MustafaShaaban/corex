/**
 * Insights, Setup Wizard, and Settings surfaces (spec 068: T204). Verifies the full designed
 * Insights widget set, the nine-step wizard flow, and the Settings sections (incl. the Advanced
 * diagnostics read-out) render from real state with no console errors and no horizontal overflow.
 */

const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

test( 'Insights renders the full designed widget set from real state', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-insights' );
	await expect(
		page.getByRole( 'heading', { name: 'Readiness & Insights' } )
	).toBeVisible();

	// Two runnable providers (run-cards) + the five informational widgets.
	await expect( page.locator( '.corex-insight-card' ) ).toHaveCount( 2 );
	await expect( page.locator( '.corex-insight-widget' ) ).toHaveCount( 5 );
	await expect( page.getByRole( 'heading', { name: 'Security events' } ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'Forms & Flows analytics' } ) ).toBeVisible();
	// No stale "Planned" widget.
	await expect( page.getByText( 'Planned', { exact: true } ) ).toHaveCount( 0 );

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'Setup Wizard walks the nine-step flow with real kit and demo levels', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-setup' );
	await expect(
		page.getByRole( 'heading', { name: 'CoreX Setup Wizard' } )
	).toBeVisible();

	// The nine-step stepper mounted (JS took over from the server fallback).
	await expect( page.locator( '.corex-setup__step' ) ).toHaveCount( 9 );
	await expect( page.locator( '#corex-setup-app .corex-setup__panel' ) ).toBeVisible();

	// Welcome → Brand → Kit: a real kit option is offered.
	await page.locator( '#corex-setup-next' ).click();
	await page.locator( '#corex-setup-next' ).click();
	await expect( page.locator( 'input[name="corex-kit"]' ).first() ).toBeVisible();

	// Kit → Demo: the three real demo levels.
	await page.locator( '#corex-setup-next' ).click();
	await expect( page.locator( 'input[name="corex-level"]' ) ).toHaveCount( 3 );

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'Settings renders the real sections including the Advanced diagnostics read-out', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-settings-config&corex_tab=advanced' );
	await expect(
		page.getByRole( 'heading', { name: 'CoreX Settings' } )
	).toBeVisible();

	// The section tabs, including the new Advanced tab.
	await expect( page.locator( '[data-corex-tab="advanced"]' ) ).toBeVisible();
	await expect( page.locator( '[data-corex-tab="media"]' ) ).toBeVisible();
	// The Advanced diagnostics show a real PHP version — never a fabricated value.
	await expect( page.getByText( 'PHP version' ) ).toBeVisible();
	await expect( page.getByText( /8\.\d+\.\d+/ ).first() ).toBeVisible();

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'the three surfaces have no horizontal overflow at mobile width', async ( {
	page,
} ) => {
	for ( const slug of [ 'corex-insights', 'corex-setup', 'corex-settings-config' ] ) {
		await page.setViewportSize( { width: 375, height: 800 } );
		await page.goto( `/wp-admin/admin.php?page=${ slug }` );
		await page.waitForTimeout( 400 );
		const fits = await page.evaluate(
			() =>
				document.documentElement.scrollWidth <=
				document.documentElement.clientWidth + 1
		);
		expect( fits, `horizontal overflow on ${ slug }` ).toBe( true );
	}
} );
