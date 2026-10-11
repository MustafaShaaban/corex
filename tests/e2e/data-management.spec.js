/**
 * Capability-derived Data browser evidence (spec 068: T130; consolidated by spec 069).
 *
 * There used to be two screens here. `page=corex-data` and `page=corex-data-models` mounted the
 * identical explorer, so the standalone one was retired and its address redirects to the Records
 * tab.
 */

const fs = require( 'node:fs' );
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors, pinAppearance } = require( './helpers' );

/**
 * Ticks the first model of the Export tab's list: the form submissions, which the suite seeds.
 *
 * @param {import('@playwright/test').Page} page A page showing the Export tab.
 */
async function tickFirstModel( page ) {
	await page
		.locator( '.corex-data-models__checklist li input[type="checkbox"]' )
		.first()
		.check();
}

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

	// The tab is a list of models to tick (spec 109): the first is ticked, and its dialog is the
	// one a single model always opened.
	await tickFirstModel( page );
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

	// The history has it already, ready to download: nothing was refreshed. It says which
	// model, in what format, and when its file expires.
	const newest = page
		.locator( '.corex-data-models__past .corex-export__entry' )
		.first();
	await expect( newest ).toContainText( 'CSV' );
	await expect( newest.locator( '.corex-export__entry-file' ) ).toHaveText(
		/^Expires /
	);
	await expect(
		newest.getByRole( 'button', { name: 'Download' } )
	).toBeVisible();
	await expect( page.getByRole( 'button', { name: 'Refresh' } ) ).toHaveCount(
		0
	);

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );
/**
 * Personal data is marked where it is, and asked about by name (spec 109, slice 1).
 *
 * Every column of every table model was declared as holding nothing personal, so the Models tab
 * said nothing of an actor's name and an export of it asked for no confirmation. Where a
 * confirmation was asked, it named nothing.
 *
 * Walked as an administrator, who holds the right to export personal data. The line a person
 * without that right is shown is drawn in Jest (`DataExportDialog.test.js`) and its refusal is
 * held on real WordPress (`DataManagementControllerTest`): no fixture account manages data
 * without being an administrator.
 */
