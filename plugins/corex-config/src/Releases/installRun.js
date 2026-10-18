/**
 * An installation, driven from the browser a request at a time (spec 107, plan D8; FR-025).
 *
 * Each request does a piece on the site and answers where the installation stands. This asks
 * for the next, paced, until the release's files are in place, and then asks the release that
 * was just installed to finish: its database step, across as many requests as it needs.
 *
 * From the swap on, the files may be the new release's. A failure there carries
 * `filesInPlace`, so the screen offers the way back and not "try again". The one failure there
 * that does not is the site saying a folder would not move and every folder is back.
 */

/** The pause before each request after the first: a host answers a burst with a challenge page. */
const PACE_MS = 400;

/** How long to wait when the site says another request of the installation is still at work. */
const BUSY_MS = 2000;

/** How many times in a row the site may say so before it is taken as the answer. */
const BUSY_TRIES = 10;

const BUSY = 'installation_busy';

/** The steps from which the site's folders may be the new release's. */
const FILES_MAY_BE_IN_PLACE = [ 'swap', 'finish', 'stuck' ];

/** The site's answer when a folder would not move and every folder was put back (`ReleaseSwap::FAILED`). */
const EVERY_FOLDER_IS_BACK = 'swap_failed';

const sleep = ( ms ) => new Promise( ( resolve ) => setTimeout( resolve, ms ) );

/**
 * Ask, and ask again while the answer is that another request is at work.
 *
 * @param {() => Promise<Object>}         request The question.
 * @param {(ms: number) => Promise<void>} wait    Waits a number of milliseconds.
 * @return {Promise<Object>} The answer.
 */
async function askPastBusy( request, wait ) {
	for ( let attempt = 1; ; attempt++ ) {
		try {
			return await request();
		} catch ( failure ) {
			if ( failure.reason !== BUSY || attempt >= BUSY_TRIES ) {
				throw failure;
			}
			await wait( BUSY_MS );
		}
	}
}

/**
 * @param {Object}                        client            The installer's client.
 * @param {Object}                        [options]
 * @param {string}                        [options.from]    The step the installation stands at: `finish` for one whose files are in place.
 * @param {(stand: Object) => void}       [options.onStand] Called with where the installation stands after each request.
 * @param {(ms: number) => void}          [options.onWait]  Called when the site has not answered a request, before it is asked again.
 * @param {(ms: number) => Promise<void>} [options.wait]    Waits a number of milliseconds.
 * @return {Promise<{version: string, changes: Array}>} The release now installed, and the database changes made.
 */
export async function runInstallation(
	client,
	{
		from = 'unpack',
		onStand = () => {},
		onWait = () => {},
		wait = sleep,
	} = {}
) {
	let step = from;
	let asked = 0;

	const paced = async ( request ) => {
		if ( asked++ > 0 ) {
			await wait( PACE_MS );
		}
		return askPastBusy( () => request( { onWait } ), wait );
	};

	try {
		while ( step !== 'finish' ) {
			const stand = await paced( client.step );
			step = stand.step;
			onStand( stand );
		}

		for (;;) {
			const stand = await paced( client.finish );
			if ( stand.done ) {
				return { version: stand.version, changes: stand.changes };
			}
			onStand( stand );
		}
	} catch ( failure ) {
		failure.filesInPlace =
			FILES_MAY_BE_IN_PLACE.includes( step ) &&
			failure.reason !== EVERY_FOLDER_IS_BACK;
		throw failure;
	}
}
