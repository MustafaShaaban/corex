/**
 * Capability-derived Data browser evidence (spec 068: T130; consolidated by spec 069).
 *
 * There used to be two screens here. `page=corex-data` and `page=corex-data-models` mounted the
 * identical explorer, so the standalone one was retired and its address redirects to the Records
 * tab.
 */

const fs = require( 'node:fs' );
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

test( 'redirects the retired Data address to the Records tab', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-data' );

	// The bookmark still works: same records, one screen.
	await expect( page ).toHaveURL( /page=corex-data-models/ );
	await expect( page ).toHaveURL( /tab=records/ );
	await expect(
		page.getByRole( 'button', { name: 'Records', exact: true } )
	).toHaveAttribute( 'aria-current', 'page' );

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

/**
 * The Export tab opens the same dialog (spec 103, D12c). It was a second export form of its own,
 * with one scope and no word about it, that said "Refresh history when the job completes."
 */
test( 'exports a whole model from the Export tab, and lists it without a refresh', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-data-models&tab=export' );
	await expect(
		page.getByRole( 'heading', { name: 'Model exports' } )
	).toBeVisible();

	const opener = page.getByRole( 'button', { name: /^Export .+…$/ } );
	await opener.click();
	const dialog = page.getByRole( 'dialog' );
	await expect( dialog ).toBeVisible();

	// There are no rows and no filters behind it here, so everything is the one choice, counted.
	const scopes = dialog.locator( '.corex-export__scope' );
	await expect( scopes ).toHaveCount( 1 );
	await expect( scopes ).toContainText( 'Everything' );
	await expect( scopes.locator( '.corex-export__count' ) ).toHaveText(
		/^[\d,]+$/
	);

	await dialog.getByRole( 'combobox', { name: 'File type' } ).click();
	await page.getByRole( 'option', { name: 'CSV (.csv)' } ).click();
	const acknowledgement = dialog.getByText(
		'I understand this export contains personal data'
	);
	if ( await acknowledgement.isVisible().catch( () => false ) ) {
		await acknowledgement.click();
	}
	const arriving = page.waitForEvent( 'download' );
	await dialog
		.getByRole( 'button', { name: /^Export [\d,]+ records?$/ } )
		.click();
	await arriving;
	await expect( dialog.getByText( /^Saved .+\.csv\.$/ ) ).toBeVisible();
	await dialog
		.getByRole( 'button', { name: 'Close', exact: true } )
		.last()
		.click();
	await expect( dialog ).toBeHidden();

	// The history has it already, ready to download: nothing was refreshed.
	const newest = page.locator( '.corex-data-models__history li' ).first();
	await expect( newest ).toContainText( 'Everything' );
	await expect( newest ).toContainText( 'CSV' );
	await expect( newest ).toContainText( 'Ready' );
	await expect(
		newest.getByRole( 'button', { name: 'Download' } )
	).toBeVisible();
	await expect( page.getByRole( 'button', { name: 'Refresh' } ) ).toHaveCount(
		0
	);

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );
test( 'opens a tab directly from its address', async ( { page } ) => {
	// Tabs were component state only, so no view here could be linked to or reopened.
	await page.goto(
		'/wp-admin/admin.php?page=corex-data-models&tab=migrations'
	);
	await expect(
		page.getByRole( 'button', { name: 'Migrations', exact: true } )
	).toHaveAttribute( 'aria-current', 'page' );

	await page.getByRole( 'button', { name: 'Models', exact: true } ).click();
	await expect( page ).toHaveURL( /tab=models/ );
} );

test( 'queries source records, opens detail, and exports them from one dialog', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-data-models&tab=records' );
	await expect(
		page.getByRole( 'heading', { name: 'CoreX Data' } )
	).toBeVisible();
	await expect( page.getByText( 'Loading records…' ) ).toBeHidden();

	const fixture = await page.evaluate( async () => {
		const config = window.corexDataModels;
		const catalog = await window.Corex.api.get(
			`${ config.restUrl }/sources`,
			{ nonce: config.nonce }
		);
		for ( const source of catalog.envelope.data.sources ) {
			if ( ! source.actions.query?.visible ) {
				continue;
			}
			const result = await window.Corex.api.get(
				`${ config.restUrl }/${ source.key }?page=1&per_page=5`,
				{ nonce: config.nonce }
			);
			if ( result.envelope.ok && result.envelope.data.rows.length ) {
				return { key: source.key, label: source.label };
			}
		}
		return null;
	} );

	expect( fixture ).not.toBeNull();
	await page
		.locator( '.corex-data__source-row', { hasText: fixture.label } )
		.click();
	await expect
		.poll( () => page.locator( '.corex-data__table tbody tr' ).count() )
		.toBeGreaterThan( 0 );
	await page.getByRole( 'button', { name: 'View' } ).first().click();
	const recordDialog = page.getByRole( 'dialog', { name: 'Record detail' } );
	await expect( recordDialog ).toBeVisible();
	// The dialog exposes both an icon and a text close affordance; use the first within scope.
	await recordDialog.getByRole( 'button', { name: 'Close' } ).first().click();

	// "Export records", not "Export": the Export tab sits on this same screen now, and the two must
	// not answer to the same name.
	const exportButton = page.getByRole( 'button', {
		name: 'Export records',
		exact: true,
	} );
	await expect( exportButton ).toBeVisible();
	await exportButton.click();
	// The dialog the Submissions export uses, over this source's records (spec 103, US10). It
	// was a WordPress modal that closed and said "The export was queued."
	const dialog = page.getByRole( 'dialog', {
		name: `Export ${ fixture.label }`,
	} );
	await expect( dialog ).toBeVisible();

	// All three scopes, each with its count before anything is exported (FR-010). No row is
	// ticked, so the rows cannot be chosen and the filters are what it opens on.
	const scopes = dialog.locator( '.corex-export__scope' );
	await expect( scopes ).toHaveCount( 3 );
	await expect(
		dialog.locator( '.corex-export__count' ).filter( { hasText: '…' } )
	).toHaveCount( 0 );
	await expect( scopes.first() ).toHaveClass( /is-disabled/ );
	await expect(
		dialog.locator( '.corex-export__scope.is-chosen' )
	).toContainText( 'Current filters' );

	const measured = await dialog.evaluate( ( node ) => {
		const box = ( element ) => element.getBoundingClientRect();
		const style = ( element ) => window.getComputedStyle( element );
		const parts = Array.from(
			node.querySelector( '.corex-dialog__body' ).children
		);
		// How far the middle of a control is from the middle of the first line of its name.
		const offCentre = ( input, name ) =>
			Math.abs(
				box( input ).top +
					box( input ).height / 2 -
					( box( name ).top +
						parseFloat( style( name ).lineHeight ) / 2 )
			);

		return {
			gaps: parts
				.slice( 1 )
				.map( ( part, index ) =>
					Math.round( box( part ).top - box( parts[ index ] ).bottom )
				),
			radios: Array.from(
				node.querySelectorAll( '.corex-export__scope' )
			).map( ( scope ) =>
				offCentre(
					scope.querySelector( 'input' ),
					scope.querySelector( '.corex-export__scope-name' )
				)
			),
			checkboxes: Array.from(
				node.querySelectorAll( '.corex-export__column' )
			).map( ( column ) =>
				offCentre(
					column.querySelector( 'input' ),
					column.querySelector( 'label' )
				)
			),
			name: parseFloat(
				style( node.querySelector( '.corex-export__scope-name' ) )
					.fontSize
			),
			detail: parseFloat(
				style( node.querySelector( '.corex-export__detail' ) ).fontSize
			),
			focusInside: node.contains( node.ownerDocument.activeElement ),
		};
	} );
	expect( measured.focusInside ).toBe( true );
	expect( new Set( measured.gaps ), 'one distance between parts' ).toEqual(
		new Set( [ 24 ] )
	);
	expect( Math.max( ...measured.radios ) ).toBeLessThan( 1 );
	expect( Math.max( ...measured.checkboxes ) ).toBeLessThan( 1 );
	// A choice's name is not smaller than the line that describes it. It was, by a pixel.
	expect( measured.name ).toBeGreaterThanOrEqual( measured.detail );

	const acknowledgement = dialog.getByText(
		'I understand this export contains personal data'
	);
	if ( await acknowledgement.isVisible().catch( () => false ) ) {
		await acknowledgement.click();
	}

	// One click, and the file arrives (FR-022). Excel first, where the source allows it: no
	// source did until this slice. A workbook is a zip, which begins with these two bytes.
	const start = dialog.getByRole( 'button', {
		name: /^Export [\d,]+ records?$/,
	} );
	const workbookArriving = page.waitForEvent( 'download' );
	await start.click();
	const workbook = await workbookArriving;
	expect( workbook.suggestedFilename() ).toMatch(
		new RegExp( `-${ fixture.key }-\\d{4}-\\d{2}-\\d{2}\\.xlsx$` )
	);
	expect(
		fs
			.readFileSync( await workbook.path() )
			.subarray( 0, 2 )
			.toString( 'latin1' )
	).toBe( 'PK' );
	await expect( dialog.getByText( /^Saved .+\.xlsx\.$/ ) ).toBeVisible();

	// The same export as text: it begins with the mark that tells a spreadsheet it is UTF-8.
	await dialog.getByRole( 'combobox', { name: 'File type' } ).click();
	await page.getByRole( 'option', { name: 'CSV (.csv)' } ).click();
	await expect(
		dialog.getByRole( 'combobox', { name: 'Separator' } )
	).toBeVisible();
	const textArriving = page.waitForEvent( 'download' );
	await start.click();
	const text = await textArriving;
	expect( text.suggestedFilename() ).toMatch( /\.csv$/ );
	expect(
		fs
			.readFileSync( await text.path() )
			.subarray( 0, 3 )
			.toString( 'hex' )
	).toBe( 'efbbbf' );
	await expect( dialog.getByText( /^Saved .+\.csv\.$/ ) ).toBeVisible();

	// Escape leaves, and focus goes back to what opened it (FR-035).
	await page.keyboard.press( 'Escape' );
	await expect( dialog ).toBeHidden();
	await expect( exportButton ).toBeFocused();

	// One ticked row, as a document to file (US8). A PDF holds a stated number of records
	// (FR-019a), and a source may hold more than that, so this exports the one row.
	await page
		.getByRole( 'checkbox', { name: /^Select record / } )
		.first()
		.check();
	await exportButton.click();
	await expect(
		dialog.locator( '.corex-export__scope.is-chosen' )
	).toContainText( 'Selected rows' );
	await dialog.getByRole( 'combobox', { name: 'File type' } ).click();
	await page.getByRole( 'option', { name: 'PDF document (.pdf)' } ).click();
	await expect( dialog.getByText( /Up to 500 records\.$/ ) ).toBeVisible();
	const acknowledgeAgain = dialog.getByText(
		'I understand this export contains personal data'
	);
	if ( await acknowledgeAgain.isVisible().catch( () => false ) ) {
		await acknowledgeAgain.click();
	}
	const documentArriving = page.waitForEvent( 'download' );
	await dialog.getByRole( 'button', { name: 'Export 1 record' } ).click();
	const filed = await documentArriving;
	expect( filed.suggestedFilename() ).toMatch( /\.pdf$/ );
	expect(
		fs
			.readFileSync( await filed.path() )
			.subarray( 0, 5 )
			.toString( 'latin1' )
	).toBe( '%PDF-' );
	await expect( dialog.getByText( /^Saved .+\.pdf\.$/ ) ).toBeVisible();
	await page.keyboard.press( 'Escape' );
	await expect( dialog ).toBeHidden();

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'presents models as a keyboard-operable accordion', async ( {
	page,
} ) => {
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
	await expect(
		entries.nth( 1 ).locator( '.corex-data-models__fields' )
	).toBeHidden();

	const summary = entries.nth( 1 ).locator( 'summary' );
	await summary.focus();
	await expect( summary ).toBeFocused();
	await page.keyboard.press( 'Enter' );
	await expect( entries.nth( 1 ) ).toHaveAttribute( 'open', '' );

	await page.keyboard.press( 'Space' );
	await expect( entries.nth( 1 ) ).not.toHaveAttribute( 'open', '' );
} );

test( 'renders every Data workflow from declared source capabilities', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-data-models' );
	await expect(
		page.getByRole( 'heading', { name: 'CoreX Data' } )
	).toBeVisible();
	await expect(
		page.locator( '.corex-data-models__card' ).first()
	).toBeVisible();

	for ( const tab of [
		'Records',
		'Import',
		'Export',
		'Migrations',
		'Models',
	] ) {
		await page.getByRole( 'button', { name: tab, exact: true } ).click();
		await expect(
			page.getByRole( 'button', { name: tab, exact: true } )
		).toHaveAttribute( 'aria-current', 'page' );
	}

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'never offers a workflow tab that leads to a dead end', async ( {
	page,
} ) => {
	// Before spec 074, Import and Migrations were shown to anyone holding the models ability and
	// could only ever say "No registered model provides an import adapter" — no shipped source
	// declared either capability, and none could. A tab is only offered now when some model
	// genuinely satisfies it, and the tab's panel must never be that sentence.
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-data-models' );
	await expect(
		page.locator( '.corex-data-models__card' ).first()
	).toBeVisible();

	const tabs = await page
		.locator( '.corex-data-models__tabs button' )
		.allInnerTexts();

	for ( const tab of tabs ) {
		await page.getByRole( 'button', { name: tab, exact: true } ).click();
		await expect( page.locator( '.corex-data-models__empty' ) ).toHaveCount(
			0
		);
	}

	// Whatever the capability, the workflow never explains itself in the word "adapter".
	await expect( page.getByText( 'adapter', { exact: false } ) ).toHaveCount(
		0
	);

	// The Models catalog is where capability is explained, in words.
	await page.getByRole( 'button', { name: 'Models', exact: true } ).click();
	const summary = page
		.locator( '.corex-data-models__capability-summary' )
		.first();
	await expect( summary ).toBeVisible();
	await expect( summary ).toContainText(
		'You can browse and search these records.'
	);

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'contains Data at mobile, tablet, desktop, wide, and RTL viewports', async ( {
	page,
} ) => {
	for ( const tab of [ 'records', 'models' ] ) {
		await page.goto(
			`/wp-admin/admin.php?page=corex-data-models&tab=${ tab }`
		);
		for ( const width of [ 375, 768, 1024, 1440 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			const fits = await page.evaluate(
				() =>
					document.documentElement.scrollWidth <=
					document.documentElement.clientWidth
			);
			expect( fits, `${ tab } horizontal overflow at ${ width }px` ).toBe(
				true
			);
		}
		await page
			.locator( 'html' )
			.evaluate( ( root ) => root.setAttribute( 'dir', 'rtl' ) );
		await expect( page.locator( '.corex-admin' ) ).toHaveCSS(
			'direction',
			'rtl'
		);
	}
} );
