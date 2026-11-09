/**
 * Corex E2E: Settings → Assistant (spec 129 slice 2).
 *
 * The page is in four parts. Under Services the owner rearranges the order the chat asks the
 * services in with Move up and Move down, the save keeps it, and the status table follows it; the paid
 * services start off. Under Booking the hours are shown only while "Offer call times" is on. The order
 * and the status panel have no WCAG A or AA violation.
 *
 * Signed in as the suite's administrator. The order is put back as it was at the end.
 *
 * ENVIRONMENT-GATED: needs the site at COREX_BASE_URL with corex-assistant active, and
 * `npx playwright install`.
 */
const { test, expect } = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;

const PAGE = '/wp-admin/admin.php?page=corex-settings-config&section=assistant';
const WCAG_TAGS = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ];

async function order( page ) {
	return page
		.locator( '[data-corex-order="assistant_order"] .corex-order__label' )
		.allTextContents();
}

async function save( page ) {
	const bar = page.locator( '[data-corex-savebar]' );
	await Promise.all( [
		page.waitForURL( /section=assistant/ ),
		bar.getByRole( 'button', { name: 'Save changes' } ).click(),
	] );
	await page.waitForLoadState( 'domcontentloaded' );
}

test( 'orders the services, keeps the order on save, and shows the hours only while booking is on', async ( {
	page,
} ) => {
	await page.goto( PAGE );
	test.skip(
		( await page.locator( '.corex-settings-addon-off' ).count() ) > 0,
		'corex-assistant is not active on this site.'
	);

	const before = await order( page );
	expect( before ).toHaveLength( 6 );
	await expect(
		page.getByRole( 'switch', { name: 'Ask OpenAI' } )
	).not.toBeChecked();
	await expect(
		page.getByRole( 'switch', { name: 'Ask Google Gemini' } )
	).toBeChecked();

	// Booking's rows follow their switch.
	const zone = page
		.locator( 'tr[data-corex-show-for="assistant.booking.enabled"]' )
		.first();
	const offer = page.getByRole( 'switch', { name: 'Offer call times' } );
	const bookingOn = await offer.isChecked();
	await expect( zone ).toBeVisible( { visible: bookingOn } );
	await offer.click( { force: true } );
	await expect( zone ).toBeVisible( { visible: ! bookingOn } );
	await offer.click( { force: true } );

	const { violations } = await new AxeBuilder( { page } )
		// What slice 2 adds. The help lines every Settings page shares are not this spec's.
		.include( '[data-corex-order="assistant_order"]' )
		.include( '[data-corex-assistant-panel]' )
		.withTags( WCAG_TAGS )
		.analyze();
	expect(
		violations.map(
			( violation ) => `${ violation.id }: ${ violation.nodes.length }`
		)
	).toEqual( [] );

	// The second service moves to the top, and says where it went.
	const second = before[ 1 ];
	await page.getByRole( 'button', { name: `Move ${ second } up` } ).click();
	await expect( page.locator( '[data-corex-order-status]' ) ).toHaveText(
		`${ second } moved to place 1 of 6.`
	);
	await expect(
		page.getByRole( 'button', { name: `Move ${ second } down` } )
	).toBeFocused();
	expect( ( await order( page ) )[ 0 ] ).toBe( second );

	await save( page );
	try {
		expect( ( await order( page ) ).slice( 0, 2 ) ).toEqual( [
			second,
			before[ 0 ],
		] );
		await expect(
			page.locator( '.corex-assistant-panel__services tbody tr' ).first()
		).toContainText( second );
	} finally {
		// Put the order back as it was.
		await page
			.getByRole( 'button', { name: `Move ${ second } down` } )
			.click();
		await save( page );
	}
	expect( await order( page ) ).toEqual( before );
} );
