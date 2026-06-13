/**
 * Corex Form viewScript — the shared client runtime (`window.Corex`, spec 043) owns the
 * whole submit lifecycle (schema validation, loading, server-error rendering) and
 * auto-binds every `.corex-form` on load. This thin bootstrap only re-binds forms that
 * may have been inserted after the runtime initialised; `Corex.forms.bind` is idempotent.
 */
( function () {
	function bind() {
		if ( window.Corex && window.Corex.forms ) {
			document.querySelectorAll( '.corex-form' ).forEach( window.Corex.forms.bind );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', bind );
	} else {
		bind();
	}
} )();
