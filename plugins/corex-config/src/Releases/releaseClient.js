/**
 * The installer's own client (spec 107, plan D12; FR-025).
 *
 * The Releases routes are not asked through the admin's API helper. A shared host puts pages of
 * its own in front of a site, with status 200 and a body that is not the site's answer, and a
 * client that read one of those as "installed" would be worse than no client. This one takes an
 * answer as one only if it is JSON carrying the installer's mark. Anything else, whatever its
 * status, is "wait and ask again", for a stated number of tries; then it says it could not reach
 * the site, and nothing is assumed about what happened.
 *
 * A refusal is an answer. It is marked, it is not asked for again, and it is given as the site
 * worded it.
 *
 * Once an installation is in hand, its key goes with every request, in a header and in the
 * address: a shared host may not hand a header to PHP, and WordPress's maintenance answer lets
 * through only a request whose key it finds (FR-024a). And "wait and ask again" then lasts as
 * long as that answer can: WordPress lets every request in by itself ten minutes after the
 * release was put in place, so a client that is still asking then finishes the installation
 * whatever the host did to its key.
 */

import { __, sprintf } from '@wordpress/i18n';
import { withQuery } from '../admin/restAddress.js';
import { createSha256 } from './sha256.js';

/** The key every answer of the Releases routes carries (`ReleaseRestGateway::MARK`). */
const MARK = 'corex_release';

/** The header an installation's key is carried in (`ReleaseMaintenance::HEADER`). */
const KEY_HEADER = 'X-Corex-Release-Key';

/** The query argument it is carried in as well (`ReleaseMaintenance::ARGUMENT`). */
const KEY_ARGUMENT = 'corex_release_key';

/** How long to wait before the second try; doubled before each one after, up to the longest. */
const FIRST_WAIT_MS = 1000;
const LONGEST_WAIT_MS = 15000;

const DEFAULT_TRIES = 6;

/**
 * How long a request of an installation is asked again: WordPress's ten minutes of maintenance
 * (`wp_is_maintenance_mode()`), and one more for the request that put the release in place.
 */
const MAINTENANCE_MS = 11 * 60 * 1000;

/** How much of a file is read at a time where the hash is computed here. */
const HASHED_AT_A_TIME = 4 * 1024 * 1024;

/** The site answered, and the answer was no: wrong package, damaged upload, not allowed. */
export class ReleaseRefusedError extends Error {
	constructor( reason, message ) {
		super( message );
		this.name = 'ReleaseRefusedError';
		this.reason = reason;
	}
}

/** No answer of the installer's came back. What happened on the site is not known. */
export class ReleaseUnreachableError extends Error {
	constructor( message ) {
		super( message );
		this.name = 'ReleaseUnreachableError';
	}
}

/**
 * A response's body as the installer's answer, or null when it is not one.
 *
 * @param {string} text The body.
 * @return {Object|null} The marked answer.
 */
function markedAnswer( text ) {
	try {
		const body = JSON.parse( text );
		return body && body[ MARK ] === 1 ? body : null;
	} catch {
		return null;
	}
}

/**
 * WordPress's own refusal, made before the installer's routes are reached: the session has
 * expired, or the person may not do this. It is not marked and asking again cannot change it.
 *
 * @param {number} status The response's status.
 * @param {string} text   Its body.
 * @return {boolean} Whether WordPress turned the request away.
 */
function turnedAwayByWordPress( status, text ) {
	if ( status !== 401 && status !== 403 ) {
		return false;
	}
	try {
		return String( JSON.parse( text )?.code || '' ).startsWith( 'rest_' );
	} catch {
		return false;
	}
}

const sleep = ( ms ) => new Promise( ( resolve ) => setTimeout( resolve, ms ) );

/**
 * The SHA-256 of a file, as sixty-four lower-case hexadecimal digits.
 *
 * The browser computes it where it will: on a page served over HTTPS, or from the machine
 * itself. On any other page it has no `crypto.subtle` at all, and the hash is computed here,
 * a part of the file at a time.
 *
 * @param {File} file The file.
 * @return {Promise<string>} Its hash.
 */
