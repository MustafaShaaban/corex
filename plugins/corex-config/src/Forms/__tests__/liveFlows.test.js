/**
 * Flows that stay live and route to people (spec 122): what the editor offers to publish, the
 * state it shows, the target and recipient pickers, availability, and the list's words.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import {
	copyVersionEndpoint,
	displayState,
	flowReducer,
	initialFlowState,
	publishState,
	requestedFlow,
} from '../flowEditor.js';
import { catalogRows } from '../formCatalog.js';
import { FlowEditorPanel } from '../FlowEditorPanel.js';
import { PendingControl } from '../../admin/components/working.js';
import {
	ownerOptions,
	targetChoice,
	targetFromChoice,
	targetOptions,
} from '../tabs/RoutingTab.js';
import {
	firstRecipient,
	recipientKind,
	ROUTED_OWNER,
} from '../tabs/EmailsTab.js';
import {
	availabilityForm,
	availabilityPayload,
} from '../tabs/AvailabilityTab.js';

const CATALOG = {
	routing_targets: [
		{ key: 'user', label: 'User' },
		{ key: 'team', label: 'Team' },
		{ key: 'flow_owner', label: 'Flow owner' },
		{ key: 'extension', label: 'By region', extension: 'by_region' },
	],
	people: [ { id: 3, name: 'Omar' } ],
	teams: [ { key: 'sales', name: 'Sales', members: [ 3 ] } ],
	roles: [ { key: 'editor', name: 'Editor' } ],
};

describe( 'publishing a live flow', () => {
	const draft = ( state, published, saved ) => ( {
		flow: { state, published_version: published },
		version: { version_number: saved },
	} );

	it.each( [
		[ 'a draft', draft( 'draft', 0, 1 ), 'publish' ],
		[
			'a live flow with a newer saved draft',
			draft( 'published', 2, 3 ),
			'changes',
		],
		[
			'a live flow with nothing new',
			draft( 'published', 3, 3 ),
			'current',
		],
		[ 'a closed flow', draft( 'closed', 3, 3 ), '' ],
	] )( 'offers the right command for %s', ( _name, value, expected ) => {
		expect( publishState( value ) ).toBe( expected );
	} );

	it( 'shows what a flow does now: expired by its dates, not open yet', () => {
		expect(
			displayState( { state: 'published', status: { state: 'expired' } } )
		).toBe( 'expired' );
		expect(
			displayState( {
				state: 'published',
				status: { state: 'scheduled' },
			} )
		).toBe( 'scheduled' );
		expect(
			displayState( { state: 'published', status: { state: 'open' } } )
		).toBe( 'published' );
		expect( displayState( { state: 'closed' } ) ).toBe( 'closed' );
	} );

	it( 'copies a version through its own address, and opens a flow an address names', () => {
		expect(
			copyVersionEndpoint(
				'https://acme.test/wp-json/corex/v1/flows',
				7,
				2
			)
		).toBe( 'https://acme.test/wp-json/corex/v1/flows/7/versions/2/copy' );
		expect( requestedFlow( '?page=corex-forms&corex_flow=12' ) ).toBe( 12 );
		expect( requestedFlow( '?page=corex-forms' ) ).toBe( 0 );
	} );

	it( 'keeps the edits on screen when only the flow record changed', () => {
		const state = {
			...initialFlowState,
			draft: {
				flow: { id: 7, availability: {} },
				configuration: { schema: [ 'edited' ] },
			},
		};

		const next = flowReducer( state, {
			type: 'flow_changed',
			payload: { id: 7, availability: { max_responses: 3 } },
		} );

		expect( next.draft.configuration.schema ).toEqual( [ 'edited' ] );
		expect( next.draft.flow.availability.max_responses ).toBe( 3 );
	} );
} );

describe( 'the editor header', () => {
	let container;
	let root;

	function render( flow, version = 3 ) {
		const studio = {
			state: {
				...initialFlowState,
				status: 'ready',
				draft: {
					flow: { id: 7, slug: 'apply', name: 'Apply', ...flow },
					version: { version_number: version, checksum: 'x' },
					configuration: { schema: [] },
					versions: [],
				},
				saved: JSON.stringify( { schema: [] } ),
				extensions: {},
			},
			dispatch: () => {},
		};
		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
		act( () => {
			root.render(
				<PendingControl.Provider value="">
					<FlowEditorPanel studio={ studio } onBack={ () => {} } />
				</PendingControl.Provider>
			);
		} );
		return container.querySelector( '.corex-flow-editor__toolbar' )
			.textContent;
	}

	beforeAll( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
	} );

	it( 'says a live flow with nothing new is up to date, and offers nothing to publish', () => {
		const header = render( {
			state: 'published',
			published_version: 3,
			publications: [],
		} );

		expect( header ).toContain( 'Live version is up to date' );
		expect( header ).toContain( 'Published' );
		expect( header ).not.toContain( 'Publish changes' );
		expect( header ).toContain( 'Unpublish' );
		expect( header ).not.toContain( 'Move to draft' );
	} );

	it( 'offers Publish changes and names who published the live version', () => {
		const header = render( {
			state: 'published',
			published_version: 2,
			publications: [
				{
					version: 2,
					at: '2026-10-21T10:00:00+00:00',
					by: 4,
					by_name: 'Sara Ahmed',
				},
			],
		} );

		expect( header ).toContain( 'Publish changes' );
		expect( header ).toMatch(
			/Live: version 2, published .* by Sara Ahmed/
		);
	} );
} );

describe( 'routing pickers', () => {
	it( 'offers the targets by name, an extension as its own choice', () => {
		expect(
			targetOptions( CATALOG.routing_targets, { type: 'user' } ).map(
				( option ) => option.value
			)
		).toEqual( [ 'user', 'team', 'flow_owner', 'extension:by_region' ] );
		expect(
			targetChoice( {
				type: 'extension',
				config: { value: 'by_region' },
			} )
		).toBe( 'extension:by_region' );
		expect( targetFromChoice( 'extension:by_region' ) ).toEqual( {
			type: 'extension',
			config: { value: 'by_region' },
		} );
		expect( targetFromChoice( 'team' ) ).toEqual( {
			type: 'team',
			config: {},
		} );
	} );

	it( 'keeps a target the editor no longer offers visible, so it can be replaced', () => {
		const options = targetOptions( CATALOG.routing_targets, {
			type: 'department',
			config: { value: 'x' },
		} );

		expect( options.at( -1 ) ).toEqual( {
			value: 'department',
			label: 'No longer offered: choose another',
		} );
	} );

	it( 'names people, teams and roles, never by a stored key alone', () => {
		expect( ownerOptions( 'user', CATALOG ).slice( 1 ) ).toEqual( [
			{ value: '3', label: 'Omar' },
		] );
		expect( ownerOptions( 'team', CATALOG ).slice( 1 ) ).toEqual( [
			{ value: 'sales', label: 'Sales' },
		] );
		expect( ownerOptions( 'role', CATALOG ).slice( 1 ) ).toEqual( [
			{ value: 'editor', label: 'Editor' },
		] );
	} );
} );

describe( 'the recipient picker', () => {
	const schema = [
		{ key: 'email', label: 'Email', type: 'email' },
		{ key: 'name', label: 'Name', type: 'text' },
	];

	it.each( [
		[ ROUTED_OWNER, 'routed' ],
		[ 'email', 'submitter' ],
		[ 'submission.email', 'submitter' ],
		[ 'team@example.test', 'address' ],
		[ '', 'address' ],
	] )( 'reads %p as %s', ( mapping, kind ) => {
		expect( recipientKind( mapping, schema ) ).toBe( kind );
	} );

	it( 'starts a new choice with the routed owner or the first email question', () => {
		expect( firstRecipient( 'routed', schema ) ).toBe( ROUTED_OWNER );
		expect( firstRecipient( 'submitter', [ schema[ 0 ] ] ) ).toBe(
			'email'
		);
		expect( firstRecipient( 'address', [] ) ).toBe( '' );
	} );
} );

describe( 'availability', () => {
	it( 'fills the fields from what is stored, in the site’s local time', () => {
		expect(
			availabilityForm( {
				opens_at_local: '2026-11-01T09:00',
				max_responses: 200,
				alternative: { type: 'email', email: 'jobs@example.test' },
			} )
		).toEqual( {
			opens_at: '2026-11-01T09:00',
			closes_at: '',
			max_responses: '200',
			closed_message: '',
			alternative: {
				type: 'email',
				label: '',
				url: '',
				email: 'jobs@example.test',
			},
		} );
	} );

	it( 'sends a cap as a number, and no cap as nothing', () => {
		expect(
			availabilityPayload( availabilityForm( { max_responses: 3 } ) )
				.max_responses
		).toBe( 3 );
		expect(
			availabilityPayload( availabilityForm( {} ) ).max_responses
		).toBeNull();
	} );
} );

describe( 'the flow list', () => {
	it( 'names the fallback in words and says why a flow is expired', () => {
		const [ row ] = catalogRows(
			[
				{
					id: 4,
					slug: 'survey',
					name: 'Survey',
					state: 'published',
					status: {
						state: 'expired',
						label: 'Reached 200 responses',
					},
					routing_target: 'flow_owner',
					routing_owner: 'Form owner (Sara Ahmed)',
				},
			],
			[]
		);

		expect( row.state ).toBe( 'expired' );
		expect( row.reason ).toBe( 'Reached 200 responses' );
		expect( row.routingOwner ).toBe( 'Form owner (Sara Ahmed)' );
	} );
} );