test( 'says what a model holds about people, and names it before an export', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-data-models&tab=models' );

	const activity = page.locator( '.corex-data-models__card' ).filter( {
		has: page.getByRole( 'heading', { name: 'Activity events' } ),
	} );
	if ( ( await activity.getAttribute( 'open' ) ) === null ) {
		await activity.locator( 'summary' ).click();
	}
	const fieldRow = ( key ) =>
		activity.locator( 'tbody tr' ).filter( {
			has: page.locator( 'code', {
				hasText: new RegExp( `^${ key }$` ),
			} ),
		} );
	await expect( fieldRow( 'actor_label' ) ).toContainText(
		'Personal data: names and identities'
	);
	await expect( fieldRow( 'outcome' ) ).not.toContainText( 'Personal data' );
	// No shipped model leaves a column unclassified.
	await expect(
		page.getByText( 'data nobody has classified yet' )
	).toHaveCount( 0 );

	await page.goto( '/wp-admin/admin.php?page=corex-data-models&tab=export' );
	await tickFirstModel( page );
	await page.getByRole( 'button', { name: /^Export .+…$/ } ).click();
	const dialog = page.getByRole( 'dialog' );
	const notice = dialog.locator( '.corex-export__notice' );
	const start = dialog.getByRole( 'button', {
		name: /^Export [\d,]+ records?$/,
	} );

	// The confirmation says what it is confirming, and the export waits for it.
	await expect( notice ).toContainText(
		/contains personal data \(.*what people wrote.*\) and will handle it/
	);
	await expect( start ).toBeDisabled();
	await expect( dialog.locator( '.corex-export__summary' ) ).toHaveText(
		'Confirm the personal-data notice to export these columns.'
	);
	// An administrator is offered every column: nothing is withheld, and nothing says so.
	await expect( dialog.getByText( /not offered/ ) ).toHaveCount( 0 );

	// The longer sentence stays inside the dialog and its box stays beside its first line, in
	// both directions and at a narrow width, where it wraps.
	for ( const [ direction, width ] of [
		[ 'ltr', 1280 ],
		[ 'rtl', 1280 ],
		[ 'ltr', 375 ],
		[ 'rtl', 375 ],
	] ) {
		await page.setViewportSize( { width, height: 900 } );
		await pinAppearance( page, 'light', direction );
		const measured = await dialog.evaluate( ( node ) => {
			const box = ( element ) => element.getBoundingClientRect();
			const check = node.querySelector( '.corex-export__notice' );
			const body = node.querySelector( '.corex-dialog__body' );
			const input = check.querySelector( 'input' );
			// The first line as it was drawn, not as the stylesheet says a line should be.
			const range = node.ownerDocument.createRange();
			range.selectNodeContents(
				check.querySelector( 'span:last-child' )
			);
			const firstLine = range.getClientRects()[ 0 ];

			return {
				insideStart: box( check ).left >= box( body ).left - 0.5,
				insideEnd: box( check ).right <= box( body ).right + 0.5,
				offCentre: Math.abs(
					box( input ).top +
						box( input ).height / 2 -
						( firstLine.top + firstLine.height / 2 )
				),
			};
		} );
		const where = `${ direction } at ${ width }px`;
		expect( measured.insideStart, `notice starts inside, ${ where }` ).toBe(
			true
		);
		expect( measured.insideEnd, `notice ends inside, ${ where }` ).toBe(
			true
		);
		expect(
			measured.offCentre,
			`box beside the first line, ${ where }`
		).toBeLessThan( 3 );
	}

	await notice.click();
	await expect( start ).toBeEnabled();

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

/**
 * Several models are exported in one go (spec 109, slice 2).
 *
 * The owner, on a live site: "the import/export/migrate model is using one by one, i can't do a
 * bulk or multiselect". The tab had one dropdown named "Model".
 */
test( 'exports several ticked models into one file, lists the export once, and deletes its file', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-data-models&tab=export' );

	const list = page.locator( '.corex-data-models__checklist' );
	const opener = page.locator( '.corex-data-models__export-action button' );
	// Two groups, every model with its count, and nothing ticked for the person.
	await expect( list.locator( 'legend' ) ).toHaveText( [
		'Records',
		'Logs',
	] );
	await expect( list.locator( '.corex-admin-skeleton' ) ).toHaveCount( 0 );
	await expect( list.locator( 'li' ).first() ).toContainText(
		/[\d,]+ records?$/
	);
	await expect( list.locator( 'input:checked' ) ).toHaveCount( 0 );
	await expect( opener ).toBeDisabled();

	// Measured, in both directions: every row one height, every box level with its name, every
	// count ending on one edge.
	for ( const direction of [ 'ltr', 'rtl' ] ) {
		await pinAppearance( page, 'light', direction );
		const measured = await list.evaluate( ( node ) => {
			const box = ( element ) => element.getBoundingClientRect();
			const middle = ( element ) =>
				box( element ).top + box( element ).height / 2;
			const rows = Array.from( node.querySelectorAll( 'li' ) );
			const edge = ( element ) =>
				Math.round(
					node.ownerDocument.dir === 'rtl'
						? box( element ).left
						: box( element ).right
				);

			return {
				heights: new Set(
					rows.map( ( row ) => Math.round( box( row ).height ) )
				).size,
				offLevel: Math.max(
					...rows.map( ( row ) =>
						Math.abs(
							middle( row.querySelector( 'input' ) ) -
								middle(
									row.querySelector(
										'.corex-data-models__checklist-name'
									)
								)
						)
					)
				),
				countEdges: new Set(
					rows.map( ( row ) =>
						edge(
							row.querySelector(
								'.corex-data-models__checklist-count'
							)
						)
					)
				).size,
				target: Math.min(
					...rows.map( ( row ) =>
						Math.round( box( row.querySelector( 'label' ) ).height )
					)
				),
			};
		} );
		expect( measured.heights, `rows of one height, ${ direction }` ).toBe(
			1
		);
		expect(
			measured.offLevel,
			`boxes level with names, ${ direction }`
		).toBeLessThan( 1.5 );
		expect(
			measured.countEdges,
			`counts on one edge, ${ direction }`
		).toBe( 1 );
		// The smallest target WCAG 2.2 accepts (2.5.8).
		expect( measured.target ).toBeGreaterThanOrEqual( 24 );
	}
	await pinAppearance( page, 'light', 'ltr' );

	// By keyboard alone: the form submissions, and the activity log, which this suite has
	// already written to.
	const submissions = list.getByLabel( 'Form submissions' );
	await submissions.focus();
	await page.keyboard.press( 'Space' );
	await list.getByLabel( 'Activity events' ).focus();
	await page.keyboard.press( 'Space' );
	await expect( opener ).toHaveText( 'Export 2 models…' );
	await opener.focus();
	await page.keyboard.press( 'Enter' );

	const dialog = page.getByRole( 'dialog', { name: 'Export 2 models' } );
	await expect( dialog ).toBeVisible();
	const models = dialog.locator( '.corex-export__model' );
	await expect( models ).toHaveCount( 2 );
	await expect(
		dialog.locator( '.corex-export__count' ).filter( { hasText: '…' } )
	).toHaveCount( 0 );
	await expect(
		dialog.getByText( 'One workbook, with a sheet for each model.' )
	).toBeVisible();

	// A model's columns are behind its own disclosure, and stay inside the dialog.
	await models.first().locator( 'summary' ).click();
	await expect(
		models.first().locator( '.corex-export__column' ).first()
	).toBeVisible();
	const inside = await dialog.evaluate( ( node ) => {
		const box = ( element ) => element.getBoundingClientRect();
		const body = node.querySelector( '.corex-dialog__body' );
		const parts = Array.from( body.children );

		return {
			models: Array.from(
				node.querySelectorAll( '.corex-export__model' )
			).every(
				( row ) =>
					box( row ).left >= box( body ).left - 0.5 &&
					box( row ).right <= box( body ).right + 0.5
			),
			gaps: new Set(
				parts
					.slice( 1 )
					.map( ( part, index ) =>
						Math.round(
							box( part ).top - box( parts[ index ] ).bottom
						)
					)
			),
		};
	} );
	expect( inside.models ).toBe( true );
	expect( inside.gaps, 'one distance between parts' ).toEqual(
		new Set( [ 24 ] )
	);

	const notice = dialog.locator( '.corex-export__notice' );
	await expect( notice ).toContainText( 'contains personal data (' );
	await notice.click();

	const arriving = page.waitForEvent( 'download' );
	await dialog
		.getByRole( 'button', {
			name: /^Export [\d,]+ records? from 2 models$/,
		} )
		.click();
	const file = await arriving;
	// One workbook for both: a zip, which begins with these two bytes.
	expect( file.suggestedFilename() ).toMatch(
		/-2-models-\d{4}-\d{2}-\d{2}\.xlsx$/
	);
	expect(
		fs
			.readFileSync( await file.path() )
			.subarray( 0, 2 )
			.toString( 'latin1' )
	).toBe( 'PK' );
	await dialog
		.getByRole( 'button', { name: 'Close', exact: true } )
		.last()
		.click();
	await expect( dialog ).toBeHidden();

	// Listed once, with both models, and its file can be deleted after being asked again.
	const newest = page
		.locator( '.corex-data-models__past .corex-export__entry' )
		.first();
	await expect( newest.locator( '.corex-export__entry-what' ) ).toHaveText(
		'Form submissions, Activity events'
	);
	await expect( newest ).toContainText( 'Excel' );
	await expect( newest.locator( '.corex-export__entry-file' ) ).toHaveText(
		/^Expires /
	);
	await newest.getByRole( 'button', { name: 'Delete' } ).click();
	await expect( newest.getByText( 'Delete this file?' ) ).toBeVisible();
	await newest.getByRole( 'button', { name: 'Delete file' } ).click();
	await expect( newest.locator( '.corex-export__entry-file' ) ).toHaveText(
		/^Deleted /
	);
	await expect(
		newest.getByRole( 'button', { name: 'Download' } )
	).toHaveCount( 0 );

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
	// The list's own signal (spec 108). Waiting for "Loading records…" to be hidden passed when
	// the sentence had never been drawn, which is every time now: a placeholder is drawn.
	await expect(
		page.locator( '.corex-data__panel-body .corex-loadable' )
	).toHaveAttribute( 'data-corex-state', 'ready' );

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
