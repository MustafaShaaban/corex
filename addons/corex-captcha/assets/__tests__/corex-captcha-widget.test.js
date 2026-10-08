/**
 * Jest tests for the Turnstile and hCaptcha widget script (spec 104 US4; issue #264).
 *
 * The provider is stood in for: an object with the two calls the script makes, `render` and
 * `reset`, as each provider documents them. What is under test is CoreX's side of it: where the
 * token goes, what happens to a submission made before the visitor has passed, and that a spent
 * token is not sent twice.
 */

const SCRIPT = '../corex-captcha-widget.js';

function loadScript() {
	jest.isolateModules( () => {
		require( SCRIPT );
	} );
}

/** A provider that records what it was asked to render and lets a test answer as the visitor. */
function standInProvider() {
	const provider = {
		rendered: [],
		reset: jest.fn(),
		render: jest.fn( ( container, options ) => {
			provider.rendered.push( { container, options } );
			return 'widget-' + provider.rendered.length;
		} ),
	};
	return provider;
}

function mountForms( count, provider = 'turnstile' ) {
	document.body.innerHTML = Array.from(
		{ length: count },
		( _, index ) => `
		<form class="corex-form" id="form-${ index }">
			<input type="hidden" name="captcha_token" value="" />
			<div class="corex-form__challenge" data-corex-challenge="${ provider }" data-corex-sitekey="a-site-key"></div>
			<button type="submit">Send</button>
			<p class="corex-form__status" role="status"></p>
		</form>`
	).join( '' );
}

function submit( form ) {
	const event = new Event( 'submit', { cancelable: true, bubbles: true } );
	form.dispatchEvent( event );
	return event;
}

afterEach( () => {
	delete window.turnstile;
	delete window.hcaptcha;
	delete window.corexCaptchaWidget;
	delete window.corexCaptchaWidgetReady;
	document.body.innerHTML = '';
} );

it.each( [ 'turnstile', 'hcaptcha' ] )(
	'asks %s to render in each form, once, with the site key',
	( name ) => {
		mountForms( 2, name );
		window[ name ] = standInProvider();
		loadScript();
		// The provider's script calls back when it has loaded; nothing is rendered twice.
		window.corexCaptchaWidgetReady();

		expect( window[ name ].rendered ).toHaveLength( 2 );
		expect( window[ name ].rendered[ 0 ].options.sitekey ).toBe(
			'a-site-key'
		);
		// Turnstile is handed a selector and hCaptcha the element; either way it is that form's place.
		const { container } = window[ name ].rendered[ 1 ];
		expect(
			typeof container === 'string'
				? document.querySelector( container )
				: container
		).toBe( document.querySelector( '#form-1 .corex-form__challenge' ) );
	}
);

it( 'renders when the provider arrives after the page, through the callback it is given', () => {
	mountForms( 1 );
	loadScript();
	expect(
		document.querySelector( '[data-corex-challenge-ready]' )
	).toBeNull();

	window.turnstile = standInProvider();
	window.corexCaptchaWidgetReady();

	expect( window.turnstile.rendered ).toHaveLength( 1 );
} );

it( 'holds a submission made before the visitor has passed, and says why', () => {
	mountForms( 1 );
	window.turnstile = standInProvider();
	window.corexCaptchaWidget = {
		i18n: { incomplete: 'Complete the challenge first.' },
	};
	loadScript();
	const form = document.getElementById( 'form-0' );
	const reachedRuntime = jest.fn();
	form.addEventListener( 'submit', reachedRuntime );

	const event = submit( form );

	expect( event.defaultPrevented ).toBe( true );
	expect( reachedRuntime ).not.toHaveBeenCalled();
	expect( form.querySelector( '.corex-form__status' ).textContent ).toBe(
		'Complete the challenge first.'
	);
} );

it( 'sends the token the provider called back with, in the form it was rendered in', () => {
	mountForms( 2 );
	window.turnstile = standInProvider();
	loadScript();
	const second = document.getElementById( 'form-1' );
	const reachedRuntime = jest.fn();
	second.addEventListener( 'submit', reachedRuntime );

	window.turnstile.rendered[ 1 ].options.callback( 'token-for-the-second' );
	submit( second );

	expect( second.querySelector( '[name="captcha_token"]' ).value ).toBe(
		'token-for-the-second'
	);
	expect(
		document.querySelector( '#form-0 [name="captcha_token"]' ).value
	).toBe( '' );
	expect( reachedRuntime ).toHaveBeenCalledTimes( 1 );
} );

it.each( [
	[ 'accepted', 'corex:form:success', { envelope: { ok: true } } ],
	[
		'refused by the server',
		'corex:form:error',
		{ envelope: { ok: false } },
	],
] )(
	'spends the token once the form has been %s, and asks for a new challenge',
	( _, eventName, detail ) => {
		mountForms( 1 );
		window.turnstile = standInProvider();
		loadScript();
		const form = document.getElementById( 'form-0' );
		window.turnstile.rendered[ 0 ].options.callback( 'a-token' );

		form.dispatchEvent( new CustomEvent( eventName, { detail } ) );

		expect( form.querySelector( '[name="captcha_token"]' ).value ).toBe(
			''
		);
		expect( window.turnstile.reset ).toHaveBeenCalledWith( 'widget-1' );
	}
);

it( 'keeps the token when the browser refused the answers and nothing was sent', () => {
	mountForms( 1 );
	window.turnstile = standInProvider();
	loadScript();
	const form = document.getElementById( 'form-0' );
	window.turnstile.rendered[ 0 ].options.callback( 'a-token' );

	form.dispatchEvent(
		new CustomEvent( 'corex:form:error', {
			detail: { errors: { phone: 'required' }, fields: [ 'phone' ] },
		} )
	);

	expect( form.querySelector( '[name="captcha_token"]' ).value ).toBe(
		'a-token'
	);
	expect( window.turnstile.reset ).not.toHaveBeenCalled();
} );

it( 'forgets an expired token, so the next submission is held', () => {
	mountForms( 1 );
	window.turnstile = standInProvider();
	loadScript();
	const form = document.getElementById( 'form-0' );
	const { options } = window.turnstile.rendered[ 0 ];
	options.callback( 'a-token' );

	options[ 'expired-callback' ]();

	expect( submit( form ).defaultPrevented ).toBe( true );
} );

it( 'tells the visitor when the provider cannot show its challenge', () => {
	mountForms( 1 );
	window.turnstile = standInProvider();
	loadScript();
	const form = document.getElementById( 'form-0' );

	window.turnstile.rendered[ 0 ].options[ 'error-callback' ]();

	expect( form.querySelector( '.corex-form__status' ).textContent ).toBe(
		'We could not verify your submission. Please try again.'
	);
} );
