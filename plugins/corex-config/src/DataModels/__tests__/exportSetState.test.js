/**
 * What the Export tab says and sends when several models are exported together (spec 109,
 * slice 2).
 *
 * The owner, on a live site: "the import/export/migrate model is using one by one, i can't do a
 * bulk or multiselect". The tab had one dropdown named "Model". These are the words and the
 * request of the list that replaces it, tested without a browser.
 */
import {
	buildSetPayload,
	canContinue,
	columnsOf,
	entryModels,
	exportableModels,
	groupedModels,
	modelsNotOffered,
	setBlockedReason,
	setExportLabel,
	setFormatDetail,
	setFormats,
	setKinds,
	setTotal,
	withGroup,
} from '../exportSetState.js';

const model = ( key, label, category, extra = {} ) => ( {
	key,
	label,
	category,
	actions: { export_csv: { visible: true }, export_xlsx: { visible: true } },
	permissions: { personal_data: true },
	fields: [
		{ key: 'code', label: 'Code', personal_data_class: 'none' },
		{ key: 'person', label: 'Person', personal_data_class: 'identity' },
	],
	...extra,
} );

const LEADS = model( 'submissions', 'Form submissions', 'records' );
const SUBSCRIBERS = model(
	'table-subscribers',
	'Newsletter subscribers',
	'records'
);
const JOBS = model( 'table-bounded-jobs', 'Bounded jobs', 'log', {
	fields: [ { key: 'kind', label: 'Kind', personal_data_class: 'none' } ],
} );
const HIDDEN = model( 'table-secrets', 'Secrets', 'log', {
	actions: { export_csv: { visible: false } },
} );

describe( 'the models to choose from', () => {
	it( 'are the ones this person may export, and the rest are counted', () => {
		const sources = [ LEADS, JOBS, HIDDEN ];

		expect(
			exportableModels( sources ).map( ( source ) => source.key )
		).toEqual( [ 'submissions', 'table-bounded-jobs' ] );
		expect( modelsNotOffered( sources ) ).toBe( 1 );
	} );

	it( 'are listed in two groups, what a site keeps for its work before its own logs', () => {
		expect(
			groupedModels( [ JOBS, LEADS, SUBSCRIBERS ] ).map( ( group ) => [
				group.label,
				group.models.map( ( source ) => source.label ),
			] )
		).toEqual( [
			[ 'Records', [ 'Form submissions', 'Newsletter subscribers' ] ],
			[ 'Logs', [ 'Bounded jobs' ] ],
		] );
	} );

	it( 'leave out a group that holds none', () => {
		expect(
			groupedModels( [ JOBS ] ).map( ( group ) => group.label )
		).toEqual( [ 'Logs' ] );
	} );

	it( 'are ticked or unticked a group at a time, leaving the other group as it was', () => {
		const [ records ] = groupedModels( [ LEADS, SUBSCRIBERS, JOBS ] );

		expect( withGroup( [ 'table-bounded-jobs' ], records, true ) ).toEqual(
			[ 'table-bounded-jobs', 'submissions', 'table-subscribers' ]
		);
		expect(
			withGroup(
				[ 'submissions', 'table-bounded-jobs', 'table-subscribers' ],
				records,
				false
			)
		).toEqual( [ 'table-bounded-jobs' ] );
	} );
} );

describe( 'what several models would export', () => {
	it( 'is every column of each that this person may export, unless a model was narrowed', () => {
		const withoutRight = {
			...LEADS,
			permissions: { personal_data: false },
		};

		expect( columnsOf( LEADS, {} ) ).toEqual( [ 'code', 'person' ] );
		expect( columnsOf( withoutRight, {} ) ).toEqual( [ 'code' ] );
		expect( columnsOf( LEADS, { submissions: [ 'person' ] } ) ).toEqual( [
			'person',
		] );
	} );

	it( 'is counted across the models, and not counted while any count is unknown', () => {
		expect(
			setTotal( [ LEADS, JOBS ], {
				submissions: 40,
				'table-bounded-jobs': 3,
			} )
		).toBe( 43 );
		expect( setTotal( [ LEADS, JOBS ], { submissions: 40 } ) ).toBeNull();
		expect( setTotal( [ LEADS, JOBS ], null ) ).toBeNull();
	} );

	it( 'names the kinds of personal data across the models, once each', () => {
		expect( setKinds( [ LEADS, SUBSCRIBERS, JOBS ], {} ) ).toEqual( [
			'names and identities',
		] );
		expect(
			setKinds( [ LEADS, JOBS ], { submissions: [ 'code' ] } )
		).toEqual( [] );
	} );
} );

