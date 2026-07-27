/**
 * Corex E2E — Notification Center bell + drawer (spec 072 US2, FR-016) and the screen's view
 * structure (spec 074, FR-4.2).
 *
 * Verifies the accessibility contract that jsdom cannot: the server-rendered bell in the CoreX shell
 * header opens the drawer on click, the drawer is a modal dialog, Escape closes it AND returns focus
 * to the bell, and focus stays trapped inside the panel while it is open.
 *
 * The screen specs here assert **structure**, not item content: nothing seeds notification records,
 * so an item-level assertion would be testing whatever the run happened to produce. What one
 * notification says about itself — above all that reading it never changes whether it still needs
 * action — is covered against a real DOM in
 * `plugins/corex-config/src/admin/notifications/__tests__/notificationItem.test.js`.
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

const view = ( page, label ) =>
	page.locator( '.corex-notifications-screen__view', { hasText: label } );

test( 'the Notifications screen offers the three views and switches between them', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=corex-notifications' );

	await expect(
		page.locator( '.corex-notifications-screen__views' )
	).toBeVisible();

	// Each view is a bounded server-side filter, so a missing one means a filter regressed rather
	// than only a label being absent.
	for ( const label of [
		'Action needed',
		'Updates',
		'History',
		'Preferences',
	] ) {
		await expect( view( page, label ) ).toBeVisible();
	}

	for ( const label of [ 'Action needed', 'Updates', 'History' ] ) {
		await view( page, label ).click();
		await expect( view( page, label ) ).toHaveAttribute(
			'aria-current',
			'true'
		);
		// A view that errored renders the alert state instead of a list or an empty state, so this
		// is what tells us the server-side filter behind the tab actually answered.
		await expect(
			page.locator( '.corex-notifications-screen__state[role="alert"]' )
		).toHaveCount( 0 );
	}
} );

test( 'the retired tabs are gone, and the questions they asked are filters now', async ( {
	page,
} ) => {
	// The point of spec 074's FR-4.2. "Requires attention" filtered on the actor's *unread* state,
	// so reading a production readiness blocker took it off the attention list while the blocker
	// was still true; Inbox / Assigned to me / Submissions / Security were tabs competing with the
	// only real question — does this still need me? Asserting their absence is the regression
	// guard: a revert would restore them, and every other assertion here would still pass.
	await page.goto( '/wp-admin/admin.php?page=corex-notifications' );
	await expect( view( page, 'Action needed' ) ).toBeVisible();

	for ( const retired of [
		'Inbox',
		'Requires attention',
		'Assigned to me',
	] ) {
		await expect( view( page, retired ) ).toHaveCount( 0 );
	}

	// Assignment is a refine over whichever view you are in, not a place you go.
	const assigned = page.locator( '.corex-notifications-screen__toggle' );
	await expect( assigned ).toBeVisible();
	await expect(
		assigned.locator( 'input[type="checkbox"]' )
	).not.toBeChecked();

	// Severity and category are the other two refines. They are CorexSelect, not native <select>
	// (DECISIONS #141), so they expose the combobox role rather than a listbox element.
	for ( const label of [ 'Severity', 'Category' ] ) {
		await expect(
			page.locator(
				`.corex-notifications-screen__filters [role="combobox"][aria-label="${ label }"]`
			)
		).toBeVisible();
	}
} );

test( 'Preferences swaps the list for the per-category toggles', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=corex-notifications' );
	await view( page, 'Preferences' ).click();

	const prefs = page.locator( '.corex-notifications-prefs' );
	await expect( prefs ).toBeVisible();
	// The filters belong to the list views; Preferences is not a filtered list.
	await expect(
		page.locator( '.corex-notifications-screen__filters' )
	).toHaveCount( 0 );

	// Mandatory categories render disabled, so a user can never mute a required notification.
	await expect(
		prefs
			.locator( '.corex-notifications-prefs__row', {
				hasText: 'security',
			} )
			.locator( 'input[type="checkbox"]' )
	).toBeDisabled();
} );
