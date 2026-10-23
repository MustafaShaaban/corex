/**
 * The Settings page's save bar and secret removal (spec 119, US-SET-01 and US-SET-15). jsdom drives
 * the script over markup shaped as the page draws it.
 */

const BAR =
	'<div class="corex-savebar" data-corex-savebar data-state="clean" data-text-clean="No changes"' +
	' data-text-dirty="Unsaved changes" data-text-saved="All changes saved" data-text-leave="Leave?"' +
	' data-text-failed="The save did not go through.">' +
	'<p><span data-corex-savebar-text>No changes</span></p>' +
	'<p data-corex-savebar-error hidden></p>' +
	'<button type="reset" data-corex-discard>Discard</button>' +
	'<button type="submit" data-corex-save>Save changes</button></div>';

function page( fields, state = 'clean' ) {
	document.body.innerHTML =
		'<form data-corex-settings-page="general" class="corex-settings-form" action="http://site.test/settings">' +
		fields +
		BAR.replace( 'data-state="clean"', `data-state="${ state }"` ) +
		'</form>';
	jest.isolateModules( () => {
		require( '../settings.js' );
	} );

	return {
		form: document.querySelector( 'form' ),
		words: document.querySelector( '[data-corex-savebar-text]' ),
		save: document.querySelector( '[data-corex-save]' ),
		discard: document.querySelector( '[data-corex-discard]' ),
	};
}

function type( input, value ) {
	input.value = value;
	input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
}

afterEach( () => {
	document.body.innerHTML = '';
	delete window.confirm;
} );

describe( 'the save bar', () => {
	it( 'reads "No changes" with both buttons off until something changes', () => {
		const { words, save, discard } = page(
			'<input id="brand_tagline" value="Old" data-corex-saved="Old" />'
		);

		expect( words.textContent ).toBe( 'No changes' );
		expect( save.disabled ).toBe( true );
		expect( discard.disabled ).toBe( true );
	} );

	it( 'reads "Unsaved changes" after an edit, and "No changes" when it is typed back', () => {
		const { words, save } = page(
			'<input id="brand_tagline" value="Old" data-corex-saved="Old" />'
		);
		const input = document.getElementById( 'brand_tagline' );

		type( input, 'New' );
		expect( words.textContent ).toBe( 'Unsaved changes' );
		expect( save.disabled ).toBe( false );

		type( input, 'Old' );
		expect( words.textContent ).toBe( 'No changes' );
	} );

	it( 'puts every field back as stored on Discard, a switch included', () => {
		const { form, words } = page(
			'<input id="brand_tagline" value="Old" data-corex-saved="Old" />' +
				'<input id="w" type="checkbox" value="1" data-corex-saved="0" />'
		);
		type( document.getElementById( 'brand_tagline' ), 'New' );
		const box = document.getElementById( 'w' );
		box.checked = true;
		box.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		form.dispatchEvent( new Event( 'reset', { cancelable: true } ) );

		expect( document.getElementById( 'brand_tagline' ).value ).toBe(
			'Old'
		);
		expect( box.checked ).toBe( false );
		expect( words.textContent ).toBe( 'No changes' );
	} );

	it( 'opens unsaved when a refused value is drawn in its field', () => {
		const { words } = page(
			'<input id="brand_email" value="sales@" data-corex-saved="old@example.com" />',
			'saved'
		);

		expect( words.textContent ).toBe( 'Unsaved changes' );
	} );

	it( 'reads "All changes saved" after a save, until the next edit', () => {
		const { words } = page(
			'<input id="brand_tagline" value="Old" data-corex-saved="Old" />',
			'saved'
		);
		expect( words.textContent ).toBe( 'All changes saved' );

		type( document.getElementById( 'brand_tagline' ), 'New' );
		expect( words.textContent ).toBe( 'Unsaved changes' );
	} );

	it( 'puts a logo removed from the field back on Discard, preview included', () => {
		const { form } = page(
			'<div class="corex-media"><div class="corex-media__frame">' +
				'<img class="corex-media-preview" src="https://site.test/logo.png" alt="" />' +
				'<span class="corex-media__placeholder" hidden>No logo set</span></div>' +
				'<button type="button" class="corex-media-remove" data-target="brand_logo_url">Remove</button>' +
				'<input id="brand_logo_url" class="corex-media__url" value="https://site.test/logo.png"' +
				' data-corex-saved="https://site.test/logo.png" /></div>'
		);
		document.querySelector( '.corex-media-remove' ).click();
		expect( document.querySelector( '.corex-media-preview' ).hidden ).toBe(
			true
		);

		form.dispatchEvent( new Event( 'reset', { cancelable: true } ) );

		const preview = document.querySelector( '.corex-media-preview' );
		expect( document.getElementById( 'brand_logo_url' ).value ).toBe(
			'https://site.test/logo.png'
		);
		expect( preview.hidden ).toBe( false );
		expect( preview.getAttribute( 'src' ) ).toBe(
			'https://site.test/logo.png'
		);
		expect(
			document.querySelector( '.corex-media__placeholder' ).hidden
		).toBe( true );
	} );

	it( 'puts a stored value with markup back as text, never as markup', () => {
		const markup = '"><img src=x onerror="window.__corexXss=1">';
		const { form } = page(
			'<div class="corex-media"><div class="corex-media__frame">' +
				'<img class="corex-media-preview" src="" alt="" hidden />' +
				'<span class="corex-media__placeholder">No logo set</span></div>' +
				'<input id="brand_logo_url" class="corex-media__url" value="" data-corex-saved="" /></div>'
		);
		const field = document.getElementById( 'brand_logo_url' );
		field.setAttribute( 'data-corex-saved', markup );

		form.dispatchEvent( new Event( 'reset', { cancelable: true } ) );

		expect( field.value ).toBe( markup );
		expect( document.querySelectorAll( 'img' ) ).toHaveLength( 1 );
		expect(
			document
				.querySelector( '.corex-media-preview' )
				.getAttribute( 'src' )
		).toBe( '' );
		expect( window.__corexXss ).toBeUndefined();
	} );

	it( 'ignores what cannot be edited: a field locked or of an add-on that is off', () => {
		const { words } = page(
			'<input id="brand_company_name" value="Acme" data-corex-saved="Old" readonly />'
		);

		expect( words.textContent ).toBe( 'No changes' );
	} );

	it( 'asks before leaving only with unsaved changes', () => {
		page(
			'<input id="brand_tagline" value="Old" data-corex-saved="Old" />'
		);
		const leave = () => {
			const event = new Event( 'beforeunload', { cancelable: true } );
			window.dispatchEvent( event );

			return event.defaultPrevented;
		};

		expect( leave() ).toBe( false );
		type( document.getElementById( 'brand_tagline' ), 'New' );
		expect( leave() ).toBe( true );
	} );

	it( 'says a save that did not go through, and keeps the edit', async () => {
		const { form } = page(
			'<input id="brand_tagline" name="brand_tagline" value="Old" data-corex-saved="Old" />'
		);
		window.fetch = jest.fn( () =>
			Promise.resolve( { type: 'basic', status: 500 } )
		);
		type( document.getElementById( 'brand_tagline' ), 'Kept' );

		form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		const error = document.querySelector( '[data-corex-savebar-error]' );
		expect( error.hidden ).toBe( false );
		expect( error.textContent ).toBe( 'The save did not go through.' );
		expect( document.getElementById( 'brand_tagline' ).value ).toBe(
			'Kept'
		);
		delete window.fetch;
	} );
} );

