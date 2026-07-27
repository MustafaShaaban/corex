import {
	DATA_MODEL_TABS,
	actionSources,
	allowedTabs,
	capabilitySummary,
	importSummary,
	migrationState,
	resolveTab,
	tabFromUrl,
} from '../modelClient.js';

const BOTH = { data: true, models: true };

/** A source that can do everything the capability tabs ask for. */
const CAPABLE = [
	{
		key: 'subscribers',
		actions: {
			import_dry_run: { visible: true },
			export_csv: { visible: true },
			migrations: { visible: true },
		},
	},
];

/** The shape every first-party managed table had before spec 074: readable, and nothing else. */
const READ_ONLY = [
	{
		key: 'activity-events',
		actions: {
			read: { visible: true },
			export_csv: { visible: true },
			import_dry_run: { visible: false },
			migrations: { visible: false },
		},
	},
];

describe( 'Data Models tab permissions and deep-linking', () => {
	it( 'shows a data-only user records, and nothing that reshapes the models', () => {
		// MANAGE_DATA and MANAGE_DATA_MODELS are independent abilities. This user could reach
		// records through the retired Data screen; removing it must not take that away.
		expect(
			allowedTabs( { data: true, models: false }, CAPABLE ).map(
				( t ) => t.key
			)
		).toEqual( [ 'records' ] );
	} );

	it( 'shows a models-only user everything except records', () => {
		// Records reads sources gated on `data`, so offering the tab would open an explorer that
		// can read nothing — which is exactly what it used to do.
		expect(
			allowedTabs( { data: false, models: true }, CAPABLE ).map(
				( t ) => t.key
			)
		).toEqual( [ 'models', 'import', 'export', 'migrations' ] );
	} );

	it( 'shows nothing to a user with neither ability', () => {
		expect( allowedTabs( {}, CAPABLE ) ).toEqual( [] );
		expect( resolveTab( 'records', {}, CAPABLE ) ).toBe( '' );
	} );

	it( 'opens the requested tab when it is permitted', () => {
		expect( resolveTab( 'migrations', BOTH, CAPABLE ) ).toBe(
			'migrations'
		);
		expect(
			tabFromUrl(
				'/wp-admin/admin.php?page=corex-data-models&tab=records',
				BOTH,
				CAPABLE
			)
		).toBe( 'records' );
	} );

	it( 'falls back to the first permitted tab rather than rendering an empty shell', () => {
		// The retired Data screen redirects here with ?tab=records; a models-only user following an
		// old bookmark has to land on something.
		expect(
			resolveTab( 'records', { data: false, models: true }, CAPABLE )
		).toBe( 'models' );
		expect(
			resolveTab( 'migrations', { data: true, models: false }, CAPABLE )
		).toBe( 'records' );
		expect( resolveTab( 'nonsense', BOTH, CAPABLE ) ).toBe( 'models' );
		expect(
			tabFromUrl(
				'/wp-admin/admin.php?page=corex-data-models',
				BOTH,
				CAPABLE
			)
		).toBe( 'models' );
	} );

	it( 'survives a URL it cannot parse', () => {
		expect( tabFromUrl( '', BOTH, CAPABLE ) ).toBe( 'models' );
		expect( tabFromUrl( undefined, BOTH, CAPABLE ) ).toBe( 'models' );
	} );
} );

describe( 'Data Models tabs are capability-aware, not just permission-aware', () => {
	it( 'hides Import and Migrations when no model can satisfy them', () => {
		// The defect this closes: both tabs were shown to anyone holding `models` and could only
		// ever render "No registered model provides an import adapter."
		expect( allowedTabs( BOTH, READ_ONLY ).map( ( t ) => t.key ) ).toEqual(
			[ 'models', 'records', 'export' ]
		);
	} );

	it( 'hides every capability tab when there are no sources at all', () => {
		expect( allowedTabs( BOTH, [] ).map( ( t ) => t.key ) ).toEqual( [
			'models',
			'records',
		] );
		expect( allowedTabs( BOTH ).map( ( t ) => t.key ) ).toEqual( [
			'models',
			'records',
		] );
	} );

	it( 'shows a capability tab as soon as one model declares it', () => {
		expect(
			allowedTabs( BOTH, [ ...READ_ONLY, ...CAPABLE ] ).map(
				( t ) => t.key
			)
		).toEqual( [ 'models', 'records', 'import', 'export', 'migrations' ] );
	} );

	it( 'refuses to deep-link to a tab nothing can satisfy', () => {
		// A bookmark to ?tab=import must not reopen the dead end it used to lead to.
		expect( resolveTab( 'import', BOTH, READ_ONLY ) ).toBe( 'models' );
		expect(
			tabFromUrl(
				'/wp-admin/admin.php?page=corex-data-models&tab=migrations',
				BOTH,
				READ_ONLY
			)
		).toBe( 'models' );
	} );
} );

