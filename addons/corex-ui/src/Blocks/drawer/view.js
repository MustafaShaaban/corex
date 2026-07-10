/**
 * corex/drawer front-end behavior. The native <dialog> gives the focus trap, ::backdrop, and
 * ESC-to-close for free; this only opens it, closes on the close button + a backdrop click, and
 * returns focus to the trigger. Loaded only where the block renders (viewScript). Without JS the
 * trigger/close are inert and the dialog content stays in the DOM — nothing is lost.
 */
( function () {
	function wire( wrap ) {
		var trigger = wrap.querySelector( '.corex-drawer__trigger' );
		var dialog = wrap.querySelector( 'dialog.corex-drawer' );
		if ( ! trigger || ! dialog ) {
			return;
		}

		trigger.addEventListener( 'click', function () {
			if ( typeof dialog.showModal === 'function' ) {
				dialog.showModal();
			} else {
				dialog.setAttribute( 'open', '' ); // very old browsers: inline fallback
			}
		} );

		// Backdrop click (the click lands on the <dialog> element itself).
		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target === dialog ) {
				close( dialog );
			}
		} );

		var closeBtn = dialog.querySelector( '[data-corex-drawer-close]' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', function () {
				close( dialog );
			} );
		}

		dialog.addEventListener( 'close', function () {
			trigger.focus();
		} );
	}

	function close( dialog ) {
		if ( typeof dialog.close === 'function' ) {
			dialog.close();
		} else {
			dialog.removeAttribute( 'open' );
		}
	}

	function init() {
		var wraps = document.querySelectorAll( '.corex-drawer-wrap' );
		Array.prototype.forEach.call( wraps, wire );
	}

	if ( document.readyState !== 'loading' ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
} )();
