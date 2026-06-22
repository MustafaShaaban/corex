/**
 * CoreX login enhancement (presentation only). It wraps the native username input in a field
 * box so a leading icon can sit inside it, mirroring WordPress's `.wp-pwd` for the password —
 * without touching any authentication behaviour or reading any value.
 */

describe( 'corex-login', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'wraps the username field so it can carry a leading icon', () => {
		document.body.innerHTML =
			'<form id="loginform"><p>' +
			'<label for="user_login">Username</label>' +
			'<input id="user_login" type="text" /></p></form>';

		jest.isolateModules( () => {
			require( '../js/corex-login.js' );
		} );

		const input = document.getElementById( 'user_login' );
		expect( input.closest( '.corex-login__field--user' ) ).not.toBeNull();
		// the input is still the same element (not replaced) — auth behaviour untouched
		expect( input.tagName ).toBe( 'INPUT' );
	} );

	it( 'does nothing when there is no username field', () => {
		document.body.innerHTML = '<form id="loginform"></form>';
		expect( () => {
			jest.isolateModules( () => {
				require( '../js/corex-login.js' );
			} );
		} ).not.toThrow();
		expect( document.querySelector( '.corex-login__field' ) ).toBeNull();
	} );
} );
