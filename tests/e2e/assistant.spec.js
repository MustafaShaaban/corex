/**
 * Corex E2E: the CoreX Assistant on a public page (spec 129).
 *
 * Off, it puts nothing on the page. On (the `corex-e2e-assistant.php` fixture answers as a site with it
 * on and one service keyed, with a stand-in model), the page carries the button and its own small
 * files only; the chat arrives on first use. A question is answered with a link to this site and none
 * elsewhere, a price brings the hand-off form, the open chat has no WCAG A or AA violation, and Escape
 * closes it with focus back on the button.
 *
 * Read as a visitor: no session.
 *
 * ENVIRONMENT-GATED: needs the site at COREX_BASE_URL + `npx playwright install`.
 */
const { test, expect } = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;

const PAGE = '/sample-page/';
const WCAG_TAGS = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ];

test.use( { storageState: { cookies: [], origins: [] } } );

test( 'puts nothing on the page while it is off', async ( { page } ) => {
	const requested = [];
	page.on( 'request', ( request ) => requested.push( request.url() ) );
	await page.goto( PAGE );

	await expect( page.locator( '[data-corex-assistant]' ) ).toHaveCount( 0 );
	expect(
		requested.filter( ( url ) => url.includes( '/corex-assistant/' ) )
	).toEqual( [] );
} );

test.describe( 'with the assistant on', () => {
	test.beforeEach( async ( { page } ) => {
		await page.setExtraHTTPHeaders( { 'X-Corex-E2E-Assistant': 'on' } );
	} );

	test( 'answers from the site, hands off to the team, and closes back to its button', async ( {
		page,
	} ) => {
		const requested = [];
		page.on( 'request', ( request ) => requested.push( request.url() ) );
		await page.goto( PAGE );

		const button = page.getByRole( 'button', { name: /^Chat with / } );
		await expect( button ).toBeVisible();
		expect(
			requested.filter( ( url ) => url.includes( '/assistant/chat.' ) )
		).toEqual( [] );

		await button.click();
		const dialog = page.getByRole( 'dialog' );
		await expect( dialog ).toBeVisible();
		await expect( dialog.getByRole( 'textbox' ) ).toBeFocused();
		expect(
			requested.some( ( url ) => url.includes( '/assistant/chat.js' ) )
		).toBe( true );

		await dialog
			.getByRole( 'button', { name: 'What do you offer?' } )
			.click();
		await expect( dialog.getByText( 'We build websites.' ) ).toBeVisible();
		const links = dialog.locator( '.corex-assistant__link' );
		await expect( links ).toHaveCount( 1 );
		await expect( links.first() ).toHaveAttribute(
			'href',
			/\/assistant-e2e\/$/
		);
		await expect( dialog ).not.toContainText( 'elsewhere.example' );

		await dialog.getByRole( 'textbox' ).fill( 'What is the price?' );
		await dialog.getByRole( 'textbox' ).press( 'Enter' );
		await expect(
			dialog.getByRole( 'heading', { name: 'Shall we contact you?' } )
		).toBeVisible();
		await expect( dialog.getByLabel( 'Your name' ) ).toBeVisible();

		const { violations } = await new AxeBuilder( { page } )
			.include( '.corex-assistant' )
			.withTags( WCAG_TAGS )
			.analyze();
		expect(
			violations.map(
				( violation ) =>
					`${ violation.id }: ${ violation.nodes.length }`
			)
		).toEqual( [] );

		await page.keyboard.press( 'Escape' );
		await expect( dialog ).toBeHidden();
		await expect( button ).toBeFocused();
		await expect( button ).toHaveAttribute( 'aria-expanded', 'false' );
	} );

	test( 'keeps the conversation when the visitor moves to another page', async ( {
		page,
	} ) => {
		await page.goto( PAGE );
		await page.getByRole( 'button', { name: /^Chat with / } ).click();
		const dialog = page.getByRole( 'dialog' );
		await dialog.getByRole( 'textbox' ).fill( 'What do you build?' );
		await dialog.getByRole( 'textbox' ).press( 'Enter' );
		await expect( dialog.getByText( 'We build websites.' ) ).toBeVisible();

		await page.goto( '/' );
		await page.getByRole( 'button', { name: /^Chat with / } ).click();

		await expect(
			page.getByRole( 'dialog' ).getByText( 'What do you build?' )
		).toBeVisible();
	} );
} );
