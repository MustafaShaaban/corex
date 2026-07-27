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
	await expect(
		page.getByRole( 'heading', { name: 'CoreX Overview' } )
	).toBeVisible();

	// Real projections — no fabricated placeholders.
	await expect( page.getByText( 'Launch readiness' ) ).toBeVisible();
	await expect( page.locator( '.corex-overview__tile' ) ).toHaveCount( 4 );
	await expect( page.getByText( 'Read-only' ) ).toHaveCount( 0 );
	await expect(
		page.getByText( 'once event logging is available' )
	).toHaveCount( 0 );
	// The recent-activity card shows either real events or the honest empty state, never the
	// old "logging not available" placeholder.
	await expect(
		page.getByRole( 'heading', { name: 'Recent activity' } )
	).toBeVisible();

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
		.evaluateAll( ( els ) =>
			els.map( ( el ) => el.getBoundingClientRect().x )
		);

	// One row of four: four distinct offsets, evenly pitched.
	expect( new Set( tiles.map( Math.round ) ).size ).toBe( 4 );
	const pitches = tiles.slice( 1 ).map( ( x, i ) => x - tiles[ i ] );
	for ( const pitch of pitches ) {
		expect( Math.abs( pitch - pitches[ 0 ] ) ).toBeLessThan( 2 );
	}
} );

test( 'Add-ons lists real packages with truthful summary counts', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-addons' );
	await expect(
		page.getByRole( 'heading', { name: 'CoreX Add-ons' } )
	).toBeVisible();

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
		await expect(
			page.locator( '.corex-admin__eyebrow' ).first()
		).toContainText( label );
	}
} );

const OVERFLOW_WIDTHS = [ 375, 768, 1024, 1440 ];
const OVERFLOW_DIRECTIONS = [ 'ltr', 'rtl' ];

/**
 * How far the loaded page scrolls sideways at each width and direction, in CSS pixels.
 *
 * Measured on one page load: resizing and flipping `dir` re-run the cascade without a
 * navigation, and reading `scrollWidth` forces the layout that makes the value current. The
 * same approach as forms-flow.spec.js and data-management.spec.js — and the reason this test
 * costs 13 navigations rather than 104, which is what put it over the 60s budget on CI.
 *
 * @param {import('@playwright/test').Page} page The already-loaded page to measure.
 * @return {Promise<Object>} Overflow in px, keyed `<dir>@<width>`.
 */
async function overflowByViewport( page ) {
	const measured = {};

	for ( const dir of OVERFLOW_DIRECTIONS ) {
		await page
			.locator( 'html' )
			.evaluate(
				( root, value ) => root.setAttribute( 'dir', value ),
				dir
			);

		for ( const width of OVERFLOW_WIDTHS ) {
			await page.setViewportSize( { width, height: 900 } );
			measured[ `${ dir }@${ width }px` ] = await page.evaluate( () => {
				const root = document.documentElement;
				return Math.max( 0, root.scrollWidth - root.clientWidth );
			} );
		}
	}

	return measured;
}

/*
 * A CoreX screen may not scroll sideways any further than stock wp-admin already does.
 *
 * The absolute assertion — `scrollWidth <= clientWidth` — is the one you would reach for, and it
 * is the one that cannot be made to pass. At 375px with dir=rtl, wp-admin's own Dashboard,
 * Settings and Plugins screens scroll by exactly 1px, from core's visually-hidden admin-bar chrome
 * (`#wpadminbar` items carrying the `position:absolute; margin:-1px; width:1px` recipe). Their
 * static position in RTL sits one pixel outside the inline edge, and left-of-origin content
 * extends scrollWidth in RTL where it does not in LTR. Hiding `#wpadminbar` takes every one of
 * those screens — and every CoreX screen — to 0. Nothing in the CoreX shell contributes to it,
 * and no CoreX rule can remove it without overriding core admin chrome site-wide.
 *
 * So the honest claim is the comparative one, and it is the one worth guarding: whatever core
 * costs, CoreX adds nothing on top. That fails the moment a CoreX rule genuinely overflows, which
 * is what this is for — and it does not fail for a pixel we did not cause and cannot fix.
 *
 * PROGRESS.md recorded this as "the CoreX admin shell overflows by 1px in RTL on every CoreX
 * screen". Measured against stock wp-admin, that attribution was wrong; the reading was not.
 */
test( 'no CoreX route scrolls sideways any further than stock wp-admin', async ( {
	page,
} ) => {
	// Measured per viewport and per direction, not assumed — core's chrome changes at wp-admin's
	// own breakpoints, so a single number would be the wrong baseline at three of the four widths.
	await page.goto( '/wp-admin/index.php' );
	const baseline = await overflowByViewport( page );

	for ( const [ slug ] of ROUTES ) {
		await page.goto( `/wp-admin/admin.php?page=${ slug }` );
		const actual = await overflowByViewport( page );

		for ( const viewport of Object.keys( baseline ) ) {
			expect(
				actual[ viewport ],
				`${ slug } at ${ viewport }: ${ actual[ viewport ] }px of horizontal scroll against a wp-admin baseline of ${ baseline[ viewport ] }px`
			).toBeLessThanOrEqual( baseline[ viewport ] );
		}
	}
} );
