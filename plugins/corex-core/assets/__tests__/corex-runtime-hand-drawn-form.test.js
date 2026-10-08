/**
 * The runtime against a form a site drew itself (spec 104 US3; issue #248).
 *
 * The markup is not written here. It is `tests/Fixtures/Forms/hand-drawn-form.html`, which the
 * PHP test of the renderer asserts is exactly what a form's own `markup()` produces from the
 * published parts. So this is the real runtime against the real output of the contract: a class
 * of the site's own on the form, a control written by hand in a wrapper of the site's own, the
 * button and the status place in a footer.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const RUNTIME = path.join( __dirname, '..', 'js', 'corex-runtime.js' );
const FORM = path.join(
	__dirname,
	'..',
	'..',
	'..',
	'..',
	'tests',
	'Fixtures',
	'Forms',
	'hand-drawn-form.html'
);

const settle = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

function submit( form ) {
	form.dispatchEvent(
		new Event( 'submit', { cancelable: true, bubbles: true } )
	);
}

let form;

beforeEach( () => {
	document.body.innerHTML = fs.readFileSync( FORM, 'utf8' );
	delete window.Corex;
	window.wp = { i18n: { __: ( text ) => text } };
	// eslint-disable-next-line no-eval
	window.eval( fs.readFileSync( RUNTIME, 'utf8' ) );
	form = document.querySelector( 'form.lead-card' );
	window.Corex.forms.bind( form );
} );

afterEach( () => {
	delete window.fetch;
} );

it( 'writes a missing answer into the place the site put that field, and marks the control', () => {
	window.fetch = jest.fn();

	submit( form );

	const control = form.querySelector( 'input.lead-card__input' );
	const error = form.querySelector( '.lead-card__row .corex-form__error' );

	expect( error.textContent ).toBe( 'This field is required.' );
	expect( control.getAttribute( 'aria-invalid' ) ).toBe( 'true' );
	// The error is the one the control says describes it.
	expect( control.getAttribute( 'aria-describedby' ) ).toContain( error.id );
	expect(
		form.querySelector( '.lead-card__foot .corex-form__status' ).textContent
	).toBe( 'Please review the highlighted fields and try again.' );
	expect( window.fetch ).not.toHaveBeenCalled();
} );

it( 'sends the answers and the trap field to the form endpoint, and confirms in the site status place', async () => {
	window.fetch = jest.fn( () =>
		Promise.resolve( {
			ok: true,
			status: 200,
			json: () => Promise.resolve( { ok: true, message: '', data: {} } ),
		} )
	);
	form.querySelector( 'input.lead-card__input' ).value = '+201001234567';

	submit( form );
	await settle();
	await settle();

	const [ url, request ] = window.fetch.mock.calls[ 0 ];
	expect( url ).toBe( 'https://example.test/wp-json/corex/v1/forms/lead' );
	expect( JSON.parse( request.body ) ).toEqual( {
		phone: '+201001234567',
		note: '',
		corex_hp: '',
	} );
	expect( request.headers[ 'X-WP-Nonce' ] ).toBe( 'test-nonce' );
	expect(
		form.querySelector( '.lead-card__foot .corex-form__status' ).textContent
	).toBe( 'Thank you — your message has been sent.' );
} );
