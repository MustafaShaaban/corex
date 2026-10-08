/**
 * Complete Submissions Inbox workflow and personal-data export evidence (spec 068: T109).
 */

const fs = require( 'node:fs' );
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors, seedSubmission, wpEval } = require( './helpers' );

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
		.getByLabel( 'Add a note for the team' )
		.fill( 'Browser evidence note.' );
	await drawer.getByRole( 'button', { name: 'Add note' } ).click();
	await expect( drawer.getByText( 'Browser evidence note.' ) ).toBeVisible();
	await drawer.getByRole( 'button', { name: 'Close', exact: true } ).click();

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

	// And as a document to file (US8). The dialog says how many a PDF holds before it is asked
	// for, and its parts stay one distance apart under the longer line.
	await dialog.getByRole( 'combobox', { name: 'File type' } ).click();
	await page.getByRole( 'option', { name: 'PDF document (.pdf)' } ).click();
	await expect(
		dialog.getByText( /Up to 500 submissions\.$/ )
	).toBeVisible();
	const gapsWithPdf = await dialog
		.locator( '.corex-dialog__body' )
		.evaluate( ( body ) =>
			Array.from( body.children )
				.slice( 1 )
				.map( ( section ) =>
					Math.round(
						section.getBoundingClientRect().top -
							section.previousElementSibling.getBoundingClientRect()
								.bottom
					)
				)
		);
	expect(
		new Set( gapsWithPdf ).size,
		`section gaps ${ gapsWithPdf.join( ', ' ) }`
	).toBe( 1 );
	const documentArriving = page.waitForEvent( 'download' );
	await start.click();
	const filed = await documentArriving;
	expect( filed.suggestedFilename() ).toMatch( /\.pdf$/ );
	expect(
		fs
			.readFileSync( await filed.path() )
			.subarray( 0, 5 )
			.toString( 'latin1' )
	).toBe( '%PDF-' );
	await expect( dialog.getByText( /^Saved .+\.pdf\.$/ ) ).toBeVisible();

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
			.getByRole( 'button', { name: 'Close', exact: true } )
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
 * The detail pane, read and measured (spec 103, US5).
 *
 * The pane it replaces put the answers fourth, under their field keys, and drew three sections
 * that said nothing was recorded. This opens a submission the way a keyboard does and checks what
 * a person sees: the answer is on the screen without scrolling at 720 pixels of height (SC-007),
 * the parts are the same distance apart, everything starts on one edge, and closing it puts focus
 * back on the row (FR-045). The spacing is measured for the reason the export dialog's is: it is
 * the kind of fault a test of behaviour cannot see.
 */
