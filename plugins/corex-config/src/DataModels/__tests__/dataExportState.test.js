/**
 * What the Data export dialog says and sends (spec 103, US10).
 *
 * The Data screen had two exports, a select of three scopes with no count on one and no choice of
 * scope on the other. These are the words of the one dialog that replaces both.
 */
import {
	buildDataExportPayload,
	dataBlockedReason,
	dataColumnChoices,
	dataExportLabel,
	dataFormats,
	dataProgress,
	dataScopes,
	defaultDataScope,
	describeDataQuery,
} from '../dataExportState.js';

const FIELDS = [
	{ key: 'name', label: 'Name', personal_data_class: 'identity' },
	{ key: 'status', label: 'Status', personal_data_class: 'none' },
	{ key: 'city', label: 'City', personal_data_class: 'none' },
];

describe( 'the filters in force, in words', () => {
	it.each( [
		[ 'nothing', { search: '', filters: {} }, [] ],
		[ 'no query at all', undefined, [] ],
		[ 'a search', { search: 'salma', filters: {} }, [ 'Search: “salma”' ] ],
		[
			'a filter, under its field’s label',
			{ search: '', filters: { status: 'active' } },
			[ 'Status: active' ],
		],
		[
			'a filter on a field that is not declared, under its key',
			{ search: '', filters: { region: 'north' } },
			[ 'region: north' ],
		],
		[
			'a filter left empty',
			{ search: '', filters: { status: '', city: 'Cairo' } },
			[ 'City: Cairo' ],
		],
	] )( 'for %s', ( _name, query, words ) => {
		expect( describeDataQuery( query, FIELDS ) ).toEqual( words );
	} );
} );

describe( 'the scopes', () => {
	const counts = { selected: 2, filtered: 14, all: 90 };

	it( 'are the ticked rows, the filters and everything, each with its count', () => {
		const scopes = dataScopes( {
			counts,
			selectedCount: 2,
			filters: [ 'Status: active' ],
			withRows: true,
		} );

		expect(
			scopes.map( ( scope ) => [
				scope.value,
				scope.count,
				scope.disabled,
			] )
		).toEqual( [
			[ 'selected', 2, false ],
			[ 'filtered', 14, false ],
			[ 'all', 90, false ],
		] );
		expect( scopes[ 1 ].detail ).toBe( 'Status: active' );
	} );

	it( 'do not offer ticked rows when none is ticked, and say how to', () => {
		const selected = dataScopes( {
			counts,
			selectedCount: 0,
			filters: [],
			withRows: true,
		} )[ 0 ];

		expect( selected.disabled ).toBe( true );
		expect( selected.count ).toBe( 0 );
		expect( selected.detail ).toBe(
			'Tick rows in the table to export only those.'
		);
	} );

	it( 'do not offer more ticked rows than one export may hold', () => {
		const selected = dataScopes( {
			counts,
			selectedCount: 501,
			filters: [],
			withRows: true,
		} )[ 0 ];

		expect( selected.disabled ).toBe( true );
		expect( selected.detail ).toMatch( /More than 500 rows are ticked/ );
	} );

	it( 'say that no filter is on, when none is', () => {
		const filtered = dataScopes( {
			counts,
			selectedCount: 0,
			filters: [],
			withRows: true,
		} )[ 1 ];

		expect( filtered.detail ).toBe(
			'No filter is on, so this is every record you can see.'
		);
	} );

	it( 'are everything alone where there are no rows and no filters to choose by', () => {
		const scopes = dataScopes( {
			counts,
			selectedCount: 0,
			filters: [],
			withRows: false,
		} );

		expect( scopes.map( ( scope ) => scope.value ) ).toEqual( [ 'all' ] );
	} );

	it( 'show no count while the counts are being asked for', () => {
		const scopes = dataScopes( {
			counts: null,
			selectedCount: 2,
			filters: [],
			withRows: true,
		} );

		expect( scopes.map( ( scope ) => scope.count ) ).toEqual( [
			null,
			null,
			null,
		] );
	} );

	it.each( [
		[ 2, true, 'selected' ],
		[ 0, true, 'filtered' ],
		[ 501, true, 'filtered' ],
		[ 0, false, 'all' ],
	] )(
		'open on what was just done: %d ticked, rows %s',
		( ticked, withRows, scope ) => {
			expect( defaultDataScope( ticked, withRows ) ).toBe( scope );
		}
	);
} );