async function sha256Of( file ) {
	if ( ! window.crypto?.subtle ) {
		const sha256 = createSha256();
		for ( let at = 0; at < file.size; at += HASHED_AT_A_TIME ) {
			const part = file.slice( at, at + HASHED_AT_A_TIME );
			sha256.update( new Uint8Array( await part.arrayBuffer() ) );
		}
		return sha256.hex();
	}

	const digest = await window.crypto.subtle.digest(
		'SHA-256',
		await file.arrayBuffer()
	);
	return Array.from( new Uint8Array( digest ) )
		.map( ( byte ) => byte.toString( 16 ).padStart( 2, '0' ) )
		.join( '' );
}

/**
 * @param {Object}                        options
 * @param {string}                        options.restUrl The Releases routes' base, without a trailing slash.
 * @param {string}                        options.nonce   The REST nonce the screen was given.
 * @param {typeof window.fetch}           [options.fetch] `fetch`, or what stands in for it.
 * @param {(ms: number) => Promise<void>} [options.wait]  Waits a number of milliseconds.
 * @param {number}                        [options.tries] How many times a request is made before giving up, when no installation is in hand.
 * @param {() => number}                  [options.now]   The time in milliseconds, as `Date.now` gives it.
 * @return {Object} `{ overview, inspect, upload, prepare, confirm, step, finish, cancel, goBack }`.
 */
