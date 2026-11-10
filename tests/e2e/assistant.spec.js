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

const { STORAGE_STATE } = require( './global-setup' );
const { wpEval } = require( './helpers' );

const PAGE = '/sample-page/';
// At example.com, so the suite's teardown knows a request for a test's own.
const SENDER = 'sara.e2e@example.com';
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

	// The callback requests a test sent, which would otherwise sit in Submissions and the Data screen for the
	// specs after this one. Deleting them frees the times they claimed.
	test.afterEach( () => {
		wpEval(
			`foreach ( get_posts( [ 'post_type' => 'corex_submission', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'corex_field_email', 'meta_value' => '${ SENDER }' ] ) as $id ) { wp_delete_post( $id, true ); }`
		);
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
		await expect(
			dialog.getByLabel( 'When should we reach you?' )
		).toBeVisible();

		// Every field ends inside its form, padding and border included; the honeypot is placed outside on
		// purpose, so people never see it.
		const overflow = await dialog
			.locator( 'form.corex-assistant__handoff' )
			.evaluate( ( form ) => {
				const edge = form.getBoundingClientRect().right;
				return [ ...form.querySelectorAll( 'input, select' ) ]
					.filter( ( field ) => field.name !== 'corex_hp' )
					.filter( ( field ) => field.getClientRects().length > 0 )
					.filter(
						( field ) =>
							field.getBoundingClientRect().right > edge + 0.5
					)
					.map( ( field ) => field.name );
			} );
		expect( overflow ).toEqual( [] );

		const { violations } = await new AxeBuilder( { page } )
			.include( '.corex-assistant' )
			.withTags( WCAG_TAGS )
			.analyze();
		expect(
			violations.map(
				( violation ) =>
					`${ violation.id }: ${ violation.nodes.map( ( node ) => node.target.join( ' ' ) ).join( ' | ' ) }`
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

	test( 'hides a time one visitor asked for from the next, and offers the open ones when it was just taken', async ( {
		context,
	} ) => {
		// Each visitor is a tab of their own, so no conversation is carried from one to the next.
		await context.setExtraHTTPHeaders( { 'X-Corex-E2E-Assistant': 'on' } );
		const offeredBy = ( picker ) =>
			picker
				.locator( 'option' )
				.evaluateAll( ( options ) =>
					options.map( ( option ) => option.value ).filter( Boolean )
				);
		const openHandoff = async ( visitor ) => {
			await visitor.goto( PAGE );
			await visitor
				.getByRole( 'button', { name: /^Chat with / } )
				.click();
			const dialog = visitor.getByRole( 'dialog' );
			await dialog
				.getByRole( 'button', { name: /talk to someone/ } )
				.click();
			const picker = dialog.getByLabel( 'When should we reach you?' );
			await expect( picker ).toBeVisible();
			return { dialog, picker, times: await offeredBy( picker ) };
		};
		const send = async ( { dialog, picker }, slot ) => {
			await dialog.getByLabel( 'Your name' ).fill( 'Sara E2E' );
			await dialog.getByLabel( 'Mobile number' ).fill( '50 123 4567' );
			await dialog
				.getByRole( 'textbox', { name: /^Email/ } )
				.fill( SENDER );
			await picker.selectOption( slot );
			await dialog
				.getByRole( 'button', { name: 'Send request' } )
				.click();
		};

		// The first visitor asks for the second time on offer: the first is the coming hour, which could pass
		// before the request is sent.
		const first = await openHandoff( await context.newPage() );
		const asked = first.times[ 1 ];
		await send( first, asked );
		await expect(
			first.dialog.getByText( /^Thank you, Sara E2E/ )
		).toBeVisible();

		// The next visitor is not offered it.
		const next = await openHandoff( await context.newPage() );
		expect( next.times.length ).toBeGreaterThan( 0 );
		expect( next.times ).not.toContain( asked );

		// A visitor whose list was drawn before that request picks it anyway: the site refuses it, and
		// the chat fetches the times again.
		const lateTab = await context.newPage();
		let stale = true;
		await lateTab.route(
			'**/corex/v1/assistant/slots*',
			async ( route ) => {
				if ( ! stale ) {
					return route.continue();
				}
				stale = false;
				const fresh = await route.fetch();
				const body = await fresh.json();
				return route.fulfill( {
					response: fresh,
					json: {
						...body,
						slots: [ Number( asked ), ...body.slots ],
					},
				} );
			}
		);
		const late = await openHandoff( lateTab );
		expect( late.times ).toContain( asked );
		await send( late, asked );

		await expect( late.dialog.getByRole( 'alert' ) ).toHaveText(
			'Someone has just asked for that time. Please choose another.'
		);
		await expect( late.picker ).toBeFocused();
		await expect( late.picker ).toHaveValue( '' );
		await expect( late.picker ).toHaveAttribute( 'aria-invalid', 'true' );
		const offered = await offeredBy( late.picker );
		expect( offered.length ).toBeGreaterThan( 0 );
		expect( offered ).not.toContain( asked );
		await expect( late.dialog.getByText( /^Thank you/ ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'the owner’s screen', () => {
	test.use( { storageState: STORAGE_STATE } );

	test( 'lists the services with the paid ones off, and has no WCAG A or AA violation', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=corex-assistant' );

		await expect(
			page.getByRole( 'heading', { name: 'Assistant', level: 1 } )
		).toBeVisible();
		await expect( page.getByLabel( 'OpenAI on' ) ).not.toBeChecked();
		await expect(
			page.getByLabel( 'Anthropic Claude on' )
		).not.toBeChecked();
		await expect( page.getByLabel( 'Groq on' ) ).toBeChecked();

		const { violations } = await new AxeBuilder( { page } )
			.include( '.corex-assistant-screen' )
			// The shell's primary button is a known contrast failure on every CoreX screen
			// (tests/e2e/quality/known-failures.json, US-BRD-02), not this screen's.
			.exclude( '.corex-assistant-screen__save' )
			.withTags( WCAG_TAGS )
			.analyze();
		expect(
			violations.map(
				( violation ) =>
					`${ violation.id }: ${ violation.nodes.map( ( node ) => node.target.join( ' ' ) ).join( ' | ' ) }`
			)
		).toEqual( [] );
	} );
} );
