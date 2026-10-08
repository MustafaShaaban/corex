/**
 * Which Forms & Flows button says it is working, and for how long (spec 108, story 3).
 *
 * Saving a flow is a write and then a read of what was written. One status disabled every button
 * on the screen through both, and nothing said which had been pressed. The button that was
 * pressed works until the screen is current again: through the read, not only the write.
 *
 * The network is the one thing replaced: each request is answered by the test, when it chooses.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { useFlows } from '../useFlows.js';

const config = {
	restUrl: 'https://acme.test/wp-json/corex/v1/flows',
	nonce: 'n',
};

/** A flow's detail, as `GET flows/{id}` answers. */
const detail = {
	flow: {
		id: 7,
		slug: 'contact',
		name: 'Contact',
		state: 'draft',
		current_draft_version: 3,
	},
	versions: [ { version_number: 3, checksum: 'abc', configuration: {} } ],
};

let requests;
let studio;
let root;

function Probe() {
	studio = useFlows( config );
	return null;
}

const ok = ( data ) => ( { envelope: { ok: true, data } } );

async function answer( index, data ) {
	await act( async () => {
		requests[ index ].resolve( ok( data ) );
	} );
}

beforeEach( async () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	requests = [];
	const send = ( method ) => ( url ) =>
		new Promise( ( resolve ) => {
			requests.push( { method, url, resolve } );
		} );
	window.Corex = {
		api: {
			get: send( 'GET' ),
			post: send( 'POST' ),
			patch: send( 'PATCH' ),
		},
	};

	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
	act( () => {
		root.render( <Probe /> );
	} );
	// The list and its extensions, read when the screen opens; then the flow is opened.
	await answer( 0, { flows: [ detail.flow ] } );
	await answer( 1, {} );
	act( () => {
		studio.select( 7 );
	} );
	await answer( 2, detail );
} );

afterEach( () => {
	act( () => root.unmount() );
	document.body.innerHTML = '';
	delete window.Corex;
} );

it( 'names no control while nothing is out', () => {
	expect( studio.pending ).toBe( '' );
} );

it( 'keeps "Save draft" working through the read that follows the write', async () => {
	act( () => {
		studio.saveDraft();
	} );
	expect( studio.pending ).toBe( 'saveDraft' );

	await answer( 3, {} );
	expect( requests[ 3 ].method ).toBe( 'PATCH' );
	expect( requests[ 4 ].method ).toBe( 'GET' );
	expect( studio.pending ).toBe( 'saveDraft' );

	await answer( 4, detail );
	expect( studio.pending ).toBe( '' );
} );

it( 'stops working when the write is refused', async () => {
	act( () => {
		studio.publish();
	} );
	expect( studio.pending ).toBe( 'publish' );

	await act( async () => {
		requests[ 3 ].resolve( {
			envelope: { ok: false, message: 'Somebody else saved it first.' },
		} );
	} );

	expect( studio.pending ).toBe( '' );
	expect( studio.state.message ).toBe( 'Somebody else saved it first.' );
} );
