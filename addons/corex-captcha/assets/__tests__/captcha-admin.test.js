/**
 * Unit tests for the captcha "Test verification" admin module (spec 053 US3). The module is
 * a no-build vanilla IIFE that also exports its two pure helpers for test. Requiring it in
 * jsdom is side-effect-free (no #captcha.secret anchor + no window.corexCaptcha → it returns
 * early), so we test the helpers that decide the request URL and the *displayable* result.
 *
 * The critical assertion is FR-014: the result the UI renders is built from the envelope's
 * ok + message only — never any other field — so a secret can never reach the screen.
 */
const { testEndpoint, resultFromEnvelope } = require( '../captcha-admin.js' );

describe( 'testEndpoint', () => {
	it( 'builds the captcha test route from the REST base, with or without a trailing slash', () => {
		expect( testEndpoint( 'https://x.test/wp-json/corex/v1' ) ).toBe(
			'https://x.test/wp-json/corex/v1/captcha/test'
		);
		expect( testEndpoint( 'https://x.test/wp-json/corex/v1/' ) ).toBe(
			'https://x.test/wp-json/corex/v1/captcha/test'
		);
	} );
} );

describe( 'resultFromEnvelope', () => {
	it( 'maps ok + message and never carries any other field (no secret can leak)', () => {
		const view = resultFromEnvelope( {
			ok: false,
			error: 'missing_keys',
			message: 'Add your captcha secret key.',
			data: { secret: 'SHOULD-NEVER-LEAK', site_key: 'ALSO-NOT' },
		} );
		expect( view ).toEqual( {
			ok: false,
			message: 'Add your captcha secret key.',
		} );
		expect( JSON.stringify( view ) ).not.toContain( 'SHOULD-NEVER-LEAK' );
		expect( JSON.stringify( view ) ).not.toContain( 'ALSO-NOT' );
	} );

	it( 'treats a success envelope as ok', () => {
		expect(
			resultFromEnvelope( { ok: true, message: 'Captcha verified.' } )
		).toEqual( {
			ok: true,
			message: 'Captcha verified.',
		} );
	} );

	it( 'falls back to an empty message on a malformed/absent envelope', () => {
		expect( resultFromEnvelope( null ) ).toEqual( {
			ok: false,
			message: '',
		} );
		expect( resultFromEnvelope( {} ) ).toEqual( {
			ok: false,
			message: '',
		} );
	} );
} );

/**
 * The button itself (spec 108): while its test is on its way it is the admin's one working
 * control, and it cannot ask twice. It was disabled, with a sentence beside it, and looked like
 * a button that had been switched off.
 */
describe( 'the "Test verification" button while its request is out', () => {
	let answer;
	let sent;

	beforeEach( () => {
		sent = 0;
		document.body.innerHTML = '<input id="captcha.secret" />';
		window.corexCaptcha = {
			restUrl: 'https://x.test/wp-json/corex/v1',
			nonce: 'n',
		};
		window.Corex = {
			api: {
				post: () => {
					sent += 1;
					return new Promise( ( resolve ) => {
						answer = resolve;
					} );
				},
			},
		};
		jest.isolateModules( () => {
			require( '../captcha-admin.js' );
		} );
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		delete window.corexCaptcha;
		delete window.Corex;
	} );

	it( 'works, asks once, and is itself again when the provider has answered', async () => {
		const button = document.querySelector( '.corex-captcha-test__button' );

		button.click();
		button.click();

		expect( sent ).toBe( 1 );
		expect( button.textContent ).toBe( 'Test verification' );
		expect( button.getAttribute( 'aria-busy' ) ).toBe( 'true' );
		expect( button.dataset.corexWorking ).toBe( 'true' );

		answer( { envelope: { ok: true, message: 'Captcha verified.' } } );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( button.disabled ).toBe( false );
		expect( button.hasAttribute( 'data-corex-working' ) ).toBe( false );
		expect(
			document.querySelector( '.corex-captcha-test__result' ).textContent
		).toBe( 'Captcha verified.' );
	} );
} );
