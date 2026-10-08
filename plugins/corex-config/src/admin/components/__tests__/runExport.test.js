/**
 * One export, from asked for to saved (spec 103, FR-022 to FR-024).
 *
 * The Submissions export and the Data export both wait on an export this way. What a person is
 * told at each turn is decided here, so it is tested here, with requests that answer what the
 * test says.
 */
import { STEPS_TO_WAIT, runExport } from '../export/runExport.js';

const step = ( state, processed, total = 3, error = '' ) => ( {
	state,
	processed,
	total,
	error,
} );

/**
 * Runs an export whose requests answer from the lists given, in order.
 *
 * @param {Object} answers         What the requests answer.
 * @param {*}      answers.created The export's id, or nothing.
 * @param {Array}  answers.steps   What each step answers; the last is repeated.
 * @param {*}      answers.file    What the download answers.
 * @return {Promise<Object>} What was reported, saved, waited for, and whether it started.
 */
async function exported( {
	created = 7,
	steps = [],
	file = { filename: 'a.csv' },
} ) {
	const reports = [];
	const saved = [];
	const pauses = [];
	let taken = 0;
	const started = await runExport( {
		create: async () => created,
		advance: async () => {
			const answer = steps[ Math.min( taken, steps.length - 1 ) ];
			taken++;
			return answer;
		},
		download: async () => file,
		save: ( what ) => saved.push( what ),
		report: ( what ) => reports.push( what ),
		total: 3,
		notDownloaded: 'Ready, and not fetched.',
		pause: async ( milliseconds ) => pauses.push( milliseconds ),
	} );

	return { reports, saved, pauses, started, taken };
}

const phases = ( reports ) => reports.map( ( report ) => report.phase );

it( 'saves the file when the last step is done, and says which file', async () => {
	const run = await exported( {
		steps: [ step( 'running', 2 ), step( 'completed', 3 ) ],
	} );

	expect( phases( run.reports ) ).toEqual( [
		'running',
		'running',
		'running',
		'done',
	] );
	expect( run.reports[ 1 ].progress.processed ).toBe( 2 );
	expect( run.saved ).toEqual( [ { filename: 'a.csv' } ] );
	expect( run.reports[ 3 ].saved ).toEqual( { filename: 'a.csv' } );
	expect( run.started ).toBe( true );
	// Every step moved the export on, so there was nothing to wait for.
	expect( run.pauses ).toEqual( [] );
} );

it( 'says the export could not be started, and takes no step of it', async () => {
	const run = await exported( { created: null } );

	expect( run.reports[ run.reports.length - 1 ] ).toEqual( {
		phase: 'failed',
		message: 'The export could not be started.',
	} );
	expect( run.taken ).toBe( 0 );
	expect( run.started ).toBe( false );
} );

it.each( [
	[
		'the job failed, in the job’s own words',
		step( 'failed', 1, 3, 'The source went away.' ),
		'The source went away.',
	],
	[
		'the job failed and gave no reason',
		step( 'failed', 1 ),
		'The export stopped before it finished.',
	],
	[
		'the job was cancelled',
		step( 'cancelled', 1 ),
		'The export stopped before it finished.',
	],
	[
		'a step could not be asked for',
		null,
		'The export stopped before it finished.',
	],
] )(
	'says why it stopped when %s, and saves nothing',
	async ( _name, answer, message ) => {
		const run = await exported( { steps: [ answer ] } );

		expect( run.reports[ run.reports.length - 1 ] ).toEqual( {
			phase: 'failed',
			message,
		} );
		expect( run.saved ).toEqual( [] );
		expect( run.started ).toBe( true );
	}
);

it( 'waits when a step moved nothing, because something else is taking that step', async () => {
	const run = await exported( {
		steps: [
			step( 'running', 0 ),
			step( 'running', 0 ),
			step( 'completed', 3 ),
		],
	} );

	expect( run.pauses ).toHaveLength( 2 );
	expect( phases( run.reports ).pop() ).toBe( 'done' );
} );

it( 'stops waiting on an export that is not finishing, and says it will finish on its own', async () => {
	const run = await exported( { steps: [ step( 'running', 1 ) ] } );

	expect( run.taken ).toBe( STEPS_TO_WAIT );
	expect( phases( run.reports ).pop() ).toBe( 'later' );
	expect( run.saved ).toEqual( [] );
} );

it( 'says the file is ready when it cannot be fetched', async () => {
	const run = await exported( {
		steps: [ step( 'completed', 3 ) ],
		file: null,
	} );

	expect( run.reports[ run.reports.length - 1 ] ).toEqual( {
		phase: 'failed',
		message: 'Ready, and not fetched.',
	} );
	expect( run.saved ).toEqual( [] );
} );
