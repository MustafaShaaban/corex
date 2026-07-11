import {
	addField,
	buildFlowListUrl,
	draftFromDetail,
	flowEndpoint,
	flowReducer,
	initialFlowState,
	moveField,
	normalizeFlowPayload,
	removeField,
	stageStatus,
	updateField,
} from '../flowEditor.js';

const draftFixture = () => ( {
	flow: { id: 7, state: 'draft', current_draft_version: 1 },
	version: { version_number: 1, checksum: 'abc' },
	configuration: {
		schema: [
			{ uuid: 'field-name', key: 'name', label: 'Name', type: 'text' },
			{ uuid: 'field-email', key: 'email', label: 'Email', type: 'email' },
		],
		validation: { email: [ 'required', 'email' ] },
		routing: { rules: [], fallback: { type: 'flow_owner', config: {} } },
		email_routes: [],
		success: { type: 'inline', message: 'Thanks' },
		placement_snapshot: { type: 'none' },
	},
} );

describe( 'flow editor state', () => {
	it( 'normalizes absent collections and keeps server values truthful', () => {
		expect( normalizeFlowPayload( {} ) ).toEqual( {
			flows: [],
			extensions: null,
		} );
		expect( normalizeFlowPayload( { flows: [ { id: 1 } ], extensions: { field_types: [] } } ) ).toEqual( {
			flows: [ { id: 1 } ],
			extensions: { field_types: [] },
		} );
	} );

	it( 'moves through load selection draft mutation and failure immutably', () => {
		const loading = flowReducer( initialFlowState, { type: 'load' } );
		const loaded = flowReducer( loading, { type: 'loaded', payload: { flows: [ { id: 7 } ] } } );
		const draft = draftFixture();
		const selected = flowReducer( loaded, { type: 'selected', payload: draft } );
		const changed = flowReducer( selected, {
			type: 'draft_changed',
			payload: { ...draft, configuration: { ...draft.configuration, success: { type: 'inline', message: 'Updated' } } },
		} );
		const failed = flowReducer( changed, { type: 'failed', message: 'Conflict' } );

		expect( loading.status ).toBe( 'loading' );
		expect( loaded.flows ).toEqual( [ { id: 7 } ] );
		expect( selected.selected ).toBe( 7 );
		expect( changed.draft.configuration.success.message ).toBe( 'Updated' );
		expect( selected.draft.configuration.success.message ).toBe( 'Thanks' );
		expect( failed ).toMatchObject( { status: 'error', message: 'Conflict' } );
	} );
} );

describe( 'flow field editing', () => {
	beforeEach( () => {
		Object.defineProperty( globalThis, 'crypto', {
			configurable: true,
			value: { randomUUID: () => 'field-phone' },
		} );
	} );

	it( 'adds and updates a field without mutating the previous draft', () => {
		const original = draftFixture();
		const added = addField( original, 'phone' );
		const updated = updateField( added, 'field-phone', {
			key: 'phone',
			label: 'Phone',
			required: true,
		} );

		expect( original.configuration.schema ).toHaveLength( 2 );
		expect( added.configuration.schema[ 2 ] ).toMatchObject( {
			uuid: 'field-phone',
			type: 'phone',
			key: 'phone',
		} );
		expect( updated.configuration.schema[ 2 ] ).toMatchObject( {
			label: 'Phone',
			required: true,
		} );
	} );

	it( 'reorders by stable uuid and removes only the requested field', () => {
		const original = draftFixture();
		const moved = moveField( original, 'field-email', 'up' );
		const removed = removeField( moved, 'field-name' );

		expect( moved.configuration.schema.map( ( field ) => field.uuid ) ).toEqual( [
			'field-email',
			'field-name',
		] );
		expect( removed.configuration.schema.map( ( field ) => field.uuid ) ).toEqual( [ 'field-email' ] );
		expect( original.configuration.schema.map( ( field ) => field.uuid ) ).toEqual( [
			'field-name',
			'field-email',
		] );
	} );
} );

describe( 'flow editor contracts', () => {
	it( 'derives stage readiness only from persisted draft configuration', () => {
		expect( stageStatus( draftFixture() ) ).toEqual( {
			form: 'ready',
			validation: 'ready',
			routing: 'ready',
			emails: 'incomplete',
			success: 'ready',
		} );
	} );

	it( 'builds only declared flow endpoints', () => {
		expect( flowEndpoint( '/corex/v1/flows' ) ).toBe( '/corex/v1/flows' );
		expect( flowEndpoint( '/corex/v1/flows/', 7 ) ).toBe( '/corex/v1/flows/7' );
		expect( flowEndpoint( '/corex/v1/flows/', 7, 'publish' ) ).toBe( '/corex/v1/flows/7/publish' );
		expect( () => flowEndpoint( '/corex/v1/flows', 7, 'delete' ) ).toThrow( 'Unknown flow action' );
	} );

	it( 'builds encoded server filters and selects the current immutable draft', () => {
		expect( buildFlowListUrl( '/corex/v1/flows', 'sales & support', 'draft' ) ).toBe(
			'/corex/v1/flows?search=sales+%26+support&state=draft'
		);
		expect( draftFromDetail( {
			flow: { id: 7, current_draft_version: 2 },
			versions: [
				{ version_number: 1, configuration: { schema: [] } },
				{ version_number: 2, configuration: { schema: [ { uuid: 'current' } ] } },
			],
		} ).configuration.schema ).toEqual( [ { uuid: 'current' } ] );
	} );
} );
