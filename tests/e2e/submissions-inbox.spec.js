/**
 * Complete Submissions Inbox workflow and personal-data export evidence (spec 068: T109).
 */

const fs = require( 'node:fs' );
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors, seedSubmission } = require( './helpers' );

const FLOW_SLUG = 'corex-inbox-e2e';
// Unique per run so the seeded submission is searchable to exactly one row on a shared site
// where prior runs' fixtures accumulate.
const EMAIL = `corex-inbox-e2e-${ Date.now() }@example.com`;

test.beforeEach( async ( { page } ) => {
	const seeded = await seedSubmission( page, FLOW_SLUG, EMAIL );
	expect( seeded.real.envelope.ok ).toBe( true );
	expect( seeded.marked.envelope.ok ).toBe( true );
	await page.goto( '/wp-admin/admin.php?page=corex-submissions' );
	await expect(
		page.getByRole( 'heading', { name: 'Submission Inbox' } )
	).toBeVisible();
	await expect( page.getByText( 'Loading submissions…' ) ).toBeHidden();
} );

/**
 * The heading stack's rhythm (spec 074, FR-2).
 *
 * The eyebrow, the title, and the count used to be loose children of a bare div, separated only by
 * a margin on each paragraph — so they rendered as one compressed block. They are a grid stack now,
 * which is measured rather than eyeballed here because the failure mode is a few pixels, and
 * because a translation that wraps or a doubled zoom is exactly where per-element margins drifted.
 *
 * @param {import('@playwright/test').Page} page The page showing the inbox.
 * @return {Promise<Object>} The measured gaps between the stacked elements.
 */
async function headingGaps( page ) {
	return page.evaluate( () => {
		const box = ( selector ) =>
			document.querySelector( selector )?.getBoundingClientRect();
		const eyebrow = box( '.corex-inbox__eyebrow' );
		const title = box( '.corex-inbox__header h2' );
		const count = box( '.corex-inbox__count' );

		return {
			eyebrowToTitle: title.top - eyebrow.bottom,
			titleToCount: count.top - title.bottom,
			// 1px of tolerance: in RTL the browser rounds the scroll origin of the table's own
			// scroll container, which reports a one-pixel document width with nothing actually
			// outside the viewport. Anything wider than that is a real layout escape.
			overflows:
				document.documentElement.scrollWidth -
					document.documentElement.clientWidth >
				1,
		};
	} );
}

test( 'spaces the inbox heading stack at every width, zoom, and direction', async ( {
	page,
} ) => {
	const cases = [
		{ name: 'desktop', width: 1440 },
		{ name: 'narrow', width: 360 },
		// 200% zoom is emulated as half the CSS viewport at twice the scale factor; the layout
		// question is the same one — does the stack still separate when space runs out.
		{ name: '200% zoom', width: 720 },
	];

	for ( const direction of [ 'ltr', 'rtl' ] ) {
		await page
			.locator( 'html' )
			.evaluate(
				( root, dir ) => root.setAttribute( 'dir', dir ),
				direction
			);

		for ( const viewport of cases ) {
			await page.setViewportSize( {
				width: viewport.width,
				height: 900,
			} );
			const gaps = await headingGaps( page );
			const where = `${ direction } @ ${ viewport.name }`;

			expect(
				gaps.eyebrowToTitle,
				`eyebrow/title gap collapsed at ${ where }`
			).toBeGreaterThanOrEqual( 4 );
			expect(
				gaps.titleToCount,
				`title/count gap collapsed at ${ where }`
			).toBeGreaterThanOrEqual( 4 );
			// One stack, one rhythm: the two gaps come from the same grid gap, so a difference
			// means a margin crept back in.
			expect(
				Math.abs( gaps.eyebrowToTitle - gaps.titleToCount ),
				`uneven rhythm at ${ where }`
			).toBeLessThanOrEqual( 1 );
			expect( gaps.overflows, `horizontal overflow at ${ where }` ).toBe(
				false
			);
		}
	}

	await page
		.locator( 'html' )
		.evaluate( ( root ) => root.setAttribute( 'dir', 'ltr' ) );
} );

test( 'opens pre-filtered when Forms & Flows links to one form’s submissions', async ( {
	page,
} ) => {
	// The catalog's "View submissions for this form" link (spec 074, FR-1.9). A link that landed on
	// an unfiltered inbox would be a control that does nothing.
	await page.goto(
		'/wp-admin/admin.php?page=corex-submissions&corex_form=slug:contact'
	);
	await expect(
		page.getByRole( 'heading', { name: 'Submission Inbox' } )
	).toBeVisible();
	await expect( page.getByText( 'Loading submissions…' ) ).toBeHidden();

	await expect( page.getByRole( 'combobox', { name: 'Form' } ) ).toHaveText(
		/Contact/i
	);
} );

