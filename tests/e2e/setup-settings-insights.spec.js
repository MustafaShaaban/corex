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

test( 'Insights lays its cards and widgets out on one shared grid', async ( { page } ) => {
	// This spec used to assert element counts only, which is exactly why the broken layout passed
	// CI for so long: the widgets were appended into a nested container that the outer grid then
	// treated as ONE cell, so all five collapsed into a single narrow column beside the cards.
	// Counts were still 2 and 5. Geometry is what catches it.
	await page.setViewportSize( { width: 1440, height: 900 } );
	await page.goto( '/wp-admin/admin.php?page=corex-insights' );
	await expect( page.locator( '.corex-insight-widget' ) ).toHaveCount( 5 );

	// The nested container is gone and every tile is a direct child of the one grid.
	await expect( page.locator( '.corex-insights__widgets' ) ).toHaveCount( 0 );
	await expect( page.locator( '#corex-insights-app > *' ) ).toHaveCount( 7 );

	// Two columns above the 48rem breakpoint: every tile starts on one of exactly two x offsets.
	const columns = await page
		.locator( '#corex-insights-app > *' )
		.evaluateAll( ( tiles ) => [
			...new Set( tiles.map( ( tile ) => Math.round( tile.getBoundingClientRect().x ) ) ),
		] );
	expect( columns, `tile x offsets: ${ columns.join( ', ' ) }` ).toHaveLength( 2 );

	// And they are real columns, not a stack: the widest tile cannot span the whole row.
	const grid = await page.locator( '#corex-insights-app' ).boundingBox();
	const tile = await page.locator( '.corex-insight-widget' ).first().boundingBox();
	expect( tile.width ).toBeLessThan( grid.width * 0.75 );
} );

test( 'Insights orders its widgets by urgency, with nothing-to-show last', async ( { page } ) => {
	// FR-027. The chip text carries the state, so DOM order plus chip text is the whole contract.
	await page.goto( '/wp-admin/admin.php?page=corex-insights' );
	await expect( page.locator( '.corex-insight-widget' ) ).toHaveCount( 5 );

	const chips = await page
		.locator( '.corex-insight-widget .corex-badge' )
		.allInnerTexts();
	const idle = chips.map( ( chip, index ) =>
		/Not connected|No data yet/.test( chip ) ? index : -1
	);
	const busy = chips.map( ( chip, index ) =>
		/Not connected|No data yet/.test( chip ) ? -1 : index
	);

	const lastBusy = Math.max( ...busy );
	const firstIdle = Math.min( ...idle.filter( ( index ) => index >= 0 ) );

	expect(
		firstIdle === Infinity || lastBusy < firstIdle,
		`chips in DOM order: ${ chips.join( ' | ' ) }`
	).toBe( true );
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
