/**
 * CorexCopyField (spec 112, FR-023): a value somebody has to copy, set left to right whatever the
 * page's direction, with a button that copies it and says what happened.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';
import CorexCopyField from '../CorexCopyField.js';

let container;
let root;

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	container = document.createElement( 'div' );
	container.setAttribute( 'dir', 'rtl' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
	delete window.navigator.clipboard;
	delete document.execCommand;
} );

function render( props ) {
	act( () => {
		root.render(
			<CorexCopyField
				label="Command"
				value="wp corex security reset-login"
				{ ...props }
			/>
		);
	} );

	return {
		input: container.querySelector( 'textarea' ),
		button: container.querySelector( 'button' ),
		status: container.querySelector( '[role="status"]' ),
	};
}

async function press( button ) {
	await act( async () => {
		button.click();
	} );
}

it( 'shows the value read-only, left to right, named by its label', () => {
	const { input } = render();

	expect( input.value ).toBe( 'wp corex security reset-login' );
	expect( input.readOnly ).toBe( true );
	expect( input.getAttribute( 'dir' ) ).toBe( 'ltr' );
	expect( container.querySelector( 'label' ).htmlFor ).toBe( input.id );
	expect( container.querySelector( 'label' ).textContent ).toBe( 'Command' );
} );

it( 'copies through the Clipboard API and says so', async () => {
	const writeText = jest.fn( () => Promise.resolve() );
	Object.defineProperty( window.navigator, 'clipboard', {
		value: { writeText },
		configurable: true,
	} );
	const { button, status } = render();

	await press( button );

	expect( writeText ).toHaveBeenCalledWith( 'wp corex security reset-login' );
	expect( status.textContent ).toBe( 'Copied.' );
} );

it( 'falls back to copying the selection where the Clipboard API refuses', async () => {
	Object.defineProperty( window.navigator, 'clipboard', {
		value: { writeText: () => Promise.reject( new Error( 'denied' ) ) },
		configurable: true,
	} );
	document.execCommand = jest.fn( () => true );
	const { button, status } = render();

	await press( button );

	expect( document.execCommand ).toHaveBeenCalledWith( 'copy' );
	expect( status.textContent ).toBe( 'Copied.' );
} );

it( 'leaves the value selected and says how to copy it when nothing could', async () => {
	document.execCommand = jest.fn( () => false );
	const { input, button, status } = render();

	await press( button );

	expect( document.activeElement ).toBe( input );
	expect( input.selectionStart ).toBe( 0 );
	expect( input.selectionEnd ).toBe( input.value.length );
	expect( status.textContent ).toContain( 'copy it from the keyboard' );
} );
