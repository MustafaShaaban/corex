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
 */

import { __, sprintf } from '@wordpress/i18n';
import { createSha256 } from './sha256.js';

/** The key every answer of the Releases routes carries (`ReleaseRestGateway::MARK`). */
const MARK = 'corex_release';

/** How long to wait before the second try; doubled before each one after. */
const FIRST_WAIT_MS = 1000;

const DEFAULT_TRIES = 6;

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
 * @param {number}                        [options.tries] How many times a request is made before giving up.
 * @return {Object} `{ overview, inspect, upload }`.
 */
export function createReleaseClient( {
	restUrl,
	nonce,
	fetch = window.fetch.bind( window ),
	wait = sleep,
	tries = DEFAULT_TRIES,
} ) {
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
			response = await fetch( url, {
				credentials: 'same-origin',
				...init,
				headers: { 'X-WP-Nonce': nonce, ...init.headers },
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
	 * Ask until the site answers, waiting longer each time; then give up and say so.
	 *
	 * @param {string} path   The route, after the base.
	 * @param {Object} [init] What `fetch` is given.
	 * @return {Promise<Object>} The marked answer, whether it is a yes or a no.
	 */
	async function ask( path, init = {} ) {
		for ( let attempt = 0; attempt < tries; attempt++ ) {
			if ( attempt > 0 ) {
				await wait( FIRST_WAIT_MS * 2 ** ( attempt - 1 ) );
			}
			const answer = await once( restUrl + path, init );
			if ( answer ) {
				return answer;
			}
		}

		throw new ReleaseUnreachableError(
			sprintf(
				/* translators: %d: how many times the request was made. */
				__(
					'The site did not answer after %d tries. Nothing is assumed about what it did: open this screen again to see.',
					'corex'
				),
				tries
			)
		);
	}

	/**
	 * Ask, and take a no as an error carrying the site's own words.
	 *
	 * @param {string} path   The route, after the base.
	 * @param {Object} [init] What `fetch` is given.
	 * @return {Promise<Object>} The answer's data.
	 */
	async function data( path, init ) {
		const answer = await ask( path, init );
		if ( ! answer.ok ) {
			throw new ReleaseRefusedError( answer.reason, answer.message );
		}
		return answer.data;
	}

	// A site without pretty permalinks spells its routes as a query
	// (`?rest_route=/corex/v1/releases`): what a request adds of its own joins with `&`.
	const querySign = restUrl.includes( '?' ) ? '&' : '?';

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
				`${ route }${ querySign }offset=${ held }`,
				{
					method: 'POST',
					headers: { 'Content-Type': 'application/octet-stream' },
					body: file.slice( held, held + partBytes ),
				}
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

	return {
		overview: () => data( '' ),
		inspect: ( name ) => data( '/inspect', json( { package: name } ) ),
		upload,
	};
}
