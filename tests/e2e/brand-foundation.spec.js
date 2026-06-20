const path = require( 'node:path' );
const { pathToFileURL } = require( 'node:url' );
const { test, expect } = require( '@playwright/test' );

const fixtureUrl = pathToFileURL(
	path.join( __dirname, 'fixtures/brand-foundation.html' )
).href;

test.beforeEach( async ( { page } ) => page.goto( fixtureUrl ) );

test( 'supports complete light and dark surfaces', async ( { page } ) => {
	const surface = page.getByTestId( 'surface' );
	await expect( surface ).toBeVisible();
	await page
		.locator( 'html' )
		.evaluate( ( root ) => root.setAttribute( 'data-mode', 'dark' ) );
	await expect( surface ).toHaveCSS( 'background-color', 'rgb(16, 42, 76)' );
} );

test( 'keeps focus visible and content usable at 200 percent zoom', async ( {
	page,
} ) => {
	await page.setViewportSize( { width: 640, height: 720 } );
	await page.locator( 'body' ).evaluate( ( body ) => {
		body.style.zoom = '2';
	} );
	const button = page.getByRole( 'button', { name: 'Focusable action' } );
	await button.focus();
	await expect( button ).toBeFocused();
	const fitsViewport = await page.evaluate(
		() =>
			document.documentElement.scrollWidth <=
			document.documentElement.clientWidth
	);
	expect( fitsViewport ).toBe( true );
} );

test( 'preserves rtl and mixed-script isolation hooks', async ( { page } ) => {
	await page
		.locator( 'html' )
		.evaluate( ( root ) => root.setAttribute( 'dir', 'rtl' ) );
	await expect( page.getByTestId( 'arabic' ) ).toHaveAttribute(
		'dir',
		'rtl'
	);
	await expect( page.getByTestId( 'arabic' ) ).toHaveCSS(
		'direction',
		'rtl'
	);
	await expect( page.getByTestId( 'mixed' ).locator( 'bdi' ) ).toHaveCount(
		1
	);
	await expect( page.locator( 'main' ) ).toHaveCSS( 'max-width', '768px' );
} );

test( 'honors reduced motion and forced colors media contracts', async ( {
	page,
} ) => {
	await page.emulateMedia( {
		reducedMotion: 'reduce',
		forcedColors: 'active',
	} );
	const button = page.getByRole( 'button', { name: 'Focusable action' } );
	await expect( button ).toHaveCSS( 'animation-name', 'none' );
	await button.focus();
	await expect( button ).toBeFocused();
	await expect( button ).toHaveCSS( 'outline-style', 'solid' );
} );
