/**
 * Jest unit tests for the Corex client runtime (spec 043). Loads the buildless
 * `window.Corex` against jsdom, mocking `fetch`/`wp.apiFetch`, and asserts the
 * envelope normalisation, the form lifecycle, loading/dedupe, and the events.
 */

const RUNTIME = '../plugins/corex-core/assets/js/corex-runtime.js';

const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

function loadRuntimeWithFetch() {
	delete window.wp; // force the fetch path (no wp.apiFetch)
	jest.isolateModules( () => {
		require( RUNTIME );
	} );
}

function mockFetch( body, { ok = true, status = 200, nonJson = false } = {} ) {
	global.fetch = jest.fn( () =>
		Promise.resolve( {
			ok,
			status,
			json: () => ( nonJson ? Promise.reject( new Error( 'not json' ) ) : Promise.resolve( body ) ),
		} )
	);
}

function makeForm( schema ) {
	document.body.innerHTML = `
		<form class="corex-form"
			data-corex-endpoint="/wp-json/corex/v1/forms/contact"
			data-corex-nonce="abc123"
			data-corex-success="Thanks!"
			data-corex-error="Please check the form."
			data-corex-schema='${ JSON.stringify( schema ) }'>
			<div data-corex-field="email">
				<input name="email" type="email" />
				<span class="corex-form__error"></span>
			</div>
			<div class="corex-form__status"></div>
			<button type="submit">Send</button>
		</form>`;
	return document.querySelector( '.corex-form' );
}

const EMAIL_REQUIRED = [ { name: 'email', required: true, rules: [ { rule: 'required' }, { rule: 'email' } ] } ];

beforeEach( () => {
	document.body.innerHTML = '';
	loadRuntimeWithFetch();
} );