describe( 'the columns', () => {
	it( 'are the source’s fields, by label, with the ones that hold personal data marked', () => {
		expect( dataColumnChoices( FIELDS ) ).toEqual( [
			{ id: 'name', label: 'Name', personal: true, hint: '' },
			{ id: 'status', label: 'Status', personal: false, hint: '' },
			{ id: 'city', label: 'City', personal: false, hint: '' },
		] );
	} );
} );

describe( 'the formats', () => {
	it( 'are Excel first where the source allows it, and CSV', () => {
		const source = { actions: { export_xlsx: { visible: true } } };

		expect(
			dataFormats( source ).map( ( format ) => format.value )
		).toEqual( [ 'xlsx', 'csv' ] );
	} );

	it( 'are CSV alone where the source does not', () => {
		expect(
			dataFormats( { actions: {} } ).map( ( format ) => format.value )
		).toEqual( [ 'csv' ] );
	} );
} );

describe( 'whether the export can start', () => {
	const ready = {
		count: 4,
		columns: [ 'status' ],
		personal: false,
		acknowledged: false,
		problem: '',
	};

	it.each( [
		[ 'it can', ready, '' ],
		[
			'the counts could not be asked for',
			{ ...ready, count: null, problem: 'You cannot export this.' },
			'You cannot export this.',
		],
		[
			'the count is not in yet',
			{ ...ready, count: null },
			'Counting the records…',
		],
		[
			'there is nothing to export',
			{ ...ready, count: 0 },
			'There is nothing to export with this choice.',
		],
		[
			'no column is chosen',
			{ ...ready, columns: [] },
			'Choose at least one column.',
		],
		[
			'personal data is chosen and not confirmed',
			{ ...ready, personal: true },
			'Confirm the personal-data notice to export these columns.',
		],
		[
			'personal data is chosen and confirmed',
			{ ...ready, personal: true, acknowledged: true },
			'',
		],
	] )( 'when %s', ( _name, state, reason ) => {
		expect( dataBlockedReason( state ) ).toBe( reason );
	} );
} );

it( 'says how much the action will export', () => {
	expect( dataExportLabel( null ) ).toBe( 'Export' );
	expect( dataExportLabel( 1 ) ).toBe( 'Export 1 record' );
	expect( dataExportLabel( 1200 ) ).toBe( 'Export 1,200 records' );
} );

it( 'says how far an export has got', () => {
	expect( dataProgress( { processed: 100, total: 250 } ) ).toEqual( {
		value: 100,
		max: 250,
		text: '100 of 250 records',
	} );
} );

describe( 'what is sent', () => {
	const chosen = {
		scope: 'selected',
		selectedIds: [ 9, 3 ],
		query: { search: 'salma', filters: { status: 'active' }, page: 4 },
		columns: [ 'name' ],
		format: 'csv',
		separator: 'semicolon',
		acknowledged: true,
	};

	it( 'is the ticked rows for a selection, and no query', () => {
		expect( buildDataExportPayload( chosen ) ).toEqual( {
			scope: 'selected',
			selected_ids: [ 9, 3 ],
			query: {},
			columns: [ 'name' ],
			format: 'csv',
			separator: 'semicolon',
			personal_data_acknowledged: true,
		} );
	} );

	it( 'is the search and the filters for the current filters, and no page', () => {
		expect(
			buildDataExportPayload( { ...chosen, scope: 'filtered' } )
		).toMatchObject( {
			scope: 'filtered',
			selected_ids: [],
			query: { search: 'salma', filters: { status: 'active' } },
		} );
	} );

	it( 'is neither for everything', () => {
		expect(
			buildDataExportPayload( { ...chosen, scope: 'all' } )
		).toMatchObject( { scope: 'all', selected_ids: [], query: {} } );
	} );
} );
