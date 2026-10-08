/**
 * Tests for useDebounced (spec 108, FR-012): a search box asks the server when typing pauses.
 *
 * The Data search box sent a request for every letter. Ten letters were ten requests, and each
 * one took the rows away to say "loading".
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { useDebounced } from '../useDebounced.js';

let root;
let asked;
let ask;

function Probe() {
	ask = useDebounced( ( value ) => asked.push( value ), 300 );
	return null;
}

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	jest.useFakeTimers();
	asked = [];
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
	act( () => {
		root.render( <Probe /> );
	} );
} );

afterEach( () => {
	act( () => root.unmount() );
	jest.useRealTimers();
	document.body.innerHTML = '';
} );

it( 'asks once, with the last value, when ten letters are typed without a pause', () => {
	for ( const typed of [
		'c',
		'co',
		'con',
		'cont',
		'conta',
		'contac',
		'contact',
		'contact ',
		'contact f',
		'contact fo',
	] ) {
		ask( typed );
		jest.advanceTimersByTime( 100 );
	}
	expect( asked ).toEqual( [] );

	jest.advanceTimersByTime( 300 );

	expect( asked ).toEqual( [ 'contact fo' ] );
} );

it( 'asks again after a pause', () => {
	ask( 'con' );
	jest.advanceTimersByTime( 300 );
	ask( 'contact' );
	jest.advanceTimersByTime( 300 );

	expect( asked ).toEqual( [ 'con', 'contact' ] );
} );

it( 'asks nothing after the screen it belonged to has gone', () => {
	ask( 'con' );
	act( () => root.unmount() );
	jest.advanceTimersByTime( 1000 );

	expect( asked ).toEqual( [] );

	// The afterEach unmounts again; give it something to unmount.
	const container = document.createElement( 'div' );
	root = createRoot( container );
} );
