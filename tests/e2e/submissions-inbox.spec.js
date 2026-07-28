/**
 * Complete Submissions Inbox workflow and personal-data export evidence (spec 068: T109).
 */

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
	const modal = page.getByRole( 'dialog', { name: 'Export submissions' } );
	await modal
		.getByText( 'I understand this export contains personal data' )
		.click();
	await modal.getByRole( 'button', { name: 'Create export' } ).click();
	await modal.getByRole( 'button', { name: 'Refresh history' } ).click();
	await expect(
		modal.locator( '.corex-inbox__export-history > li' ).first()
	).toContainText( 'selected' );

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
