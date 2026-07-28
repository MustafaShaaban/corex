/**
 * The form runtime's collection, validation and error signalling (#148).
 *
 * `corex-runtime.js` is a build-free IIFE that attaches to `window.Corex`, so it is loaded by
 * evaluating the file — the pattern `corex-login.test.js` already uses. No bundler, no mocks: the
 * real script against a real DOM.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const RUNTIME = path.join( __dirname, '..', 'js', 'corex-runtime.js' );

/** Evaluate the runtime into this jsdom window. */
function loadRuntime() {
	// eslint-disable-next-line no-eval
	window.eval( fs.readFileSync( RUNTIME, 'utf8' ) );
}

beforeEach( () => {
	document.body.innerHTML = '';
	delete window.Corex;
	window.wp = { i18n: { __: ( text ) => text } };
	loadRuntime();
} );

/**
 * A form with one field, its wrapper and its error slot — the DOM contract the runtime expects.
 *
 * @param {string} innerHtml The control markup.
 * @param {Object} [attrs]   Extra attributes for the <form>.
 * @return {HTMLFormElement} The form, already in the document.
 */
function formWith( innerHtml, attrs = {} ) {
	const form = document.createElement( 'form' );
	form.className = 'corex-form';
	Object.entries( attrs ).forEach( ( [ key, value ] ) =>
		form.setAttribute( key, value )
	);
	form.innerHTML = innerHtml;
	document.body.appendChild( form );
	return form;
}

describe( 'multi-value collection', () => {
	it( 'keeps every selected option, not just the first', () => {
		const form = formWith( `
			<div data-corex-field="services">
				<select name="services" multiple>
					<option value="brand-identity" selected>Brand</option>
					<option value="motion-graphics" selected>Motion</option>
					<option value="web-design">Web</option>
				</select>
				<span class="corex-form__error"></span>
			</div>
		` );

		// `el.value` on a multiple select is the FIRST selected option — the defect in one line.
		expect( form.querySelector( 'select' ).value ).toBe( 'brand-identity' );

		expect( window.Corex.forms.collect( form ).services ).toEqual( [
			'brand-identity',
			'motion-graphics',
		] );
	} );

	it( 'reports an empty list when a multi-select has no selection', () => {
		const form = formWith( `
			<div data-corex-field="services">
				<select name="services" multiple>
					<option value="brand-identity">Brand</option>
				</select>
			</div>
		` );

		expect( window.Corex.forms.collect( form ).services ).toEqual( [] );
	} );

	it( 'leaves a single select alone', () => {
		const form = formWith( `
			<div data-corex-field="service">
				<select name="service">
					<option value="brand-identity" selected>Brand</option>
				</select>
			</div>
		` );

		expect( window.Corex.forms.collect( form ).service ).toBe(
			'brand-identity'
		);
	} );
} );

describe( 'the client rule table mirrors the server', () => {
	it.each( [
		[ '+201016999700', null ],
		[ '+20 101 699 9700', null ],
		[ '+1 (555) 010-0199', null ],
		[ 'call me', 'phone' ],
		[ '0123456789', 'phone' ],
	] )( 'validates the phone %s', ( value, expected ) => {
		const errors = window.Corex.forms.validate(
			[ { name: 'phone', rules: [ { rule: 'phone' } ] } ],
			{ phone: value }
		);

		expect( errors.phone ?? null ).toBe( expected );
	} );

	it.each( [
		[ 'https://example.test', null ],
		[ 'not a url', 'url' ],
	] )( 'validates the url %s', ( value, expected ) => {
		const errors = window.Corex.forms.validate(
			[ { name: 'site', rules: [ { rule: 'url' } ] } ],
			{ site: value }
		);

		expect( errors.site ?? null ).toBe( expected );
	} );
} );

describe( 'validation messages', () => {
	it( 'prefers the message the server rendered into the form', () => {
		const form = formWith(
			`<div data-corex-field="name">
				<input name="name" />
				<span class="corex-form__error"></span>
			</div>`,
			{
				'data-corex-schema': JSON.stringify( [
					{
						name: 'name',
						required: true,
						rules: [ { rule: 'required' } ],
					},
				] ),
				'data-corex-messages': JSON.stringify( {
					required: 'هذا الحقل مطلوب.',
				} ),
			}
		);

		window.Corex.forms.bind( form );
		form.dispatchEvent(
			new window.Event( 'submit', { cancelable: true } )
		);

		expect( form.querySelector( '.corex-form__error' ).textContent ).toBe(
			'هذا الحقل مطلوب.'
		);
	} );

	it( 'falls back to its own table when the attribute is malformed', () => {
		const form = formWith(
			`<div data-corex-field="name">
				<input name="name" />
				<span class="corex-form__error"></span>
			</div>`,
			{
				'data-corex-schema': JSON.stringify( [
					{
						name: 'name',
						required: true,
						rules: [ { rule: 'required' } ],
					},
				] ),
				'data-corex-messages': '{ not json',
			}
		);

		window.Corex.forms.bind( form );
		form.dispatchEvent(
			new window.Event( 'submit', { cancelable: true } )
		);

		// A broken attribute must not take validation down with it.
		expect( form.querySelector( '.corex-form__error' ).textContent ).toBe(
			'This field is required.'
		);
	} );
} );

describe( 'error signalling', () => {
	it( 'emits corex:form:error on the client branch, with the failing fields', () => {
		const form = formWith(
			`<div data-corex-field="name">
				<input name="name" />
				<span class="corex-form__error"></span>
			</div>`,
			{
				'data-corex-schema': JSON.stringify( [
					{
						name: 'name',
						required: true,
						rules: [ { rule: 'required' } ],
					},
				] ),
			}
		);

		const seen = [];
		form.addEventListener( 'corex:form:error', ( event ) =>
			seen.push( event.detail )
		);

		window.Corex.forms.bind( form );
		form.dispatchEvent(
			new window.Event( 'submit', { cancelable: true } )
		);

		// Before this the server branch emitted and the client branch did not, so a theme using
		// the event as its only visual channel showed nothing at all on client-side failure.
		expect( seen ).toHaveLength( 1 );
		expect( seen[ 0 ].fields ).toEqual( [ 'name' ] );
	} );

	it( 'clears a field error once the field is corrected', () => {
		const form = formWith(
			`<div data-corex-field="name">
				<input name="name" />
				<span class="corex-form__error"></span>
			</div>`,
			{
				'data-corex-schema': JSON.stringify( [
					{
						name: 'name',
						required: true,
						rules: [ { rule: 'required' } ],
					},
				] ),
			}
		);

		window.Corex.forms.bind( form );
		form.dispatchEvent(
			new window.Event( 'submit', { cancelable: true } )
		);

		const input = form.querySelector( 'input' );
		expect( input.getAttribute( 'aria-invalid' ) ).toBe( 'true' );

		input.value = 'Sam';
		input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );

		expect( input.getAttribute( 'aria-invalid' ) ).toBeNull();
		expect( form.querySelector( '.corex-form__error' ).textContent ).toBe(
			''
		);
	} );
} );
