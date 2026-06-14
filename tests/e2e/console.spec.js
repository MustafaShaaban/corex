/**
 * Corex E2E — console-error sweep (spec 052, US2). Fails if a console *error* (not a
 * warning) is emitted while loading the block editor, a Corex admin screen, or a front-end
 * page with Corex blocks — so a JS/asset regression (a 404 asset, a bad block registration,
 * an item-20 block error) fails CI instead of hiding.
 *
 * ENVIRONMENT-GATED: needs wp-env up + `npx playwright install`. Runs in the e2e workflow.
 */
const { test, expect } = require( '@playwright/test' );
const { login, collectConsoleErrors } = require( './helpers' );

test( 'the block editor loads with no console errors', async ( { page } ) => {
	const errors = collectConsoleErrors( page );

	await login( page );
	await page.goto( '/wp-admin/post-new.php?post_type=page' );
	await page.waitForLoadState( 'networkidle' );

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'the Corex settings screen loads with no console errors', async ( { page } ) => {
	const errors = collectConsoleErrors( page );

	await login( page );
	await page.goto( '/wp-admin/admin.php?page=corex-settings' );
	await page.waitForLoadState( 'networkidle' );

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'a front-end page loads with no console errors', async ( { page } ) => {
	const errors = collectConsoleErrors( page );

	await page.goto( '/' );
	await page.waitForLoadState( 'networkidle' );

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );
