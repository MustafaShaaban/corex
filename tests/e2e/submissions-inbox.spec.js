/**
 * Complete Submissions Inbox workflow and personal-data export evidence (spec 068: T109).
 */

const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

const FLOW_SLUG = 'corex-inbox-e2e';
// Unique per run so the seeded submission is searchable to exactly one row on a shared site
// where prior runs' fixtures accumulate.
const EMAIL = `corex-inbox-e2e-${ Date.now() }@example.com`;

/**
 * The configuration the inbox fixture needs.
 *
 * `protection.captcha: 'off'` is load-bearing rather than incidental: this spec exercises the
 * inbox, and on any site where a captcha driver *is* configured the protection stage judges an
 * empty token and rejects every seeded submission. Opting the fixture out keeps the spec about
 * the inbox on a bare CI site and on a real one alike.
 */
const FIXTURE_CONFIGURATION = {
	schema: [ { uuid: 'email-field', key: 'email', label: 'Email', type: 'email', required: true } ],
	validation: { email: [ 'required', 'email' ] },
	routing: { rules: [], fallback: { type: 'flow_owner', config: {} } },
	email_routes: [],
	success: { type: 'inline', message: 'Received.' },
	placement_snapshot: { type: 'block' },
	protection: { captcha: 'off' },
};

async function seedSubmission( page ) {
	await page.goto( '/wp-admin/admin.php?page=corex-forms' );
	await expect( page.getByText( 'Loading forms…' ) ).toBeHidden();
	return page.evaluate( async ( fixture ) => {
		const api = window.Corex.api;
		const config = window.corexFlows;
		const list = await api.get( `${ config.restUrl }?search=${ fixture.slug }`, {
			nonce: config.nonce,
		} );
		let flow = list.envelope.data.flows.find( ( item ) => item.slug === fixture.slug );
		if ( ! flow ) {
			const created = await api.post(
				config.restUrl,
				{
					slug: fixture.slug,
					name: 'Inbox E2E flow',
					owner_id: config.ownerId,
					placement_type: 'block',
					configuration: fixture.configuration,
				},
				{ nonce: config.nonce }
			);
			flow = created.envelope.data.flow;
		}

		// Bring an existing fixture back to the shape this spec needs, rather than trusting
		// whatever state a previous run — or a person clicking around the builder — left it in.
		// The old seed only configured and published a flow it had just created, so once this
		// flow drifted, every test in the file failed permanently and told you nothing useful.
		let detail = await api.get( `${ config.restUrl }/${ flow.id }`, { nonce: config.nonce } );
		let latest = detail.envelope.data.versions.at( -1 );
		const captchaOff = latest.configuration?.protection?.captcha === 'off';

		if ( ! captchaOff ) {
			await api.patch(
				`${ config.restUrl }/${ flow.id }`,
				{
					expected_version: latest.version_number,
					expected_checksum: latest.checksum,
					configuration: fixture.configuration,
				},
				{ nonce: config.nonce }
			);
			detail = await api.get( `${ config.restUrl }/${ flow.id }`, { nonce: config.nonce } );
			latest = detail.envelope.data.versions.at( -1 );
		}

		if ( detail.envelope.data.flow.state !== 'published'
			|| detail.envelope.data.flow.published_version !== latest.version_number ) {
			if ( detail.envelope.data.flow.state === 'published' ) {
				await api.post( `${ config.restUrl }/${ flow.id }/unpublish`, { expected_version: latest.version_number }, { nonce: config.nonce } );
			}
			await api.post( `${ config.restUrl }/${ flow.id }/publish`, { expected_version: latest.version_number }, { nonce: config.nonce } );
			detail = await api.get( `${ config.restUrl }/${ flow.id }`, { nonce: config.nonce } );
			latest = detail.envelope.data.versions.at( -1 );
		}

		const version = latest.version_number;
		const real = await api.post( `${ config.restUrl }/${ flow.id }/submit`, { email: fixture.email, utm_source: 'playwright' }, { nonce: config.nonce } );
		const marked = await api.post( `${ config.restUrl }/${ flow.id }/test`, { expected_version: version, values: { email: 'marked-test@example.com' } }, { nonce: config.nonce } );
		return { real, marked };
	}, { slug: FLOW_SLUG, email: EMAIL, configuration: FIXTURE_CONFIGURATION } );
}

test.beforeEach( async ( { page } ) => {
	const seeded = await seedSubmission( page );
	expect( seeded.real.envelope.ok ).toBe( true );
	expect( seeded.marked.envelope.ok ).toBe( true );
	await page.goto( '/wp-admin/admin.php?page=corex-submissions' );
	await expect( page.getByRole( 'heading', { name: 'Submission Inbox' } ) ).toBeVisible();
	await expect( page.getByText( 'Loading submissions…' ) ).toBeHidden();
} );

/**
 * The heading stack's rhythm (spec 074, FR-2).
 *
 * The eyebrow, the title, and the count used to be loose children of a bare div, separated only by
 * a margin on each paragraph — so they rendered as one compressed block. They are a grid stack now,
 * which is measured rather than eyeballed here because the failure mode is a few pixels, and
 * because a translation that wraps or a doubled zoom is exactly where per-element margins drifted.
 */
async function headingGaps( page ) {
	return page.evaluate( () => {
		const box = ( selector ) => document.querySelector( selector )?.getBoundingClientRect();
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
				document.documentElement.scrollWidth - document.documentElement.clientWidth > 1,
		};
	} );
}