test( 'filters works assigns notes bulk actions and audits personal-data exports', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await page.getByLabel( 'Search' ).fill( EMAIL );
	// `.first()`: every test in this file seeds another submission under the same fixture email,
	// so more than one row legitimately matches by the time the later tests run.
	await expect(
		page.getByText( EMAIL, { exact: true } ).first()
	).toBeVisible();
	await expect(
		page.getByText( 'marked-test@example.com', { exact: true } )
	).toHaveCount( 0 );

	// Scope to a single matching submission: the seeded fixture email can accumulate across
	// runs on a shared site, so operate on the first match to keep the workflow isolation-safe.
	await page
		.getByLabel( /Select submission/ )
		.first()
		.check();
	// Bulk action is a CorexSelect now (spec 069) — an in-DOM listbox rather than a native
	// <select>, so it is opened and picked rather than driven with selectOption().
	await page.getByRole( 'combobox', { name: 'Bulk action' } ).click();
	await page.getByRole( 'option', { name: 'Mark read' } ).click();
	await page.getByRole( 'button', { name: 'Preview action' } ).click();
	await expect(
		page.getByText( /will affect exactly 1 submissions/ )
	).toBeVisible();
	await page.getByRole( 'button', { name: 'Confirm and apply' } ).click();
	await expect( page.getByText( 'Bulk action applied.' ) ).toBeVisible();

	await page
		.getByRole( 'button', { name: new RegExp( EMAIL ) } )
		.first()
		.click();
	const drawer = page.locator( '.corex-inbox__drawer' );
	await expect( drawer ).toBeVisible();
	await drawer.getByRole( 'combobox', { name: 'Status' } ).click();
	await page.getByRole( 'option', { name: 'In progress' } ).click();
	await drawer
		.getByPlaceholder( 'Add a team note' )
		.fill( 'Browser evidence note.' );
	await drawer.getByRole( 'button', { name: 'Add note' } ).click();
	await expect( drawer.getByText( 'Browser evidence note.' ) ).toBeVisible();
	await drawer.getByRole( 'button', { name: 'Close detail' } ).click();

	await page
		.getByLabel( /Select submission/ )
		.first()
		.check();
	// On a shared site the inbox list can be long enough to keep the toolbar Export button
	// outside the window viewport (sticky toolbar in a scroll container); dispatch the click
	// event directly so the workflow stays reliable regardless of list length.
	const exportButton = page.getByRole( 'button', {
		name: 'Export',
		exact: true,
	} );
	await exportButton.dispatchEvent( 'click' );
	const dialog = page.getByRole( 'dialog', { name: 'Export submissions' } );

	// It says what each choice covers before anything is exported (spec 103, FR-010): the one
	// ticked row is the choice already made, with its count.
	const chosen = dialog.locator( '.corex-export__scope.is-chosen' );
	await expect( chosen ).toContainText( 'Selected rows' );
	await expect( chosen.locator( '.corex-export__count' ) ).toHaveText( '1' );

	// Personal data is chosen, so the export cannot start until the notice is confirmed, and the
	// dialog says that is why.
	const start = dialog.getByRole( 'button', { name: 'Export 1 submission' } );
	await expect( start ).toBeDisabled();
	await expect( dialog.getByRole( 'status' ).last() ).toContainText(
		'Confirm the personal-data notice'
	);
	await dialog
		.getByText( 'I understand this export contains personal data' )
		.click();

	// One click, and the file arrives. No refresh, no second button (FR-022). The dialog offers
	// an Excel workbook first (FR-016): a zip, which begins with these two bytes.
	const workbookArriving = page.waitForEvent( 'download' );
	await start.click();
	const workbook = await workbookArriving;
	expect( workbook.suggestedFilename() ).toMatch( /\.xlsx$/ );
	expect(
		fs
			.readFileSync( await workbook.path() )
			.subarray( 0, 2 )
			.toString( 'latin1' )
	).toBe( 'PK' );
	await expect( dialog.getByText( /^Saved .+\.xlsx\.$/ ) ).toBeVisible();

	// The same export as text, which this test can read.
	await dialog.getByRole( 'combobox', { name: 'File type' } ).click();
	await page.getByRole( 'option', { name: 'CSV (.csv)' } ).click();
	const downloading = page.waitForEvent( 'download' );
	await start.click();
	const download = await downloading;
	const contents = fs.readFileSync( await download.path(), 'utf8' );

	expect( download.suggestedFilename() ).toMatch( /\.csv$/ );
	// A column per answer, headed by the form's own question (FR-002): not one cell of JSON.
	expect( contents ).toContain(
		'ID,Submitted,Form,Status,"Assigned to",Read,Test,'
	);
	expect( contents ).toContain( EMAIL );
	expect( contents ).not.toContain( '{"' );
	await expect( dialog.getByText( /^Saved .+\.csv\.$/ ) ).toBeVisible();
	await expect(
		dialog.getByRole( 'heading', { name: 'Recent exports' } )
	).toBeVisible();

	// The page behind an open dialog cannot be reached, so it is closed first.
	await dialog
		.getByRole( 'button', { name: 'Close', exact: true } )
		.last()
		.click();
	await expect( dialog ).toBeHidden();

	// Opened from the keyboard, Escape leaves, and focus goes back to what opened it (FR-035).
	await exportButton.focus();
	await page.keyboard.press( 'Enter' );
	await expect( dialog ).toBeVisible();
	await page.keyboard.press( 'Escape' );
	await expect( dialog ).toBeHidden();
	await expect( exportButton ).toBeFocused();

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'contains the Inbox at mobile tablet desktop wide and RTL viewports', async ( {
	page,
} ) => {
	for ( const width of [ 375, 768, 1024, 1440 ] ) {
		await page.setViewportSize( { width, height: 900 } );
		const fits = await page.evaluate(
			() =>
				document.documentElement.scrollWidth <=
				document.documentElement.clientWidth
		);
		expect( fits, `horizontal overflow at ${ width }px` ).toBe( true );
	}
	await page
		.locator( 'html' )
		.evaluate( ( root ) => root.setAttribute( 'dir', 'rtl' ) );
	await expect( page.locator( '.corex-inbox' ) ).toHaveCSS(
		'direction',
		'rtl'
	);
} );