describe( 'the formats of several models', () => {
	it( 'are a workbook only where every model can be one', () => {
		const textOnly = {
			...JOBS,
			actions: { export_csv: { visible: true } },
		};

		expect(
			setFormats( [ LEADS, JOBS ], [ 'xlsx', 'csv', 'pdf' ] ).map(
				( format ) => format.value
			)
		).toEqual( [ 'xlsx', 'csv', 'pdf' ] );
		expect(
			setFormats( [ LEADS, textOnly ], [ 'xlsx', 'csv' ] ).map(
				( format ) => format.value
			)
		).toEqual( [ 'csv' ] );
	} );

	it.each( [
		[ 'xlsx', 'One workbook, with a sheet for each model.' ],
		[ 'csv', 'One archive (.zip), with a CSV file for each model.' ],
		[
			'pdf',
			'One document, with a section for each model. Up to 500 records in all.',
		],
	] )( 'say what one file of %s holds', ( format, words ) => {
		expect( setFormatDetail( format, 500 ) ).toBe( words );
	} );
} );

describe( 'whether several models can be exported', () => {
	const ready = {
		models: [ LEADS, JOBS ],
		counts: { submissions: 40, 'table-bounded-jobs': 3 },
		columns: {},
		acknowledged: true,
		problem: '',
		format: 'xlsx',
		pdfMost: 500,
	};

	it.each( [
		[ 'yes, when everything is in place', {}, '' ],
		[
			'not while the records are being counted',
			{ counts: null },
			'Counting the records…',
		],
		[
			'not when no model holds a record',
			{ counts: { submissions: 0, 'table-bounded-jobs': 0 } },
			'None of these models holds a record to export.',
		],
		[
			'not as a PDF of more than a PDF holds',
			{
				format: 'pdf',
				counts: { submissions: 600, 'table-bounded-jobs': 3 },
			},
			'A PDF holds up to 500 records. Choose fewer models, or export a workbook.',
		],
		[
			'not with a model that has every column unticked',
			{ columns: { submissions: [] } },
			'Choose at least one column of Form submissions.',
		],
		[
			'not before the personal-data notice is confirmed',
			{ acknowledged: false },
			'Confirm the personal-data notice to export these columns.',
		],
		[
			'not when the counts could not be asked for',
			{ problem: 'The request failed.' },
			'The request failed.',
		],
	] )( '%s', ( _, changes, reason ) => {
		expect( setBlockedReason( { ...ready, ...changes } ) ).toBe( reason );
	} );

	it( 'does not ask for a confirmation when no chosen column holds personal data', () => {
		expect(
			setBlockedReason( {
				...ready,
				models: [ JOBS ],
				acknowledged: false,
			} )
		).toBe( '' );
	} );

	it( 'says on its button how much it will export, and from how many models', () => {
		expect( setExportLabel( 43, 2 ) ).toBe(
			'Export 43 records from 2 models'
		);
		expect( setExportLabel( null, 2 ) ).toBe( 'Export' );
	} );
} );

describe( 'what is sent', () => {
	it( 'names each model, with its columns only where they were narrowed', () => {
		expect(
			buildSetPayload( {
				models: [ LEADS, JOBS ],
				columns: { submissions: [ 'code' ] },
				format: 'csv',
				separator: 'semicolon',
				acknowledged: true,
			} )
		).toEqual( {
			parts: [
				{ source: 'submissions', columns: [ 'code' ] },
				{ source: 'table-bounded-jobs' },
			],
			format: 'csv',
			separator: 'semicolon',
			personal_data_acknowledged: true,
		} );
	} );

	it( 'leaves the columns to the server where a model was not narrowed, whoever is asking', () => {
		const withoutRight = {
			...LEADS,
			permissions: { personal_data: false },
		};

		expect(
			buildSetPayload( {
				models: [ withoutRight ],
				columns: {},
				format: 'csv',
				separator: 'comma',
				acknowledged: false,
			} ).parts
		).toEqual( [ { source: 'submissions' } ] );
	} );
} );

describe( 'a past export', () => {
	const sources = [ LEADS, JOBS ];

	it( 'names the models it covered, a model that is gone by its key, and one that was left out', () => {
		expect(
			entryModels(
				{
					parts: [
						{ source_key: 'submissions', left_out: false },
						{ source_key: 'table-gone', left_out: false },
						{ source_key: 'table-bounded-jobs', left_out: true },
					],
				},
				sources
			)
		).toBe( 'Form submissions, table-gone, Bounded jobs (left out)' );
	} );

	it( 'can be continued only while it is not finished', () => {
		expect(
			canContinue( { state: 'queued', file_state: 'pending' } )
		).toBe( true );
		expect(
			canContinue( { state: 'completed', file_state: 'ready' } )
		).toBe( false );
		expect(
			canContinue( { state: 'completed', file_state: 'deleted' } )
		).toBe( false );
	} );
} );
