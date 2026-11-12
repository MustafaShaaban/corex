/**
 * What Email Studio says happens to mail (spec 113, US4): one statement, the same on every tab,
 * in one of the three outcomes; on a site that keeps its mail, the "Capture only CoreX mail"
 * switch saves and changes the statement.
 *
 * Written for any site: it reads which outcome the site states and checks the rest against it.
 */

const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

const OUTCOMES =
	/^(Delivered for real|Captured: nothing leaves this site|Captured: CoreX mail stays on this site|Blocked: nothing is sent|Blocked: Email Studio sends nothing)$/;

const TABS = [
	'Overview',
	'Templates',
	'Layouts',
	'Partials',
	'Variables',
	'Routing',
	'Preview',
	'Plain text',
	'Test send',
	'Delivery logs',
	'Health',
];

async function openStudio( page ) {
	await page.goto( '/wp-admin/admin.php?page=corex-email-studio' );
	await expect(
		page.locator( '.corex-email-app > .corex-loadable' )
	).toHaveAttribute( 'data-corex-state', 'ready' );
}

test( 'says on every tab what happens to mail, the same way', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await openStudio( page );
	const banner = page.locator( '.corex-email-app__outcome' );
	const title = ( await banner.locator( 'h2' ).textContent() ).trim();

	expect( title ).toMatch( OUTCOMES );
	await expect( banner ).toContainText( 'Environment:' );
	await expect( banner ).toContainText( 'Live delivery:' );
	await expect( banner ).toContainText( 'Provider:' );

	for ( const tab of TABS ) {
		await page.getByRole( 'button', { name: tab, exact: true } ).click();
		await expect( banner.locator( 'h2' ) ).toHaveText( title );
	}

	expect( errors ).toEqual( [] );
} );

test( 'opens on the tab its address names', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=corex-email-studio&tab=logs' );
	await expect(
		page.getByRole( 'heading', { name: 'Captured and held mail' } )
	).toBeVisible();
} );

test( 'saves the capture switch on a site that keeps its mail', async ( {
	page,
} ) => {
	await openStudio( page );
	const scope = page.getByLabel( 'Capture only CoreX mail' );
	test.skip(
		( await scope.count() ) === 0,
		'A production site sends its mail: there is nothing to capture.'
	);
	const banner = page.locator( '.corex-email-app__outcome' );
	const wasOn = await scope.isChecked();

	// The box changes when the save comes back, not when it is clicked.
	try {
		await scope.click();
		await expect( banner ).toContainText(
			wasOn
				? 'Captures: all mail from this site'
				: 'Captures: only CoreX mail'
		);
		await page.reload();
		await expect(
			page.locator( '.corex-email-app > .corex-loadable' )
		).toHaveAttribute( 'data-corex-state', 'ready' );
		await expect(
			page.getByLabel( 'Capture only CoreX mail' )
		).toBeChecked( {
			checked: ! wasOn,
		} );
	} finally {
		const now = page.getByLabel( 'Capture only CoreX mail' );
		if ( ( await now.isChecked() ) !== wasOn ) {
			await now.click();
			await expect( banner ).toContainText(
				wasOn
					? 'Captures: only CoreX mail'
					: 'Captures: all mail from this site'
			);
		}
	}
} );

test( 'keeps the statement inside a phone-width screen', async ( { page } ) => {
	await page.setViewportSize( { width: 375, height: 800 } );
	await openStudio( page );
	const banner = page.locator( '.corex-email-app__outcome' );
	const box = await banner.boundingBox();
	const overflow = await page.evaluate(
		() => document.documentElement.scrollWidth - window.innerWidth
	);

	expect( box.width ).toBeLessThanOrEqual( 375 );
	expect( overflow ).toBeLessThanOrEqual( 0 );
} );