/**
 * The whole row opens the submission (spec 103, US4).
 *
 * Only the submitter cell's button did: a click on the form, the status or the date did nothing,
 * which read as a broken row. The button is still the row's one control; its hit area covers the
 * row, and the checkbox sits above it.
 */
test( 'opens a submission from any cell of its row, and marks the row that is open', async ( {
	page,
} ) => {
	await page.getByLabel( 'Search' ).fill( EMAIL );
	const row = page
		.locator( '.corex-inbox__table tbody tr' )
		.filter( { hasText: EMAIL } )
		.first();
	await expect( row ).toBeVisible();
	// The search reloads the list. A click made while it does is answered by the reload.
	await expect(
		page.getByText( 'marked-test@example.com', { exact: true } )
	).toHaveCount( 0 );
	await expect( page.locator( '.corex-inbox' ) ).toHaveAttribute(
		'data-status',
		'ready'
	);
	const drawer = page.locator( '.corex-inbox__drawer' );
	const cells = row.locator( 'td' );
	const count = await cells.count();

	// Every cell after the checkbox and the submitter: form, status, notification, owner, received.
	for ( let index = 2; index < count; index++ ) {
		// By position, as a mouse does: the row's button covers the cell, so a click aimed at
		// the cell element itself is one Playwright refuses to make.
		await cells.nth( index ).scrollIntoViewIfNeeded();
		const box = await cells.nth( index ).boundingBox();
		await page.mouse.click( box.x + box.width / 2, box.y + box.height / 2 );
		await expect(
			drawer,
			`cell ${ index } opens the submission`
		).toBeVisible();
		await expect( row ).toHaveAttribute( 'aria-current', 'true' );
		await drawer
			.getByRole( 'button', { name: /^Close/ } )
			.first()
			.click();
		await expect( drawer ).toBeHidden();
		await expect( row ).not.toHaveAttribute( 'aria-current', 'true' );
	}

	// The checkbox selects the row and opens nothing.
	await row.getByLabel( /Select submission/ ).check();
	await expect( row.getByLabel( /Select submission/ ) ).toBeChecked();
	await expect( drawer ).toBeHidden();

	// One stop per row opens it: the row holds exactly one button.
	await expect( row.getByRole( 'button' ) ).toHaveCount( 1 );
} );

/**
 * The export dialog's spacing, measured (spec 103, FR-034).
 *
 * WordPress gives a checkbox and a radio a negative top margin, for sitting in a line of text. In
 * the dialog's rows that lifted each one off the line it belongs to: five pixels for a checkbox,
 * two for a radio. A paragraph took WordPress's bottom margin as well, so one section was twelve
 * pixels further from the next than the others. Both are the kind of fault that is plain on the
 * screen and invisible to a test of behaviour, so they are measured here.
 */
