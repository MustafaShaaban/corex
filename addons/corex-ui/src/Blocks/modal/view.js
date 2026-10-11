/**
 * corex/modal front-end behavior. The native <dialog> gives the focus trap, ::backdrop, and
 * ESC-to-close for free; this only opens it, closes on the close button + a press on the
 * backdrop, returns focus to the trigger, and keeps what the dialog holds in reach of the keyboard
 * while that is more than fits. Loaded only where the block renders (viewScript).
 * With scripting off this does not run and nothing answers the trigger or the close button: the
 * stylesheet then shows the dialog in the page and hides both (shared/_dialog-parts.scss).
 */
import { onBackdropPress } from '../shared/backdrop-press';
import { keepInReachOfTheKeyboard } from '../shared/keyboard-scroll';

( function () {
	function wire( wrap ) {
		const trigger = wrap.querySelector( '.corex-modal__trigger' );
		const dialog = wrap.querySelector( 'dialog.corex-modal' );
		if ( ! trigger || ! dialog ) {
			return;
		}

		const readBody = keepInReachOfTheKeyboard(
			dialog,
			dialog.querySelector( '.corex-modal__body' )
		);

		trigger.addEventListener( 'click', function () {
			if ( typeof dialog.showModal === 'function' ) {
				dialog.showModal();
			} else {
				dialog.setAttribute( 'open', '' ); // very old browsers: inline fallback
			}
			readBody();
		} );

		onBackdropPress( dialog, function () {
			close( dialog );
		} );

		const closeBtn = dialog.querySelector( '[data-corex-modal-close]' );
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
		const wraps = document.querySelectorAll( '.corex-modal-wrap' );
		Array.prototype.forEach.call( wraps, wire );
	}

	if ( document.readyState !== 'loading' ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
} )();
