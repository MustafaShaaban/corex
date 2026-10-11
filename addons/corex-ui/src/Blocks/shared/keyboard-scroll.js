/**
 * The keyboard's way to what a `corex/modal` or `corex/drawer` holds, when that is more than fits.
 *
 * The dialog's body scrolls and its header, with the title and the close button, does not. The
 * keys that scroll (the arrows, Page Down, End) act on what has focus or on what is around it, and
 * a dialog opens with focus on its close button, which the body is not around. From there the
 * keys would scroll nothing.
 *
 * So while the body holds more than fits it takes focus itself: a stop for Tab between the close
 * button and the first thing in it, a group named by the dialog's title. While all of it fits it
 * is not a stop, and Tab goes from the button to the first thing in it. Left to themselves the
 * browsers differ: Firefox makes a scrolling element a stop, Chromium only while it holds nothing
 * that takes focus, WebKit never (DECISIONS #315).
 */

/** What makes the body a stop, and what it is to a screen reader while it is one. */
const AS_A_STOP = { tabindex: '0', role: 'group' };

/**
 * Keep a dialog's body a stop for Tab for as long as it holds more than fits.
 *
 * @param {HTMLDialogElement} dialog The dialog.
 * @param {HTMLElement}       body   The part of it that scrolls.
 * @return {() => void} Reads the body again. The body is watched where the browser can watch it;
 *                      call this when the dialog opens, for where it cannot.
 */
export function keepInReachOfTheKeyboard( dialog, body ) {
	const title = dialog.getAttribute( 'aria-labelledby' );
	const whileItScrolls = title
		? { ...AS_A_STOP, 'aria-labelledby': title }
		: AS_A_STOP;

	function read() {
		const scrolls = body.scrollHeight > body.clientHeight;

		Object.keys( whileItScrolls ).forEach( function ( name ) {
			if ( scrolls ) {
				body.setAttribute( name, whileItScrolls[ name ] );
			} else {
				body.removeAttribute( name );
			}
		} );
	}

	// The body's box changes when the dialog opens and closes and when the window does, which is
	// when the answer can change.
	if ( typeof window.ResizeObserver === 'function' ) {
		new window.ResizeObserver( read ).observe( body );
	}

	return read;
}
