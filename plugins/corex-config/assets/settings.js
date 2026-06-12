/**
 * Corex settings — wires the WordPress media frame to media fields (spec 032). Clicking
 * "Select image" opens the media library and writes the chosen image URL into the field's
 * value input + preview; "Remove" clears them. Vanilla JS (no build); the field degrades to
 * an editable URL input when this script is absent.
 */
( function () {
	function previewFor( input ) {
		return input && input.parentNode
			? input.parentNode.querySelector( '.corex-media-preview' )
			: null;
	}

	document.addEventListener( 'click', function ( event ) {
		var selectBtn = event.target.closest( '.corex-media-select' );
		var removeBtn = event.target.closest( '.corex-media-remove' );

		if ( selectBtn && window.wp && window.wp.media ) {
			event.preventDefault();
			var input = document.getElementById( selectBtn.dataset.target );
			var frame = window.wp.media( {
				title: selectBtn.textContent,
				button: { text: selectBtn.textContent },
				multiple: false,
			} );
			frame.on( 'select', function () {
				var url = frame.state().get( 'selection' ).first().toJSON().url;
				if ( input ) {
					input.value = url;
				}
				var preview = previewFor( input );
				if ( preview ) {
					preview.src = url;
					preview.style.display = '';
				}
			} );
			frame.open();
		}

		if ( removeBtn ) {
			event.preventDefault();
			var target = document.getElementById( removeBtn.dataset.target );
			if ( target ) {
				target.value = '';
			}
			var p = previewFor( target );
			if ( p ) {
				p.src = '';
				p.style.display = 'none';
			}
		}
	} );
} )();
