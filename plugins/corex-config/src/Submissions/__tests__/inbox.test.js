import {
	initialInboxState,
	inboxReducer,
	buildInboxUrl,
	inboxSubmissionFromUrl,
	toggleSubmission,
	normalizeInboxPage,
	buildExportPayload,
} from '../inbox.js';

describe( 'Submissions Inbox client state', () => {
	it( 'builds a stable permission-scoped filter URL', () => {
		expect(
			buildInboxUrl( '/corex/v1/submissions', {
				search: 'sam@example.com',
				flow: '42',
				status: 'in_progress',
				owner: 'team:sales',
				dateFrom: '2026-07-01',
				dateTo: '2026-07-04',
				includeTest: false,
				page: 2,
				perPage: 25,
			} )
		).toBe(
			'/corex/v1/submissions?search=sam%40example.com&flow=42&status=in_progress&owner=team%3Asales&date_from=2026-07-01&date_to=2026-07-04&page=2&per_page=25'
		);
	} );

	it( 'normalizes loaded rows and excludes fabricated values', () => {
		const page = normalizeInboxPage( {
			items: [
				{
					id: 7,
					status: 'new',
					flow: 'Contact',
					submitter_name: 'Sam',
					submitter_email: 'sam@example.com',
				},
			],
			total: 1,
			page: 1,
			per_page: 25,
		} );

		expect( page.items ).toHaveLength( 1 );
		expect( page.items[ 0 ] ).toMatchObject( {
			id: 7,
			status: 'new',
			is_test: false,
		} );
		expect( page.total ).toBe( 1 );
	} );

	it( 'tracks selection and clears rows no longer present', () => {
		expect( toggleSubmission( [], 4 ) ).toEqual( [ 4 ] );
		expect( toggleSubmission( [ 4, 8 ], 4 ) ).toEqual( [ 8 ] );

		const loaded = inboxReducer(
			{ ...initialInboxState, selectedIds: [ 4, 99 ] },
			{
				type: 'loaded',
				page: { items: [ { id: 4 } ], total: 1, page: 1, perPage: 25 },
			}
		);
		expect( loaded.selectedIds ).toEqual( [ 4 ] );
	} );

	it( 'opens the detail drawer and preserves loading/error states', () => {
		let state = inboxReducer( initialInboxState, {
			type: 'detailLoading',
			id: 12,
		} );
		expect( state.drawer ).toMatchObject( {
			open: true,
			id: 12,
			status: 'loading',
		} );

		state = inboxReducer( state, {
			type: 'detailLoaded',
			record: { id: 12, values: { name: 'Sam' } },
		} );
		expect( state.drawer.status ).toBe( 'ready' );
		expect( state.drawer.record.values.name ).toBe( 'Sam' );

		state = inboxReducer( state, { type: 'drawerClosed' } );
		expect( state.drawer.open ).toBe( false );
	} );

	it( 'builds explicit selected and filtered export requests', () => {
		expect(
			buildExportPayload( {
				scope: 'selected',
				selectedIds: [ 8, 4 ],
				columns: [ 'identity', 'submitted_fields' ],
				includeTest: false,
				acknowledged: true,
				filters: { status: 'new' },
			} )
		).toEqual( {
			scope: 'selected',
			selected_ids: [ 4, 8 ],
			columns: [ 'identity', 'submitted_fields' ],
			query: {},
			include_test: false,
			personal_data_acknowledged: true,
		} );

		expect(
			buildExportPayload( {
				scope: 'filtered',
				selectedIds: [],
				columns: [ 'workflow' ],
				includeTest: true,
				acknowledged: false,
				filters: { status: 'closed' },
			} ).query
		).toEqual( { status: 'closed' } );
	} );
} );

/**
 * The deep link an assignment notification uses (spec 087, FR-014).
 *
 * Before this parameter existed, "a submission needs your reply" could only honestly link to the
 * unfiltered inbox — handing somebody a list and asking them to find the row the notification was
 * already holding.
 */
describe( 'opening the inbox on one submission', () => {
	const base = 'https://acme.test/wp-admin/admin.php?page=corex-submissions';

	it( 'reads the submission the address was sent to', () => {
		expect(
			inboxSubmissionFromUrl( `${ base }&corex_submission=42` )
		).toBe( 42 );
	} );

	it( 'ignores anything that is not a submission id', () => {
		expect( inboxSubmissionFromUrl( base ) ).toBe( 0 );
		expect( inboxSubmissionFromUrl( `${ base }&corex_submission=` ) ).toBe(
			0
		);
		expect(
			inboxSubmissionFromUrl( `${ base }&corex_submission=abc` )
		).toBe( 0 );
		expect(
			inboxSubmissionFromUrl( `${ base }&corex_submission=1;DROP` )
		).toBe( 0 );
		expect(
			inboxSubmissionFromUrl( `${ base }&corex_submission=-4` )
		).toBe( 0 );
	} );

	it( 'answers 0 rather than throwing on an address it cannot parse', () => {
		expect( inboxSubmissionFromUrl( '' ) ).toBe( 0 );
		expect( inboxSubmissionFromUrl( undefined ) ).toBe( 0 );
	} );

	/**
	 * The two deep links are independent: a form filter and an opened detail can arrive together,
	 * and neither may swallow the other.
	 */
	it( 'coexists with the form filter on the same address', () => {
		const url = `${ base }&corex_form=7&corex_submission=42`;

		expect( inboxSubmissionFromUrl( url ) ).toBe( 42 );
	} );
} );