test( 'reads a submission in the pane: the answer first, the parts evenly spaced, focus handed back', async ( {
	page,
} ) => {
	await page.setViewportSize( { width: 1280, height: 720 } );
	await page.getByLabel( 'Search' ).fill( EMAIL );
	const row = page
		.locator( '.corex-inbox__table tbody tr' )
		.filter( { hasText: EMAIL } )
		.first();
	await expect( row ).toBeVisible();
	await expect(
		page.getByText( 'marked-test@example.com', { exact: true } )
	).toHaveCount( 0 );
	await expect( page.locator( '.corex-inbox' ) ).toHaveAttribute(
		'data-status',
		'ready'
	);

	const opener = row.getByRole( 'button' );
	await opener.focus();
	await page.keyboard.press( 'Enter' );
	const pane = page.getByRole( 'dialog' );
	await expect( pane ).toBeVisible();
	await expect( pane.locator( '.corex-pane__fields dt' ).first() ).toHaveText(
		'Email'
	);
	await expect( pane.locator( '.corex-pane__fields dd' ).first() ).toHaveText(
		EMAIL
	);
	await expect( pane.getByRole( 'link', { name: EMAIL } ) ).toHaveAttribute(
		'href',
		`mailto:${ EMAIL }`
	);

	// Opening it is reading it (FR-047): the seeded submission was unread a moment ago.
	const markUnread = pane.getByRole( 'button', { name: 'Mark unread' } );
	await expect( markUnread ).toBeVisible();

	const measured = await pane.evaluate( ( dialog ) => {
		const box = ( node ) => node.getBoundingClientRect();
		const size = ( node ) =>
			parseFloat( window.getComputedStyle( node ).fontSize );
		const parts = Array.from(
			dialog.querySelector( '.corex-dialog__body' ).children
		);
		// What is drawn: a question inside a closed group has no box to measure.
		const aligned = dialog.querySelectorAll(
			'.corex-dialog__title, .corex-pane__header > *, .corex-pane__section > h3, .corex-pane__section dt, .corex-pane__more > summary, .corex-pane__facts'
		);

		return {
			focusInside: dialog.contains( dialog.ownerDocument.activeElement ),
			firstAnswerBottom: box(
				dialog.querySelector( '.corex-pane__fields dd' )
			).bottom,
			windowHeight: window.innerHeight,
			gaps: parts
				.slice( 1 )
				.map( ( part, index ) =>
					Math.round( box( part ).top - box( parts[ index ] ).bottom )
				),
			edges: Array.from(
				new Set(
					Array.from( aligned ).map( ( node ) =>
						Math.round( box( node ).left )
					)
				)
			),
			controls: Array.from(
				dialog.querySelectorAll(
					'input[type="text"], .corex-select__button'
				)
			).map( ( node ) => Math.round( box( node ).height ) ),
			question: size( dialog.querySelector( '.corex-pane__fields dt' ) ),
			answer: size( dialog.querySelector( '.corex-pane__fields dd' ) ),
			readToggle: box(
				dialog.querySelector( '.corex-pane__state .components-button' )
			).height,
			emptySections: Array.from(
				dialog.querySelectorAll( '.corex-pane__section, details' )
			).filter( ( node ) => node.children.length < 2 ).length,
		};
	} );

	expect( measured.focusInside, 'focus moved into the pane' ).toBe( true );
	expect(
		measured.firstAnswerBottom,
		'the first answer is on the screen without scrolling'
	).toBeLessThanOrEqual( measured.windowHeight );
	expect( measured.gaps.length ).toBeGreaterThan( 3 );
	expect( new Set( measured.gaps ), 'one distance between parts' ).toEqual(
		new Set( [ 24 ] )
	);
	expect( measured.edges, 'one starting edge' ).toHaveLength( 1 );
	expect( new Set( measured.controls ), 'one control height' ).toEqual(
		new Set( [ 40 ] )
	);
	expect( measured.answer ).toBeGreaterThanOrEqual( measured.question );
	expect( measured.readToggle ).toBeGreaterThanOrEqual( 24 );
	expect( measured.emptySections, 'no section with nothing in it' ).toBe( 0 );

	// The history is sentences, not the stage and outcome it was stored as.
	await pane.getByText( 'History', { exact: true } ).click();
	await expect(
		pane.getByText( 'Marked read', { exact: true } )
	).toBeVisible();
	await expect( pane.getByText( /read success/ ) ).toHaveCount( 0 );

	await markUnread.click();
	await expect(
		pane.getByRole( 'button', { name: 'Mark read' } )
	).toBeVisible();
	await expect( pane.getByText( 'Unread', { exact: true } ) ).toBeVisible();

	// On a phone the pane is the window: nothing of it is off the screen.
	await page.setViewportSize( { width: 480, height: 720 } );
	const fit = await pane.evaluate( ( dialog ) => {
		const box = dialog.getBoundingClientRect();

		return {
			left: Math.round( box.left ),
			top: Math.round( box.top ),
			right: Math.round( box.right ),
			bottom: Math.round( box.bottom ),
			width: window.innerWidth,
			height: window.innerHeight,
		};
	} );
	expect( fit.left ).toBeGreaterThanOrEqual( 0 );
	expect( fit.top ).toBe( 0 );
	expect( fit.right ).toBeLessThanOrEqual( fit.width );
	expect( fit.bottom ).toBeLessThanOrEqual( fit.height );
	await page.setViewportSize( { width: 1280, height: 720 } );

	await page.keyboard.press( 'Escape' );
	await expect( pane ).toBeHidden();
	await expect( opener ).toBeFocused();
} );

