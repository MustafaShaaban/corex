/**
 * The operations-mode form's progressive disclosure (spec 077, FR-006/FR-008).
 *
 * The server renders all four mode blocks with three hidden and their inputs disabled. This makes
 * the swap immediate when the operator changes the selection, so choosing Production reveals the
 * typed confirmation without a round trip.
 *
 * It is an enhancement, not the mechanism. With this file absent or broken the form still works:
 * the server rendered the block for the proposed mode, and submitting a mode whose confirmation was
 * not on screen comes back — through the normal redirect — proposing that mode with its
 * confirmation shown. Two steps instead of one, and the second one is correct.
 *
 * Buildless and jQuery-free, like the other admin enhancements loaded beside the shell.
 */
( function () {
	'use strict';

	/**
	 * Reveal one block and retire the rest.
	 *
	 * `disabled` matters as much as `hidden`: a hidden input is still submitted, so without it the
	 * server could receive a confirmation belonging to a mode the operator did not choose. The
	 * server would reject it, but the operator would have to work out why.
	 *
	 * @param {HTMLFormElement} form     The mode form.
	 * @param {string}          selected The mode now chosen.
	 */
	function show( form, selected ) {
		form.querySelectorAll( '[data-mode]' ).forEach( function ( block ) {
			const isActive = block.getAttribute( 'data-mode' ) === selected;

			block.hidden = ! isActive;
			block
				.querySelectorAll( 'input, textarea, select' )
				.forEach( function ( field ) {
					field.disabled = ! isActive;
				} );
		} );
	}

	/**
	 * Applying the mode you are already in does nothing, so the control says so.
	 *
	 * A courtesy, not a guarantee — `OperationsModeStore` refuses to record a non-change whatever
	 * arrives, which is where the actual promise lives.
	 *
	 * @param {HTMLFormElement} form     The mode form.
	 * @param {string}          selected The mode now chosen.
	 */
	function reflectNoOp( form, selected ) {
		const apply = form.querySelector( '[data-corex-mode-apply]' );
		const current = form.getAttribute( 'data-current-mode' );

		if ( ! apply || ! current ) {
			return;
		}

		const unchanged = selected === current;

		apply.disabled = unchanged;
		apply.setAttribute( 'aria-disabled', String( unchanged ) );
	}

	function bind( form ) {
		const select = form.querySelector( '[data-corex-mode-select]' );

		if ( ! select ) {
			return;
		}

		const sync = function () {
			show( form, select.value );
			reflectNoOp( form, select.value );
		};

		select.addEventListener( 'change', sync );
		sync();
	}

	function init() {
		document.querySelectorAll( '[data-corex-mode-form]' ).forEach( bind );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
