/**
 * Spec 115, US-FLW-02: edits in the flow editor are never lost without a warning.
 *
 * In a real browser, because what matters here is the browser's: the modal question takes focus and
 * gives it back, Escape is Cancel, and leaving the page with edits on screen raises the browser's own
 * "Leave site?" warning. The flow is a draft this spec keeps and reuses, as the forms spec does with its
 * own, so a run leaves one flow at most.
 */
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

const FLOW_NAME = 'CoreX E2E unsaved changes';
const FLOW_SLUG = 'corex-e2e-unsaved-changes';
const SAVED_LABEL = 'Saved label';

/**
 * Open the flow, making it (one field, saved) the first time.
 *
 * @param {import('@playwright/test').Page} page The admin's page.
 * @return {Promise<import('@playwright/test').Locator>} The field's Label input.
 */
async function openTheFlow( page ) {
	await page.goto( '/wp-admin/admin.php?page=corex-forms' );
	await expect( page.locator( '.corex-flow-list__catalog' ) ).toHaveAttribute(
		'data-status',
		'ready'
	);
	const existing = page.getByRole( 'button', {
		name: new RegExp( FLOW_NAME ),
	} );

	if ( await existing.isVisible().catch( () => false ) ) {
		await existing.click();
	} else {
		await page.getByLabel( 'Flow name' ).fill( FLOW_NAME );
		await page.getByLabel( 'Slug', { exact: true } ).fill( FLOW_SLUG );
		await page.getByRole( 'button', { name: 'Create draft' } ).click();
	}
	await expect(
		page.getByRole( 'heading', { name: FLOW_NAME } )
	).toBeVisible();

	// The flow's one field, selected so its settings show: added the first time.
	const fields = page.locator(
		'.corex-flow-editor__workspace ol > li > button'
	);
	if ( ( await fields.count() ) === 0 ) {
		await page.getByRole( 'button', { name: 'Add field' } ).click();
	} else {
		await fields.first().click();
	}

	// Every run starts from the same saved label.
	const label = page.getByLabel( 'Label', { exact: true } ).first();
	if ( ( await label.inputValue() ) !== SAVED_LABEL ) {
		await label.fill( SAVED_LABEL );
		await page.getByRole( 'button', { name: 'Save draft' } ).click();
	}
	await expect(
		page.getByText( 'All changes saved', { exact: true } )
	).toBeVisible();

	return label;
}

test( 'says when there are unsaved changes, and asks before Back throws them away', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	const label = await openTheFlow( page );

	await label.fill( 'Edited, not saved' );
	await expect(
		page.getByText( 'Unsaved changes', { exact: true } )
	).toBeVisible();

	const back = page.getByRole( 'button', { name: 'Back to flows' } );
	await back.click();
	const question = page.getByRole( 'dialog', {
		name: 'Save your changes before leaving?',
	} );
	await expect( question ).toBeVisible();
	// The question has the focus, on the answer that loses nothing.
	await expect(
		question.getByRole( 'button', { name: 'Save' } )
	).toBeFocused();

	// Escape is Cancel: the edit stays, and the focus goes back to Back.
	await page.keyboard.press( 'Escape' );
	await expect( question ).toBeHidden();
	await expect( back ).toBeFocused();
	await expect( label ).toHaveValue( 'Edited, not saved' );

	// Discard leaves without saving: the flow opens again with its saved label.
	await back.click();
	await question.getByRole( 'button', { name: 'Discard' } ).click();
	await expect( page.locator( '.corex-flow-list__catalog' ) ).toBeVisible();
	await page.getByRole( 'button', { name: new RegExp( FLOW_NAME ) } ).click();
	await expect(
		page.getByLabel( 'Label', { exact: true } ).first()
	).toHaveValue( SAVED_LABEL );

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'the browser warns before a reload drops the edits, and not when there are none', async ( {
	page,
} ) => {
	const label = await openTheFlow( page );
	const warnings = [];
	page.on( 'dialog', async ( dialog ) => {
		warnings.push( dialog.type() );
		await dialog.dismiss();
	} );

	await label.fill( 'Edited, not saved' );
	// Chromium shows the warning only after the person has used the page.
	await label.click();
	await page.reload( { timeout: 5_000 } ).catch( () => {} );
	expect( warnings ).toEqual( [ 'beforeunload' ] );
	await expect( label ).toHaveValue( 'Edited, not saved' );

	await label.fill( SAVED_LABEL );
	await expect(
		page.getByText( 'All changes saved', { exact: true } )
	).toBeVisible();
	await page.reload();
	expect( warnings ).toEqual( [ 'beforeunload' ] );
} );

test( 'the Test tab names the version it tests', async ( { page } ) => {
	await openTheFlow( page );

	await page
		.locator( '.corex-flow-editor__stages button', { hasText: 'Test' } )
		.click();

	await expect(
		page.getByText( /^Tests the saved draft, version \d+$/ )
	).toBeVisible();
} );