describe( 'removing a saved secret', () => {
	const SECRET =
		'<input id="captcha_secret" type="password" value="" data-corex-saved="" />' +
		'<span data-corex-secret-remove="captcha_secret">' +
		'<input type="checkbox" id="captcha_secret-remove" name="corex_remove_secret[]" value="captcha.secret" data-corex-saved="0" />' +
		'<label for="captcha_secret-remove">Remove the saved value when I save</label></span>' +
		'<p id="captcha_secret-remove-effect">The captcha check stops.</p>';

	it( 'asks first, naming what stops, and marks the page unsaved once confirmed', () => {
		const { words } = page( SECRET );
		window.confirm = jest.fn( () => true );
		const button = document.querySelector( '.corex-secret-remove__button' );

		expect( button.textContent ).toBe( 'Remove' );
		button.click();

		expect( window.confirm ).toHaveBeenCalledWith(
			'Remove the saved value? The captcha check stops.'
		);
		expect(
			document.getElementById( 'captcha_secret-remove' ).checked
		).toBe( true );
		expect( words.textContent ).toBe( 'Unsaved changes' );
		expect( button.textContent ).toBe( 'Keep it' );
	} );

	it( 'removes nothing when the question is answered no', () => {
		const { words } = page( SECRET );
		window.confirm = jest.fn( () => false );

		document.querySelector( '.corex-secret-remove__button' ).click();

		expect(
			document.getElementById( 'captcha_secret-remove' ).checked
		).toBe( false );
		expect( words.textContent ).toBe( 'No changes' );
	} );
} );
