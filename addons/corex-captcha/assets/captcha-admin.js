/**
 * Corex captcha — the "Test verification" button on the Corex settings captcha card (spec
 * 053 US3). Vanilla, no build (matches insights.js): it injects a Test button after the
 * captcha secret field, POSTs to corex/v1/captcha/test via the shared runtime, and shows the
 * classified, actionable message the server returns (CaptchaDiagnostic).
 *
 * Secret-safety (FR-014): the test runs server-side; the response carries only a classified
 * status + message, and this UI renders only ok + message — never any key value.
 */
( function () {
	/**
	 * The captcha test endpoint for a REST base (tolerant of a trailing slash).
	 *
	 * @param {string} restBase e.g. https://site/wp-json/corex/v1
	 * @return {string} the /captcha/test URL
	 */
	function testEndpoint( restBase ) {
		return String( restBase ).replace( /\/$/, '' ) + '/captcha/test';
	}

	/**
	 * The displayable result — ok + message only, so no other envelope field (a secret, a
	 * key) can ever reach the screen.
	 *
	 * @param {Object} envelope the spec-043 envelope
	 * @return {{ok: boolean, message: string}} what the UI may render
	 */
	function resultFromEnvelope( envelope ) {
		return {
			ok: Boolean( envelope && envelope.ok ),
			message: ( envelope && envelope.message ) || '',
		};
	}

	function translate( text ) {
		return window.wp && window.wp.i18n ? window.wp.i18n.__( text, 'corex' ) : text;
	}

	function init() {
		var config = window.corexCaptcha;
		var anchor = document.getElementById( 'captcha.secret' );

		if ( ! config || ! anchor || ! window.Corex || ! window.Corex.api ) {
			return;
		}

		var wrap = document.createElement( 'p' );
		wrap.className = 'corex-captcha-test';

		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'button corex-captcha-test__button';
		button.textContent = translate( 'Test verification' );

		var result = document.createElement( 'span' );
		result.className = 'corex-captcha-test__result';
		result.setAttribute( 'role', 'status' );
		result.setAttribute( 'aria-live', 'polite' );

		wrap.appendChild( button );
		wrap.appendChild( result );
		( anchor.closest( 'td' ) || anchor.parentNode ).appendChild( wrap );

		button.addEventListener( 'click', function () {
			button.disabled = true;
			result.className = 'corex-captcha-test__result';
			result.textContent = translate( 'Testing…' );

			window.Corex.api
				.post( testEndpoint( config.restUrl ), {}, { nonce: config.nonce } )
				.then( function ( res ) {
					var view = resultFromEnvelope( res && res.envelope );
					result.textContent = view.message || translate( 'No response from the provider.' );
					result.className = 'corex-captcha-test__result is-' + ( view.ok ? 'ok' : 'error' );
				} )
				.catch( function () {
					result.textContent = translate( 'The test request failed. Check your connection and try again.' );
					result.className = 'corex-captcha-test__result is-error';
				} )
				.then( function () {
					button.disabled = false;
				} );
		} );
	}

	if ( typeof document !== 'undefined' ) {
		if ( document.readyState !== 'loading' ) {
			init();
		} else {
			document.addEventListener( 'DOMContentLoaded', init );
		}
	}

	// Exported for unit tests (no effect in the browser, where there is no CommonJS module).
	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { testEndpoint: testEndpoint, resultFromEnvelope: resultFromEnvelope };
	}
} )();
