/**
 * The New flow form asks for the emails CoreX ships (spec 120, FR-020): a flow made from nothing
 * starts with "Admin notification" and "Contact confirmation". The server gives them only when
 * asked, so a flow created from a configuration of its own (a copy, an import) does not gain them.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { useFlows } from '../useFlows.js';

let flows;
let posted;
let root;

function Probe() {
	flows = useFlows( {
		restUrl: 'https://acme.test/wp-json/corex/v1/flows',
		nonce: 'n',
	} );
	return null;
}

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	posted = [];
	window.Corex = {
		api: {
			get: () => new Promise( () => {} ),
			post: ( url, body ) => {
				posted.push( { url, body } );
				return new Promise( () => {} );
			},
		},
	};
	root = createRoot(
		document.body.appendChild( document.createElement( 'div' ) )
	);
	act( () => {
		root.render( <Probe /> );
	} );
} );

afterEach( () => {
	act( () => root.unmount() );
	document.body.innerHTML = '';
	delete window.Corex;
} );

test( 'a flow made on the New flow form asks for the default emails', () => {
	act( () => {
		flows.create( {
			slug: 'quote',
			name: 'Quote',
			successMessage: 'Thanks.',
		} );
	} );

	expect( posted ).toHaveLength( 1 );
	expect( posted[ 0 ].body.default_emails ).toBe( true );
	expect( posted[ 0 ].body.configuration.email_routes ).toEqual( [] );
} );
