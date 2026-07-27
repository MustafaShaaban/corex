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

	/*
	 * `corex-runtime`, this script's dependency, depends on `wp-i18n`, so `wp.i18n.__` is
	 * there in WordPress; the identity fallback covers the buildless/test cases. Binding the
	 * real function instead of wrapping a call around it is what lets `wp i18n make-pot` see
	 * the literals at the call sites below.
	 */
	const __ =
		window.wp && window.wp.i18n && typeof window.wp.i18n.__ === 'function'
			? window.wp.i18n.__
			: function ( text ) {
					return text;
			  };

	function init() {
		const config = window.corexCaptcha;
		const anchor = document.getElementById( 'captcha.secret' );

		if ( ! config || ! anchor || ! window.Corex || ! window.Corex.api ) {
			return;
		}

		const wrap = document.createElement( 'p' );
		wrap.className = 'corex-captcha-test';

		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'button corex-captcha-test__button';
		button.textContent = __( 'Test verification', 'corex' );

		const result = document.createElement( 'span' );
		result.className = 'corex-captcha-test__result';
		result.setAttribute( 'role', 'status' );
		result.setAttribute( 'aria-live', 'polite' );

		wrap.appendChild( button );
		wrap.appendChild( result );
		( anchor.closest( 'td' ) || anchor.parentNode ).appendChild( wrap );

		button.addEventListener( 'click', function () {
			button.disabled = true;
			result.className = 'corex-captcha-test__result';
			result.textContent = __( 'Testing…', 'corex' );

			window.Corex.api
				.post(
					testEndpoint( config.restUrl ),
					{},
					{ nonce: config.nonce }
				)
				.then( function ( res ) {
					const view = resultFromEnvelope( res && res.envelope );
					result.textContent =
						view.message ||
						__( 'No response from the provider.', 'corex' );
					result.className =
						'corex-captcha-test__result is-' +
						( view.ok ? 'ok' : 'error' );
				} )
				.catch( function () {
					result.textContent = __(
						'The test request failed. Check your connection and try again.',
						'corex'
					);
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
		module.exports = {
			testEndpoint,
			resultFromEnvelope,
		};
	}
} )();
