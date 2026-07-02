/**
 * Newsletter Signup — front-end submission.
 *
 * Posts the signup to the REAL `corex/v1/newsletter/subscribe` REST route (carried on
 * the form's data attributes) and reflects the endpoint's truthful outcome in an
 * accessible live region: a new/pending subscriber is told to check their email to
 * confirm (double opt-in); the honeypot and consent are enforced. It fabricates no
 * success — the message follows the real response. `fetchImpl` is injected for testing.
 */
import { __ } from '@wordpress/i18n';

const STATUS_SELECTOR = '.corex-newsletter-signup__status';

/**
 * Wire one signup form.
 *
 * @param {HTMLFormElement} form      A `.corex-newsletter-signup__form` element.
 * @param {Function}        fetchImpl The fetch implementation (defaults to window.fetch).
 */
export function initNewsletterSignup(
	form,
	fetchImpl = typeof fetch !== 'undefined' ? fetch : null
) {
	if ( ! form || ! fetchImpl ) {
		return;
	}

	const status = form.querySelector( STATUS_SELECTOR );
	const setStatus = ( message, state ) => {
		if ( status ) {
			status.textContent = message;
			status.dataset.state = state;
		}
	};

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		const consent = !! ( form.querySelector( '[name="consent"]' ) || {} )
			.checked;
		if ( ! consent ) {
			setStatus(
				__( 'Please agree before subscribing.', 'corex' ),
				'error'
			);
			return;
		}

		const endpoint = form.getAttribute( 'data-corex-newsletter' );
		const nonce = form.getAttribute( 'data-corex-newsletter-nonce' ) || '';
		const email =
			( form.querySelector( '[name="email"]' ) || {} ).value || '';
		const honeypot =
			( form.querySelector( '[name="corex_hp"]' ) || {} ).value || '';

		setStatus( __( 'Subscribing…', 'corex' ), 'loading' );

		try {
			const response = await fetchImpl( endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( {
					email,
					consent,
					corex_hp: honeypot,
					topics: [],
				} ),
			} );
			const data = await response.json().catch( () => ( {} ) );

			if ( response.ok && data.ok ) {
				setStatus(
					__(
						'Almost there — check your email to confirm your subscription.',
						'corex'
					),
					'success'
				);
				form.reset();
			} else if ( data.error === 'captcha' ) {
				setStatus(
					__(
						'Additional verification is required. Please try again.',
						'corex'
					),
					'error'
				);
			} else {
				setStatus(
					__(
						'Sorry, that did not work. Please check your email and try again.',
						'corex'
					),
					'error'
				);
			}
		} catch {
			setStatus(
				__( 'A network error occurred. Please try again.', 'corex' ),
				'error'
			);
		}
	} );
}

/**
 * Wire every signup form in the document.
 *
 * @param {Document} doc
 */
export function initAll(
	doc = typeof document !== 'undefined' ? document : null
) {
	if ( ! doc ) {
		return;
	}
	doc.querySelectorAll( '.corex-newsletter-signup__form' ).forEach(
		( form ) => initNewsletterSignup( form )
	);
}

if ( typeof document !== 'undefined' ) {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => initAll() );
	} else {
		initAll();
	}
}
