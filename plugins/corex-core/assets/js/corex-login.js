/**
 * CoreX login enhancement (presentation only — WordPress owns all auth behaviour). It wraps the
 * native username input in a field box so a leading icon can sit inside it, mirroring the
 * password field's `.wp-pwd` wrapper. Without this script the fields still work; they just lose
 * the decorative leading icon. No values are read or changed.
 */
( function () {
	const username = document.getElementById( 'user_login' );

	if ( username && ! username.closest( '.corex-login__field' ) ) {
		const wrap = document.createElement( 'div' );
		wrap.className = 'corex-login__field corex-login__field--user';
		username.parentNode.insertBefore( wrap, username );
		wrap.appendChild( username );
	}
} )();
