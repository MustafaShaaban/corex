/**
 * corex/drawer front-end behavior. The native <dialog> gives the focus trap, ::backdrop, and
 * ESC-to-close for free; this only opens it, closes on the close button + a press on the
 * backdrop, and returns focus to the trigger. Loaded only where the block renders (viewScript).
 * Without JS the trigger/close are inert and the dialog content stays in the DOM — nothing is lost.
 */
import { onBackdropPress } from '../shared/backdrop-press';

( function () {
	function wire( wrap ) {
		const trigger = wrap.querySelector( '.corex-drawer__trigger' );
		const dialog = wrap.querySelector( 'dialog.corex-drawer' );
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

		onBackdropPress( dialog, function () {
			close( dialog );
		} );

		const closeBtn = dialog.querySelector( '[data-corex-drawer-close]' );
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
		const wraps = document.querySelectorAll( '.corex-drawer-wrap' );
		Array.prototype.forEach.call( wraps, wire );
	}

	if ( document.readyState !== 'loading' ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
} )();
