/**
 * Pure Data client contracts: query serialization, sort and selection behavior,
 * reducer transitions, capability projection, endpoint construction, and view states.
 */
import {
	buildListUrl,
	toggleSort,
	viewState,
	toggleSelection,
	allRowsSelected,
	initialDataState,
	dataReducer,
	canAction,
	dataEndpoint,
	normalizeCatalog,
} from '../dataClient.js';

describe( 'buildListUrl', () => {
	it( 'sends search/form/sort/dir + paging to the source route', () => {
		const url = buildListUrl( 'https://x.test/wp-json/corex/v1/data', 'submissions', {
			search: 'hello',
			form: 'contact',
			sort: 'date',
			dir: 'desc',
			page: 2,
			perPage: 20,
		} );
		expect( url ).toContain( '/submissions?' );
		expect( url ).toContain( 'search=hello' );
		expect( url ).toContain( 'form=contact' );
		expect( url ).toContain( 'sort=date' );
		expect( url ).toContain( 'dir=desc' );
		expect( url ).toContain( 'page=2' );
		expect( url ).toContain( 'per_page=20' );
	} );

	it( 'serializes declared nested field filters', () => {
		const url = new URL( buildListUrl( '/data', 'contacts', {
			filters: { status: 'active', empty: '' }, page: 1, perPage: 20,
		} ), 'https://example.test' );

		expect( url.searchParams.get( 'filters[status]' ) ).toBe( 'active' );
		expect( url.searchParams.has( 'filters[empty]' ) ).toBe( false );
	} );

	it( 'omits empty optional params but always sends paging', () => {
		const url = buildListUrl( '/data', 'submissions', { page: 1, perPage: 20 } );
		expect( url ).not.toContain( 'search=' );
		expect( url ).not.toContain( 'form=' );
		expect( url ).not.toContain( 'sort=' );
		expect( url ).toContain( 'page=1' );
		expect( url ).toContain( 'per_page=20' );
	} );
} );

describe( 'toggleSort', () => {
	it( 'sorts ascending on a new column', () => {
		expect( toggleSort( { sort: 'date', dir: 'desc' }, 'form' ) ).toEqual( {
			sort: 'form',
			dir: 'asc',
		} );
	} );

	it( 'flips direction on the same column', () => {
		expect( toggleSort( { sort: 'date', dir: 'asc' }, 'date' ) ).toEqual( {
			sort: 'date',
			dir: 'desc',
		} );
		expect( toggleSort( { sort: 'date', dir: 'desc' }, 'date' ) ).toEqual( {
			sort: 'date',
			dir: 'asc',
		} );
	} );
} );

describe( 'toggleSelection', () => {
	it( 'adds an unselected id and removes a selected one, preserving order', () => {
		expect( toggleSelection( [], 5 ) ).toEqual( [ 5 ] );
		expect( toggleSelection( [ 1, 2, 3 ], 2 ) ).toEqual( [ 1, 3 ] );
		expect( toggleSelection( [ 1, 3 ], 4 ) ).toEqual( [ 1, 3, 4 ] );
	} );
} );

describe( 'allRowsSelected', () => {
	it( 'is true only when every visible row is selected and rows exist', () => {
		expect( allRowsSelected( [ 1, 2 ], [ { id: 1 }, { id: 2 } ] ) ).toBe( true );
		expect( allRowsSelected( [ 1 ], [ { id: 1 }, { id: 2 } ] ) ).toBe( false );
		expect( allRowsSelected( [], [] ) ).toBe( false );
	} );
} );

describe( 'viewState', () => {
	it( 'classifies loading and error first', () => {
		expect( viewState( { status: 'loading', rowCount: 5, hasQuery: false } ) ).toBe( 'loading' );
		expect( viewState( { status: 'error', rowCount: 0, hasQuery: true } ) ).toBe( 'error' );
	} );

	it( 'distinguishes an empty source from no-matches under a query', () => {
		expect( viewState( { status: 'ready', rowCount: 0, hasQuery: false } ) ).toBe( 'empty' );
		expect( viewState( { status: 'ready', rowCount: 0, hasQuery: true } ) ).toBe( 'empty-filtered' );
		expect( viewState( { status: 'ready', rowCount: 3, hasQuery: true } ) ).toBe( 'ready' );
	} );
} );

describe( 'Spec 068 data management state', () => {
	it( 'normalizes the permission-projected source catalog without inventing actions', () => {
		const catalog = normalizeCatalog( [ {
			key: 'contacts',
			label: 'Contacts',
			actions: {
				create: { supported: true, allowed: true, visible: true },
				delete: { supported: true, allowed: false, visible: false },
			},
			fields: [ { key: 'name', label: 'Name', read_only: false } ],
		} ] );

		expect( canAction( catalog[ 0 ], 'create' ) ).toBe( true );
		expect( canAction( catalog[ 0 ], 'delete' ) ).toBe( false );
		expect( canAction( catalog[ 0 ], 'bulk_delete' ) ).toBe( false );
		expect( catalog[ 0 ].fields[ 0 ].key ).toBe( 'name' );
	} );

	it( 'reduces query selection request and preview state without stale cross-source data', () => {
		let state = initialDataState( 'contacts' );
		state = dataReducer( state, { type: 'query', patch: { search: 'Ada', page: 3 } } );
		expect( state.query ).toMatchObject( { search: 'Ada', page: 1 } );

		state = dataReducer( state, { type: 'loaded', payload: {
			rows: [ { id: 1, name: 'Ada' } ], fields: [], columns: [], total: 1, page: 1, per_page: 20,
		} } );
		state = dataReducer( state, { type: 'select', id: 1 } );
		expect( state.selected ).toEqual( [ 1 ] );

		state = dataReducer( state, { type: 'preview', preview: { token: 'one-time', operation: 'delete' } } );
		expect( state.preview.token ).toBe( 'one-time' );
		state = dataReducer( state, { type: 'source', sourceKey: 'orders' } );
		expect( state.sourceKey ).toBe( 'orders' );
		expect( state.rows ).toEqual( [] );
		expect( state.selected ).toEqual( [] );
		expect( state.preview ).toBeNull();
	} );

	it( 'tracks mutation success and recoverable errors for announced feedback', () => {
		let state = initialDataState( 'contacts' );
		state = dataReducer( state, { type: 'request', request: 'mutation' } );
		expect( state.pending ).toBe( 'mutation' );
		state = dataReducer( state, { type: 'success', message: 'Record updated.' } );
		expect( state.pending ).toBe( '' );
		expect( state.notice ).toEqual( { tone: 'success', message: 'Record updated.' } );
		state = dataReducer( state, { type: 'error', message: 'Try again.' } );
		expect( state.notice ).toEqual( { tone: 'error', message: 'Try again.' } );
	} );
} );

describe( 'Spec 068 endpoint construction', () => {
	it( 'builds mutation import export and migration endpoints from one REST root', () => {
		expect( dataEndpoint( '/corex/v1/data', 'contacts', 'mutation-preview' ) )
			.toBe( '/corex/v1/data/contacts/mutations/preview' );
		expect( dataEndpoint( '/corex/v1/data', 'contacts', 'import', 12 ) )
			.toBe( '/corex/v1/data/contacts/imports/12' );
		expect( dataEndpoint( '/corex/v1/data', 'contacts', 'export-download', 9 ) )
			.toBe( '/corex/v1/data/contacts/exports/9/download' );
		expect( dataEndpoint( '/corex/v1/data', '', 'migration-preview' ) )
			.toBe( '/corex/v1/data/migrations/preview' );
	} );
} );
