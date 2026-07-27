/**
 * The Forms & Flows catalog rows (spec 074, FR-1.9).
 */
import {
	catalogRows,
	readOnlyCount,
	SOURCE_CODE_FORM,
	SOURCE_EXTERNAL,
	SOURCE_VISUAL_FLOW,
} from '../formCatalog.js';

const flow = {
	id: 7,
	slug: 'quote',
	name: 'Quote request',
	state: 'published',
	field_count: 4,
	routing_target: 'sales@example.com',
	updated_at: '2026-07-01T00:00:00+00:00',
};

const codeForm = {
	slug: 'contact',
	label: 'Contact',
	source: SOURCE_CODE_FORM,
	field_count: 3,
	active: true,
	submission_count: 194,
	validation_summary: 'email: required, email',
	fields: [ { key: 'email', label: 'Email', type: 'email', rules: [ 'required' ] } ],
};

it( 'lists code forms alongside visual flows', () => {
	const rows = catalogRows( [ flow ], [ codeForm ] );

	expect( rows.map( ( row ) => row.slug ) ).toEqual( [ 'contact', 'quote' ] );
	expect( rows[ 0 ].source ).toBe( SOURCE_CODE_FORM );
	expect( rows[ 1 ].source ).toBe( SOURCE_VISUAL_FLOW );
} );

it( 'marks only visual flows as editable in the builder', () => {
	const rows = catalogRows( [ flow ], [ codeForm ] );

	expect( rows.find( ( row ) => row.slug === 'quote' ).editable ).toBe( true );
	expect( rows.find( ( row ) => row.slug === 'contact' ).editable ).toBe( false );
	expect( readOnlyCount( rows ) ).toBe( 1 );
} );

it( 'prefers the live flow record over the catalog entry for the same slug', () => {
	const rows = catalogRows(
		[ flow ],
		[ { ...codeForm, slug: 'quote', label: 'Stale label', source: SOURCE_VISUAL_FLOW } ]
	);

	expect( rows ).toHaveLength( 1 );
	expect( rows[ 0 ].name ).toBe( 'Quote request' );
	expect( rows[ 0 ].editable ).toBe( true );
} );

it( 'never re-adds a visual flow the server filter excluded', () => {
	const rows = catalogRows(
		[],
		[ { slug: 'archived', label: 'Archived flow', source: SOURCE_VISUAL_FLOW, active: false } ]
	);

	expect( rows ).toEqual( [] );
} );

it( 'carries the field definitions and validation summary of a code form', () => {
	const row = catalogRows( [], [ codeForm ] )[ 0 ];

	expect( row.fields ).toHaveLength( 1 );
	expect( row.validationSummary ).toBe( 'email: required, email' );
	expect( row.fieldCount ).toBe( 3 );
} );

it( 'keeps an unmeasured submission count null rather than turning it into zero', () => {
	const unmeasured = catalogRows( [], [ { ...codeForm, submission_count: null } ] )[ 0 ];
	const measured = catalogRows( [], [ { ...codeForm, submission_count: 0 } ] )[ 0 ];

	expect( unmeasured.submissionCount ).toBeNull();
	expect( measured.submissionCount ).toBe( 0 );
} );

it( 'searches code forms by name and slug', () => {
	expect( catalogRows( [], [ codeForm ], { search: 'cont' } ) ).toHaveLength( 1 );
	expect( catalogRows( [], [ codeForm ], { search: 'contact' } ) ).toHaveLength( 1 );
	expect( catalogRows( [], [ codeForm ], { search: 'nothing' } ) ).toHaveLength( 0 );
} );

it( 'leaves server-filtered flows alone when a client search is typed', () => {
	// The flow list is filtered by the REST call; re-filtering it here would hide rows the
	// server deliberately returned.
	expect( catalogRows( [ flow ], [], { search: 'nothing matches' } ) ).toHaveLength( 1 );
} );

it( 'excludes code forms from a lifecycle filter they cannot satisfy', () => {
	expect( catalogRows( [], [ codeForm ], { lifecycle: 'draft' } ) ).toHaveLength( 0 );
	expect( catalogRows( [], [ codeForm ], { lifecycle: '' } ) ).toHaveLength( 1 );
	expect( catalogRows( [], [ codeForm ], { lifecycle: 'registered' } ) ).toHaveLength( 1 );
} );

it( 'reports an inactive registered form as inactive rather than registered', () => {
	const row = catalogRows( [], [ { ...codeForm, active: false } ] )[ 0 ];

	expect( row.state ).toBe( 'inactive' );
} );

it( 'accepts an external provider entry', () => {
	const row = catalogRows(
		[],
		[ { slug: 'partner', label: 'Partner signup', source: SOURCE_EXTERNAL, field_count: 2 } ]
	)[ 0 ];

	expect( row.source ).toBe( SOURCE_EXTERNAL );
	expect( row.editable ).toBe( false );
	expect( row.fieldCount ).toBe( 2 );
} );

it( 'survives missing or malformed inputs', () => {
	expect( catalogRows( undefined, undefined ) ).toEqual( [] );
	expect( catalogRows( null, [ null, {}, { slug: '' } ] ) ).toEqual( [] );
	expect( readOnlyCount( undefined ) ).toBe( 0 );
} );
