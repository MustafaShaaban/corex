/**
 * Unit tests for the captcha "Test verification" admin module (spec 053 US3). The module is
 * a no-build vanilla IIFE that also exports its pure helpers for test. Requiring it in jsdom
 * with no secret field and no window.corexCaptcha is side-effect-free (it returns early), so the
 * helpers that decide the request URL and the *displayable* result are tested alone.
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
			status: '',
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
			status: '',
		} );
	} );

	it( 'carries the line Settings keeps for the latest test, and nothing else', () => {
		// Spec 119, US-SET-03: the answer and the "Last tested" line, never a key.
		const view = resultFromEnvelope( {
			ok: false,
			message: 'Wrong key.',
			details: {
				status: 'Last test failed on 23 October 2026: the provider refused the secret key',
				secret: 'SHOULD-NEVER-LEAK',
			},
		} );
		expect( view.status ).toBe(
			'Last test failed on 23 October 2026: the provider refused the secret key'
		);
		expect( JSON.stringify( view ) ).not.toContain( 'SHOULD-NEVER-LEAK' );
	} );

	it( 'falls back to an empty message on a malformed/absent envelope', () => {
		expect( resultFromEnvelope( null ) ).toEqual( {
			ok: false,
			message: '',
			status: '',
		} );
		expect( resultFromEnvelope( {} ) ).toEqual( {
			ok: false,
			message: '',
			status: '',
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
		// The field as the Settings form draws it (spec 114: the id it really has).
		document.body.innerHTML =
			'<table><tr><td><input id="captcha_secret" name="captcha_secret" type="password" /></td></tr></table>';
		window.corexCaptcha = {
			restUrl: 'https://x.test/wp-json/corex/v1',
			nonce: 'n',
			ready: true,
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

/*
 * Spec 114, US-SAFE-16: the button looked for a field id no page has, so it never appeared, and
 * the test passed because it drew that id itself. It now finds the field the form renders, sits
 * under it, and is offered only when there are keys to test.
 */
describe( 'where the "Test verification" button is offered', () => {
	function load( ready ) {
		document.body.innerHTML =
			'<table><tr><td><input id="captcha_secret" name="captcha_secret" type="password" /></td></tr></table>';
		window.corexCaptcha = {
			restUrl: 'https://x.test/wp-json/corex/v1',
			nonce: 'n',
			ready,
		};
		window.Corex = { api: { post: () => new Promise( () => {} ) } };
		jest.isolateModules( () => {
			require( '../captcha-admin.js' );
		} );
	}

	afterEach( () => {
		document.body.innerHTML = '';
		delete window.corexCaptcha;
		delete window.Corex;
	} );

	it( 'finds the secret key field by the id the Settings form gives it', () => {
		expect( require( '../captcha-admin.js' ).SECRET_FIELD_ID ).toBe(
			'captcha_secret'
		);
	} );

	it( 'sits under the secret key field, with a live region for the answer, when keys are saved', () => {
		load( true );

		const cell = document
			.getElementById( 'captcha_secret' )
			.closest( 'td' );
		const button = cell.querySelector( '.corex-captcha-test__button' );
		const result = cell.querySelector( '.corex-captcha-test__result' );

		expect( button.textContent ).toBe( 'Test verification' );
		expect( button.type ).toBe( 'button' );
		expect( result.getAttribute( 'role' ) ).toBe( 'status' );
		expect( result.getAttribute( 'aria-live' ) ).toBe( 'polite' );
	} );

	it( 'is not offered without saved keys, and the field says what is needed', () => {
		load( false );

		const cell = document
			.getElementById( 'captcha_secret' )
			.closest( 'td' );
		expect(
			cell.querySelector( '.corex-captcha-test__button' )
		).toBeNull();
		expect(
			cell.querySelector( '.corex-captcha-test__needed' ).textContent
		).toBe(
			'Save a site key and a secret key for this provider, then test them here.'
		);
	} );
} );