describe( 'Corex.api', () => {
	it( 'normalises a bare success body into an envelope and fires start/end events', async () => {
		mockFetch( { id: 7 } );
		const start = jest.fn();
		const end = jest.fn();
		document.addEventListener( 'corex:request:start', start );
		document.addEventListener( 'corex:request:end', end );

		const result = await window.Corex.api.post( '/x', { a: 1 } );

		expect( result.ok ).toBe( true );
		expect( result.envelope.ok ).toBe( true );
		expect( result.envelope.data ).toEqual( { id: 7 } );
		expect( start ).toHaveBeenCalledTimes( 1 );
		expect( end ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'passes through a real envelope unchanged', async () => {
		mockFetch( { ok: false, code: 'validation_failed', message: 'no', errors: { email: 'required' }, details: {} }, { ok: false, status: 422 } );
		const result = await window.Corex.api.post( '/x', {} );
		expect( result.envelope.code ).toBe( 'validation_failed' );
		expect( result.envelope.errors ).toEqual( { email: 'required' } );
	} );

	it( 'attaches the nonce header on the fetch path', async () => {
		mockFetch( { ok: true, message: '', data: {} } );
		await window.Corex.api.post( '/x', { a: 1 }, { nonce: 'n-42' } );
		const init = global.fetch.mock.calls[ 0 ][ 1 ];
		expect( init.headers[ 'X-WP-Nonce' ] ).toBe( 'n-42' );
	} );

	it( 'supports PATCH mutations through the shared request contract', async () => {
		mockFetch( { ok: true, message: '', data: { version: 2 } } );
		const result = await window.Corex.api.patch( '/x/7', { expected_version: 1 } );
		const init = global.fetch.mock.calls[ 0 ][ 1 ];

		expect( init.method ).toBe( 'PATCH' );
		expect( JSON.parse( init.body ) ).toEqual( { expected_version: 1 } );
		expect( result.envelope.data.version ).toBe( 2 );
	} );

	it( 'resolves a network failure to an error result and never throws', async () => {
		global.fetch = jest.fn( () => Promise.reject( new Error( 'offline' ) ) );
		const result = await window.Corex.api.get( '/x' );
		expect( result.ok ).toBe( false );
		expect( result.envelope.ok ).toBe( false );
		expect( result.envelope.code ).toBe( 'error' );
	} );

	it( 'resolves a non-JSON (HTML) error body to a generic error envelope', async () => {
		mockFetch( null, { ok: false, status: 500, nonJson: true } );
		const result = await window.Corex.api.get( '/x' );
		expect( result.envelope.ok ).toBe( false );
		expect( typeof result.envelope.message ).toBe( 'string' );
	} );
} );

describe( 'Corex.forms.bind', () => {
	it( 'blocks submit and shows a field error on a client-side validation failure', async () => {
		mockFetch( { ok: true, message: '', data: {} } );
		const form = makeForm( EMAIL_REQUIRED );
		window.Corex.forms.bind( form );

		form.dispatchEvent( new Event( 'submit', { cancelable: true, bubbles: true } ) );
		await flush();

		expect( global.fetch ).not.toHaveBeenCalled();
		expect( form.querySelector( '[data-corex-field="email"] .corex-form__error' ).textContent ).not.toBe( '' );
		expect( form.querySelector( 'input[name="email"]' ).getAttribute( 'aria-invalid' ) ).toBe( 'true' );
	} );

	it( 'submits once on valid input, resets, and fires corex:form:success', async () => {
		mockFetch( { ok: true, message: 'Saved', data: {} } );
		const form = makeForm( EMAIL_REQUIRED );
		form.querySelector( 'input[name="email"]' ).value = 'a@b.com';
		const success = jest.fn();
		form.addEventListener( 'corex:form:success', success );
		window.Corex.forms.bind( form );

		form.dispatchEvent( new Event( 'submit', { cancelable: true, bubbles: true } ) );
		await flush();

		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		expect( success ).toHaveBeenCalledTimes( 1 );
		expect( form.querySelector( '.corex-form__status' ).textContent ).toBe( 'Thanks!' );
	} );

	it( 'honors the published flow success definition returned by the visitor endpoint', async () => {
		mockFetch( { ok: true, message: '', data: { success: { type: 'inline', message: 'Flow complete.' } } } );
		const form = makeForm( EMAIL_REQUIRED );
		form.querySelector( 'input[name="email"]' ).value = 'a@b.com';
		window.Corex.forms.bind( form );

		form.dispatchEvent( new Event( 'submit', { cancelable: true, bubbles: true } ) );
		await flush();

		expect( form.querySelector( '.corex-form__status' ).textContent ).toBe( 'Flow complete.' );
	} );

	it( 'renders server envelope errors and fires corex:form:error', async () => {
		mockFetch(
			{ ok: false, code: 'validation_failed', message: 'Check fields', errors: { email: 'email' }, details: {} },
			{ ok: false, status: 422 }
		);
		const form = makeForm( EMAIL_REQUIRED );
		form.querySelector( 'input[name="email"]' ).value = 'a@b.com';
		const onError = jest.fn();
		form.addEventListener( 'corex:form:error', onError );
		window.Corex.forms.bind( form );

		form.dispatchEvent( new Event( 'submit', { cancelable: true, bubbles: true } ) );
		await flush();

		expect( onError ).toHaveBeenCalledTimes( 1 );
		expect( form.querySelector( '[data-corex-field="email"] .corex-form__error' ).textContent ).not.toBe( '' );
	} );

	it( 'is idempotent — binding twice does not double-submit', async () => {
		mockFetch( { ok: true, message: '', data: {} } );
		const form = makeForm( EMAIL_REQUIRED );
		form.querySelector( 'input[name="email"]' ).value = 'a@b.com';
		window.Corex.forms.bind( form );
		window.Corex.forms.bind( form );

		form.dispatchEvent( new Event( 'submit', { cancelable: true, bubbles: true } ) );
		await flush();

		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'Corex.loading', () => {
	it( 'dedupes a second start and toggles aria-busy + disabled', () => {
		const form = makeForm( EMAIL_REQUIRED );
		const submitEl = form.querySelector( '[type="submit"]' );

		const token = window.Corex.loading.start( form, submitEl );
		expect( form.getAttribute( 'aria-busy' ) ).toBe( 'true' );
		expect( submitEl.disabled ).toBe( true );
		expect( window.Corex.loading.start( form, submitEl ) ).toBeNull();

		window.Corex.loading.stop( token );
		expect( form.hasAttribute( 'aria-busy' ) ).toBe( false );
		expect( submitEl.disabled ).toBe( false );
	} );
} );

describe( 'Corex.api via wp.apiFetch (admin path)', () => {
	it( 'uses wp.apiFetch when present and normalises the response', async () => {
		const apiFetch = jest.fn( () =>
			Promise.resolve( { ok: true, status: 200, json: () => Promise.resolve( { ok: true, message: 'ok', data: { n: 1 } } ) } )
		);
		window.wp = { apiFetch, i18n: { __: ( s ) => s } };
		let runtime;
		jest.isolateModules( () => {
			require( RUNTIME );
		} );
		runtime = window.Corex;

		const result = await runtime.api.get( '/admin', { nonce: 'x' } );

		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( result.envelope.data ).toEqual( { n: 1 } );
		delete window.wp;
	} );

	/**
	 * With `parse: false` core does not resolve on a non-2xx — parseAndThrowError() rethrows
	 * the raw Response. Every test above this one mocked a *resolve*, which is why a real
	 * server error could reach the UI as "Something went wrong" for a whole release.
	 */
	const loadWithApiFetch = ( apiFetch ) => {
		window.wp = { apiFetch, i18n: { __: ( s ) => s } };
		jest.isolateModules( () => {
			require( RUNTIME );
		} );
		return window.Corex;
	};

	const rejectingApiFetch = ( body, status, { nonJson = false } = {} ) =>
		jest.fn( () =>
			Promise.reject( {
				ok: false,
				status,
				json: () =>
					nonJson
						? Promise.reject( new Error( 'not json' ) )
						: Promise.resolve( body ),
			} )
		);

	it( 'keeps the server message when apiFetch rejects with an error Response', async () => {
		const runtime = loadWithApiFetch(
			rejectingApiFetch(
				{
					ok: false,
					code: 'email_template_not_found',
					message: 'That email template was not found.',
					details: {},
				},
				404
			)
		);

		const result = await runtime.api.post( '/admin/templates/1/draft', {} );

		expect( result.ok ).toBe( false );
		expect( result.status ).toBe( 404 );
		expect( result.envelope.code ).toBe( 'email_template_not_found' );
		expect( result.envelope.message ).toBe(
			'That email template was not found.'
		);
		delete window.wp;
	} );

	it( 'keeps field details from a rejected validation response', async () => {
		const runtime = loadWithApiFetch(
			rejectingApiFetch(
				{
					ok: false,
					code: 'email_template_unsafe',
					message: 'The draft contains invalid or unsafe content.',
					details: { fields: { layout_id: 'Choose a layout.' } },
				},
				422
			)
		);

		const result = await runtime.api.post( '/admin/x', {} );

		expect( result.status ).toBe( 422 );
		expect( result.envelope.details.fields ).toEqual( {
			layout_id: 'Choose a layout.',
		} );
		delete window.wp;
	} );

	it( 'names the status when a rejected response carries no readable body', async () => {
		const runtime = loadWithApiFetch(
			rejectingApiFetch( null, 500, { nonJson: true } )
		);

		const result = await runtime.api.get( '/admin/x' );

		expect( result.status ).toBe( 500 );
		expect( result.envelope.message ).toContain( '500' );
		delete window.wp;
	} );

	it( 'still reports a transport failure as status 0', async () => {
		const runtime = loadWithApiFetch(
			jest.fn( () => Promise.reject( new Error( 'offline' ) ) )
		);

		const result = await runtime.api.get( '/admin/x' );

		expect( result.ok ).toBe( false );
		expect( result.status ).toBe( 0 );
		expect( result.envelope.code ).toBe( 'error' );
		delete window.wp;
	} );
} );