export function createReleaseClient( {
	restUrl,
	nonce,
	fetch = window.fetch.bind( window ),
	wait = sleep,
	tries = DEFAULT_TRIES,
	now = Date.now,
} ) {
	// The key of the installation in hand. The site makes it when an installation is prepared
	// and keeps only its hash; it is held here for as long as the page is open, and goes with
	// every request from then on. While a release is being put in place WordPress turns away
	// every request but the one that carries it.
	let installationKey = null;

	/**
	 * One try: the marked answer, or null when what came back was not one.
	 *
	 * @param {string} url  The route.
	 * @param {Object} init What `fetch` is given.
	 * @return {Promise<Object|null>} The answer.
	 */
	async function once( url, init ) {
		let response;
		try {
			response = await fetch( withKey( url ), {
				credentials: 'same-origin',
				...init,
				headers: {
					'X-WP-Nonce': nonce,
					...( installationKey && {
						[ KEY_HEADER ]: installationKey,
					} ),
					...init.headers,
				},
			} );
		} catch {
			return null;
		}

		const text = await response.text();

		if ( turnedAwayByWordPress( response.status, text ) ) {
			throw new ReleaseUnreachableError(
				__(
					'The site did not accept this request: the session may have expired. Reload the page and sign in again.',
					'corex'
				)
			);
		}

		return markedAnswer( text );
	}

	/**
	 * An address with the installation's key added, when there is one in hand.
	 *
	 * @param {string} url The address.
	 * @return {string} The address the request is made to.
	 */
	function withKey( url ) {
		return installationKey
			? withQuery(
					url,
					`${ KEY_ARGUMENT }=${ encodeURIComponent( installationKey ) }`
				)
			: url;
	}

	/**
	 * Whether to ask again after a try that brought no answer: for as long as WordPress's
	 * maintenance answer can last while an installation is in hand, and for the tries given
	 * otherwise.
	 *
	 * @param {number} made    How many tries have been made.
	 * @param {number} started When the first was made.
	 * @return {boolean} Whether to ask again.
	 */
	function asksAgain( made, started ) {
		return installationKey
			? now() - started < MAINTENANCE_MS
			: made < tries;
	}

	/**
	 * Ask until the site answers, waiting longer each time; then give up and say so.
	 *
	 * @param {string}               path     The route, after the base.
	 * @param {Object}               [init]   What `fetch` is given.
	 * @param {string}               [query]  What the request adds to the address.
	 * @param {(ms: number) => void} [onWait] Called before each wait, with how long it is.
	 * @return {Promise<Object>} The marked answer, whether it is a yes or a no.
	 */
	async function ask( path, init = {}, query = '', onWait = () => {} ) {
		let made = 1;

		for ( const started = now(); ; made++ ) {
			const answer = await once(
				withQuery( restUrl + path, query ),
				init
			);
			if ( answer ) {
				return answer;
			}
			if ( ! asksAgain( made, started ) ) {
				break;
			}
			const ms = Math.min(
				FIRST_WAIT_MS * 2 ** ( made - 1 ),
				LONGEST_WAIT_MS
			);
			onWait( ms );
			await wait( ms );
		}

		throw new ReleaseUnreachableError(
			sprintf(
				/* translators: %d: how many times the request was made. */
				__(
					'The site did not answer after %d tries. Nothing is assumed about what it did: open this screen again to see.',
					'corex'
				),
				made
			)
		);
	}

	/**
	 * Ask, and take a no as an error carrying the site's own words.
	 *
	 * @param {string}               path     The route, after the base.
	 * @param {Object}               [init]   What `fetch` is given.
	 * @param {(ms: number) => void} [onWait] Called before each wait for an answer.
	 * @return {Promise<Object>} The answer's data.
	 */
	async function data( path, init, onWait ) {
		const answer = await ask( path, init, '', onWait );
		if ( ! answer.ok ) {
			throw new ReleaseRefusedError( answer.reason, answer.message );
		}
		return answer.data;
	}

	const json = ( body ) => ( {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify( body ),
	} );

	/**
	 * Send a package in parts, from wherever the site says it holds it to.
	 *
	 * @param {File}                            file                 The package.
	 * @param {Object}                          options
	 * @param {number}                          options.partBytes    How much to send at a time.
	 * @param {(held: number) => void}          [options.onProgress] Called with how many bytes the site holds.
	 * @param {(file: File) => Promise<string>} [options.hash]       Computes the file's SHA-256.
	 * @return {Promise<string>} The name the package is kept under.
	 */
	async function upload(
		file,
		{ partBytes, onProgress = () => {}, hash = sha256Of }
	) {
		const sha256 = await hash( file );
		const route = `/uploads/${ sha256 }`;
		let held = ( await data( route ) ).received;
		onProgress( held );

		while ( held < file.size ) {
			const answer = await ask(
				route,
				{
					method: 'POST',
					headers: { 'Content-Type': 'application/octet-stream' },
					body: file.slice( held, held + partBytes ),
				},
				`offset=${ held }`
			);

			// Out of step is the site saying how much it holds: the answer to a part
			// that arrived when its own answer did not. Anything else that is a no ends it.
			if ( ! answer.ok && answer.reason !== 'out_of_step' ) {
				throw new ReleaseRefusedError( answer.reason, answer.message );
			}

			held = answer.ok ? answer.data.received : answer.received;
			onProgress( held );
		}

		return (
			await data(
				`${ route }/complete`,
				json( { size: file.size, name: file.name } )
			)
		).package;
	}

	/**
	 * What installing a package would do, and the way back. The key in the answer is kept for
	 * the requests that follow.
	 *
	 * @param {string} name The package's name on the site.
	 * @return {Promise<Object>} The statement, `drops`, `key`, `recovery_link`, `copies_days`.
	 */
	async function prepare( name ) {
		const prepared = await data(
			'/install/prepare',
			json( { package: name } )
		);
		installationKey = prepared.key;

		return prepared;
	}

	async function cancel() {
		await data( '/install/cancel', json( {} ) );
		installationKey = null;
	}

	/**
	 * The release just installed brings the database to it, a part at a time. When it says it
	 * is done the installation is over, and so is its key.
	 *
	 * @param {Object}               [options]
	 * @param {(ms: number) => void} [options.onWait] Called before each wait for an answer.
	 * @return {Promise<Object>} Where the installation stands, or `{ done, version, changes }`.
	 */
	async function finish( { onWait } = {} ) {
		const stand = await data( '/install/finish', json( {} ), onWait );
		if ( stand.done ) {
			installationKey = null;
		}

		return stand;
	}

	return {
		overview: () => data( '' ),
		inspect: ( name ) => data( '/inspect', json( { package: name } ) ),
		upload,
		prepare,
		confirm: ( phrase, more = {} ) =>
			data( '/install/confirm', json( { phrase, ...more } ) ),
		step: ( { onWait } = {} ) =>
			data( '/install/step', json( {} ), onWait ),
		finish,
		goBack: ( phrase ) => data( '/return', json( { phrase } ) ),
		cancel,
	};
}
