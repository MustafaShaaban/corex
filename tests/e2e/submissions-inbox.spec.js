/**
 * Complete Submissions Inbox workflow and personal-data export evidence (spec 068: T109).
 */

const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

const FLOW_SLUG = 'corex-inbox-e2e';
// Unique per run so the seeded submission is searchable to exactly one row on a shared site
// where prior runs' fixtures accumulate.
const EMAIL = `corex-inbox-e2e-${ Date.now() }@example.com`;

async function seedSubmission( page ) {
	await page.goto( '/wp-admin/admin.php?page=corex-forms' );
	await expect( page.getByText( 'Loading flows…' ) ).toBeHidden();
	return page.evaluate( async ( fixture ) => {
		const api = window.Corex.api;
		const config = window.corexFlows;
		let list = await api.get( `${ config.restUrl }?search=${ fixture.slug }`, {
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
					configuration: {
						schema: [ { uuid: 'email-field', key: 'email', label: 'Email', type: 'email', required: true } ],
						validation: { email: [ 'required', 'email' ] },
						routing: { rules: [], fallback: { type: 'flow_owner', config: {} } },
						email_routes: [],
						success: { type: 'inline', message: 'Received.' },
						placement_snapshot: { type: 'block' },
					},
				},
				{ nonce: config.nonce }
			);
			flow = created.envelope.data.flow;
			await api.post( `${ config.restUrl }/${ flow.id }/publish`, { expected_version: 1 }, { nonce: config.nonce } );
		}
		const detail = await api.get( `${ config.restUrl }/${ flow.id }`, { nonce: config.nonce } );
		const version = detail.envelope.data.versions.at( -1 ).version_number;
		const real = await api.post( `${ config.restUrl }/${ flow.id }/submit`, { email: fixture.email, utm_source: 'playwright' }, { nonce: config.nonce } );
		const marked = await api.post( `${ config.restUrl }/${ flow.id }/test`, { expected_version: version, values: { email: 'marked-test@example.com' } }, { nonce: config.nonce } );
		return { real, marked };
	}, { slug: FLOW_SLUG, email: EMAIL } );
}

test.beforeEach( async ( { page } ) => {
	const seeded = await seedSubmission( page );
	expect( seeded.real.envelope.ok ).toBe( true );
	expect( seeded.marked.envelope.ok ).toBe( true );
	await page.goto( '/wp-admin/admin.php?page=corex-submissions' );
	await expect( page.getByRole( 'heading', { name: 'Submission Inbox' } ) ).toBeVisible();
	await expect( page.getByText( 'Loading submissions…' ) ).toBeHidden();
} );

test( 'filters works assigns notes bulk actions and audits personal-data exports', async ( { page } ) => {
	const errors = collectConsoleErrors( page );
	await page.getByLabel( 'Search' ).fill( EMAIL );
	await expect( page.getByText( EMAIL, { exact: true } ) ).toBeVisible();
	await expect( page.getByText( 'marked-test@example.com', { exact: true } ) ).toHaveCount( 0 );

	// Scope to a single matching submission: the seeded fixture email can accumulate across
	// runs on a shared site, so operate on the first match to keep the workflow isolation-safe.
	await page.getByLabel( /Select submission/ ).first().check();
	await page.getByRole( 'combobox', { name: 'Bulk action' } ).selectOption( 'mark_read' );
	await page.getByRole( 'button', { name: 'Preview action' } ).click();
	await expect( page.getByText( /will affect exactly 1 submissions/ ) ).toBeVisible();
	await page.getByRole( 'button', { name: 'Confirm and apply' } ).click();
	await expect( page.getByText( 'Bulk action applied.' ) ).toBeVisible();

	await page.getByRole( 'button', { name: new RegExp( EMAIL ) } ).first().click();
	const drawer = page.locator( '.corex-inbox__drawer' );
	await expect( drawer ).toBeVisible();
	await drawer.locator( '.corex-inbox__drawer-actions select' ).selectOption( 'in_progress' );
	await drawer.getByPlaceholder( 'Add a team note' ).fill( 'Browser evidence note.' );
	await drawer.getByRole( 'button', { name: 'Add note' } ).click();
	await expect( drawer.getByText( 'Browser evidence note.' ) ).toBeVisible();
	await drawer.getByRole( 'button', { name: 'Close detail' } ).click();

	await page.getByLabel( /Select submission/ ).first().check();
	await page.getByRole( 'button', { name: 'Export', exact: true } ).click();
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
