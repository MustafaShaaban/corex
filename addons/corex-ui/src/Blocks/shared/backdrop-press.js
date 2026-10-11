/**
 * A press on the backdrop of a native `<dialog>` that is its own box: `corex/modal` and
 * `corex/drawer`, whose padding, border and background are the dialog element's.
 *
 * A click whose target is the dialog element is not a press on the backdrop, for two reasons:
 *
 * - When the mouse goes down on one element and comes up on another, the browser gives the click
 *   to the nearest element holding both, and between the backdrop and anything in the dialog that
 *   is the dialog element. A press slid off a button reads as a click on it.
 * - The dialog element is hit inside its box too, wherever a child does not cover it: on its
 *   padding, and in a drawer on all the height its content does not fill.
 *
 * So both ends of the press are read, and each is on the backdrop only when it is on the dialog
 * element and outside the dialog's box.
 */

/**
 * @param {HTMLDialogElement} dialog The dialog.
 * @param {MouseEvent}        event  A mouse event.
 * @return {boolean} Whether the event happened on the dialog's backdrop.
 */
function isOnBackdrop( dialog, event ) {
	if ( event.target !== dialog ) {
		return false;
	}

	const box = dialog.getBoundingClientRect();

	return (
		event.clientX < box.left ||
		event.clientX > box.right ||
		event.clientY < box.top ||
		event.clientY > box.bottom
	);
}

/**
 * Call back when a press of the mouse began on the dialog's backdrop and ended there too.
 *
 * @param {HTMLDialogElement} dialog   The dialog.
 * @param {() => void}        callback What to do about such a press.
 */
export function onBackdropPress( dialog, callback ) {
	let pressOnBackdrop = false;

	// In the capture phase: read as they happen, before anything in the dialog can stop them.
	dialog.addEventListener(
		'mousedown',
		function ( event ) {
			pressOnBackdrop = isOnBackdrop( dialog, event );
		},
		true
	);
	dialog.addEventListener(
		'mouseup',
		function ( event ) {
			pressOnBackdrop = pressOnBackdrop && isOnBackdrop( dialog, event );
		},
		true
	);
	dialog.addEventListener( 'click', function ( event ) {
		if ( pressOnBackdrop && isOnBackdrop( dialog, event ) ) {
			callback();
		}
	} );
}
