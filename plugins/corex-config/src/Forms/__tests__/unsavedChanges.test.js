/**
 * Edits in the flow editor are never lost without a warning (spec 115, US-FLW-02).
 *
 * The editor and the flows hook run as they do on the screen. The network is the one thing
 * replaced: a small server that keeps one flow, gives each save a new version number and
 * records every request it is sent.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { FlowEditorPanel } from '../FlowEditorPanel.js';
import { useFlows } from '../useFlows.js';
import { PendingControl } from '../../admin/components/working.js';

const config = {
	restUrl: 'https://acme.test/wp-json/corex/v1/flows',
	nonce: 'n',
};

const QUESTION = 'Save your changes before leaving?';

let server;
let sent;
let studio;
let root;
let container;

const ok = ( data ) => ( { envelope: { ok: true, data } } );

function flowDetail() {
	return {
		flow: {
			id: 7,
			slug: 'contact',
			name: 'Contact',
			state: server.state,
			current_draft_version: server.version,
		},
		versions: [
			{
				version_number: server.version,
				checksum: `sum-${ server.version }`,
				configuration: {
					schema: [
						{
							uuid: 'field-name',
							key: 'name',
							label: server.label,
							type: 'text',
							options: [],
						},
					],
					validation: {},
					routing: {
						rules: [],
						fallback: { type: 'flow_owner', config: {} },
					},
					email_routes: [],
					success: { type: 'inline', message: 'Thanks' },
				},
			},
		],
	};
}

function fakeApi() {
	const record = ( method, url, body ) => sent.push( { method, url, body } );
	return {
		get: async ( url ) => {
			record( 'GET', url );
			if ( url.endsWith( '/extensions' ) ) {
				return ok( { field_types: [] } );
			}
			if ( url.endsWith( '/flows/7' ) ) {
				return ok( flowDetail() );
			}
			return ok( { flows: [ flowDetail().flow ] } );
		},
		patch: async ( url, body ) => {
			record( 'PATCH', url, body );
			if ( server.refuseSave ) {
				return {
					envelope: {
						ok: false,
						message: 'Somebody else saved it first.',
					},
				};
			}
			server.version += 1;
			server.label = body.configuration.schema[ 0 ].label;
			return ok( {} );
		},
		post: async ( url, body ) => {
			record( 'POST', url, body );
			return ok( { stages: [] } );
		},
	};
}

function Harness() {
	studio = useFlows( config );
	const { state } = studio;
	return (
		<PendingControl.Provider value={ studio.pending }>
			{ state.draft && state.extensions ? (
				<FlowEditorPanel
					studio={ studio }
					onBack={ () => studio.dispatch( { type: 'cleared' } ) }
				/>
			) : (
				<p>Flow catalog</p>
			) }
		</PendingControl.Provider>
	);
}

/** Lets every request in flight be answered and the screen catch up. */
async function settle() {
	await act( async () => {
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	} );
}

function button( name, scope = container ) {
	return Array.from( scope.querySelectorAll( 'button' ) ).find(
		( candidate ) => candidate.textContent.trim() === name
	);
}

function stage( name ) {
	return Array.from(
		container.querySelectorAll( '.corex-flow-editor__stages button' )
	).find(
		( candidate ) =>
			candidate.querySelector( 'strong' ).textContent === name
	);
}

const question = () => container.querySelector( 'dialog' );
const labelInput = () => container.querySelector( '#corex-flow-field-label' );
const saveState = () =>
	container.querySelector( '[role="status"]' ).textContent;
const requestsAfterOpening = () =>
	sent.slice( 3 ).map( ( { method, url } ) => `${ method } ${ url }` );

async function press( control ) {
	await act( async () => {
		control.focus();
		control.click();
	} );
	await settle();
}

async function typeLabel( value ) {
	const field = labelInput();
	const setter = Object.getOwnPropertyDescriptor(
		window.HTMLInputElement.prototype,
		'value'
	).set;
	await act( async () => {
		setter.call( field, value );
		field.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
	} );
}

async function open( state = 'draft' ) {
	server.state = state;
	act( () => {
		root.render( <Harness /> );
	} );
	await settle();
	await act( async () => {
		await studio.select( 7 );
	} );
	await settle();
}

function leavePage() {
	const event = new window.Event( 'beforeunload', { cancelable: true } );
	window.dispatchEvent( event );
	return event.defaultPrevented;
}

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	window.HTMLDialogElement.prototype.showModal = function () {
		this.setAttribute( 'open', '' );
	};
	window.HTMLDialogElement.prototype.close = function () {
		this.removeAttribute( 'open' );
	};
} );

beforeEach( () => {
	server = { version: 3, label: 'Name', state: 'draft', refuseSave: false };
	sent = [];
	window.Corex = { api: fakeApi() };
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	document.body.innerHTML = '';
	delete window.Corex;
} );

describe( 'the save state in the header', () => {
	it( 'says unsaved once the flow differs, and saved when the old value is typed back', async () => {
		await open();
		expect( saveState() ).toBe( 'All changes saved' );

		await typeLabel( 'Full name' );
		expect( saveState() ).toBe( 'Unsaved changes' );

		await typeLabel( 'Name' );
		expect( saveState() ).toBe( 'All changes saved' );
	} );

	it( 'says saved after Save draft succeeds', async () => {
		await open();
		await typeLabel( 'Full name' );

		await press( button( 'Save draft' ) );

		expect( server.label ).toBe( 'Full name' );
		expect( saveState() ).toBe( 'All changes saved' );
	} );
} );

