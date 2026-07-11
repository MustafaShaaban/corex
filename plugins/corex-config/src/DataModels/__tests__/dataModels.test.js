import {
	DATA_MODEL_TABS,
	actionSources,
	importSummary,
	migrationState,
} from '../modelClient.js';

describe( 'Data Models navigation and capability projection', () => {
	it( 'keeps every approved workspace tab reachable', () => {
		expect( DATA_MODEL_TABS.map( ( tab ) => tab.key ) ).toEqual( [
			'models', 'records', 'import', 'export', 'migrations',
		] );
	} );

	it( 'returns only sources with a visible real adapter action', () => {
		const sources = [
			{ key: 'contacts', actions: { import_commit: { visible: true } } },
			{ key: 'submissions', actions: { import_commit: { visible: false } } },
		];
		expect( actionSources( sources, 'import_commit' ).map( ( source ) => source.key ) )
			.toEqual( [ 'contacts' ] );
	} );
} );

describe( 'Data Models workflow summaries', () => {
	it( 'summarizes accepted rejected and unknown import evidence', () => {
		expect( importSummary( {
			accepted_rows: [ { name: 'Ada' } ],
			rejected_rows: [ { line: 3 }, { line: 4 } ],
			unknown_columns: [ 'legacy' ],
		} ) ).toEqual( { accepted: 1, rejected: 2, total: 3, unknown: [ 'legacy' ] } );
	} );

	it( 'distinguishes pending applied failed and rollback migration states', () => {
		expect( migrationState( null ) ).toBe( 'pending' );
		expect( migrationState( { state: 'applied' } ) ).toBe( 'applied' );
		expect( migrationState( { state: 'failed' } ) ).toBe( 'failed' );
		expect( migrationState( { state: 'rolled_back' } ) ).toBe( 'rolled-back' );
	} );
} );
