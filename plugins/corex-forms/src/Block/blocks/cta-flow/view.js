function bind() {
	if ( window.Corex?.forms ) document.querySelectorAll( '.corex-flow .corex-form' ).forEach( window.Corex.forms.bind );
}
document.readyState === 'loading' ? document.addEventListener( 'DOMContentLoaded', bind ) : bind();
