/**
 * The Settings page's order of choices (spec 129, FR-018) and rows that follow a switch. jsdom
 * drives the script over markup shaped as SettingsControls draws it.
 */

const BAR =
	'<div class="corex-savebar" data-corex-savebar data-state="clean" data-text-clean="No changes"' +
	' data-text-dirty="Unsaved changes" data-text-saved="All changes saved" data-text-leave="Leave?"' +
	' data-text-failed="The save did not go through.">' +
	'<p><span data-corex-savebar-text>No changes</span></p>' +
	'<button type="reset" data-corex-discard>Discard</button>' +
	'<button type="submit" data-corex-save>Save changes</button></div>';

function item( key, label ) {
	return (
		`<li class="corex-order__item" data-corex-order-key="${ key }"><span class="corex-order__label">${ label }</span>` +
		`<span class="corex-order__moves"><button type="button" data-corex-order-move="up" aria-label="Move ${ label } up"></button>` +
		`<button type="button" data-corex-order-move="down" aria-label="Move ${ label } down"></button></span></li>`
	);
}

const ORDER =
	'<td><ol class="corex-order" data-corex-order="assistant_order" aria-label="Order">' +
	item( 'gemini', 'Google Gemini' ) +
	item( 'groq', 'Groq' ) +
	item( 'openai', 'OpenAI' ) +
	'</ol><input type="hidden" id="assistant_order" name="assistant_order" value="gemini,groq,openai"' +
	' data-corex-saved="gemini,groq,openai" />' +
	'<p class="screen-reader-text" aria-live="polite" data-corex-order-status></p></td>';

function page( fields ) {
	document.body.innerHTML =
		'<form data-corex-settings-page="assistant" class="corex-settings-form">' +
		fields +
		BAR +
		'</form>';
	jest.isolateModules( () => {
		require( '../settings.js' );
	} );
}

function keys() {
	return [ ...document.querySelectorAll( '[data-corex-order-key]' ) ].map(
		( node ) => node.getAttribute( 'data-corex-order-key' )
	);
}

function move( key, way ) {
	document
		.querySelector(
			`[data-corex-order-key="${ key }"] [data-corex-order-move="${ way }"]`
		)
		.click();
}

afterEach( () => {
	document.body.innerHTML = '';
} );

describe( 'an order of choices', () => {
	it( 'moves an item, rewrites the saved input and counts as an unsaved change', () => {
		page( ORDER );

		move( 'openai', 'up' );

		expect( keys() ).toEqual( [ 'gemini', 'openai', 'groq' ] );
		expect( document.getElementById( 'assistant_order' ).value ).toBe(
			'gemini,openai,groq'
		);
		expect(
			document.querySelector( '[data-corex-savebar-text]' ).textContent
		).toBe( 'Unsaved changes' );
	} );

	it( 'says where the item went and keeps the focus on it', () => {
		page( ORDER );

		move( 'gemini', 'down' );

		expect(
			document.querySelector( '[data-corex-order-status]' ).textContent
		).toBe( 'Google Gemini moved to place 2 of 3.' );
		expect( document.activeElement.getAttribute( 'aria-label' ) ).toBe(
			'Move Google Gemini down'
		);
	} );

	it( 'turns off the button that can no longer move, and gives the focus to the other', () => {
		page( ORDER );

		move( 'groq', 'up' );

		const groqUp = document.querySelector(
			'[data-corex-order-key="groq"] [data-corex-order-move="up"]'
		);
		expect( groqUp.disabled ).toBe( true );
		expect( document.activeElement.getAttribute( 'aria-label' ) ).toBe(
			'Move Groq down'
		);
		expect(
			document.querySelector(
				'[data-corex-order-key="gemini"] [data-corex-order-move="up"]'
			).disabled
		).toBe( false );
	} );

	it( 'goes back to the saved order on Discard', () => {
		page( ORDER );
		move( 'openai', 'up' );
		move( 'openai', 'up' );

		document.querySelector( 'form' ).reset();

		expect( keys() ).toEqual( [ 'gemini', 'groq', 'openai' ] );
		expect( document.getElementById( 'assistant_order' ).value ).toBe(
			'gemini,groq,openai'
		);
	} );
} );

describe( 'a row that follows a switch', () => {
	it( 'shows while the switch is on and hides when it is off', () => {
		page(
			'<input type="checkbox" id="assistant_booking_enabled" value="1" data-corex-saved="0" />' +
				'<table><tr data-corex-show-for="assistant.booking.enabled" data-corex-show-values="1" hidden><td>Hours</td></tr></table>'
		);
		const box = document.getElementById( 'assistant_booking_enabled' );
		const row = document.querySelector( 'tr' );

		box.checked = true;
		box.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( row.hidden ).toBe( false );

		box.checked = false;
		box.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( row.hidden ).toBe( true );
	} );
} );
