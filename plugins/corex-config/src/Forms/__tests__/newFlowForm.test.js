/**
 * The "New flow" form is cleared once its flow exists (#313).
 *
 * It was reset through `event.currentTarget` after the request was answered. React has cleared
 * that by then, so the reset threw in a promise nobody was holding and the form kept what had
 * been typed.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { FlowList } from '../FlowList.js';

let container;
let root;

function show( onCreate ) {
	act( () => {
		root.render(
			<FlowList
				flows={ [] }
				status="ready"
				listed
				ownerId={ 1 }
				onLoad={ () => {} }
				onCreate={ onCreate }
				onSelect={ () => {} }
			/>
		);
	} );
}

async function createFlowNamed( name ) {
	const field = container.querySelector( '#corex-flow-name' );
	field.value = name;
	await act( async () => {
		container
			.querySelector( '.corex-flow-list__create form' )
			.dispatchEvent(
				new window.Event( 'submit', {
					bubbles: true,
					cancelable: true,
				} )
			);
	} );
	return field;
}

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	document.body.innerHTML = '';
} );

it( 'is emptied after the flow was created', async () => {
	const sent = [];
	show( async ( values ) => {
		sent.push( values.name );
		return true;
	} );

	const field = await createFlowNamed( 'Quote request' );

	expect( sent ).toEqual( [ 'Quote request' ] );
	expect( field.value ).toBe( '' );
} );

it( 'keeps what was typed when the flow was not created', async () => {
	show( async () => false );

	const field = await createFlowNamed( 'Quote request' );

	expect( field.value ).toBe( 'Quote request' );
} );
