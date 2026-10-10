/**
 * Running a template's health checks leaves the rest of the studio as it was (#313).
 *
 * When the checks came back the studio was "loaded" again from its own state. That state is not
 * what the server sends: it names the list `recentTestSends`, the server `recent_test_sends`,
 * and read a second time the list came out empty. "Recent test sends" on the overview went blank
 * until the page was loaded again.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { useEmailStudio } from '../useEmailStudio.js';

const config = {
	restUrl: 'https://acme.test/wp-json/corex/v1/email-studio',
	nonce: 'n',
};

const template = { id: 4, slug: 'welcome', draft_version: 2 };

let requests;
let studio;
let root;

function Probe() {
	studio = useEmailStudio( config, () => {} );
	return null;
}

async function answer( index, data ) {
	await act( async () => {
		requests[ index ].resolve( { envelope: { ok: true, data } } );
	} );
}

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	requests = [];
	window.Corex = {
		api: {
			get: ( url ) =>
				new Promise( ( resolve ) => {
					requests.push( { url, resolve } );
				} ),
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

it( 'keeps the recent test sends when a template’s health checks come back', async () => {
	await answer( 0, {
		templates: [ template ],
		recent_test_sends: [ { attempt_id: 9, status: 'sent' } ],
	} );
	act( () => {
		studio.selection.chooseTemplate( template );
	} );
	await answer( 1, { template, versions: [] } );

	act( () => {
		studio.health.runHealth();
	} );

	expect( studio.state.pending ).toBe( 'health' );

	await answer( 2, { health: [ { key: 'subject', status: 'pass' } ] } );

	expect( studio.health.health ).toEqual( [
		{ key: 'subject', status: 'pass' },
	] );
	expect( studio.state.data.recentTestSends ).toEqual( [
		{ attempt_id: 9, status: 'sent' },
	] );
	expect( studio.state.pending ).toBe( '' );
} );
