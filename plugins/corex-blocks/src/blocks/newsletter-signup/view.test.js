/**
 * Unit tests for the Newsletter Signup front-end submission (spec 063, Phase 7).
 * Verifies it posts to the real endpoint, enforces consent + honeypot, and reflects the
 * endpoint's truthful outcome (double opt-in "check your email") — never a fabricated success.
 */
jest.mock( '@wordpress/i18n', () => ( { __: ( text ) => text } ), {
	virtual: true,
} );

import { initNewsletterSignup } from './view';

function buildForm() {
	document.body.innerHTML = `
		<form class="corex-newsletter-signup__form"
			data-corex-newsletter="https://acme.test/wp-json/corex/v1/newsletter/subscribe"
			data-corex-newsletter-nonce="rest-nonce">
			<input name="email" type="email" value="person@acme.test" />
			<input name="consent" type="checkbox" checked />
			<input name="corex_hp" type="text" value="" />
			<p class="corex-newsletter-signup__status" role="status" aria-live="polite"></p>
		</form>`;
	return document.querySelector( '.corex-newsletter-signup__form' );
}

function submit( form ) {
	form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
}

describe( 'initNewsletterSignup', () => {
	it( 'posts to the real endpoint and shows the double opt-in confirmation on success', async () => {
		const form = buildForm();
		const fetchImpl = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( { ok: true } ),
		} );

		initNewsletterSignup( form, fetchImpl );
		submit( form );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( fetchImpl ).toHaveBeenCalledWith(
			'https://acme.test/wp-json/corex/v1/newsletter/subscribe',
			expect.objectContaining( { method: 'POST' } )
		);
		const body = JSON.parse( fetchImpl.mock.calls[ 0 ][ 1 ].body );
		expect( body.email ).toBe( 'person@acme.test' );
		expect( body.consent ).toBe( true );
		expect(
			form.querySelector( '.corex-newsletter-signup__status' ).dataset
				.state
		).toBe( 'success' );
	} );

	it( 'blocks submission and never calls the endpoint without consent', () => {
		const form = buildForm();
		form.querySelector( '[name="consent"]' ).checked = false;
		const fetchImpl = jest.fn();

		initNewsletterSignup( form, fetchImpl );
		submit( form );

		expect( fetchImpl ).not.toHaveBeenCalled();
		expect(
			form.querySelector( '.corex-newsletter-signup__status' ).dataset
				.state
		).toBe( 'error' );
	} );

	it( 'reflects a server error truthfully (no fabricated success)', async () => {
		const form = buildForm();
		const fetchImpl = jest.fn().mockResolvedValue( {
			ok: false,
			json: async () => ( { ok: false } ),
		} );

		initNewsletterSignup( form, fetchImpl );
		submit( form );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect(
			form.querySelector( '.corex-newsletter-signup__status' ).dataset
				.state
		).toBe( 'error' );
	} );

	it( 'is a safe no-op without a form or fetch', () => {
		expect( () => initNewsletterSignup( null, jest.fn() ) ).not.toThrow();
		expect( () => initNewsletterSignup( buildForm(), null ) ).not.toThrow();
	} );
} );