test( 'keeps the export dialog’s controls level with their labels, and its sections evenly spaced', async ( {
	page,
} ) => {
	await page
		.getByRole( 'button', { name: 'Export', exact: true } )
		.dispatchEvent( 'click' );
	const dialog = page.getByRole( 'dialog', { name: 'Export submissions' } );
	await expect( dialog ).toBeVisible();

	for ( const direction of [ 'ltr', 'rtl' ] ) {
		await page
			.locator( 'html' )
			.evaluate(
				( root, dir ) => root.setAttribute( 'dir', dir ),
				direction
			);

		const measured = await page.evaluate( () => {
			const box = ( node ) => node.getBoundingClientRect();
			const centre = ( node ) => box( node ).top + box( node ).height / 2;
			const firstLine = ( node ) => {
				const range = document.createRange();
				range.selectNodeContents( node );
				return range.getClientRects()[ 0 ];
			};
			const offLine = ( input, label ) => {
				const line = firstLine( label );
				return Math.abs(
					centre( input ) - ( line.top + line.height / 2 )
				);
			};
			const sections = Array.from(
				document.querySelector( '.corex-dialog__body' ).children
			);
			const pairs = Array.from(
				document.querySelectorAll( '.corex-export__column' )
			).map( ( column ) => [
				column.querySelector( 'input' ),
				column.querySelector( '.corex-export__column-name' ),
			] );
			const scope = document.querySelector( '.corex-export__scope' );
			pairs.push( [
				scope.querySelector( 'input' ),
				scope.querySelector( '.corex-export__scope-name' ),
			] );
			const tests = document.querySelector( '.corex-export__check' );
			pairs.push( [
				tests.querySelector( 'input' ),
				tests.querySelector( 'span:last-child' ),
			] );

			return {
				furthestOffItsLine: Math.max(
					...pairs.map( ( [ input, label ] ) =>
						offLine( input, label )
					)
				),
				sectionGaps: sections
					.slice( 1 )
					.map( ( section, index ) =>
						Math.round(
							box( section ).top - box( sections[ index ] ).bottom
						)
					),
				paragraphMargins: Array.from(
					document.querySelectorAll( '.corex-export p' )
				).map(
					( paragraph ) => window.getComputedStyle( paragraph ).margin
				),
			};
		} );
		const where = `in ${ direction }`;

		// A pixel of tolerance for rounding; the faults this guards were two and five.
		expect( measured.furthestOffItsLine, where ).toBeLessThanOrEqual( 1 );
		expect(
			new Set( measured.sectionGaps ).size,
			`${ where }: section gaps ${ measured.sectionGaps.join( ', ' ) }`
		).toBe( 1 );
		expect(
			measured.paragraphMargins.every( ( margin ) => margin === '0px' ),
			where
		).toBe( true );
	}

	await page
		.locator( 'html' )
		.evaluate( ( root ) => root.setAttribute( 'dir', 'ltr' ) );
} );

/**
 * The inbox's filters, measured (spec 103).
 *
 * Six controls stood at four heights, 50, 40, 47 and 54 pixels, with three different inner
 * paddings, because each input type brought its own from WordPress. "To" fell onto a row by
 * itself, away from "From". And the heading's checkbox stood two pixels off the column under it.
 */
test( 'keeps the inbox filters one height, the date range together, and the checkbox column straight', async ( {
	page,
} ) => {
	for ( const width of [ 1440, 1280, 900, 480 ] ) {
		await page.setViewportSize( { width, height: 900 } );
		const measured = await page.evaluate( () => {
			const filters = document.querySelector( '.corex-inbox__filters' );
			const box = ( node ) => node.getBoundingClientRect();
			const controls = Array.from(
				filters.querySelectorAll(
					'input:not([type="checkbox"]), .corex-select__button'
				)
			);
			const [ from, to ] =
				filters.querySelectorAll( 'input[type="date"]' );
			const check = filters.querySelector( '.is-check input' );
			const words = filters.querySelector( '.is-check span' );
			const middle = ( node ) => box( node ).top + box( node ).height / 2;

			return {
				heights: controls.map( ( node ) =>
					Math.round( box( node ).height )
				),
				paddings: controls.map(
					( node ) =>
						window.getComputedStyle( node ).paddingInlineStart
				),
				datesOnOneRow:
					Math.round( box( from ).top ) ===
					Math.round( box( to ).top ),
				checkboxOffItsWords: Math.abs(
					middle( check ) - middle( words )
				),
				columnOffset: Math.abs(
					box(
						document.querySelector(
							'.corex-inbox__table thead input'
						)
					).left -
						box(
							document.querySelector(
								'.corex-inbox__table tbody input'
							)
						).left
				),
			};
		} );
		const where = `at ${ width }px`;

		expect(
			new Set( measured.heights ).size,
			`${ where }: heights ${ measured.heights.join( ', ' ) }`
		).toBe( 1 );
		expect(
			new Set( measured.paddings ).size,
			`${ where }: paddings ${ measured.paddings.join( ', ' ) }`
		).toBe( 1 );
		expect( measured.datesOnOneRow, where ).toBe( true );
		expect( measured.checkboxOffItsWords, where ).toBeLessThanOrEqual( 1 );
		expect( measured.columnOffset, where ).toBeLessThanOrEqual( 0.5 );
	}
} );
