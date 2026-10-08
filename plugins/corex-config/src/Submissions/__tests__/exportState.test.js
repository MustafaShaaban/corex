/**
 * What the export dialog says and sends (spec 103, US2 and US3).
 *
 * The dialog used to offer a scope with no count and no word about what it covered, and its
 * column choices were raw keys. These are the words it shows now.
 */
import {
	blockedReason,
	columnsFor,
	coverageOf,
	defaultScope,
	describeFilters,
	exportLabel,
	fileNoteOf,
	formatName,
	holdsPersonalData,
	offeredChoices,
	progressOf,
	scopeOptions,
	sizeOf,
} from '../export/exportState.js';

const FLOWS = [
	{ id: 12, slug: 'lead', name: 'Lead form' },
	{ id: 0, slug: 'contact', name: 'Contact' },
];
const STATUSES = { new: 'New', in_progress: 'In progress' };
const asWritten = ( date ) => `<${ date }>`;

describe( 'the filters in force, in words', () => {
	it.each( [
		[ 'no filter', {}, [] ],
		[ 'a flow by its id', { flow: '12' }, [ 'Form: Lead form' ] ],
		[
			'a form defined in code, by its slug',
			{ flow: 'slug:contact' },
			[ 'Form: Contact' ],
		],
		[
			'a form that is no longer listed',
			{ flow: 'slug:gone' },
			[ 'Form: gone' ],
		],
		[ 'a status', { status: 'in_progress' }, [ 'Status: In progress' ] ],
		[
			'a date range',
			{ dateFrom: '2026-10-01', dateTo: '2026-10-07' },
			[ '<2026-10-01> to <2026-10-07>' ],
		],
		[ 'only a start', { dateFrom: '2026-10-01' }, [ 'From <2026-10-01>' ] ],
		[ 'only an end', { dateTo: '2026-10-07' }, [ 'Until <2026-10-07>' ] ],
		[
			'everything at once, in a fixed order',
			{
				search: 'salma',
				owner: 'team:sales',
				status: 'new',
				flow: '12',
			},
			[
				'Form: Lead form',
				'Status: New',
				'Owner: team:sales',
				'Search: “salma”',
			],
		],
	] )( 'describes %s', ( _name, filters, expected ) => {
		expect(
			describeFilters( filters, FLOWS, STATUSES, asWritten )
		).toEqual( expected );
	} );
} );

describe( 'the three scopes', () => {
	const counts = { selected: 2, filtered: 14, accessible: 380 };

	it( 'gives each its count, and says what the filters are', () => {
		const options = scopeOptions( {
			counts,
			selectedCount: 2,
			filters: [ 'Form: Lead form', 'Status: New' ],
		} );

		expect(
			options.map( ( { value, count, disabled } ) => [
				value,
				count,
				disabled,
			] )
		).toEqual( [
			[ 'selected', 2, false ],
			[ 'filtered', 14, false ],
			[ 'accessible', 380, false ],
		] );
		expect( options[ 1 ].detail ).toBe( 'Form: Lead form · Status: New' );
	} );

	it( 'cannot offer a selection when no row is ticked, and says what to do', () => {
		const [ selected, filtered ] = scopeOptions( {
			counts,
			selectedCount: 0,
			filters: [],
		} );

		expect( selected.disabled ).toBe( true );
		expect( selected.count ).toBe( 0 );
		expect( selected.detail ).toMatch( /Tick rows/ );
		expect( filtered.detail ).toMatch( /No filter is on/ );
	} );

	it( 'cannot offer a selection past the limit, and points at the filters', () => {
		const [ selected ] = scopeOptions( {
			counts,
			selectedCount: 101,
			filters: [],
		} );

		expect( selected.disabled ).toBe( true );
		expect( selected.detail ).toMatch( /More than 100 rows/ );
	} );

	it( 'shows no count while the counts are still loading', () => {
		const options = scopeOptions( {
			counts: null,
			selectedCount: 2,
			filters: [],
		} );

		expect( options.map( ( option ) => option.count ) ).toEqual( [
			null,
			null,
			null,
		] );
	} );

	it.each( [
		[ 0, 'filtered' ],
		[ 3, 'selected' ],
		[ 100, 'selected' ],
		[ 101, 'filtered' ],
	] )( 'opens with %i rows ticked on "%s"', ( selectedCount, scope ) => {
		expect( defaultScope( selectedCount ) ).toBe( scope );
	} );
} );

describe( 'the columns', () => {
	it( 'turns the choices into the column names the route takes, in a fixed order', () => {
		expect( columnsFor( [ 'answers', 'details' ] ) ).toEqual( [
			'id',
			'submitted',
			'form',
			'answers',
		] );
	} );

	it( 'knows which choices hold personal data', () => {
		expect( holdsPersonalData( [ 'details', 'triage' ] ) ).toBe( false );
		expect( holdsPersonalData( [ 'details', 'utm' ] ) ).toBe( true );
	} );

	it( 'does not offer personal data to somebody who may not export it', () => {
		expect(
			offeredChoices( false ).map( ( choice ) => choice.id )
		).toEqual( [ 'details', 'triage' ] );
		expect( offeredChoices( true ) ).toHaveLength( 7 );
	} );
} );