test( 'spaces the inbox heading stack at every width, zoom, and direction', async ( { page } ) => {
	const cases = [
		{ name: 'desktop', width: 1440 },
		{ name: 'narrow', width: 360 },
		// 200% zoom is emulated as half the CSS viewport at twice the scale factor; the layout
		// question is the same one — does the stack still separate when space runs out.
		{ name: '200% zoom', width: 720 },
	];

	for ( const direction of [ 'ltr', 'rtl' ] ) {
		await page.locator( 'html' ).evaluate( ( root, dir ) => root.setAttribute( 'dir', dir ), direction );

		for ( const viewport of cases ) {
			await page.setViewportSize( { width: viewport.width, height: 900 } );
			const gaps = await headingGaps( page );
			const where = `${ direction } @ ${ viewport.name }`;

			expect( gaps.eyebrowToTitle, `eyebrow/title gap collapsed at ${ where }` ).toBeGreaterThanOrEqual( 4 );
			expect( gaps.titleToCount, `title/count gap collapsed at ${ where }` ).toBeGreaterThanOrEqual( 4 );
			// One stack, one rhythm: the two gaps come from the same grid gap, so a difference
			// means a margin crept back in.
			expect( Math.abs( gaps.eyebrowToTitle - gaps.titleToCount ), `uneven rhythm at ${ where }` ).toBeLessThanOrEqual( 1 );
			expect( gaps.overflows, `horizontal overflow at ${ where }` ).toBe( false );
		}
	}

	await page.locator( 'html' ).evaluate( ( root ) => root.setAttribute( 'dir', 'ltr' ) );
} );

test( 'opens pre-filtered when Forms & Flows links to one form’s submissions', async ( { page } ) => {
	// The catalog's "View submissions for this form" link (spec 074, FR-1.9). A link that landed on
	// an unfiltered inbox would be a control that does nothing.
	await page.goto( '/wp-admin/admin.php?page=corex-submissions&corex_form=slug:contact' );
	await expect( page.getByRole( 'heading', { name: 'Submission Inbox' } ) ).toBeVisible();
	await expect( page.getByText( 'Loading submissions…' ) ).toBeHidden();

	await expect( page.getByRole( 'combobox', { name: 'Form' } ) ).toHaveText( /Contact/i );
} );

test( 'filters works assigns notes bulk actions and audits personal-data exports', async ( { page } ) => {
	const errors = collectConsoleErrors( page );
	await page.getByLabel( 'Search' ).fill( EMAIL );
	// `.first()`: every test in this file seeds another submission under the same fixture email,
	// so more than one row legitimately matches by the time the later tests run.
	await expect( page.getByText( EMAIL, { exact: true } ).first() ).toBeVisible();
	await expect( page.getByText( 'marked-test@example.com', { exact: true } ) ).toHaveCount( 0 );

	// Scope to a single matching submission: the seeded fixture email can accumulate across
	// runs on a shared site, so operate on the first match to keep the workflow isolation-safe.
	await page.getByLabel( /Select submission/ ).first().check();
	// Bulk action is a CorexSelect now (spec 069) — an in-DOM listbox rather than a native
	// <select>, so it is opened and picked rather than driven with selectOption().
	await page.getByRole( 'combobox', { name: 'Bulk action' } ).click();
	await page.getByRole( 'option', { name: 'Mark read' } ).click();
	await page.getByRole( 'button', { name: 'Preview action' } ).click();
	await expect( page.getByText( /will affect exactly 1 submissions/ ) ).toBeVisible();
	await page.getByRole( 'button', { name: 'Confirm and apply' } ).click();
	await expect( page.getByText( 'Bulk action applied.' ) ).toBeVisible();

	await page.getByRole( 'button', { name: new RegExp( EMAIL ) } ).first().click();
	const drawer = page.locator( '.corex-inbox__drawer' );
	await expect( drawer ).toBeVisible();
	await drawer.getByRole( 'combobox', { name: 'Status' } ).click();
	await page.getByRole( 'option', { name: 'In progress' } ).click();
	await drawer.getByPlaceholder( 'Add a team note' ).fill( 'Browser evidence note.' );
	await drawer.getByRole( 'button', { name: 'Add note' } ).click();
	await expect( drawer.getByText( 'Browser evidence note.' ) ).toBeVisible();
	await drawer.getByRole( 'button', { name: 'Close detail' } ).click();

	await page.getByLabel( /Select submission/ ).first().check();
	// On a shared site the inbox list can be long enough to keep the toolbar Export button
	// outside the window viewport (sticky toolbar in a scroll container); dispatch the click
	// event directly so the workflow stays reliable regardless of list length.
	const exportButton = page.getByRole( 'button', { name: 'Export', exact: true } );
	await exportButton.dispatchEvent( 'click' );
	const modal = page.getByRole( 'dialog', { name: 'Export submissions' } );
	await modal.getByText( 'I understand this export contains personal data' ).click();
	await modal.getByRole( 'button', { name: 'Create export' } ).click();
	await modal.getByRole( 'button', { name: 'Refresh history' } ).click();
	await expect( modal.locator( '.corex-inbox__export-history > li' ).first() ).toContainText( 'selected' );

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'contains the Inbox at mobile tablet desktop wide and RTL viewports', async ( { page } ) => {
	for ( const width of [ 375, 768, 1024, 1440 ] ) {
		await page.setViewportSize( { width, height: 900 } );
		const fits = await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth );
		expect( fits, `horizontal overflow at ${ width }px` ).toBe( true );
	}
	await page.locator( 'html' ).evaluate( ( root ) => root.setAttribute( 'dir', 'rtl' ) );
	await expect( page.locator( '.corex-inbox' ) ).toHaveCSS( 'direction', 'rtl' );
} );
