/**
 * A CoreX screen opened read-only (spec 117, US-SHL-06).
 *
 * The holder of "See CoreX screens without changing them" gets Overview, Settings and Insights with
 * every control that changes something marked unavailable. The controls stay focusable, so each one
 * is announced with the sentence that says why (`aria-describedby` points at it), and pressing,
 * typing into or submitting them does nothing.
 *
 * What only moves around the screen keeps working: links, tabs, a form that asks for a page
 * (`method="get"`, such as a filter), and anything inside `data-corex-readonly-skip` (filters,
 * pagers, navigation). So do the browser's own keys: Tab, reload, copy, the caret in a text box.
 *
 * The screen's own islands draw their controls after this runs, so the content is watched and every
 * control that appears is marked too. The server refuses every save from this person whatever the
 * page does; this is how the page says so.
 */
( function () {
	const root = document.querySelector( '[data-corex-readonly]' );
	if ( ! root ) {
		return;
	}

	const noteId = root.getAttribute( 'data-corex-readonly' );
	const CONTROLS =
		'input, select, textarea, button, [role="button"], [role="switch"], [role="checkbox"], [contenteditable="true"]';
	const KEPT = 'a, [role="tab"], [data-corex-readonly-skip]';
	// Keys that press a control or change its value. Anything else (Tab, Escape, F5, a key held
	// with Ctrl, Alt or Cmd) is the browser's and passes.
	const PRESSES = [ 'Enter', ' ', 'Spacebar' ];
	const EDITS = [ 'Backspace', 'Delete' ];
	// They change a select, a radio group or a slider; in a read-only text box they move the caret.
	const STEPS = [
		'ArrowUp',
		'ArrowDown',
		'ArrowLeft',
		'ArrowRight',
		'Home',
		'End',
		'PageUp',
		'PageDown',
	];

	/**
	 * A form that asks for a page changes nothing: a filter, a search.
	 *
	 * @param {?HTMLFormElement} form The form, or null for a control outside one.
	 * @return {boolean} Whether it only asks for a page.
	 */
	function asksForAPage( form ) {
		return (
			!! form &&
			( form.getAttribute( 'method' ) || 'get' ).toLowerCase() === 'get'
		);
	}

	function isKept( control ) {
		return (
			control.matches( KEPT ) ||
			control.closest( '[data-corex-readonly-skip]' ) !== null ||
			asksForAPage( control.form || control.closest( 'form' ) )
		);
	}

	function mark( scope ) {
		scope.querySelectorAll( CONTROLS ).forEach( ( control ) => {
			if (
				control.dataset.corexReadonly === 'marked' ||
				control.type === 'hidden' ||
				isKept( control )
			) {
				return;
			}

			control.dataset.corexReadonly = 'marked';
			control.setAttribute( 'aria-disabled', 'true' );
			if (
				control.tagName === 'TEXTAREA' ||
				( control.tagName === 'INPUT' &&
					! [
						'checkbox',
						'radio',
						'file',
						'button',
						'submit',
						'range',
						'color',
					].includes( control.type ) )
			) {
				control.readOnly = true;
			}

			const described = (
				control.getAttribute( 'aria-describedby' ) || ''
			)
				.split( /\s+/ )
				.filter( Boolean );
			if ( noteId && ! described.includes( noteId ) ) {
				described.push( noteId );
				control.setAttribute(
					'aria-describedby',
					described.join( ' ' )
				);
			}
		} );
	}

	/**
	 * Whether a key pressed on this control would press it or change its value.
	 *
	 * @param {KeyboardEvent} event   The key pressed.
	 * @param {Element}       control The marked control it was pressed on.
	 * @return {boolean} Whether it would change something.
	 */
	function changes( event, control ) {
		if ( event.ctrlKey || event.metaKey || event.altKey ) {
			return false;
		}
		if ( PRESSES.includes( event.key ) ) {
			return true;
		}
		// The browser already keeps a read-only text box as it is; only the caret moves.
		if ( control.readOnly ) {
			return false;
		}

		return (
			STEPS.includes( event.key ) ||
			EDITS.includes( event.key ) ||
			event.key.length === 1
		);
	}

	function stop( event ) {
		const control =
			event.target instanceof window.Element
				? event.target.closest( '[data-corex-readonly="marked"]' )
				: null;
		if ( ! control ) {
			return;
		}
		if ( event.type === 'keydown' && ! changes( event, control ) ) {
			return;
		}

		event.preventDefault();
		event.stopImmediatePropagation();
	}

	mark( root );
	[
		'click',
		'mousedown',
		'keydown',
		'change',
		'input',
		'paste',
		'cut',
		'drop',
	].forEach( ( type ) => root.addEventListener( type, stop, true ) );
	root.addEventListener(
		'submit',
		( event ) => {
			if ( asksForAPage( event.target ) ) {
				return;
			}

			event.preventDefault();
			event.stopImmediatePropagation();
		},
		true
	);

	new window.MutationObserver( () => mark( root ) ).observe( root, {
		childList: true,
		subtree: true,
	} );
} )();