describe( 'whether the export can start', () => {
	const ready = { count: 14, chosen: [ 'details' ], acknowledged: false };

	it.each( [
		[ 'the counts are loading', { count: null }, /Counting/ ],
		[ 'there is nothing to export', { count: 0 }, /nothing to export/ ],
		[ 'no column is chosen', { chosen: [] }, /at least one/ ],
		[
			'personal data is chosen and not confirmed',
			{ chosen: [ 'answers' ] },
			/personal-data notice/,
		],
		[
			'a PDF is asked for with more than a PDF holds',
			{ count: 1200, format: 'pdf', pdfMost: 500 },
			/A PDF holds up to 500 submissions/,
		],
	] )( 'cannot when %s', ( _name, change, reason ) => {
		expect( blockedReason( { ...ready, ...change } ) ).toMatch( reason );
	} );

	it( 'can when there is something to export and what it needs is confirmed', () => {
		expect( blockedReason( ready ) ).toBe( '' );
		expect(
			blockedReason( {
				...ready,
				chosen: [ 'answers' ],
				acknowledged: true,
			} )
		).toBe( '' );
	} );

	it( 'can as a workbook with more than a PDF holds, and as a PDF within it', () => {
		const many = { ...ready, count: 1200, pdfMost: 500 };

		expect( blockedReason( { ...many, format: 'xlsx' } ) ).toBe( '' );
		expect( blockedReason( { ...many, count: 500, format: 'pdf' } ) ).toBe(
			''
		);
	} );

	it.each( [
		[ null, 'Export' ],
		[ 0, 'Export' ],
		[ 1, 'Export 1 submission' ],
		[ 14, 'Export 14 submissions' ],
	] )( 'labels the action for a count of %p', ( count, label ) => {
		expect( exportLabel( count ) ).toBe( label );
	} );
} );

describe( 'progress', () => {
	it.each( [
		[ { processed: 100, total: 250 }, 100, 250 ],
		[ { processed: 300, total: 250 }, 250, 250 ],
		[ { processed: 0, total: 0 }, 0, 1 ],
	] )( 'reads %p', ( progress, value, max ) => {
		expect( progressOf( progress ) ).toMatchObject( { value, max } );
	} );
} );

describe( 'a past export, in the history', () => {
	const describe_ = ( filters ) =>
		describeFilters( filters, FLOWS, STATUSES, asWritten );
	const entry = ( overrides = {} ) => ( {
		scope: 'accessible',
		selected_ids: [],
		query: {},
		include_test: false,
		format: 'xlsx',
		file_size: 0,
		state: 'ready',
		expires_at: '2026-11-06T09:00:00+00:00',
		removed_at: '',
		removed_by_name: '',
		...overrides,
	} );

	it.each( [
		[ 'everything', entry(), 'Everything' ],
		[
			'ticked rows, by how many',
			entry( { scope: 'selected', selected_ids: [ 4, 9, 12 ] } ),
			'3 selected rows',
		],
		[
			'one ticked row',
			entry( { scope: 'selected', selected_ids: [ 4 ] } ),
			'1 selected row',
		],
		[
			'the filters it was made with',
			entry( {
				scope: 'filtered',
				query: {
					flow: '12',
					status: 'new',
					date_from: '2026-10-01',
					date_to: '2026-10-07',
				},
			} ),
			'Form: Lead form, Status: New, <2026-10-01> to <2026-10-07>',
		],
		[
			'filters, when none was in force',
			entry( { scope: 'filtered', query: { search: '' } } ),
			'Everything in view, no filters',
		],
		[
			'tests, when they were included',
			entry( { include_test: true } ),
			'Everything, with tests',
		],
	] )( 'says what it covered: %s', ( _name, item, words ) => {
		expect( coverageOf( item, describe_ ) ).toBe( words );
	} );

	it.each( [
		[ 0, '' ],
		[ 512, '512 B' ],
		[ 2048, '2 KB' ],
		[ 48900, '48 KB' ],
		[ 1572864, '1.5 MB' ],
	] )( 'says how large %d bytes is', ( bytes, words ) => {
		expect( sizeOf( bytes ) ).toBe( words );
	} );

	it.each( [
		[ 'xlsx', 'Excel' ],
		[ 'csv', 'CSV' ],
		[ 'pdf', 'PDF' ],
		[ undefined, 'CSV' ],
	] )( 'names the format %s', ( format, name ) => {
		expect( formatName( format ) ).toBe( name );
	} );

	it.each( [
		[
			'a file that is kept',
			entry(),
			'Expires <2026-11-06T09:00:00+00:00>',
		],
		[
			'a file past its retention',
			entry( { state: 'expired' } ),
			'Expired <2026-11-06T09:00:00+00:00>. It can no longer be downloaded.',
		],
		[
			'a file somebody deleted',
			entry( {
				state: 'deleted',
				removed_at: '2026-10-08T10:00:00+00:00',
				removed_by_name: 'Salma Adel',
			} ),
			'Deleted by Salma Adel, <2026-10-08T10:00:00+00:00>',
		],
		[
			'a file deleted by somebody who can no longer be named',
			entry( {
				state: 'deleted',
				removed_at: '2026-10-08T10:00:00+00:00',
			} ),
			'Deleted <2026-10-08T10:00:00+00:00>',
		],
		[
			'an export with no file',
			entry( { state: 'pending' } ),
			'No file to download.',
		],
	] )( 'says what became of the file: %s', ( _name, item, words ) => {
		expect( fileNoteOf( item, asWritten ) ).toBe( words );
	} );
} );
