import {
	accessEndpoint,
	accessReducer,
	buildRoleChanges,
	initialAccessState,
	normalizeAccessPayload,
} from '../accessState.js';

const payload = {
	roles: [ { key: 'editor', name: 'Editor' } ],
	rows: [
		{
			key: 'corex_manage_forms',
			label: 'Manage forms',
			group: 'forms',
			risk: 'sensitive',
			locked: false,
			cells: {
				editor: { effect: 'inherit', editable: true, reason: null },
			},
		},
		{
			key: 'corex_manage_admin',
			label: 'Access CoreX admin',
			group: 'admin',
			risk: 'critical',
			locked: true,
			cells: {
				editor: {
					effect: 'inherit',
					editable: false,
					reason: 'locked_definition',
				},
			},
		},
	],
	conflicts: [ 'Members' ],
	nativeCapabilitiesEditable: false,
};

describe( 'Access client state', () => {
	it( 'builds stable Access REST endpoints', () => {
		expect( accessEndpoint( '/corex/v1', 'access/catalog' ) ).toBe(
			'/corex/v1/access/catalog'
		);
		expect(
			accessEndpoint( '/corex/v1/', '/access/roles/editor/apply' )
		).toBe( '/corex/v1/access/roles/editor/apply' );
	} );

	it( 'normalizes editable CoreX matrix payloads without inventing native capability edits', () => {
		const normalized = normalizeAccessPayload( payload );

		expect( normalized.conflicts ).toEqual( [ 'Members' ] );
		expect( normalized.nativeCapabilitiesEditable ).toBe( false );
		expect( normalized.rows[ 0 ].cells.editor ).toMatchObject( {
			effect: 'inherit',
			editable: true,
		} );
		expect( normalized.rows[ 1 ].cells.editor.reason ).toBe(
			'locked_definition'
		);
	} );

	it( 'stages only editable ability changes and ignores locked definitions', () => {
		let state = accessReducer( initialAccessState(), {
			type: 'loaded',
			payload,
		} );
		state = accessReducer( state, {
			type: 'setEffect',
			role: 'editor',
			ability: 'corex_manage_forms',
			effect: 'allow',
		} );
		state = accessReducer( state, {
			type: 'setEffect',
			role: 'editor',
			ability: 'corex_manage_admin',
			effect: 'deny',
		} );

		expect( state.draft.editor.corex_manage_forms ).toBe( 'allow' );
		expect( state.draft.editor.corex_manage_admin ).toBe( 'inherit' );
		expect( buildRoleChanges( state.rows, state.draft, 'editor' ) ).toEqual(
			{
				corex_manage_forms: 'allow',
			}
		);
	} );

	it( 'tracks preview, apply, request queue, modal, and recoverable errors', () => {
		let state = accessReducer( initialAccessState(), {
			type: 'loaded',
			payload,
		} );
		state = accessReducer( state, {
			type: 'preview',
			preview: { allowed: true, target_hash: 'abc' },
		} );
		expect( state.preview.allowed ).toBe( true );

		state = accessReducer( state, {
			type: 'requestQueueLoaded',
			requests: [ { id: 4, state: 'pending' } ],
		} );
		expect( state.requestQueue ).toHaveLength( 1 );

		state = accessReducer( state, {
			type: 'modal',
			modal: { type: 'grant', role: 'editor' },
		} );
		expect( state.modal.type ).toBe( 'grant' );

		state = accessReducer( state, {
			type: 'applied',
			message: 'Role updated.',
		} );
		expect( state.preview ).toBeNull();
		expect( state.notice ).toEqual( {
			tone: 'success',
			message: 'Role updated.',
		} );

		state = accessReducer( state, {
			type: 'error',
			message: 'Try again.',
		} );
		expect( state.notice ).toEqual( {
			tone: 'error',
			message: 'Try again.',
		} );
	} );
} );
