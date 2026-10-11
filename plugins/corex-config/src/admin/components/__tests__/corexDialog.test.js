/**
 * CorexDialog (spec 103, FR-034 and FR-035).
 *
 * What the browser does for a modal `<dialog>` — the focus trap, the inert page, focus handed
 * back — is the browser's, and is exercised in the browser test of the export dialog. These are
 * the parts that are this component's: that it is opened modally where it is written, that it is
 * named, and that every way of leaving asks the caller instead of closing by itself.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';
import CorexDialog from '../CorexDialog.js';

let container;
let root;

/**
 * jsdom has the element and not its modal methods. These stand in for the browser's, and record
 * that they were called.
 */
beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	window.HTMLDialogElement.prototype.showModal = jest.fn( function () {
		this.setAttribute( 'open', '' );
	} );
	window.HTMLDialogElement.prototype.close = jest.fn( function () {
		this.removeAttribute( 'open' );
	} );

	container = document.createElement( 'div' );
	container.className = 'corex-admin';
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
} );

function show( props = {} ) {
	const onClose = jest.fn();
	act( () => {
		root.render(
			<CorexDialog
				title="Export submissions"
				onClose={ onClose }
				{ ...props }
			>
				<p>What to export</p>
			</CorexDialog>
		);
	} );

	return { onClose, dialog: container.querySelector( 'dialog' ) };
}

/**
 * A press of the mouse as the browser reports one: down where it began, up where it was let go,
 * and a click on the nearest element that holds both. Between the backdrop and anything in the
 * panel that element is the dialog, which is the backdrop.
 *
 * @param {Element} from Where the mouse went down.
 * @param {Element} to   Where it came up.
 */
function press( from, to ) {
	const event = ( type ) => new window.MouseEvent( type, { bubbles: true } );
	let holdsBoth = from;
	while ( ! holdsBoth.contains( to ) ) {
		holdsBoth = holdsBoth.parentElement;
	}

	act( () => {
		from.dispatchEvent( event( 'mousedown' ) );
		to.dispatchEvent( event( 'mouseup' ) );
		holdsBoth.dispatchEvent( event( 'click' ) );
	} );
}

it( 'opens modally, inside the admin scope it was written in', () => {
	const { dialog } = show();

	expect( dialog.showModal ).toHaveBeenCalledTimes( 1 );
	// Not moved to the end of <body>: a rule scoped under `.corex-admin` has to reach it.
	expect( dialog.closest( '.corex-admin' ) ).toBe( container );
} );

it( 'is named by its title', () => {
	const { dialog } = show();
	const title = document.getElementById(
		dialog.getAttribute( 'aria-labelledby' )
	);

	expect( title.textContent ).toBe( 'Export submissions' );
} );

it( 'asks to be closed on Escape, and does not close by itself', () => {
	const { dialog, onClose } = show();
	const cancel = new window.Event( 'cancel', { cancelable: true } );

	act( () => {
		dialog.dispatchEvent( cancel );
	} );

	expect( onClose ).toHaveBeenCalledTimes( 1 );
	expect( cancel.defaultPrevented ).toBe( true );
} );

it( 'asks to be closed by its close button and by a press outside its panel', () => {
	const { dialog, onClose } = show();
	const body = dialog.querySelector( '.corex-dialog__body' );

	act( () => {
		dialog.querySelector( '.corex-dialog__close' ).click();
	} );
	press( dialog, dialog );
	// A press inside the panel is not a press outside it.
	press( body, body );

	expect( onClose ).toHaveBeenCalledTimes( 2 );
} );

// The pane of a submission closed, with the note being written in it, when a press on one of its
// buttons was let go on the backdrop (2026-10-11). The browser test of the inbox has the mouse.
it.each( [
	[
		'begun in its panel and let go on the backdrop',
		'.corex-dialog__body',
		'dialog',
	],
	[
		'begun on the backdrop and let go in its panel',
		'dialog',
		'.corex-dialog__body',
	],
] )( 'is not left by a press %s', ( _, from, to ) => {
	const { onClose } = show();

	press( container.querySelector( from ), container.querySelector( to ) );

	expect( onClose ).not.toHaveBeenCalled();
} );

it( 'refuses to be left while something is in progress', () => {
	const { dialog, onClose } = show( { busy: true } );

	act( () => {
		dialog.dispatchEvent(
			new window.Event( 'cancel', { cancelable: true } )
		);
	} );
	press( dialog, dialog );

	expect( onClose ).not.toHaveBeenCalled();
	expect( dialog.querySelector( '.corex-dialog__close' ).disabled ).toBe(
		true
	);
} );

it( 'closes itself when it stops being shown, which is what hands focus back', () => {
	const { dialog } = show();

	act( () => root.render( null ) );

	expect( dialog.close ).toHaveBeenCalledTimes( 1 );
} );

it( 'keeps its actions in a footer of their own', () => {
	const { dialog } = show( {
		footer: <button type="button">Export</button>,
	} );

	expect(
		dialog.querySelector( '.corex-dialog__footer button' ).textContent
	).toBe( 'Export' );
} );
