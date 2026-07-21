/**
 * Corex E2E — Notification Center bell + drawer (spec 072 US2, FR-016).
 *
 * Verifies the accessibility contract that jsdom cannot: the server-rendered bell in the CoreX shell
 * header opens the drawer on click, the drawer is a modal dialog, Escape closes it AND returns focus
 * to the bell, and focus stays trapped inside the panel while it is open.
 *
 * ENVIRONMENT-GATED: needs Apache up (http://corex.local), a built corex-config
 * (build/notification-ui), and `npx playwright install`. Uses the saved admin session.
 */
const { test, expect } = require( '@playwright/test' );

test.use( { storageState: require( './global-setup' ).STORAGE_STATE } );

const OVERVIEW = '/wp-admin/admin.php?page=corex-settings';

test( 'the header bell opens the drawer, and Escape closes it and returns focus', async ( {
	page,
} ) => {
	await page.goto( OVERVIEW );

	const bell = page.locator( '[data-corex-notification-bell]' );
	await expect( bell ).toBeVisible();
	await expect( bell ).toHaveAttribute( 'aria-expanded', 'false' );

	await bell.click();

	const dialog = page.locator(
		'.corex-notification-drawer__panel[role="dialog"]'
	);
	await expect( dialog ).toBeVisible();
	await expect( dialog ).toHaveAttribute( 'aria-modal', 'true' );
	await expect( bell ).toHaveAttribute( 'aria-expanded', 'true' );

	// Escape closes the drawer and hands focus back to the bell — never leaves it on a
	// now-removed element, which would restart the tab order at the top of the page.
	await page.keyboard.press( 'Escape' );
	await expect( dialog ).toBeHidden();
	await expect( bell ).toBeFocused();
	await expect( bell ).toHaveAttribute( 'aria-expanded', 'false' );
} );

test( 'focus is trapped inside the open drawer', async ( { page } ) => {
	await page.goto( OVERVIEW );
	await page.locator( '[data-corex-notification-bell]' ).click();

	const dialog = page.locator(
		'.corex-notification-drawer__panel[role="dialog"]'
	);
	await expect( dialog ).toBeVisible();

	// Tab several times; focus must remain within the dialog, never escaping to the page chrome.
	for ( let step = 0; step < 6; step++ ) {
		await page.keyboard.press( 'Tab' );
		const inside = await dialog.evaluate( ( panel ) =>
			panel.contains( document.activeElement )
		);
		expect( inside ).toBe( true );
	}
} );

test( 'the Notifications screen renders its views and switches the active tab', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=corex-notifications' );

	const views = page.locator( '.corex-notifications-screen__views' );
	await expect( views ).toBeVisible();

	const attention = page.locator( '.corex-notifications-screen__view', {
		hasText: 'Requires attention',
	} );
	await attention.click();
	await expect( attention ).toHaveAttribute( 'aria-current', 'true' );
} );
