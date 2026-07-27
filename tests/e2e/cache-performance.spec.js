/**
 * Corex E2E — Cache & Performance (spec 078, T033/T034).
 *
 * The unit and integration suites prove each probe. This proves the section: that an operator
 * opening it sees every layer with a real state, that a control appears only where a real operation
 * exists, and that it holds together at 375px, at 200% zoom and in RTL.
 *
 * ENVIRONMENT-GATED: needs a running site at COREX_BASE_URL and `npx playwright install`.
 */
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

const SECTION = '/wp-admin/admin.php?page=corex-operations-security&tab=cache';

/** Every layer an operator should be able to account for. */
const EXPECTED_LAYERS = [
	'Visitor browsers',
	'PHP OPcache',
	'This request’s cache',
	'Persistent object cache',
	'CoreX application cache',
	'Page cache',
	'CDN',
];

test( 'the section accounts for every caching layer', async ( { page } ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( SECTION );

	await expect( page.locator( '.corex-admin__tab.is-active' ) ).toHaveText(
		'Cache & Performance'
	);

	const names = await page
		.locator( '.corex-cache__layer-name' )
		.allInnerTexts();

	for ( const layer of EXPECTED_LAYERS ) {
		expect( names, `${ layer } is missing` ).toContain( layer );
	}

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'every layer states what it is doing, not just that it exists', async ( {
	page,
} ) => {
	await page.goto( SECTION );

	const layers = await page
		.locator( '.corex-cache__layer' )
		.evaluateAll( ( nodes ) =>
			nodes.map( ( node ) => ( {
				state: node
					.querySelector( '.corex-cache__layer-state' )
					?.textContent.trim(),
				purpose: node
					.querySelector( '.corex-cache__layer-purpose' )
					?.textContent.trim(),
			} ) )
		);

	expect( layers.length ).toBe( EXPECTED_LAYERS.length );

	for ( const layer of layers ) {
		// A state, and a plain-language purpose. Colour alone never carries meaning.
		expect( layer.state ).toBeTruthy();
		expect( layer.purpose?.length ).toBeGreaterThan( 20 );
	}
} );

test( 'a control appears only where a real operation exists', async ( {
	page,
} ) => {
	// The mandate's rule, as a test: no button that does nothing. A layer CoreX cannot act on says
	// so in words rather than showing a disabled control with no explanation.
	await page.goto( SECTION );

	const rows = await page
		.locator( '.corex-cache__layer' )
		.evaluateAll( ( nodes ) =>
			nodes.map( ( node ) => ( {
				name: node
					.querySelector( '.corex-cache__layer-name' )
					?.textContent.trim(),
				hasButton: !! node.querySelector( '.corex-cache__action' ),
				hasReason: !! node.querySelector( '.corex-cache__no-action' ),
			} ) )
		);

	for ( const row of rows ) {
		// Exactly one of the two, never both and never neither.
		expect( row.hasButton !== row.hasReason, row.name ).toBe( true );
	}

	// The layer nobody can clear must be one of the ones saying so.
	const browser = rows.find( ( row ) => row.name === 'Visitor browsers' );
	expect( browser.hasButton ).toBe( false );
} );

test( 'it says plainly that CoreX cannot clear a visitor’s browser cache', async ( {
	page,
} ) => {
	await page.goto( SECTION );

	await expect(
		page.locator( '.corex-cache__layer', { hasText: 'Visitor browsers' } )
	).toContainText( 'No website can empty' );
} );

test( 'no machine timestamp reaches the screen', async ( { page } ) => {
	// Spec 076 owns date presentation; this asserts the section uses it.
	await page.goto( SECTION );

	const text = await page.locator( '.corex-opsec__sections' ).innerText();

	expect( text ).not.toMatch( /\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/ );
} );

test( 'it holds together at 375px, at 200% zoom, and in RTL', async ( {
	page,
} ) => {
	await page.goto( SECTION );

	const overflow = async () =>
		page.evaluate( () => {
			const root = document.documentElement;
			return Math.max( 0, root.scrollWidth - root.clientWidth );
		} );

	const baseline = await overflow();

	for ( const dir of [ 'ltr', 'rtl' ] ) {
		await page
			.locator( 'html' )
			.evaluate(
				( root, value ) => root.setAttribute( 'dir', value ),
				dir
			);

		for ( const width of [ 375, 720, 1440 ] ) {
			await page.setViewportSize( { width, height: 900 } );

			await expect(
				page.locator( '.corex-cache__layers' )
			).toBeVisible();

			expect(
				await overflow(),
				`overflow at ${ width }px (${ dir })`
			).toBeLessThanOrEqual( Math.max( baseline, 1 ) );
		}
	}
} );
