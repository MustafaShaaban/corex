/**
 * Corex E2E — console-error sweep (spec 052, US2). Fails if a console *error* (not a
 * warning) is emitted while loading the block editor, the Corex settings screen, or the front
 * page — so a JS/asset regression (a 404 asset, a bad block registration,
 * an item-20 block error) fails CI instead of hiding.
 *
 * ENVIRONMENT-GATED: needs a served site and a browser; see the header of playwright.config.js.
 * In CI it runs in the `e2e` job of .github/workflows/ci.yml, which excludes the block-editor
 * test below by title.
 */
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

test( 'the block editor loads with no console errors', async ( { page } ) => {
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
	).toBeVisible();

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'the Corex settings screen loads with no console errors', async ( {
	page,
} ) => {
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
