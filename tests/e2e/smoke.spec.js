/**
 * Corex E2E smoke — the three flows the compliance review (P4) named:
 *   1. Insert a corex/* block in the editor (it is recognised, not "unsupported").
 *   2. Submit the front-end contact form (shared-schema validation + AJAX).
 *   3. Apply a kit in the setup wizard (flags on, modules active, demo home seeded).
 *
 * ENVIRONMENT-GATED: needs Apache up (http://corex.local) + `npx playwright install`.
 * Admin creds come from env (defaults match the dev box in PROGRESS "Environment quick
 * reference"); override with COREX_ADMIN_USER / COREX_ADMIN_PASS.
 */
const { test, expect } = require( '@playwright/test' );

const ADMIN_USER = process.env.COREX_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.COREX_ADMIN_PASS || '123456';

async function login( page ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', ADMIN_USER );
	await page.fill( '#user_pass', ADMIN_PASS );
	await page.click( '#wp-submit' );
	await expect( page ).toHaveURL( /wp-admin/ );
}

test( 'a corex block is recognised in the editor inserter', async ( { page } ) => {
	await login( page );
	await page.goto( '/wp-admin/post-new.php?post_type=page' );

	// Open the inserter and search for a Corex block.
	await page.getByLabel( /Toggle block inserter/i ).click();
	await page.getByPlaceholder( /Search/i ).fill( 'Corex' );

	// At least one corex/* block appears (not the "unsupported" placeholder).
	await expect( page.locator( '.block-editor-block-types-list__item' ).first() ).toBeVisible();
} );

test( 'the contact form validates and accepts a submission', async ( { page } ) => {
	// Assumes a page containing the corex/form (contact) block at /contact.
	await page.goto( '/contact/' );

	const form = page.locator( 'form[data-corex-schema]' );
	await expect( form ).toBeVisible();

	// Empty submit → client validation shows a field error (no navigation).
	await form.getByRole( 'button', { name: /send|submit/i } ).click();
	await expect( form.locator( '[role="alert"]' ).first() ).not.toBeEmpty();

	// Valid submit → success response region.
	await form.locator( 'input[name="name"]' ).fill( 'Smoke Test' );
	await form.locator( 'input[name="email"]' ).fill( 'smoke@example.com' );
	await form.locator( 'textarea[name="message"], input[name="message"]' ).first().fill( 'Hello from Playwright.' );
	await form.getByRole( 'button', { name: /send|submit/i } ).click();
	await expect( page.locator( '.corex-form__status, [role="status"]' ).first() ).toBeVisible();
} );

test( 'the setup wizard applies a kit', async ( { page } ) => {
	await login( page );
	await page.goto( '/wp-admin/admin.php?page=corex-setup' );

	await expect( page.getByRole( 'heading', { name: /Setup Wizard/i } ) ).toBeVisible();

	// Apply the first listed kit; expect the success notice.
	await page.getByRole( 'button', { name: /Apply this kit/i } ).first().click();
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
} );