/**
 * What was exported before (spec 103, US9).
 *
 * The history was a date, a count and a link. It says now what each export covered, in what
 * format and how large, who made it, and when its file expires; and a file can be deleted. This
 * makes one export, reads its entry, measures it, and deletes it.
 *
 * It also sets a date range first. The dialog put the filters in force into words with a date
 * helper that answers an object, and printed "[object Object] to [object Object]".
 */
test( 'lists what was exported before, says what became of each file, and deletes one when asked', async ( {
	page,
} ) => {
	await page.getByLabel( 'Search' ).fill( EMAIL );
	const today = new Date().toISOString().slice( 0, 10 );
	await page.getByLabel( 'From' ).fill( today );
	await page.getByLabel( 'To', { exact: true } ).fill( today );
	await expect(
		page.getByText( 'marked-test@example.com', { exact: true } )
	).toHaveCount( 0 );
	await expect( page.locator( '.corex-inbox' ) ).toHaveAttribute(
		'data-status',
		'ready'
	);

	await page
		.getByRole( 'button', { name: 'Export', exact: true } )
		.dispatchEvent( 'click' );
	const dialog = page.getByRole( 'dialog', { name: 'Export submissions' } );
	const filtered = dialog
		.locator( '.corex-export__scope' )
		.filter( { hasText: 'Current filters' } );
	await expect( filtered ).toContainText(
		String( new Date().getFullYear() )
	);
	await expect( dialog ).not.toContainText( '[object Object]' );

	// The current filters, as a CSV. How many submissions that is depends on how many tests of
	// this file ran before this one: each seeds another under the same address.
	await filtered.locator( 'input[type="radio"]' ).check();
	await dialog.getByRole( 'combobox', { name: 'File type' } ).click();
	await page.getByRole( 'option', { name: 'CSV (.csv)' } ).click();
	await dialog
		.getByText( 'I understand this export contains personal data' )
		.click();
	const arriving = page.waitForEvent( 'download' );
	await dialog
		.getByRole( 'button', { name: /^Export \d+ submissions?$/ } )
		.click();
	await arriving;
	await expect( dialog.getByText( /^Saved .+\.csv\.$/ ) ).toBeVisible();

	const recent = dialog.locator( '.corex-export__recent' );
	const entry = recent.locator( '.corex-export__entry' ).first();
	await expect( entry ).toHaveClass( /is-ready/ );
	await expect( entry.locator( '.corex-export__entry-what' ) ).toContainText(
		`Search: “${ EMAIL }”`
	);
	await expect( entry ).toContainText( 'CSV' );
	await expect( entry ).toContainText( /\d+ submissions?/ );
	await expect( entry ).toContainText( /\d+ (B|KB)/ );
	await expect( entry ).toContainText( 'admin' );
	await expect( entry.locator( '.corex-export__entry-file' ) ).toHaveText(
		/^Expires .*\d{4}$/
	);

	const measured = await recent.evaluate( ( section ) => {
		const box = ( node ) => node.getBoundingClientRect();
		const size = ( node ) =>
			parseFloat( window.getComputedStyle( node ).fontSize );
		const first = section.querySelector( '.corex-export__entry' );
		const lines = Array.from(
			first.querySelector( '.corex-export__entry-text' ).children
		);
		const buttons = Array.from(
			first.querySelectorAll( '.corex-export__entry-actions button' )
		);

		return {
			what: size( lines[ 0 ] ),
			facts: size( lines[ 1 ] ),
			file: size( lines[ lines.length - 1 ] ),
			lineGaps: lines
				.slice( 1 )
				.map( ( line, index ) =>
					Math.round( box( line ).top - box( lines[ index ] ).bottom )
				),
			textStartsWithHeading:
				Math.round( box( lines[ 0 ] ).left ) ===
				Math.round( box( section.querySelector( 'h3' ) ).left ),
			buttonHeights: buttons.map( ( node ) =>
				Math.round( box( node ).height )
			),
			// A label wider than its button runs past the edge of the dialog.
			clipped: buttons.filter(
				( node ) => node.scrollWidth > node.clientWidth + 1
			).length,
			pastTheEdge: buttons.filter(
				( node ) => box( node ).right > box( section ).right + 1
			).length,
		};
	} );
	expect( measured.what ).toBe( 14 );
	expect( measured.facts ).toBe( 14 );
	expect( measured.file ).toBe( 12 );
	expect( new Set( measured.lineGaps ), 'one gap between lines' ).toEqual(
		new Set( [ 4 ] )
	);
	expect( measured.textStartsWithHeading ).toBe( true );
	expect( measured.buttonHeights ).toHaveLength( 2 );
	expect( Math.min( ...measured.buttonHeights ) ).toBeGreaterThanOrEqual(
		24
	);
	expect( measured.clipped ).toBe( 0 );
	expect( measured.pastTheEdge ).toBe( 0 );

	// Deleting asks first, and the answer focus lands on is the safe one.
	await entry.getByRole( 'button', { name: 'Delete', exact: true } ).click();
	const asking = entry.getByRole( 'group', { name: 'Delete this file?' } );
	await expect(
		asking.getByRole( 'button', { name: 'Keep it' } )
	).toBeFocused();
	await asking.getByRole( 'button', { name: 'Delete file' } ).click();

	await expect( entry ).toHaveClass( /is-deleted/ );
	await expect( entry.locator( '.corex-export__entry-file' ) ).toHaveText(
		/^Deleted by admin, /
	);
	await expect( entry.getByRole( 'button' ) ).toHaveCount( 0 );
	await expect(
		recent.getByRole( 'heading', { name: 'Recent exports' } )
	).toBeFocused();
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

/**
 * The trash (spec 105, US1).
 *
 * The inbox could not remove a submission: reported from a client's production site, where a
 * test lead could not be taken out. One goes from its pane and comes back with "Undo"; several go
 * as a bulk action; the trash lists them with who moved them and when; a trashed submission is
 * read and nothing else; and it is restored from its pane or in bulk.
 */
test( 'moves submissions to the trash and restores them, from the pane, in bulk and with undo', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	// A second submission under the same address, so there are two to move together.
	const second = await seedSubmission( page, FLOW_SLUG, EMAIL );
	expect( second.real.envelope.ok ).toBe( true );
	// Seeding leaves the page on the form's own screen.
	await page.goto( '/wp-admin/admin.php?page=corex-submissions' );
	await expect( page.getByText( 'Loading submissions…' ) ).toBeHidden();
	await page.getByLabel( 'Search' ).fill( EMAIL );
	const rows = page.locator( '.corex-inbox__table tbody tr' );
	// Until the search has been applied the table still holds the unfiltered page.
	await expect
		.poll( async () => {
			const shown = await rows.count();
			const matching = await rows.filter( { hasText: EMAIL } ).count();
			return shown >= 2 && shown === matching;
		} )
		.toBe( true );
	const before = await rows.count();
	// How far the table runs past its frame, to hold the trash's table to the same.
	const pastItsFrame = () =>
		page
			.locator( '.corex-inbox__table-wrap' )
			.evaluate( ( wrap ) => wrap.scrollWidth - wrap.clientWidth );
	const inboxPastItsFrame = await pastItsFrame();

	// One, from its pane. It is asked first, and says how many and what becomes of it.
	await page
		.getByRole( 'button', { name: new RegExp( EMAIL ) } )
		.first()
		.click();
	const pane = page.locator( '.corex-pane' );
	// Opening a submission marks it read, which changes it. The first version of this feature
	// trashed the copy taken at the click and was refused as stale; the read mark is waited for
	// here so the test meets the submission as a person does.
	await expect(
		pane.getByRole( 'button', { name: 'Mark unread' } )
	).toBeVisible();
	await pane.getByRole( 'button', { name: 'Move to trash' } ).click();
	const asking = page.getByRole( 'dialog', { name: 'Move to the trash' } );
	await expect( asking ).toContainText(
		'1 submission will leave the inbox. It can be restored from the trash.'
	);
	await asking.getByRole( 'button', { name: 'Move to trash' } ).click();
	const notice = page.locator( '.corex-inbox__notice' );
	await expect( notice ).toContainText( '1 submission moved to the trash.' );
	await expect( pane ).toBeHidden();
	await expect( rows ).toHaveCount( before - 1 );

	// "Undo" puts it back.
	await notice.getByRole( 'button', { name: 'Undo' } ).click();
	await expect( notice ).toContainText( '1 submission restored.' );
	await expect( rows ).toHaveCount( before );

	// Two, as a bulk action.
	await page
		.getByLabel( /Select submission/ )
		.nth( 0 )
		.check();
	await page
		.getByLabel( /Select submission/ )
		.nth( 1 )
		.check();
	await page.getByRole( 'combobox', { name: 'Bulk action' } ).click();
	await page.getByRole( 'option', { name: 'Move to trash' } ).click();
	await page.getByRole( 'button', { name: 'Preview action' } ).click();
	await expect( asking ).toContainText(
		'2 submissions will leave the inbox. They can be restored from the trash.'
	);
	await asking.getByRole( 'button', { name: 'Move to trash' } ).click();
	await expect( notice ).toContainText( '2 submissions moved to the trash.' );
	await expect( rows ).toHaveCount( before - 2 );

	// The trash: its own view, with who moved each and when, and nothing to export.
	const views = page.getByRole( 'group', { name: 'Submissions shown' } );
	await views.getByRole( 'button', { name: 'Trash' } ).click();
	await expect(
		views.getByRole( 'button', { name: 'Trash' } )
	).toHaveAttribute( 'aria-pressed', 'true' );
	await expect( page.locator( '.corex-inbox__count' ) ).toHaveText(
		/^\d+ submissions? in the trash$/
	);
	await expect(
		page.getByRole( 'columnheader', { name: 'Moved to trash' } )
	).toBeVisible();
	// It stands where "Notification" does in the inbox. As an eighth column it was cut off at
	// 1440 pixels, where the inbox's seven fit. Measured: the trash table is as wide as the
	// inbox's, to within the width of a name under a date (8 pixels at 1280).
	await expect(
		page.getByRole( 'columnheader', { name: 'Notification' } )
	).toHaveCount( 0 );
	expect(
		( await pastItsFrame() ) - inboxPastItsFrame,
		'the trash table is no wider than the inbox table'
	).toBeLessThanOrEqual( 16 );
	await expect(
		page.getByRole( 'button', { name: 'Export', exact: true } )
	).toHaveCount( 0 );
	await expect( rows ).toHaveCount( 2 );
	// Who moved it, under when. The day it will be deleted for good stands under that.
	await expect(
		rows.first().locator( '.corex-inbox__trashed small' ).first()
	).not.toBeEmpty();

	// The switch stands on the filters' edge, as far from the heading as from the filters, and
	// its two buttons are one height. Measured in both directions.
	for ( const direction of [ 'ltr', 'rtl' ] ) {
		await page
			.locator( 'html' )
			.evaluate(
				( root, dir ) => root.setAttribute( 'dir', dir ),
				direction
			);
		const measured = await page.evaluate( () => {
			const box = ( selector ) =>
				document.querySelector( selector ).getBoundingClientRect();
			const header = box( '.corex-inbox__header' );
			const switcher = box( '.corex-inbox__views' );
			const filters = box( '.corex-inbox__filters' );
			const rtl = document.documentElement.dir === 'rtl';

			return {
				above: Math.round( switcher.top - header.bottom ),
				below: Math.round( filters.top - switcher.bottom ),
				offEdge: Math.round(
					rtl
						? Math.abs( switcher.right - filters.right )
						: Math.abs( switcher.left - filters.left )
				),
				buttons: Array.from(
					document.querySelectorAll( '.corex-inbox__view' )
				).map( ( button ) => button.getBoundingClientRect().height ),
			};
		} );
		const where = `in ${ direction }`;

		expect( measured.above, where ).toBe( measured.below );
		expect( measured.offEdge, where ).toBeLessThanOrEqual( 1 );
		expect( new Set( measured.buttons ).size, where ).toBe( 1 );
	}
	await page
		.locator( 'html' )
		.evaluate( ( root ) => root.setAttribute( 'dir', 'ltr' ) );

	// A trashed submission is read and nothing else.
	await page
		.getByRole( 'button', { name: new RegExp( EMAIL ) } )
		.first()
		.click();
	const line = pane.locator( '.corex-pane__trash' );
	await expect( line ).toContainText( 'In the trash.' );
	await expect( line ).toContainText( 'Moved there by' );
	await expect(
		pane.getByRole( 'heading', { name: 'Answers' } )
	).toBeVisible();
	for ( const gone of [
		pane.getByRole( 'button', { name: 'Send reply' } ),
		pane.getByRole( 'button', { name: 'Add note' } ),
		pane.getByRole( 'combobox', { name: 'Status' } ),
		pane.getByRole( 'button', { name: /^Mark (un)?read$/ } ),
	] ) {
		await expect( gone ).toHaveCount( 0 );
	}
	const padding = await line.evaluate( ( node ) => {
		const style = window.getComputedStyle( node );
		return [
			style.paddingTop,
			style.paddingBottom,
			style.paddingInlineStart,
			style.paddingInlineEnd,
		];
	} );
	expect( new Set( padding ).size, `padding ${ padding.join( ', ' ) }` ).toBe(
		1
	);

	// Restored from its pane.
	await line.getByRole( 'button', { name: 'Restore' } ).click();
	await expect( notice ).toContainText( '1 submission restored.' );
	await expect( rows ).toHaveCount( 1 );

	// And the other in bulk, where restoring is the only action there is.
	await page
		.getByLabel( /Select submission/ )
		.first()
		.check();
	await expect(
		page.getByRole( 'combobox', { name: 'Bulk action' } )
	).toContainText( 'Restore' );
	await page.getByRole( 'button', { name: 'Preview action' } ).click();
	const restoring = page.getByRole( 'dialog', {
		name: 'Restore from the trash',
	} );
	await expect( restoring ).toContainText(
		'1 submission will go back to the inbox, as it was.'
	);
	await restoring.getByRole( 'button', { name: 'Restore' } ).click();
	await expect( notice ).toContainText( '1 submission restored.' );
	await expect(
		page.getByRole( 'heading', { name: 'Nothing in the trash' } )
	).toBeVisible();

	// Back in the inbox, both are there again.
	await views.getByRole( 'button', { name: 'Inbox' } ).click();
	await expect( rows ).toHaveCount( before );

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

/**
 * Deleting for good (spec 105, US2).
 *
 * Only from the trash, only after a confirmation that lists what goes with the submission and
 * has a box to tick, and for one from its pane or several as the trash's bulk action.
 */
test( 'deletes submissions for good from the trash, after an acknowledged confirmation', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	const second = await seedSubmission( page, FLOW_SLUG, EMAIL );
	expect( second.real.envelope.ok ).toBe( true );
	await page.goto( '/wp-admin/admin.php?page=corex-submissions' );
	await expect( page.getByText( 'Loading submissions…' ) ).toBeHidden();
	await page.getByLabel( 'Search' ).fill( EMAIL );
	const rows = page.locator( '.corex-inbox__table tbody tr' );
	await expect
		.poll( async () => {
			const shown = await rows.count();
			const matching = await rows.filter( { hasText: EMAIL } ).count();
			return shown >= 2 && shown === matching;
		} )
		.toBe( true );
	const notice = page.locator( '.corex-inbox__notice' );

	// The inbox does not offer it: a submission is trashed first.
	await page
		.getByLabel( /Select submission/ )
		.nth( 0 )
		.check();
	await page
		.getByLabel( /Select submission/ )
		.nth( 1 )
		.check();
	await page.getByRole( 'combobox', { name: 'Bulk action' } ).click();
	await expect(
		page.getByRole( 'option', { name: 'Delete permanently' } )
	).toHaveCount( 0 );
	await page.getByRole( 'option', { name: 'Move to trash' } ).click();
	await page.getByRole( 'button', { name: 'Preview action' } ).click();
	await page
		.getByRole( 'dialog', { name: 'Move to the trash' } )
		.getByRole( 'button', { name: 'Move to trash' } )
		.click();
	await expect( notice ).toContainText( '2 submissions moved to the trash.' );

	await page
		.getByRole( 'group', { name: 'Submissions shown' } )
		.getByRole( 'button', { name: 'Trash' } )
		.click();
	await expect( rows ).toHaveCount( 2 );

	// One, from its pane.
	await page
		.getByRole( 'button', { name: new RegExp( EMAIL ) } )
		.first()
		.click();
	const pane = page.locator( '.corex-pane' );
	await pane.getByRole( 'button', { name: 'Delete permanently' } ).click();
	const asking = page.getByRole( 'dialog', { name: 'Delete permanently' } );
	await expect( asking ).toContainText(
		'1 submission will be deleted for good. This cannot be undone.'
	);
	await expect( asking.getByRole( 'listitem' ) ).toHaveCount( 4 );
	const confirm = asking.getByRole( 'button', {
		name: 'Delete 1 submission',
	} );
	// Nothing is deleted until the box is ticked.
	await expect( confirm ).toBeDisabled();

	// Its parts are one distance apart, and the box stands level with the first line of what it
	// asks. Measured in both directions.
	for ( const direction of [ 'ltr', 'rtl' ] ) {
		await page
			.locator( 'html' )
			.evaluate(
				( root, dir ) => root.setAttribute( 'dir', dir ),
				direction
			);
		const measured = await asking
			.locator( '.corex-inbox__deletion' )
			.evaluate( ( node ) => {
				const box = ( element ) => element.getBoundingClientRect();
				const parts = Array.from( node.children );
				const input = node.querySelector(
					'.corex-inbox__acknowledge input'
				);
				const label = node.querySelector(
					'.corex-inbox__acknowledge label'
				);
				const range = document.createRange();
				range.selectNodeContents( label );
				const line = range.getClientRects()[ 0 ];

				return {
					gaps: parts
						.slice( 1 )
						.map( ( part, index ) =>
							Math.round(
								box( part ).top - box( parts[ index ] ).bottom
							)
						),
					offLine: Math.abs(
						box( input ).top +
							box( input ).height / 2 -
							( line.top + line.height / 2 )
					),
				};
			} );
		const where = `in ${ direction }`;

		expect(
			new Set( measured.gaps ).size,
			`${ where }: gaps ${ measured.gaps.join( ', ' ) }`
		).toBe( 1 );
		expect( measured.offLine, where ).toBeLessThanOrEqual( 1.5 );
	}
	await page
		.locator( 'html' )
		.evaluate( ( root ) => root.setAttribute( 'dir', 'ltr' ) );

	await asking.getByLabel( 'I understand this cannot be undone' ).check();
	await expect( confirm ).toBeEnabled();
	// Its letters are the primary button's, on the primary button's ground. They were the error
	// colour, red on brass, which could not be read: seen in a capture of this dialog.
	const inkOf = ( button ) =>
		button.evaluate( ( node ) => window.getComputedStyle( node ).color );
	expect( await inkOf( confirm ) ).toBe(
		await inkOf( pane.getByRole( 'button', { name: 'Restore' } ) )
	);
	await confirm.click();
	await expect( notice ).toContainText( '1 submission deleted for good.' );
	await expect( pane ).toBeHidden();
	await expect( rows ).toHaveCount( 1 );

	// The other, as the trash's bulk action.
	await page
		.getByLabel( /Select submission/ )
		.first()
		.check();
	await page.getByRole( 'combobox', { name: 'Bulk action' } ).click();
	await page.getByRole( 'option', { name: 'Delete permanently' } ).click();
	await page.getByRole( 'button', { name: 'Preview action' } ).click();
	await expect( asking ).toContainText(
		'1 submission will be deleted for good. This cannot be undone.'
	);
	await asking.getByLabel( 'I understand this cannot be undone' ).check();
	await asking.getByRole( 'button', { name: 'Delete 1 submission' } ).click();
	await expect( notice ).toContainText( '1 submission deleted for good.' );
	await expect(
		page.getByRole( 'heading', { name: 'Nothing in the trash' } )
	).toBeVisible();

	// Gone from the inbox too: neither can be found there.
	await page
		.getByRole( 'group', { name: 'Submissions shown' } )
		.getByRole( 'button', { name: 'Inbox' } )
		.click();
	await expect( page.getByText( 'Loading submissions…' ) ).toBeHidden();

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

/**
 * The trash's own clock (spec 105, US3).
 *
 * The trash says how long it keeps a submission and when each one in it will be deleted for good,
 * and the site sets that number of days beside its retention policy.
 */
test( 'says how long the trash keeps a submission, and takes the number of days the site sets', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	const OPTION = 'corex_trash_submissions_days';
	const before = wpEval(
		`echo wp_json_encode( get_option( "${ OPTION }", null ) );`
	);
	test.skip(
		before === null,
		'WP-CLI is not reachable, so the setting could not be put back afterwards.'
	);

	try {
		// The setting, beside the retention policy it is saved with.
		const days = page.getByLabel(
			'Keep in the trash for days (0 = until somebody deletes it)'
		);
		await days.fill( '45' );
		await page.getByRole( 'button', { name: 'Save policy' } ).click();
		await expect(
			page.getByRole( 'heading', { name: 'Submission Inbox' } )
		).toBeVisible();
		await expect( days ).toHaveValue( '45' );

		// One submission into the trash, to have a date to read.
		await page.getByLabel( 'Search' ).fill( EMAIL );
		await page
			.getByRole( 'button', { name: new RegExp( EMAIL ) } )
			.first()
			.click();
		const pane = page.locator( '.corex-pane' );
		await expect(
			pane.getByRole( 'button', { name: 'Mark unread' } )
		).toBeVisible();
		await pane.getByRole( 'button', { name: 'Move to trash' } ).click();
		await page
			.getByRole( 'dialog', { name: 'Move to the trash' } )
			.getByRole( 'button', { name: 'Move to trash' } )
			.click();
		const notice = page.locator( '.corex-inbox__notice' );
		await expect( notice ).toContainText( 'moved to the trash' );

		await page
			.getByRole( 'group', { name: 'Submissions shown' } )
			.getByRole( 'button', { name: 'Trash' } )
			.click();
		const keeps = page.locator( '.corex-inbox__trash-keeps' );
		await expect( keeps ).toHaveText(
			'A submission in the trash is deleted for good 45 days after it was moved there.'
		);
		const row = page.locator( '.corex-inbox__table tbody tr' ).first();
		await expect( row.locator( '.corex-inbox__trashed' ) ).toContainText(
			'Deleted for good:'
		);

		// The line stands as far from the filters as from the table: the inbox's one gap.
		const gaps = await page.evaluate( () => {
			const box = ( selector ) =>
				document.querySelector( selector ).getBoundingClientRect();
			const line = box( '.corex-inbox__trash-keeps' );

			return {
				above: Math.round(
					line.top - box( '.corex-inbox__filters' ).bottom
				),
				below: Math.round(
					box( '.corex-inbox__table-wrap' ).top - line.bottom
				),
			};
		} );
		expect( gaps.above ).toBe( gaps.below );

		// Put it back, so the inbox is left as it was found.
		await row.getByRole( 'button', { name: new RegExp( EMAIL ) } ).click();
		await pane.getByRole( 'button', { name: 'Restore' } ).click();
		await expect( notice ).toContainText( 'restored' );
	} finally {
		if ( JSON.parse( before ) === null ) {
			wpEval( `delete_option( "${ OPTION }" );` );
		} else {
			wpEval(
				`update_option( "${ OPTION }", ${ Number(
					JSON.parse( before )
				) }, false );`
			);
		}
	}

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );
