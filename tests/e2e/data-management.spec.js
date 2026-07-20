/**
 * Capability-derived Data browser evidence (spec 068: T130; consolidated by spec 069).
 *
 * There used to be two screens here. `page=corex-data` and `page=corex-data-models` mounted the
 * identical explorer, so the standalone one was retired and its address redirects to the Records
 * tab.
 */

const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

test( 'redirects the retired Data address to the Records tab', async ( { page } ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-data' );

	// The bookmark still works: same records, one screen.
	await expect( page ).toHaveURL( /page=corex-data-models/ );
	await expect( page ).toHaveURL( /tab=records/ );
	await expect( page.getByRole( 'button', { name: 'Records', exact: true } ) ).toHaveAttribute( 'aria-current', 'page' );

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'opens a tab directly from its address', async ( { page } ) => {
	// Tabs were component state only, so no view here could be linked to or reopened.
	await page.goto( '/wp-admin/admin.php?page=corex-data-models&tab=migrations' );
	await expect( page.getByRole( 'button', { name: 'Migrations', exact: true } ) ).toHaveAttribute( 'aria-current', 'page' );

	await page.getByRole( 'button', { name: 'Models', exact: true } ).click();
	await expect( page ).toHaveURL( /tab=models/ );
} );

test( 'queries source records, opens detail, and queues a declared export', async ( { page } ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-data-models&tab=records' );
	await expect( page.getByRole( 'heading', { name: 'CoreX Data' } ) ).toBeVisible();
	await expect( page.getByText( 'Loading records…' ) ).toBeHidden();

	const fixture = await page.evaluate( async () => {
		const config = window.corexDataModels;
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

	// "Export records", not "Export": the Export tab sits on this same screen now, and the two must
	// not answer to the same name.
	const exportButton = page.getByRole( 'button', { name: 'Export records', exact: true } );
	await expect( exportButton ).toBeVisible();
	await exportButton.click();
	const dialog = page.getByRole( 'dialog', { name: 'Create export' } );
	const acknowledgement = dialog.getByText( 'I understand this export contains personal data' );
	if ( await acknowledgement.isVisible().catch( () => false ) ) await acknowledgement.click();
	await dialog.getByRole( 'button', { name: 'Queue export' } ).click();
	await expect( page.getByText( 'The export was queued.' ) ).toBeVisible();

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'presents models as a keyboard-operable accordion', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=corex-data-models&tab=models' );

	const entries = page.locator( '.corex-data-models__card' );
	expect( await entries.count() ).toBeGreaterThan( 0 );

	// First open so the panel never lands as a row of closed bars; the rest shut so it scans.
	await expect( entries.nth( 0 ) ).toHaveAttribute( 'open', '' );

	if ( ( await entries.count() ) < 2 ) {
		return;
	}

	await expect( entries.nth( 1 ) ).not.toHaveAttribute( 'open', '' );
	// Collapsed content is genuinely hidden, not merely painted over.
	await expect( entries.nth( 1 ).locator( '.corex-data-models__fields' ) ).toBeHidden();

	const summary = entries.nth( 1 ).locator( 'summary' );
	await summary.focus();
	await expect( summary ).toBeFocused();
	await page.keyboard.press( 'Enter' );
	await expect( entries.nth( 1 ) ).toHaveAttribute( 'open', '' );

	await page.keyboard.press( 'Space' );
	await expect( entries.nth( 1 ) ).not.toHaveAttribute( 'open', '' );
} );

test( 'renders every Data workflow from declared source capabilities', async ( { page } ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-data-models' );
	await expect( page.getByRole( 'heading', { name: 'CoreX Data' } ) ).toBeVisible();
	await expect( page.locator( '.corex-data-models__card' ).first() ).toBeVisible();

	for ( const tab of [ 'Records', 'Import', 'Export', 'Migrations', 'Models' ] ) {
		await page.getByRole( 'button', { name: tab, exact: true } ).click();
		await expect( page.getByRole( 'button', { name: tab, exact: true } ) ).toHaveAttribute( 'aria-current', 'page' );
	}

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'contains Data at mobile, tablet, desktop, wide, and RTL viewports', async ( { page } ) => {
	for ( const tab of [ 'records', 'models' ] ) {
		await page.goto( `/wp-admin/admin.php?page=corex-data-models&tab=${ tab }` );
		for ( const width of [ 375, 768, 1024, 1440 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			const fits = await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth );
			expect( fits, `${ tab } horizontal overflow at ${ width }px` ).toBe( true );
		}
		await page.locator( 'html' ).evaluate( ( root ) => root.setAttribute( 'dir', 'rtl' ) );
		await expect( page.locator( '.corex-admin' ) ).toHaveCSS( 'direction', 'rtl' );
	}
} );
