import {
	DATA_MODEL_TABS,
	actionSources,
	allowedTabs,
	importSummary,
	migrationState,
	resolveTab,
	tabFromUrl,
} from '../modelClient.js';

const BOTH = { data: true, models: true };

describe( 'Data Models tab permissions and deep-linking', () => {
	it( 'shows a data-only user records, and nothing that reshapes the models', () => {
		// MANAGE_DATA and MANAGE_DATA_MODELS are independent abilities. This user could reach
		// records through the retired Data screen; removing it must not take that away.
		expect( allowedTabs( { data: true, models: false } ).map( ( t ) => t.key ) ).toEqual( [ 'records' ] );
	} );

	it( 'shows a models-only user everything except records', () => {
		// Records reads sources gated on `data`, so offering the tab would open an explorer that
		// can read nothing — which is exactly what it used to do.
		expect( allowedTabs( { data: false, models: true } ).map( ( t ) => t.key ) ).toEqual( [
			'models', 'import', 'export', 'migrations',
		] );
	} );

	it( 'shows nothing to a user with neither ability', () => {
		expect( allowedTabs( {} ) ).toEqual( [] );
		expect( resolveTab( 'records', {} ) ).toBe( '' );
	} );

	it( 'opens the requested tab when it is permitted', () => {
		expect( resolveTab( 'migrations', BOTH ) ).toBe( 'migrations' );
		expect( tabFromUrl( '/wp-admin/admin.php?page=corex-data-models&tab=records', BOTH ) ).toBe( 'records' );
	} );

	it( 'falls back to the first permitted tab rather than rendering an empty shell', () => {
		// The retired Data screen redirects here with ?tab=records; a models-only user following an
		// old bookmark has to land on something.
		expect( resolveTab( 'records', { data: false, models: true } ) ).toBe( 'models' );
		expect( resolveTab( 'migrations', { data: true, models: false } ) ).toBe( 'records' );
		expect( resolveTab( 'nonsense', BOTH ) ).toBe( 'models' );
		expect( tabFromUrl( '/wp-admin/admin.php?page=corex-data-models', BOTH ) ).toBe( 'models' );
	} );

	it( 'survives a URL it cannot parse', () => {
		expect( tabFromUrl( '', BOTH ) ).toBe( 'models' );
		expect( tabFromUrl( undefined, BOTH ) ).toBe( 'models' );
	} );
} );

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
