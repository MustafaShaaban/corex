/**
 * Unit tests for the pure Data-screen client helpers (spec 053 US2). These encode the
 * client<->server contract from contracts/data-screen.md: the list URL carries the query
 * params DataController::queryFrom() sanitises; the export URL targets the admin-post
 * handler with the *current* filters + the corex_data_export nonce; sort toggles; the
 * form filter accumulates distinct forms; and the view-state classifier picks one of
 * loading/error/empty/empty-filtered/ready. Pure functions — no DOM, no network.
 */
import {
	buildListUrl,
	buildExportUrl,
	toggleSort,
	mergeForms,
	viewState,
	toggleSelection,
	allRowsSelected,
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

	it( 'omits empty optional params but always sends paging', () => {
		const url = buildListUrl( '/data', 'submissions', { page: 1, perPage: 20 } );
		expect( url ).not.toContain( 'search=' );
		expect( url ).not.toContain( 'form=' );
		expect( url ).not.toContain( 'sort=' );
		expect( url ).toContain( 'page=1' );
		expect( url ).toContain( 'per_page=20' );
	} );
} );

describe( 'buildExportUrl', () => {
	it( 'targets the admin-post export action with the current query + nonce', () => {
		const url = buildExportUrl(
			'https://x.test/wp-admin/admin-post.php',
			'submissions',
			{ search: 'hi', form: 'contact', sort: 'date', dir: 'asc' },
			'NONCE123',
		);
		expect( url ).toContain( 'admin-post.php?' );
		expect( url ).toContain( 'action=corex_data_export' );
		expect( url ).toContain( 'source=submissions' );
		expect( url ).toContain( 'search=hi' );
		expect( url ).toContain( 'form=contact' );
		expect( url ).toContain( 'sort=date' );
		expect( url ).toContain( 'dir=asc' );
		expect( url ).toContain( '_wpnonce=NONCE123' );
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

describe( 'mergeForms', () => {
	it( 'accumulates distinct non-empty form values, stable across pages', () => {
		const a = mergeForms( [], [ { form: 'contact' }, { form: 'quote' }, { form: '' } ] );
		expect( a ).toEqual( [ 'contact', 'quote' ] );
		const b = mergeForms( a, [ { form: 'contact' }, { form: 'newsletter' } ] );
		expect( b ).toEqual( [ 'contact', 'quote', 'newsletter' ] );
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
