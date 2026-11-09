/**
 * Corex E2E: the CoreX Assistant on a public page (spec 129).
 *
 * Off, it puts nothing on the page. On (the `corex-e2e-assistant.php` fixture answers as a site with it
 * on and one service keyed, with a stand-in model), the page carries the button and its own small
 * files only; the chat arrives on first use. A question is answered with a link to this site and none
 * elsewhere, a price brings the hand-off form, the open chat has no WCAG A or AA violation, and Escape
 * closes it with focus back on the button. With booking hours on (slice 2), the hand-off asks the way
 * to be reached first, needs what that way needs, offers times in the visitor's zone, and the request
 * comes back with the time written in both zones.
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

test.describe( 'with booking hours on', () => {
	test.use( { timezoneId: 'Asia/Riyadh' } );

	test.beforeEach( async ( { page } ) => {
		await page.setExtraHTTPHeaders( {
			'X-Corex-E2E-Assistant': 'booking',
		} );
	} );

	test( 'asks the way first, offers times in the visitor’s zone, and sends the time chosen', async ( {
		page,
	} ) => {
		await page.goto( PAGE );
		await page.getByRole( 'button', { name: /^Chat with / } ).click();
		const dialog = page.getByRole( 'dialog' );
		await dialog.getByRole( 'button', { name: /talk to someone/ } ).click();
		const form = dialog.locator( '.corex-assistant__handoff' );
		const times = form.getByRole( 'group', {
			name: 'Pick a time for the call (in your local time)',
		} );
		await expect( times ).toBeVisible();
		await expect( form.getByLabel( 'Best time to reach you' ) ).toHaveCount(
			0
		);

		// The way to be reached decides what is needed.
		const fields = await form
			.locator( ':scope > [data-field], :scope > fieldset' )
			.evaluateAll( ( nodes ) =>
				nodes.map( ( node ) => node.dataset.field || node.className )
			);
		expect( fields.slice( 0, 4 ) ).toEqual( [
			'name',
			'corex-assistant__channels',
			'phone',
			'email',
		] );
		await expect( form.getByLabel( 'Mobile number' ) ).toHaveAttribute(
			'aria-required',
			'true'
		);
		await form.getByRole( 'radio', { name: 'Email' } ).check();
		await expect(
			form.getByRole( 'textbox', { name: /^Email/ } )
		).toHaveAttribute( 'aria-required', 'true' );
		await expect( form.getByLabel( 'Mobile number' ) ).toHaveAttribute(
			'aria-required',
			'false'
		);
		await form.getByRole( 'radio', { name: 'Call' } ).check();

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

		// Every field stays inside the form, edge to edge.
		const overflow = await form.evaluate( ( node ) => {
			const edge = node.getBoundingClientRect().right;
			return [
				...node.querySelectorAll(
					'input:not([type=radio]):not([type=checkbox]):not(.corex-assistant__trap), select'
				),
			]
				.filter(
					( field ) =>
						field.getBoundingClientRect().right > edge + 0.5
				)
				.map( ( field ) => field.id );
		} );
		expect( overflow ).toEqual( [] );

		await times.locator( '.corex-assistant__day' ).nth( 1 ).click();
		await times.locator( '.corex-assistant__part' ).first().click();
		const time = times.locator( '.corex-assistant__times button' ).first();
		await time.click();
		await expect( time ).toHaveAttribute( 'aria-pressed', 'true' );

		await form.getByLabel( 'Your name' ).fill( 'Playwright Probe' );
		await form.getByLabel( 'Mobile number' ).fill( '50 123 4567' );
		const [ request, response ] = await Promise.all( [
			page.waitForRequest( /corex-assistant-callback/ ),
			page.waitForResponse( /corex-assistant-callback/ ),
			form.getByRole( 'button', { name: 'Send request' } ).click(),
		] );
		const sent = request.postDataJSON();
		const answered = await response.json();

		expect( sent.channel ).toBe( 'call' );
		expect( sent.timezone ).toBe( 'Asia/Riyadh' );
		expect( sent.slot ).toMatch( /^\d+$/ );
		expect( answered.values.slot ).toMatch(
			/^\w{3} \d{1,2} \w{3}, \d{1,2}:00 [ap]m Asia\/Riyadh = \w{3} \d{1,2} \w{3}, \d{1,2}:00 [ap]m UTC$/
		);
		await expect(
			dialog.getByText( /Thank you, Playwright Probe/ )
		).toBeVisible();
	} );
} );
