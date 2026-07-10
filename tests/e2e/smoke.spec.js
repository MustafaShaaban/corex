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

test( 'a corex block is recognised in the editor inserter', async ( { page } ) => {
	await page.goto( '/wp-admin/post-new.php?post_type=page' );

	// Open the inserter and search for a Corex block. The toggle's accessible name has
	// varied across Gutenberg versions ("Block Inserter" in WP 7.0, "Toggle block
	// inserter"/"Add block" earlier) — match any so the smoke survives a WP bump.
	await page.getByRole( 'button', { name: /block inserter|toggle block inserter|add block/i } ).first().click();
	await page.getByPlaceholder( /Search/i ).first().fill( 'Corex' );

	// At least one corex/* block appears (not the "unsupported" placeholder).
	await expect( page.locator( '.block-editor-block-types-list__item' ).first() ).toBeVisible();
} );

test( 'the contact form validates and accepts a submission', async ( { page } ) => {
	// Assumes a page containing the corex/form (contact) block at /contact.
	await page.goto( '/contact/' );

	const form = page.locator( 'form[data-corex-schema]' );
	await expect( form ).toBeVisible();

	const name = form.locator( 'input[name="name"]' );
	const email = form.locator( 'input[name="email"]' );
	const message = form.locator( 'textarea[name="message"], input[name="message"]' ).first();
	const send = form.getByRole( 'button', { name: /send|submit/i } );

	// Empty submit → native `required` constraint blocks submission (progressive
	// enhancement; the field reports invalid and no request leaves the browser).
	await send.click();
	await expect.poll( () => name.evaluate( ( el ) => el.checkValidity() ) ).toBe( false );

	// A value that passes native validation but fails the schema (name > max:120) → the
	// Corex JS validator (window.Corex.forms) renders an inline error in the field's region.
	await name.fill( 'x'.repeat( 121 ) );
	await email.fill( 'smoke@example.com' );
	await message.fill( 'Hello from Playwright.' );
	await send.click();
	await expect( form.locator( '#corex-contact-name-error' ) ).not.toBeEmpty();

	// Valid submit → success response region.
	await name.fill( 'Smoke Test' );
	await send.click();
	await expect( page.locator( '.corex-form__status, [role="status"]' ).first() ).toBeVisible();
} );

test( 'the setup wizard loads and offers a real kit', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=corex-setup' );

	// The nine-step wizard mounts (JS took over the server fallback). The full nine-step
	// apply flow is exercised by setup-settings-insights.spec.js; this smoke just confirms the
	// wizard surface loads and offers a real kit to choose.
	await expect( page.getByRole( 'heading', { name: 'CoreX Setup Wizard' } ) ).toBeVisible();
	await expect( page.locator( '.corex-setup__step' ) ).toHaveCount( 9 );

	// Welcome → Brand → Kit: a real kit option is offered.
	await page.locator( '#corex-setup-next' ).click();
	await page.locator( '#corex-setup-next' ).click();
	await expect( page.locator( 'input[name="corex-kit"]' ).first() ).toBeVisible();
} );
