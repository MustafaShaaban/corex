/**
 * recordRows — what the Data explorer's detail modal actually shows.
 *
 * The bug these cover: the modal rendered the schema and read `record[ field.key ]`, so a
 * submission — whose detail payload nests every answer under `fields` — showed "Submitted" and
 * "Form" and an em dash for everything else, with the submitted content never rendered at all.
 */
import recordRows from '../data/recordRows.js';

/** The submissions schema, as `SubmissionsSource::fields()` builds it. */
const SUBMISSION_SCHEMA = [
	{ key: 'date', label: 'Submitted' },
	{ key: 'form', label: 'Form' },
	{ key: 'summary', label: 'Submission' },
	{ key: 'email', label: 'Email' },
	{ key: 'message', label: 'Message' },
];

/** The payload `SubmissionsSource::record()` really returns — answers nested under `fields`. */
const SUBMISSION_RECORD = {
	id: 41,
	date: '2026-07-22 10:04',
	form: 'Contact',
	fields: [
		{ label: 'Email', value: 'ada@example.com' },
		{ label: 'Message', value: 'Please call me back.' },
	],
};

describe( 'recordRows', () => {
	it( 'shows a submission’s answers instead of em dashes', () => {
		const rows = recordRows( SUBMISSION_RECORD, SUBMISSION_SCHEMA );
		const values = rows.map( ( row ) => row.value );

		expect( values ).toContain( 'ada@example.com' );
		expect( values ).toContain( 'Please call me back.' );
		// The regression: every answer used to land here.
		expect( values ).not.toContain( '—' );
	} );

	it( 'skips declared fields the record does not carry rather than showing them empty', () => {
		// `summary` is declared but absent from the detail payload. Rendering it as an em dash
		// claims the submission had no content, which is a different (and wrong) statement.
		const labels = recordRows( SUBMISSION_RECORD, SUBMISSION_SCHEMA ).map(
			( row ) => row.label
		);

		expect( labels ).not.toContain( 'Submission' );
		expect( labels ).toEqual(
			expect.arrayContaining( [ 'Submitted', 'Form' ] )
		);
	} );

	it( 'keeps flat table records working, in declared order', () => {
		// TableDataSource::record() returns a flat row; this path must not regress.
		const rows = recordRows( { id: 7, title: 'Widget', qty: 3 }, [
			{ key: 'title', label: 'Title' },
			{ key: 'qty', label: 'Quantity' },
		] );

		expect( rows.map( ( row ) => [ row.label, row.value ] ) ).toEqual( [
			[ 'Title', 'Widget' ],
			[ 'Quantity', '3' ],
		] );
	} );

	it( 'surfaces a value the schema never declared', () => {
		// The point of the third pass: a source that grows a field stays visible without anyone
		// remembering to declare it, which is how this bug stayed hidden.
		const rows = recordRows( { id: 1, tracking_code: 'XY-9' }, [] );

		expect( rows ).toEqual( [
			{ key: 'tracking_code', label: 'Tracking Code', value: 'XY-9' },
		] );
	} );

	it( 'renders an empty answer as an em dash but a false one as false', () => {
		const rows = recordRows( { answered: false, note: '' }, [] );
		const byKey = Object.fromEntries(
			rows.map( ( row ) => [ row.key, row.value ] )
		);

		expect( byKey.note ).toBe( '—' );
		// `false` is an answer. Coercing it to an em dash would report it as unanswered.
		expect( byKey.answered ).toBe( 'false' );
	} );

	it( 'returns nothing for a missing record instead of throwing', () => {
		expect( recordRows( null, SUBMISSION_SCHEMA ) ).toEqual( [] );
	} );
} );