describe( 'Back to flows', () => {
	it( 'leaves at once when nothing is unsaved', async () => {
		await open();

		await press( button( 'Back to flows' ) );

		expect( question() ).toBeNull();
		expect( container.textContent ).toContain( 'Flow catalog' );
	} );

	it( 'asks first, and Cancel keeps the edits and gives focus back to Back', async () => {
		await open();
		await typeLabel( 'Full name' );

		await press( button( 'Back to flows' ) );
		const dialog = question();
		expect( dialog.textContent ).toContain( QUESTION );
		expect( button( 'Save', dialog ) ).toBeDefined();
		expect( button( 'Discard', dialog ) ).toBeDefined();
		expect( document.activeElement ).toBe( button( 'Save', dialog ) );

		await press( button( 'Cancel', dialog ) );

		expect( question() ).toBeNull();
		expect( labelInput().value ).toBe( 'Full name' );
		expect( document.activeElement ).toBe( button( 'Back to flows' ) );
		expect( requestsAfterOpening() ).toEqual( [] );
	} );

	it( 'treats Escape as Cancel', async () => {
		await open();
		await typeLabel( 'Full name' );
		await press( button( 'Back to flows' ) );

		await act( async () => {
			question().dispatchEvent(
				new window.Event( 'cancel', { cancelable: true } )
			);
		} );

		expect( question() ).toBeNull();
		expect( labelInput().value ).toBe( 'Full name' );
	} );

	it( 'leaves without saving on Discard', async () => {
		await open();
		await typeLabel( 'Full name' );
		await press( button( 'Back to flows' ) );

		await press( button( 'Discard', question() ) );

		expect( container.textContent ).toContain( 'Flow catalog' );
		expect( server.label ).toBe( 'Name' );
		expect( requestsAfterOpening() ).toEqual( [] );
	} );

	it( 'saves and then leaves on Save', async () => {
		await open();
		await typeLabel( 'Full name' );
		await press( button( 'Back to flows' ) );

		await press( button( 'Save', question() ) );

		expect( server.label ).toBe( 'Full name' );
		expect( container.textContent ).toContain( 'Flow catalog' );
	} );
} );

describe( 'actions on the saved version', () => {
	it( 'publishes at once, on the version it has, when nothing is unsaved', async () => {
		await open();

		await press( button( 'Publish' ) );

		expect( question() ).toBeNull();
		expect( sent[ 3 ] ).toMatchObject( {
			method: 'POST',
			url: `${ config.restUrl }/7/publish`,
			body: { expected_version: 3 },
		} );
	} );

	it( 'saves first and publishes the new version, without a Discard', async () => {
		await open();
		await typeLabel( 'Full name' );

		await press( button( 'Publish' ) );
		expect( question().textContent ).toContain( QUESTION );
		expect( button( 'Discard', question() ) ).toBeUndefined();

		await press( button( 'Save', question() ) );

		expect( requestsAfterOpening().slice( 0, 3 ) ).toEqual( [
			`PATCH ${ config.restUrl }/7`,
			`GET ${ config.restUrl }/7`,
			`POST ${ config.restUrl }/7/publish`,
		] );
		expect( sent[ 5 ].body ).toEqual( { expected_version: 4 } );
	} );

	it.each( [
		[ 'Move to draft', 'unpublish' ],
		[ 'Close', 'close' ],
	] )(
		'saves first and runs %s on the new version',
		async ( label, action ) => {
			await open( 'published' );
			await typeLabel( 'Full name' );

			await press( button( label ) );
			await press( button( 'Save', question() ) );

			expect( sent[ 5 ] ).toMatchObject( {
				method: 'POST',
				url: `${ config.restUrl }/7/${ action }`,
				body: { expected_version: 4 },
			} );
		}
	);

	it( 'does nothing on Cancel', async () => {
		await open();
		await typeLabel( 'Full name' );
		await press( button( 'Publish' ) );

		await press( button( 'Cancel', question() ) );

		expect( requestsAfterOpening() ).toEqual( [] );
		expect( saveState() ).toBe( 'Unsaved changes' );
	} );

	it( 'does not publish when the save fails, and keeps the edits and the reason', async () => {
		server.refuseSave = true;
		await open();
		await typeLabel( 'Full name' );
		await press( button( 'Publish' ) );

		await press( button( 'Save', question() ) );

		expect( requestsAfterOpening() ).toEqual( [
			`PATCH ${ config.restUrl }/7`,
		] );
		expect( question() ).toBeNull();
		expect( labelInput().value ).toBe( 'Full name' );
		expect( saveState() ).toBe( 'Unsaved changes' );
		expect( studio.state.message ).toBe( 'Somebody else saved it first.' );
	} );

	it( 'saves first and runs the marked test on the version it then names', async () => {
		await open();
		await typeLabel( 'Full name' );
		await press( stage( 'Test' ) );
		expect( container.textContent ).toContain(
			'Tests the saved draft, version 3'
		);

		await press( button( 'Run marked test' ) );
		await press( button( 'Save', question() ) );

		expect( sent[ 5 ] ).toMatchObject( {
			method: 'POST',
			url: `${ config.restUrl }/7/test`,
			body: { expected_version: 4 },
		} );
		expect( container.textContent ).toContain(
			'Tests the saved draft, version 4'
		);
	} );
} );

describe( 'leaving the page', () => {
	it( 'warns only while there are unsaved changes', async () => {
		await open();
		expect( leavePage() ).toBe( false );

		await typeLabel( 'Full name' );
		expect( leavePage() ).toBe( true );

		await press( button( 'Save draft' ) );
		expect( leavePage() ).toBe( false );
	} );

	it( 'stops warning once the editor is left', async () => {
		await open();
		await typeLabel( 'Full name' );
		await press( button( 'Back to flows' ) );

		await press( button( 'Discard', question() ) );

		expect( leavePage() ).toBe( false );
	} );
} );