describe( 'Model capability summary', () => {
	it( 'states an available capability in words rather than capability keys', () => {
		const summary = capabilitySummary( {
			capabilities: {
				read: true,
				create: true,
				import_dry_run: true,
				export_csv: true,
				migrations: true,
				rollback: true,
			},
		} );

		expect( summary.map( ( entry ) => entry.key ) ).toEqual( [
			'read',
			'write',
			'import',
			'export',
			'migrations',
		] );
		expect( summary.every( ( entry ) => entry.available ) ).toBe( true );
		expect(
			summary.find( ( entry ) => entry.key === 'migrations' ).explanation
		).toContain( 'roll back' );
	} );

	it( 'explains an unavailable capability without ever saying "adapter"', () => {
		const summary = capabilitySummary( {
			capabilities: { read: true, export_csv: true },
		} );
		const importEntry = summary.find( ( entry ) => entry.key === 'import' );

		expect( importEntry.available ).toBe( false );
		expect( importEntry.label ).toBe( 'No import' );
		expect(
			summary.map( ( entry ) => entry.explanation ).join( ' ' )
		).not.toContain( 'adapter' );
	} );

	it( 'does not promise a rollback for a migration that has none', () => {
		const summary = capabilitySummary( {
			capabilities: { migrations: true, rollback: false },
		} );
		const migrations = summary.find(
			( entry ) => entry.key === 'migrations'
		);

		expect( migrations.available ).toBe( true );
		expect( migrations.explanation ).toContain( 'cannot be undone' );
	} );

	it( 'survives a source with no capabilities at all', () => {
		expect(
			capabilitySummary( undefined ).every(
				( entry ) => entry.available === false
			)
		).toBe( true );
	} );
} );

describe( 'Data Models navigation and capability projection', () => {
	it( 'keeps every approved workspace tab reachable', () => {
		expect( DATA_MODEL_TABS.map( ( tab ) => tab.key ) ).toEqual( [
			'models',
			'records',
			'import',
			'export',
			'migrations',
		] );
	} );

	it( 'returns only sources with a visible real adapter action', () => {
		const sources = [
			{ key: 'contacts', actions: { import_commit: { visible: true } } },
			{
				key: 'submissions',
				actions: { import_commit: { visible: false } },
			},
		];
		expect(
			actionSources( sources, 'import_commit' ).map(
				( source ) => source.key
			)
		).toEqual( [ 'contacts' ] );
	} );
} );

describe( 'Data Models workflow summaries', () => {
	it( 'summarizes accepted rejected and unknown import evidence', () => {
		expect(
			importSummary( {
				accepted_rows: [ { name: 'Ada' } ],
				rejected_rows: [ { line: 3 }, { line: 4 } ],
				unknown_columns: [ 'legacy' ],
			} )
		).toEqual( {
			accepted: 1,
			rejected: 2,
			total: 3,
			unknown: [ 'legacy' ],
		} );
	} );

	it( 'distinguishes pending applied failed and rollback migration states', () => {
		expect( migrationState( null ) ).toBe( 'pending' );
		expect( migrationState( { state: 'applied' } ) ).toBe( 'applied' );
		expect( migrationState( { state: 'failed' } ) ).toBe( 'failed' );
		expect( migrationState( { state: 'rolled_back' } ) ).toBe(
			'rolled-back'
		);
	} );
} );
