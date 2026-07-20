/**
 * Settings custom-select accessibility (correction 3). Each native <select> is progressively
 * enhanced into an in-DOM ARIA listbox (so the open dropdown is readable in dark mode on every
 * browser — a native popup is drawn by the OS and cannot be styled), while the hidden native
 * <select> stays as the form value so submission still works and the field degrades without JS.
 * jsdom drives the IIFEs over a DOM we set up first.
 *
 * The enhancement itself now lives in corex-core (assets/js/corex-select.js) and is shared with
 * every CoreX screen; settings.js consumes it through window.Corex.enhanceSelect. Both scripts
 * load here in the same order WordPress enqueues them.
 */

function loadSettings() {
	jest.isolateModules( () => {
		require( '../../../corex-core/assets/js/corex-select.js' );
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

	it( 'toggles driver-aware rows live when the controlling select changes', () => {
		document.body.innerHTML =
			'<form class="corex-settings-form">' +
			'<label for="captcha_driver">Driver</label>' +
			'<select id="captcha_driver"><option value="none">None</option>' +
			'<option value="recaptcha">reCAPTCHA</option></select>' +
			'<table><tr id="notice" data-corex-show-for="captcha.driver" data-corex-show-values="none"><td>off</td></tr>' +
			'<tr id="keys" data-corex-show-for="captcha.driver" data-corex-show-values="recaptcha"><td><input id="k"/></td></tr>' +
			'</table></form>';

		loadSettings();

		// driver=none on load: notice visible, keys hidden
		expect( document.getElementById( 'notice' ).hidden ).toBe( false );
		expect( document.getElementById( 'keys' ).hidden ).toBe( true );

		// switch to reCAPTCHA via the enhanced select (no save) -> keys show, notice hides
		const wrap = document.querySelector( '.corex-select--enhanced' );
		wrap.querySelector( '.corex-select__button' ).click();
		wrap.querySelectorAll( '[role="option"]' )[ 1 ].dispatchEvent(
			new window.MouseEvent( 'mousedown', { bubbles: true } )
		);

		expect( document.getElementById( 'keys' ).hidden ).toBe( false );
		expect( document.getElementById( 'notice' ).hidden ).toBe( true );
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
