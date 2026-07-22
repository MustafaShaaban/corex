/**
 * CoreX reCAPTCHA v3 — fresh token per submission.
 *
 * Enqueued only on pages that actually contain a protected CoreX form (the asset controller
 * checks the render-time registry), so this file never runs where there is nothing to protect.
 *
 * It cooperates with corex-runtime's form handler rather than replacing it. corex-runtime binds a
 * bubble-phase submit listener that reads every named input — including `captcha_token` — and
 * posts the form. This script listens in the *capture* phase, which runs first: it holds the
 * submission, fetches a brand-new token for that form's action, writes it into the hidden field,
 * then lets the submission proceed. A token is therefore generated at submit time and never at
 * page load (FR-002), and each form uses its own action (FR-008).
 */
( function () {
	'use strict';

	const config = window.corexCaptchaV3 || {};
	const siteKey = config.siteKey || '';
	const forms = config.forms || {};
	const i18n = config.i18n || {};
	const errorMessage =
		i18n.error || 'We could not verify your submission. Please try again.';

	if ( ! siteKey ) {
		return;
	}

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

	// Resolve a fresh token for an action, or reject — never reuse a prior token.
	function freshToken( action ) {
		return new Promise( function ( resolve, reject ) {
			if (
				! window.grecaptcha ||
				typeof window.grecaptcha.ready !== 'function'
			) {
				reject( new Error( 'recaptcha-unavailable' ) );
				return;
			}
			window.grecaptcha.ready( function () {
				window.grecaptcha
					.execute( siteKey, { action } )
					.then( resolve, reject );
			} );
		} );
	}

	function guard( form, action ) {
		form.addEventListener(
			'submit',
			function ( event ) {
				// A submission we already primed with a fresh token: let it through to the runtime.
				if ( form.dataset.corexCaptchaReady === '1' ) {
					delete form.dataset.corexCaptchaReady;
					return;
				}

				// Hold this submission and stop the runtime's handler from firing on the stale token.
				event.preventDefault();
				event.stopImmediatePropagation();

				if ( form.dataset.corexCaptchaBusy === '1' ) {
					return; // a token request is already in flight — ignore the double click
				}
				form.dataset.corexCaptchaBusy = '1';

				freshToken( action ).then(
					function ( token ) {
						const field = form.querySelector(
							'input[name="captcha_token"]'
						);
						if ( field ) {
							field.value = token;
						}
						delete form.dataset.corexCaptchaBusy;
						form.dataset.corexCaptchaReady = '1';
						// Re-submit; the capture guard now lets it pass to the runtime.
						if ( typeof form.requestSubmit === 'function' ) {
							form.requestSubmit();
						} else {
							form.dispatchEvent(
								new Event( 'submit', {
									cancelable: true,
									bubbles: true,
								} )
							);
						}
					},
					function () {
						delete form.dataset.corexCaptchaBusy;
						announce( form, errorMessage );
					}
				);
			},
			true // capture phase: run before corex-runtime's bubble-phase handler
		);
	}

	function init() {
		const nodes = document.querySelectorAll(
			'.corex-form[data-corex-form]'
		);
		Array.prototype.forEach.call( nodes, function ( form ) {
			const slug = form.getAttribute( 'data-corex-form' );
			if ( slug && Object.prototype.hasOwnProperty.call( forms, slug ) ) {
				guard( form, forms[ slug ] );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
