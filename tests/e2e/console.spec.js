/**
 * Corex E2E — console-error sweep (spec 052, US2). Fails if a console *error* (not a
 * warning) is emitted while loading the block editor, a Corex admin screen, or a front-end
 * page with Corex blocks — so a JS/asset regression (a 404 asset, a bad block registration,
 * an item-20 block error) fails CI instead of hiding.
 *
 * ENVIRONMENT-GATED: needs wp-env up + `npx playwright install`. Runs in the e2e workflow.
 */
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

test( 'the block editor loads with no console errors', async ( { page } ) => {
	// First spec in the suite to open the block editor, so it pays the whole cold cost. A trace of
	// a failing CI run settled what that is: no console errors, no failed requests, and the editor
	// header — "Block Inserter" among 25 buttons — present in a snapshot captured just after the
	// assertion gave up. It renders; it is only slower than a default budget on the very first
	// open. Excluding this spec does not help: the next one to open the editor inherits the cost.
	test.setTimeout( 180_000 );

	const errors = collectConsoleErrors( page );

	await page.goto( '/wp-admin/post-new.php?post_type=page' );
	// `networkidle` is unreliable here — the editor keeps connections open (heartbeat),
	// so it may never idle. Wait for a concrete interactive signal instead: the editor
	// toolbar's inserter toggle being visible means the editor hydrated.
	await expect(
		page
			.getByRole( 'button', {
				name: /block inserter|toggle block inserter|add block/i,
			} )
			.first()
	).toBeVisible( { timeout: 150_000 } );

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'the Corex settings screen loads with no console errors', async ( { page } ) => {
	const errors = collectConsoleErrors( page );

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
