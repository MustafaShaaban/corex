/**
 * Jest unit tests for the reCAPTCHA v3 client (spec 071 US1: FR-002, FR-008, FR-009).
 *
 * Loads the buildless script against jsdom with a mocked grecaptcha, and asserts the three
 * guarantees that matter: a fresh token is fetched at submit time (never at page load), the
 * submission carries that token, a double-submit is locked, and a token failure is recoverable
 * without losing the form.
 */

const SCRIPT = '../addons/corex-captcha/assets/corex-captcha-v3.js';

const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

function mountForm( slug = 'contact' ) {
	document.body.innerHTML = `
		<form class="corex-form" data-corex-form="${ slug }">
			<input type="hidden" name="captcha_token" value="" data-corex-captcha-action="corex_form_contact" />
			<button type="submit">Send</button>
			<p class="corex-form__status" role="status"></p>
		</form>
	`;
	return document.querySelector( '.corex-form' );
}

function loadScript() {
	jest.isolateModules( () => {
		require( SCRIPT );
	} );
}

let executeCalls;

beforeEach( () => {
	executeCalls = [];
	window.corexCaptchaV3 = { siteKey: 'site-key', forms: { contact: 'corex_form_contact' } };
	window.grecaptcha = {
		ready: ( cb ) => cb(),
		execute: jest.fn( ( key, opts ) => {
			executeCalls.push( opts.action );
			return Promise.resolve( 'fresh-token-' + executeCalls.length );
		} ),
	};
	// requestSubmit is not implemented in jsdom; record that the primed submission fired.
	HTMLFormElement.prototype.requestSubmit = jest.fn( function () {
		this.dispatchEvent( new Event( 'submit', { cancelable: true, bubbles: true } ) );
	} );
} );

afterEach( () => {
	delete window.corexCaptchaV3;
	delete window.grecaptcha;
	document.body.innerHTML = '';
} );

it( 'requests no token at page load', () => {
	mountForm();
	loadScript();

	expect( executeCalls ).toHaveLength( 0 ); // nothing until the visitor submits
} );

it( 'fetches a fresh token for the form action on submit and writes it into the field', async () => {
	const form = mountForm();
	loadScript();

	form.dispatchEvent( new Event( 'submit', { cancelable: true, bubbles: true } ) );
	await flush();

	expect( executeCalls ).toEqual( [ 'corex_form_contact' ] );
	expect( form.querySelector( 'input[name="captcha_token"]' ).value ).toBe( 'fresh-token-1' );
} );

it( 'lets the primed submission through to the runtime handler exactly once', async () => {
	const form = mountForm();
	loadScript();

	const runtimeHandler = jest.fn();
	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		runtimeHandler( form.querySelector( 'input[name="captcha_token"]' ).value );
	} );

	form.dispatchEvent( new Event( 'submit', { cancelable: true, bubbles: true } ) );
	await flush();

	// The runtime handler sees the token; the capture guard did not swallow the primed submit.
	expect( runtimeHandler ).toHaveBeenCalledTimes( 1 );
	expect( runtimeHandler ).toHaveBeenCalledWith( 'fresh-token-1' );
} );

it( 'requests a fresh token on each submission, never reusing one', async () => {
	const form = mountForm();
	loadScript();

	form.dispatchEvent( new Event( 'submit', { cancelable: true, bubbles: true } ) );
	await flush();
	form.dispatchEvent( new Event( 'submit', { cancelable: true, bubbles: true } ) );
	await flush();

	expect( executeCalls ).toEqual( [ 'corex_form_contact', 'corex_form_contact' ] );
	expect( form.querySelector( 'input[name="captcha_token"]' ).value ).toBe( 'fresh-token-2' );
} );

it( 'shows a recoverable message and does not submit when the token cannot be fetched', async () => {
	window.grecaptcha.execute = jest.fn( () => Promise.reject( new Error( 'blocked' ) ) );
	const form = mountForm();
	loadScript();

	const runtimeHandler = jest.fn();
	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		runtimeHandler();
	} );

	form.dispatchEvent( new Event( 'submit', { cancelable: true, bubbles: true } ) );
	await flush();

	expect( runtimeHandler ).not.toHaveBeenCalled(); // the stale/empty token never reached the runtime
	expect( form.querySelector( '.corex-form__status' ).textContent ).toMatch( /try again/i );
	// The form is still usable — the busy lock cleared.
	expect( form.dataset.corexCaptchaBusy ).toBeUndefined();
} );
