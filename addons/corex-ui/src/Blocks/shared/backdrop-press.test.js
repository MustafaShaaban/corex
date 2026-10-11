/**
 * Jest — which presses of the mouse count as a press on a dialog's backdrop, for `corex/modal`
 * and `corex/drawer`. A press is dispatched as the three events it is (mousedown, mouseup, and the
 * click the browser gives to the nearest element holding both ends). jsdom lays nothing out, so
 * the dialog's box is given; tests/e2e/block-dialogs.spec.js makes the same presses with a mouse
 * on the rendered blocks.
 */
import { onBackdropPress } from './backdrop-press';

const BOX = { left: 100, right: 300, top: 100, bottom: 300 };
const ON_BACKDROP = { clientX: 20, clientY: 200 };
const ON_PADDING = { clientX: 105, clientY: 200 };
const ON_CONTENT = { clientX: 200, clientY: 200 };

let dialog;
let button;
let onPress;

function dispatch( type, target, point ) {
	target.dispatchEvent(
		new window.MouseEvent( type, { bubbles: true, ...point } )
	);
}

/**
 * A press from one place to another. Each end is `[ element, point ]`.
 *
 * @param {Array} from Where the mouse goes down.
 * @param {Array} to   Where it comes up.
 */
function press( from, to ) {
	dispatch( 'mousedown', ...from );
	dispatch( 'mouseup', ...to );
	// Both ends on one element: the click is that element's. Otherwise it is the dialog's, the
	// nearest element holding both.
	dispatch( 'click', from[ 0 ] === to[ 0 ] ? to[ 0 ] : dialog, to[ 1 ] );
}

beforeEach( () => {
	document.body.innerHTML =
		'<dialog><button type="button">Agree</button></dialog>';
	dialog = document.querySelector( 'dialog' );
	button = dialog.querySelector( 'button' );
	dialog.getBoundingClientRect = () => BOX;
	onPress = jest.fn();
	onBackdropPress( dialog, onPress );
} );

afterEach( () => {
	document.body.innerHTML = '';
} );

describe( 'a press on the backdrop', () => {
	test( 'calls back when it begins and ends on the backdrop', () => {
		press( [ dialog, ON_BACKDROP ], [ dialog, ON_BACKDROP ] );

		expect( onPress ).toHaveBeenCalledTimes( 1 );
	} );

	test.each( [
		[ 'it began on the content', 'content', 'backdrop' ],
		[ 'it ended on the content', 'backdrop', 'content' ],
		[ "it is a click on the dialog's own padding", 'padding', 'padding' ],
		[ "it began on the dialog's own padding", 'padding', 'backdrop' ],
	] )( 'does not call back when %s', ( scenario, from, to ) => {
		const ends = {
			content: [ button, ON_CONTENT ],
			padding: [ dialog, ON_PADDING ],
			backdrop: [ dialog, ON_BACKDROP ],
		};

		press( ends[ from ], ends[ to ] );

		expect( onPress ).not.toHaveBeenCalled();
	} );

	test( 'does not call back when the content keeps its mousedown to itself', () => {
		// A backdrop press first, so a mousedown that went unread would leave it standing.
		press( [ dialog, ON_BACKDROP ], [ dialog, ON_BACKDROP ] );
		onPress.mockClear();
		button.addEventListener( 'mousedown', ( event ) =>
			event.stopPropagation()
		);

		press( [ button, ON_CONTENT ], [ dialog, ON_BACKDROP ] );

		expect( onPress ).not.toHaveBeenCalled();
	} );
} );
