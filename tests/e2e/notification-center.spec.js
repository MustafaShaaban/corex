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
			panel.contains( panel.ownerDocument.activeElement )
		);
		expect( inside ).toBe( true );
	}
} );

test( 'the toolbar entry and the shell bell never appear at once', async ( {
	page,
} ) => {
	// Off a CoreX screen the admin-bar node carries the count; the shell header (and its bell)
	// is not rendered there at all.
	await page.goto( '/wp-admin/index.php' );
	await expect(
		page.locator( '#wp-admin-bar-corex-notifications a' )
	).toHaveAttribute( 'href', /page=corex-notifications/ );
	await expect(
		page.locator( '[data-corex-notification-bell]' )
	).toHaveCount( 0 );

	// On a CoreX screen the shell bell owns the count, so the toolbar node stands down —
	// a manager must never see two competing unread counts (FR-017).
	await page.goto( OVERVIEW );
	await expect(
		page.locator( '[data-corex-notification-bell]' )
	).toBeVisible();
	await expect(
		page.locator( '#wp-admin-bar-corex-notifications' )
	).toHaveCount( 0 );
} );

test( 'the drawer keeps its logical layout in light, dark, and RTL', async ( {
	page,
} ) => {
	await page.goto( OVERVIEW );
	await page.locator( '[data-corex-notification-bell]' ).click();

	const panel = page.locator(
		'.corex-notification-drawer__panel[role="dialog"]'
	);
	await expect( panel ).toBeVisible();

	// The drawer paints from shell tokens, so both themes must resolve — a drawer that read a
	// hardcoded colour would be identical in light and dark.
	const shell = page.locator( '.corex-admin' );
	const readSurface = ( theme ) =>
		shell.evaluate( ( element, mode ) => {
			element.setAttribute( 'data-corex-theme', mode );
			const panelElement = element.ownerDocument.querySelector(
				'.corex-notification-drawer__panel'
			);
			return element.ownerDocument.defaultView
				.getComputedStyle( panelElement )
				.getPropertyValue( 'background-color' );
		}, theme );

	expect( await readSurface( 'light' ) ).not.toBe(
		await readSurface( 'dark' )
	);

	// The panel is pinned with `inset-inline-end`, so RTL must flip it to the other edge on its
	// own — no direction-specific rule anywhere.
	// Measure against the layout viewport (not Playwright's window size) — that is what a
	// fixed-position element is actually pinned to.
	const layoutWidth = await page.evaluate(
		() => document.documentElement.clientWidth
	);
	const ltr = await panel.boundingBox();
	expect( ltr.x + ltr.width ).toBeGreaterThan( layoutWidth - 2 );

	await page
		.locator( 'html' )
		.evaluate( ( root ) => root.setAttribute( 'dir', 'rtl' ) );
	await expect( panel ).toHaveCSS( 'direction', 'rtl' );
	const rtl = await panel.boundingBox();
	expect( rtl.x ).toBeLessThan( 2 );
} );

test( 'opening the drawer adds no horizontal overflow at a mobile viewport', async ( {
	page,
} ) => {
	await page.setViewportSize( { width: 375, height: 812 } );
	await page.goto( OVERVIEW );

	// Compare against the screen's own baseline rather than an absolute "no overflow" claim —
	// the assertion under test is that the drawer contains itself, not that WP admin is perfect.
	const baseline = await page.evaluate(
		() => document.documentElement.scrollWidth
	);

	await page.locator( '[data-corex-notification-bell]' ).click();
	await expect(
		page.locator( '.corex-notification-drawer__panel[role="dialog"]' )
	).toBeVisible();

	const metrics = await page.evaluate( () => {
		const root = document.documentElement;
		const panel = document.querySelector(
			'.corex-notification-drawer__panel'
		);
		return {
			scrollWidth: root.scrollWidth,
			clientWidth: root.clientWidth,
			panelWidth: panel.getBoundingClientRect().width,
		};
	} );

	expect( metrics.scrollWidth ).toBeLessThanOrEqual( baseline );
	expect( metrics.panelWidth ).toBeLessThanOrEqual( metrics.clientWidth );
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

	// The full FR-018 set must be present — the three added last (assigned to me, updates,
	// history) each depend on a real server-side filter, so a missing one means a filter regressed
	// rather than only a label being absent.
	for ( const label of [
		'Inbox',
		'Requires attention',
		'Assigned to me',
		'Updates',
		'History',
	] ) {
		await expect(
			page.locator( '.corex-notifications-screen__view', { hasText: label } )
		).toBeVisible();
	}

	const history = page.locator( '.corex-notifications-screen__view', {
		hasText: 'History',
	} );
	await history.click();
	await expect( history ).toHaveAttribute( 'aria-current', 'true' );
	// A view that errored would render the error state instead of a list/empty state.
	await expect(
		page.locator( '.corex-notifications-screen__state[role="alert"]' )
	).toHaveCount( 0 );

	// The Preferences tab swaps the list for the per-category toggle panel, with mandatory
	// categories (security) rendered disabled so a user can never mute a required notification.
	await page
		.locator( '.corex-notifications-screen__view', {
			hasText: 'Preferences',
		} )
		.click();
	const prefs = page.locator( '.corex-notifications-prefs' );
	await expect( prefs ).toBeVisible();
	await expect(
		prefs
			.locator( '.corex-notifications-prefs__row', {
				hasText: 'security',
			} )
			.locator( 'input[type="checkbox"]' )
	).toBeDisabled();
} );
