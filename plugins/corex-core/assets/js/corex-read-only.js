/**
 * A CoreX screen opened read-only (spec 117, US-SHL-06).
 *
 * The holder of "See CoreX screens without changing them" gets Overview, Settings, Operations &
 * Security and Insights with every control that changes something marked unavailable. The controls
 * stay focusable, so each one is announced with the sentence that says why (`aria-describedby`
 * points at it), and pressing, typing into or submitting them does nothing. Links and tabs, which
 * only move around the screen, keep working.
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
	const MOVES = [ 'Tab', 'Shift', 'Escape' ];

	function mark( scope ) {
		scope.querySelectorAll( CONTROLS ).forEach( ( control ) => {
			if (
				control.dataset.corexReadonly === 'marked' ||
				control.matches( KEPT ) ||
				control.type === 'hidden'
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

	function stop( event ) {
		const control =
			event.target instanceof window.Element
				? event.target.closest( '[data-corex-readonly="marked"]' )
				: null;
		if ( ! control ) {
			return;
		}
		if ( event.type === 'keydown' && MOVES.includes( event.key ) ) {
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
		'drop',
	].forEach( ( type ) => root.addEventListener( type, stop, true ) );
	root.addEventListener(
		'submit',
		( event ) => {
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
