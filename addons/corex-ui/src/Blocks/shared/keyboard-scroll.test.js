/**
 * Jest — when the body of a `corex/modal` or `corex/drawer` is a stop for Tab: while it holds more
 * than fits, and not otherwise. jsdom lays nothing out, so the body's two heights are given, and
 * the browser's watch on its box is a stand-in that is told when to report;
 * tests/e2e/block-dialogs.spec.js reaches the last line of the rendered blocks with the keyboard.
 */
import { keepInReachOfTheKeyboard } from './keyboard-scroll';

const FITS = { scrollHeight: 200, clientHeight: 200 };
const OVERFLOWS = { scrollHeight: 900, clientHeight: 200 };

let dialog;
let body;
let boxChanged;

/**
 * Give the body the heights a browser would have measured.
 *
 * @param {{scrollHeight: number, clientHeight: number}} heights What it holds, and what fits.
 */
function measureAs( heights ) {
	Object.keys( heights ).forEach( ( name ) => {
		Object.defineProperty( body, name, {
			configurable: true,
			value: heights[ name ],
		} );
	} );
}

/**
 * What the body is to the keyboard and to a screen reader.
 *
 * @return {{tabindex: ?string, role: ?string, labelledBy: ?string, label: ?string}} Its attributes.
 */
function stop() {
	return {
		tabindex: body.getAttribute( 'tabindex' ),
		role: body.getAttribute( 'role' ),
		labelledBy: body.getAttribute( 'aria-labelledby' ),
		label: body.getAttribute( 'aria-label' ),
	};
}

const NOT_A_STOP = {
	tabindex: null,
	role: null,
	labelledBy: null,
	label: null,
};

beforeEach( () => {
	document.body.innerHTML =
		'<dialog aria-labelledby="terms-title">' +
		'<div class="header"><h2 id="terms-title">Terms</h2><button>Close</button></div>' +
		'<div class="body">What a visitor reads.</div>' +
		'</dialog>';
	dialog = document.querySelector( 'dialog' );
	body = document.querySelector( '.body' );
	boxChanged = undefined;
	window.ResizeObserver = class {
		constructor( callback ) {
			this.callback = callback;
		}
		observe( element ) {
			if ( element === body ) {
				boxChanged = this.callback;
			}
		}
	};
} );

afterEach( () => {
	delete window.ResizeObserver;
} );

describe( 'the body of a dialog block', () => {
	it( 'is a stop for Tab, named by the title, while it holds more than fits', () => {
		measureAs( OVERFLOWS );

		keepInReachOfTheKeyboard( dialog, body )();

		expect( stop() ).toEqual( {
			tabindex: '0',
			role: 'group',
			labelledBy: 'terms-title',
			label: null,
		} );
	} );

	it( 'is not a stop while all of what it holds fits', () => {
		measureAs( FITS );

		keepInReachOfTheKeyboard( dialog, body )();

		expect( stop() ).toEqual( NOT_A_STOP );
	} );

	it( 'is a stop named by the dialog’s own label when the dialog has no title', () => {
		dialog.removeAttribute( 'aria-labelledby' );
		dialog.setAttribute( 'aria-label', 'Read the terms' );
		measureAs( OVERFLOWS );

		keepInReachOfTheKeyboard( dialog, body )();

		expect( stop() ).toEqual( {
			tabindex: '0',
			role: 'group',
			labelledBy: null,
			label: 'Read the terms',
		} );
	} );

	it( 'gives the label up with the stop, when all of what it holds fits again', () => {
		dialog.removeAttribute( 'aria-labelledby' );
		dialog.setAttribute( 'aria-label', 'Read the terms' );
		measureAs( OVERFLOWS );
		keepInReachOfTheKeyboard( dialog, body )();

		measureAs( FITS );
		boxChanged();

		expect( stop() ).toEqual( NOT_A_STOP );
	} );

	it( 'becomes a stop when its box changes and it no longer fits what it holds', () => {
		measureAs( FITS );
		keepInReachOfTheKeyboard( dialog, body )();

		measureAs( OVERFLOWS );
		boxChanged();

		expect( stop().tabindex ).toBe( '0' );
	} );

	it( 'stops being one when its box changes and all of it fits again', () => {
		measureAs( OVERFLOWS );
		keepInReachOfTheKeyboard( dialog, body )();

		measureAs( FITS );
		boxChanged();

		expect( stop() ).toEqual( NOT_A_STOP );
	} );

	it( 'is read when asked, where the browser cannot watch its box', () => {
		delete window.ResizeObserver;
		measureAs( OVERFLOWS );

		const read = keepInReachOfTheKeyboard( dialog, body );
		expect( stop() ).toEqual( NOT_A_STOP );
		read();

		expect( stop().tabindex ).toBe( '0' );
	} );
} );
