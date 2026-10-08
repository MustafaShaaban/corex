/**
 * CoreX challenge widget — Cloudflare Turnstile and hCaptcha.
 *
 * Enqueued only on pages that hold a protected CoreX form, with the provider's own script. Both
 * providers work the same way: asked to render into an element, they show their widget and call
 * back with a token when the visitor has passed. This script asks them to render into each
 * `.corex-form__challenge`, and writes the token into that form's `captcha_token` field, which
 * corex-runtime sends with the form's other fields.
 *
 * A token is spent by one submission, so the widget is reset once the form has been answered,
 * whether it was accepted or refused. A submission made before the visitor has passed is held
 * here, with a message, and never reaches the server.
 */
( function () {
	'use strict';

	const config = window.corexCaptchaWidget || {};
	const i18n = config.i18n || {};
	const messages = {
		incomplete:
			i18n.incomplete || 'Please complete the challenge before sending.',
		error:
			i18n.error ||
			'We could not verify your submission. Please try again.',
	};
	const providers = {
		turnstile: () => window.turnstile,
		hcaptcha: () => window.hcaptcha,
	};

	// Read the safe recoverable-error channel corex-runtime exposes, degrading if it is absent.
	function announce( form, message ) {
		if (
			window.Corex &&
			window.Corex.notices &&
			typeof window.Corex.notices.status === 'function'
		) {
			window.Corex.notices.status( form, message, 'error' );
			return;
		}
		const status = form.querySelector( '.corex-form__status' );
		if ( status ) {
			status.textContent = message;
		}
	}

	function place( container, index ) {
		const form = container.closest( 'form' );
		const field =
			form && form.querySelector( 'input[name="captcha_token"]' );
		const name = container.dataset.corexChallenge;
		const api = providers[ name ] && providers[ name ]();

		if ( ! field || ! api || container.dataset.corexChallengeReady ) {
			return;
		}
		container.dataset.corexChallengeReady = '1';
		// Each is handed what its own documentation names: Turnstile a selector, hCaptcha the element.
		if ( ! container.id ) {
			container.id = 'corex-challenge-' + index;
		}
		const target = name === 'hcaptcha' ? container : '#' + container.id;

		const widget = api.render( target, {
			sitekey: container.dataset.corexSitekey,
			callback( token ) {
				field.value = token;
			},
			'expired-callback'() {
				field.value = '';
			},
			'error-callback'() {
				field.value = '';
				announce( form, messages.error );
			},
		} );

		function spend() {
			field.value = '';
			api.reset( widget );
		}

		form.addEventListener(
			'submit',
			function ( event ) {
				if ( field.value !== '' ) {
					return;
				}
				// Not passed yet: hold the submission before corex-runtime's handler sends it.
				event.preventDefault();
				event.stopImmediatePropagation();
				announce( form, messages.incomplete );
			},
			true // capture phase: run before corex-runtime's bubble-phase handler
		);
		form.addEventListener( 'corex:form:success', spend );
		form.addEventListener( 'corex:form:error', function ( event ) {
			// The browser's own check refused it and nothing was sent: the token is unspent.
			if ( event.detail && event.detail.envelope ) {
				spend();
			}
		} );
	}

	function placeAll() {
		Array.prototype.forEach.call(
			document.querySelectorAll(
				'.corex-form .corex-form__challenge[data-corex-challenge]'
			),
			place
		);
	}

	// The provider's script calls this by name once it has loaded.
	window.corexCaptchaWidgetReady = placeAll;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', placeAll );
	} else {
		placeAll();
	}
} )();
