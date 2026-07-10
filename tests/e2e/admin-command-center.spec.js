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
	[ 'corex-data', 'Data' ],
	[ 'corex-data-models', 'Data Models' ],
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
