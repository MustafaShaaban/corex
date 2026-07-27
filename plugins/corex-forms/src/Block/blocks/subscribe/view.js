function bind() {
	if ( window.Corex?.forms ) {
		document
			.querySelectorAll( '.corex-flow .corex-form' )
			.forEach( window.Corex.forms.bind );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bind );
} else {
	bind();
}
