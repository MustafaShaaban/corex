/**
 * Settings custom-select accessibility (correction 3). The settings script progressively
 * enhances each native <select> into an in-DOM ARIA listbox (so the open dropdown is readable
 * in dark mode on every browser), while keeping the hidden native <select> as the form value so
 * submission still works and the field degrades without JS. jsdom drives the IIFE over a DOM we
 * set up first.
 */

function loadSettings() {
	jest.isolateModules( () => {
		require( '../settings.js' );
	} );
}

describe( 'settings select enhancement', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'upgrades a native select into an accessible listbox and hides the native control', () => {
		document.body.innerHTML =
			'<form class="corex-settings-form">' +
			'<label for="d">Captcha driver</label>' +
			'<select id="d"><option value="none">None</option>' +
			'<option value="recaptcha">reCAPTCHA</option></select></form>';

		loadSettings();

		const wrap = document.querySelector( '.corex-select--enhanced' );
		expect( wrap ).not.toBeNull();

		const button = wrap.querySelector( '.corex-select__button' );
		expect( button.getAttribute( 'aria-haspopup' ) ).toBe( 'listbox' );
		expect( button.getAttribute( 'aria-label' ) ).toBe( 'Captcha driver' );

		expect( wrap.querySelector( '[role="listbox"]' ) ).not.toBeNull();
		expect( wrap.querySelectorAll( '[role="option"]' ) ).toHaveLength( 2 );

		// The native control is kept (for form submission) but visually hidden.
		expect( document.getElementById( 'd' ).style.display ).toBe( 'none' );
	} );

	it( 'opens on click and writes the chosen value back to the native select', () => {
		document.body.innerHTML =
			'<form class="corex-settings-form">' +
			'<label for="d">Captcha driver</label>' +
			'<select id="d"><option value="none">None</option>' +
			'<option value="recaptcha">reCAPTCHA</option></select></form>';

		loadSettings();

		const wrap = document.querySelector( '.corex-select--enhanced' );
		const button = wrap.querySelector( '.corex-select__button' );

		button.click();
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'true' );

		const options = wrap.querySelectorAll( '[role="option"]' );
		options[ 1 ].dispatchEvent(
			new window.MouseEvent( 'mousedown', { bubbles: true } )
		);

		expect( document.getElementById( 'd' ).value ).toBe( 'recaptcha' );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect(
			wrap.querySelector( '[role="option"][aria-selected="true"]' )
				.textContent
		).toBe( 'reCAPTCHA' );
	} );
} );
