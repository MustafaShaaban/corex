/**
 * Command Center (Overview + Add-ons) and all-route navigation matrix (spec 068: T180–T182).
 *
 * Overview projects real state (readiness, live recent-activity, real Forms/Flows and data
 * counts). Add-ons lists real packages with truthful summary counts. Every registered CoreX
 * route highlights exactly one rail item (with aria-current) and shows the matching breadcrumb.
 */

const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

const ROUTES = [
	[ 'corex-settings', 'Overview' ],
	[ 'corex-addons', 'Add-ons' ],
	[ 'corex-forms', 'Forms & Flows' ],
	[ 'corex-submissions', 'Submissions' ],
	[ 'corex-email-studio', 'Email Studio' ],
	// Spec 069: one Data entry. `corex-data` rendered the same explorer and now redirects here.
	[ 'corex-data-models', 'Data' ],
	[ 'corex-operations-security', 'Operations & Security' ],
	[ 'corex-access', 'Access & Abilities' ],
	[ 'corex-blog-pro', 'Blog Pro' ],
	[ 'corex-insights', 'Insights' ],
	[ 'corex-setup', 'Setup Wizard' ],
	[ 'corex-settings-config', 'Settings' ],
];

test( 'Overview projects real readiness, activity, and command-center counts', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-settings' );
	await expect( page.getByRole( 'heading', { name: 'CoreX Overview' } ) ).toBeVisible();

	// Real projections — no fabricated placeholders.
	await expect( page.getByText( 'Launch readiness' ) ).toBeVisible();
	await expect( page.locator( '.corex-overview__tile' ) ).toHaveCount( 4 );
	await expect( page.getByText( 'Read-only' ) ).toHaveCount( 0 );
	await expect( page.getByText( 'once event logging is available' ) ).toHaveCount( 0 );
	// The recent-activity card shows either real events or the honest empty state, never the
	// old "logging not available" placeholder.
	await expect( page.getByRole( 'heading', { name: 'Recent activity' } ) ).toBeVisible();

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'Overview tiles hold four evenly pitched columns', async ( { page } ) => {
	// The tiles were an auto-fit grid, so the track count changed with the viewport and the row
	// re-flowed to three or five unevenly-sized tiles at ordinary widths. The approved capture
	// ("Corex Admin Overview.dc.html") specifies repeat(4,1fr); four fixed tracks hold that shape.
	//
	// Note this deliberately does NOT assert alignment with the card grids below: those are
	// 1.15fr/1fr, so their gutter sits at ~53.5% while four equal tiles divide at 50%. The capture
	// specifies both, so the offset is the design, not a defect.
	await page.setViewportSize( { width: 1440, height: 900 } );
	await page.goto( '/wp-admin/admin.php?page=corex-settings' );
	await expect( page.locator( '.corex-overview__tile' ) ).toHaveCount( 4 );

	const tiles = await page
		.locator( '.corex-overview__tile' )
		.evaluateAll( ( els ) => els.map( ( el ) => el.getBoundingClientRect().x ) );

	// One row of four: four distinct offsets, evenly pitched.
	expect( new Set( tiles.map( Math.round ) ).size ).toBe( 4 );
	const pitches = tiles.slice( 1 ).map( ( x, i ) => x - tiles[ i ] );
	for ( const pitch of pitches ) {
		expect( Math.abs( pitch - pitches[ 0 ] ) ).toBeLessThan( 2 );
	}
} );

test( 'Add-ons lists real packages with truthful summary counts', async ( { page } ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-addons' );
	await expect( page.getByRole( 'heading', { name: 'CoreX Add-ons' } ) ).toBeVisible();

	await expect( page.locator( '.corex-addon-card' ).first() ).toBeVisible();
	// Updates are honestly untracked (no faked count), and real toggles are present.
	await expect( page.getByText( 'not tracked' ) ).toBeVisible();
	await expect( page.locator( '.corex-toggle' ).first() ).toBeVisible();

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'every CoreX route highlights the correct rail item and breadcrumb', async ( {
	page,
} ) => {
	for ( const [ slug, label ] of ROUTES ) {
		await page.goto( `/wp-admin/admin.php?page=${ slug }` );
		const active = page.locator( '.corex-admin__nav-item.is-active' );
		await expect( active, `${ slug } active rail item` ).toHaveCount( 1 );
		await expect( active ).toHaveAttribute( 'aria-current', 'page' );
		await expect( active ).toContainText( label );
		await expect( page.locator( '.corex-admin__eyebrow' ).first() ).toContainText( label );
	}
} );
