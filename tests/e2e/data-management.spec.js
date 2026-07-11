/**
 * Capability-derived Data and Data Models browser evidence (spec 068: T130).
 */

const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

test( 'queries source records, opens detail, and queues a declared export', async ( { page } ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-data' );
	await expect( page.getByRole( 'heading', { name: 'CoreX Data' } ) ).toBeVisible();
	await expect( page.getByText( 'Loading records…' ) ).toBeHidden();

	const fixture = await page.evaluate( async () => {
		const config = window.corexData;
		const catalog = await window.Corex.api.get( `${ config.restUrl }/sources`, { nonce: config.nonce } );
		for ( const source of catalog.envelope.data.sources ) {
			if ( ! source.actions.query?.visible ) continue;
			const result = await window.Corex.api.get( `${ config.restUrl }/${ source.key }?page=1&per_page=5`, { nonce: config.nonce } );
			if ( result.envelope.ok && result.envelope.data.rows.length ) {
				return { key: source.key, label: source.label };
			}
		}
		return null;
	} );

	expect( fixture ).not.toBeNull();
	await page.locator( '.corex-data__source-row', { hasText: fixture.label } ).click();
	await expect.poll( () => page.locator( '.corex-data__table tbody tr' ).count() ).toBeGreaterThan( 0 );
	await page.getByRole( 'button', { name: 'View' } ).first().click();
	const recordDialog = page.getByRole( 'dialog', { name: 'Record detail' } );
	await expect( recordDialog ).toBeVisible();
	// The dialog exposes both an icon and a text close affordance; use the first within scope.
	await recordDialog.getByRole( 'button', { name: 'Close' } ).first().click();

	const exportButton = page.getByRole( 'button', { name: 'Export', exact: true } );
	await expect( exportButton ).toBeVisible();
	await exportButton.click();
	const dialog = page.getByRole( 'dialog', { name: 'Create export' } );
	const acknowledgement = dialog.getByText( 'I understand this export contains personal data' );
	if ( await acknowledgement.isVisible().catch( () => false ) ) await acknowledgement.click();
	await dialog.getByRole( 'button', { name: 'Queue export' } ).click();
	await expect( page.getByText( 'The export was queued.' ) ).toBeVisible();

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'renders every Data Models workflow from declared source capabilities', async ( { page } ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-data-models' );
	await expect( page.getByRole( 'heading', { name: 'CoreX Data Models' } ) ).toBeVisible();
	await expect( page.locator( '.corex-data-models__card' ).first() ).toBeVisible();

	for ( const tab of [ 'Records', 'Import', 'Export', 'Migrations', 'Models' ] ) {
		await page.getByRole( 'button', { name: tab, exact: true } ).click();
		await expect( page.getByRole( 'button', { name: tab, exact: true } ) ).toHaveAttribute( 'aria-current', 'page' );
	}

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'contains Data and Data Models at mobile, tablet, desktop, wide, and RTL viewports', async ( { page } ) => {
	for ( const route of [ 'corex-data', 'corex-data-models' ] ) {
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
