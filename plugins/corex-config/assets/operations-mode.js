/**
 * The operations-mode form's progressive disclosure (spec 077, FR-006/FR-008).
 *
 * The server renders every mode's block with all but one hidden and their inputs disabled. This makes
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
	 * Whether the selection is the mode the site has already declared.
	 *
	 * Declared, not merely current. A site that inherits its mode from the WordPress environment
	 * type has stated none, and choosing that same mode states it: a change the server records.
	 * Treating it as a non-change left the button disabled on exactly the sites the screen tells to
	 * "declare a mode".
	 *
	 * @param {HTMLFormElement} form     The mode form.
	 * @param {string}          selected The mode now chosen.
	 * @return {boolean} True when applying the selection would change nothing.
	 */
	function proposesNothing( form, selected ) {
		return (
			form.getAttribute( 'data-mode-declared' ) === '1' &&
			selected === form.getAttribute( 'data-current-mode' )
		);
	}

	/**
	 * Reveal the block for a proposed mode and retire the rest.
	 *
	 * A block describes a change, so none is shown while the selection is the mode the site is in:
	 * there is nothing to describe and nothing to confirm, and a line says so instead.
	 *
	 * `disabled` matters as much as `hidden`: a hidden input is still submitted, so without it the
	 * server could receive a confirmation belonging to a mode the operator did not choose. The
	 * server would reject it, but the operator would have to work out why.
	 *
	 * @param {HTMLFormElement} form     The mode form.
	 * @param {string}          selected The mode now chosen.
	 */
	function show( form, selected ) {
		const unchanged = proposesNothing( form, selected );
		const same = form.querySelector( '[data-corex-mode-same]' );

		if ( same ) {
			same.hidden = ! unchanged;
		}

		form.querySelectorAll( '[data-mode]' ).forEach( function ( block ) {
			const isActive =
				! unchanged && block.getAttribute( 'data-mode' ) === selected;

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

		if ( ! apply ) {
			return;
		}

		const unchanged = proposesNothing( form, selected );

		apply.disabled = unchanged;
		apply.setAttribute( 'aria-disabled', String( unchanged ) );
	}

	/**
	 * The mode the server is already proposing, if it is not the one the select starts on.
	 *
	 * This exists because of a bug worth remembering. The first version synced from `select.value`
	 * on load, which is the mode the site is IN — so arriving at `?mode=production`, where the
	 * server had deliberately rendered the production block, the script immediately hid it again
	 * and showed the current mode instead. That silently undid the whole no-JavaScript path: submit
	 * Production, get redirected to the form that asks for the phrase, and watch the phrase field
	 * vanish before you can type in it. A loop with no way out.
	 *
	 * The server's rendering is the instruction, so the selection follows it rather than the other
	 * way round.
	 *
	 * @param {HTMLFormElement} form The mode form.
	 * @return {string} The proposed mode, or '' when the server proposed nothing in particular.
	 */
	function serverProposed( form ) {
		const visible = form.querySelector( '[data-mode]:not([hidden])' );

		return visible ? visible.getAttribute( 'data-mode' ) : '';
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

		const proposed = serverProposed( form );

		if ( proposed && proposed !== select.value ) {
			select.value = proposed;
		}

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
