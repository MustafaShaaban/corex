/**
 * Takes one export from "asked for" to "saved", for a person who is waiting on it (spec 103,
 * FR-022 to FR-024).
 *
 * An export is a job that a scheduler would finish in its own time. The dialog does not wait for
 * the scheduler: it asks the server to take the job's next step, again and again, shows how far it
 * has got, and saves the file when the last step is done. Both exports do it this way, so the
 * steps are here and each export supplies its own requests.
 */
import { __ } from '@wordpress/i18n';

/** Steps to wait through before telling the person the export will finish on its own. */
export const STEPS_TO_WAIT = 400;

/** A pause when a step moved nothing: the scheduler is taking that step, and will finish it. */
const PAUSE_WHEN_IDLE_MS = 700;

const FAILED_STATES = [ 'failed', 'cancelled' ];

function wait( milliseconds ) {
	return new Promise( ( resolve ) => {
		setTimeout( resolve, milliseconds );
	} );
}

/**
 * @param {Object}      io
 * @param {Function}    io.create        Asks for the export; resolves to its id, or to nothing when it could not be started.
 * @param {Function}    io.advance       Takes one step of it, by id; resolves to `{state,processed,total,error}`, or to nothing when the step could not be asked for.
 * @param {Function}    io.download      Fetches the finished file, by id; resolves to what `save` takes, or to nothing.
 * @param {Function}    io.save          Hands the file to the browser.
 * @param {Function}    io.report        Receives `{phase}` and what belongs to it, each time something changes.
 * @param {number|null} io.total         How many records the export was counted to hold.
 * @param {string}      io.notDownloaded What to say when the file is ready and could not be fetched.
 * @param {Function}    [io.pause]       Waits a number of milliseconds; replaced in tests.
 * @return {Promise<boolean>} Whether an export was started, whatever became of it.
 */
export async function runExport( {
	create,
	advance,
	download,
	save,
	report,
	total,
	notDownloaded,
	pause = wait,
} ) {
	report( { phase: 'running', progress: { processed: 0, total } } );

	const id = await create();
	if ( ! id ) {
		report( {
			phase: 'failed',
			message: __( 'The export could not be started.', 'corex' ),
		} );
		return false;
	}

	let progress = { state: 'queued', processed: 0, total };
	for (
		let step = 0;
		step < STEPS_TO_WAIT && progress.state !== 'completed';
		step++
	) {
		const before = progress.processed;
		const advanced = await advance( id );
		if ( ! advanced || FAILED_STATES.includes( advanced.state ) ) {
			report( {
				phase: 'failed',
				message:
					advanced?.error ||
					__( 'The export stopped before it finished.', 'corex' ),
			} );
			return true;
		}
		progress = advanced;
		report( { phase: 'running', progress } );
		if ( progress.state !== 'completed' && progress.processed === before ) {
			await pause( PAUSE_WHEN_IDLE_MS );
		}
	}

	if ( progress.state !== 'completed' ) {
		report( { phase: 'later' } );
		return true;
	}

	const saved = await download( id );
	if ( ! saved ) {
		report( { phase: 'failed', message: notDownloaded } );
		return true;
	}

	save( saved );
	report( { phase: 'done', saved } );
	return true;
}
