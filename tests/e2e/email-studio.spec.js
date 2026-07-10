/**
 * Email Studio visual/interaction smoke (spec 068: T061, SC-010).
 * Environment-gated by the shared Playwright config and authenticated storage state.
 */

const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

test.beforeEach( async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=corex-email-studio' );
	await expect(
		page.getByRole( 'heading', { name: 'CoreX Email Studio' } )
	).toBeVisible();
	await expect( page.getByText( 'Loading Email Studio…' ) ).toBeHidden();
} );

test( 'loads every functional section without console errors', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	const sections = [
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

	for ( const section of sections ) {
		await expect(
			page.getByRole( 'button', { name: section, exact: true } )
		).toBeVisible();
	}

	await page
		.getByRole( 'button', { name: 'Templates', exact: true } )
		.click();
	await expect( page.getByLabel( 'New template slug' ) ).toBeVisible();
	await expect( page.getByLabel( 'Name', { exact: true } ) ).toBeVisible();

	await page.getByRole( 'button', { name: 'Layouts', exact: true } ).click();
	await expect(
		page.getByRole( 'button', {
			name: 'Revise transactional',
			exact: true,
		} )
	).toBeVisible();

	await page.getByRole( 'button', { name: 'Routing', exact: true } ).click();
	await expect( page.getByLabel( 'Reply-to source' ) ).toBeVisible();

	await page
		.getByRole( 'button', { name: 'Variables', exact: true } )
		.click();
	await expect(
		page.getByRole( 'button', {
			name: 'Insert user.name in template',
			exact: true,
		} )
	).toBeVisible();

	await page.getByRole( 'button', { name: 'Preview', exact: true } ).click();
	const preview = page.getByTitle( 'Sandboxed email preview' );
	await expect( preview ).toBeVisible();
	await expect( preview ).toHaveAttribute( 'sandbox', '' );

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'switches light dark and RTL tokens without losing logical layout', async ( {
	page,
} ) => {
	const shell = page.locator( '.corex-admin' );
	const light = await shell.evaluate( ( element ) => {
		element.setAttribute( 'data-corex-theme', 'light' );
		return element.ownerDocument.defaultView
			.getComputedStyle( element )
			.getPropertyValue( '--corex-admin-text' );
	} );
	const dark = await shell.evaluate( ( element ) => {
		element.setAttribute( 'data-corex-theme', 'dark' );
		return element.ownerDocument.defaultView
			.getComputedStyle( element )
			.getPropertyValue( '--corex-admin-text' );
	} );

	expect( light ).not.toBe( dark );
	await page
		.locator( 'html' )
		.evaluate( ( root ) => root.setAttribute( 'dir', 'rtl' ) );
	await expect(
		page.getByRole( 'navigation', { name: 'Email Studio sections' } )
	).toHaveCSS( 'direction', 'rtl' );
} );

test( 'contains mobile overflow inside the intentional tab rail', async ( {
	page,
} ) => {
	await page.setViewportSize( { width: 375, height: 812 } );

	const dimensions = await page.evaluate( () => {
		const root = document.documentElement;
		const tabs = document.querySelector( '.corex-email-app__tabs' );
		return {
			pageFits: root.scrollWidth <= root.clientWidth,
			tabsScroll: tabs.scrollWidth > tabs.clientWidth,
		};
	} );

	expect( dimensions.pageFits ).toBe( true );
	expect( dimensions.tabsScroll ).toBe( true );
} );
