/**
 * The installer's own client (spec 107, plan D12; FR-025).
 *
 * A shared host puts pages of its own in front of a site: a challenge, a "too many requests", a
 * maintenance page. They arrive with status 200 and a body that is not the site's answer, and
 * the admin's ordinary API helper reads them as one. An installation that mistook a challenge
 * page for "installed" is the failure this client exists to make impossible: it takes an answer
 * as one only if it carries the installer's mark, and anything else as "wait and ask again".
 *
 * The network is the one thing replaced: `fetch` answers what each test scripts, in order, and
 * waiting takes no time.
 */
import { createHash } from 'node:crypto';

import {
	createReleaseClient,
	ReleaseRefusedError,
	ReleaseUnreachableError,
} from '../releaseClient.js';

const REST = 'https://acme.test/wp-json/corex/v1/releases';

// A response as `fetch` gives one, as far as the client reads it.
const response = ( status, body ) => ( {
	status,
	text: async () =>
		typeof body === 'string' ? body : JSON.stringify( body ),
} );

const marked = ( data ) =>
	response( 200, { corex_release: 1, ok: true, data } );
const refusal = ( reason, message ) =>
	response( 422, { corex_release: 1, ok: false, reason, message } );
const outOfStep = ( received ) =>
	response( 409, {
		corex_release: 1,
		ok: false,
		reason: 'out_of_step',
		received,
	} );
const challengePage = response(
	200,
	'<html><body>Checking your browser…</body></html>'
);

/**
 * A client over a scripted network.
 *
 * @param {Array}  answers   What `fetch` answers, in order; a function is called with the request.
 * @param {string} [restUrl] Where the site says its Releases routes are.
 * @return {Object} `{ client, sent, waited }`.
 */
function scripted( answers, restUrl = REST ) {
	const sent = [];
	const waited = [];
	const fetch = async ( url, init ) => {
		sent.push( { url, ...init } );
		const next = answers.shift();
		if ( next === undefined ) {
			throw new Error( 'the test scripted no more answers' );
		}
		if ( next instanceof Error ) {
			throw next;
		}
		return typeof next === 'function' ? next( url, init ) : next;
	};

	return {
		client: createReleaseClient( {
			restUrl,
			nonce: 'a-nonce',
			fetch,
			wait: async ( ms ) => waited.push( ms ),
			tries: 4,
		} ),
		sent,
		waited,
	};
}

// A file as the browser gives one, as far as the client reads it. A slice of it is only
// where it starts and ends: which bytes are sent is what the tests ask about.
function file( bytes, name = 'corex-release-acme-0.44.0-20261008-180000.zip' ) {
	const buffer = Uint8Array.from( bytes ).buffer;
	return {
		name,
		size: buffer.byteLength,
		arrayBuffer: async () => buffer,
		// As a file does: a slice past the end stops at the end.
		slice: ( from, to ) => ( {
			from,
			to: Math.min( to, buffer.byteLength ),
		} ),
	};
}

describe( 'what counts as an answer', () => {
	it( 'takes a marked answer, and signs its request', async () => {
		const { client, sent } = scripted( [ marked( { packages: [] } ) ] );

		await expect( client.overview() ).resolves.toEqual( { packages: [] } );
		expect( sent[ 0 ].url ).toBe( REST );
		expect( sent[ 0 ].headers[ 'X-WP-Nonce' ] ).toBe( 'a-nonce' );
	} );

	it.each( [
		[ 'a host’s challenge page, with status 200', challengePage ],
		[ 'JSON that is not the installer’s', response( 200, { ok: true } ) ],
		[ 'a gateway’s 502', response( 502, 'Bad Gateway' ) ],
		[ 'a connection that dropped', new TypeError( 'Failed to fetch' ) ],
	] )( 'waits and asks again after %s', async ( _what, notAnAnswer ) => {
		const { client, sent, waited } = scripted( [
			notAnAnswer,
			marked( { packages: [] } ),
		] );

		await expect( client.overview() ).resolves.toEqual( { packages: [] } );
		expect( sent ).toHaveLength( 2 );
		expect( waited ).toHaveLength( 1 );
	} );

	it( 'waits longer each time, and gives up after the tries it was given, saying so', async () => {
		const { client, sent, waited } = scripted( [
			challengePage,
			challengePage,
			challengePage,
			challengePage,
		] );

		await expect( client.overview() ).rejects.toThrow(
			ReleaseUnreachableError
		);
		expect( sent ).toHaveLength( 4 );
		// Not after the last try: there is nothing left to wait for.
		expect( waited ).toEqual( [ 1000, 2000, 4000 ] );
	} );

	it( 'does not ask again when the site says who is asking may not', async () => {
		// WordPress's own refusal, before the installer's routes are reached: an expired
		// session. Asking again cannot help; reloading the page can.
		const { client, sent } = scripted( [
			response( 403, { code: 'rest_cookie_invalid_nonce' } ),
		] );

		await expect( client.overview() ).rejects.toThrow(
			ReleaseUnreachableError
		);
		expect( sent ).toHaveLength( 1 );
	} );

	it( 'gives a refusal as the site worded it, and does not ask again', async () => {
		const { client, sent } = scripted( [
			refusal(
				'other_client',
				'This package is for globex, and this site is acme.'
			),
		] );

		const refused = await client
			.inspect( 'corex-release-globex.zip' )
			.catch( ( error ) => error );

		expect( refused ).toBeInstanceOf( ReleaseRefusedError );
		expect( refused.reason ).toBe( 'other_client' );
		expect( refused.message ).toBe(
			'This package is for globex, and this site is acme.'
		);
		expect( sent ).toHaveLength( 1 );
	} );
} );

describe( 'sending a package', () => {
	const bytes = [ 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 ];
	const sha256 = '0'.repeat( 64 );
	const upload = ( client, more = {} ) =>
		client.upload( file( bytes ), {
			partBytes: 4,
			hash: async () => sha256,
			...more,
		} );

	it( 'sends it in parts from the start, then says it is all there', async () => {
		const { client, sent } = scripted( [
			marked( { received: 0 } ),
			marked( { received: 4 } ),
			marked( { received: 8 } ),
			marked( { received: 10 } ),
			marked( {
				package: 'corex-release-acme-0.44.0-20261008-180000.zip',
			} ),
		] );

		await expect( upload( client ) ).resolves.toBe(
			'corex-release-acme-0.44.0-20261008-180000.zip'
		);
		expect( sent.slice( 1, 4 ).map( ( request ) => request.url ) ).toEqual(
			[
				`${ REST }/uploads/${ sha256 }?offset=0`,
				`${ REST }/uploads/${ sha256 }?offset=4`,
				`${ REST }/uploads/${ sha256 }?offset=8`,
			]
		);
		expect( sent[ 3 ].body ).toEqual( { from: 8, to: 10 } );
		expect( JSON.parse( sent[ 4 ].body ) ).toEqual( {
			size: 10,
			name: 'corex-release-acme-0.44.0-20261008-180000.zip',
		} );
	} );

	it( 'adds to the address as the site spells it, where its routes are a query', async () => {
		// Without pretty permalinks a route is `?rest_route=…`. A second `?` would make the
		// offset part of the route, and WordPress would know no such route.
		const plain =
			'https://acme.test/index.php?rest_route=/corex/v1/releases';
		const { client, sent } = scripted(
			[
				marked( { received: 8 } ),
				marked( { received: 10 } ),
				marked( { package: 'kept.zip' } ),
			],
			plain
		);

		await upload( client );

		expect( sent[ 1 ].url ).toBe(
			`${ plain }/uploads/${ sha256 }&offset=8`
		);
	} );

	it( 'goes on from where the site says it stopped', async () => {
		// Part of it arrived in an earlier attempt: nothing that is there is sent again.
		const { client, sent } = scripted( [
			marked( { received: 8 } ),
			marked( { received: 10 } ),
			marked( { package: 'kept.zip' } ),
		] );

		await upload( client );

		expect( sent[ 1 ].url ).toBe(
			`${ REST }/uploads/${ sha256 }?offset=8`
		);
		expect( sent ).toHaveLength( 3 );
	} );

	it( 'sends a part again from where the site holds it, when one was lost on the way', async () => {
		const { client, sent } = scripted( [
			marked( { received: 0 } ),
			marked( { received: 4 } ),
			// The second part arrived and its answer did not: sent again, it is out of step.
			outOfStep( 8 ),
			marked( { received: 10 } ),
			marked( { package: 'kept.zip' } ),
		] );

		await upload( client );

		expect( sent.slice( 1, 4 ).map( ( request ) => request.url ) ).toEqual(
			[
				`${ REST }/uploads/${ sha256 }?offset=0`,
				`${ REST }/uploads/${ sha256 }?offset=4`,
				`${ REST }/uploads/${ sha256 }?offset=8`,
			]
		);
	} );

	it( 'says how far it has got after each part', async () => {
		const { client } = scripted( [
			marked( { received: 0 } ),
			marked( { received: 4 } ),
			marked( { received: 8 } ),
			marked( { received: 10 } ),
			marked( { package: 'kept.zip' } ),
		] );
		const progress = [];

		await upload( client, {
			onProgress: ( sentBytes ) => progress.push( sentBytes ),
		} );

		expect( progress ).toEqual( [ 0, 4, 8, 10 ] );
	} );

	it( 'names the upload by the file’s hash on a page where the browser computes none', async () => {
		// A page that is not served over HTTPS has no `crypto.subtle`, and neither has this
		// one: the hash is the client's own, read from the file a part at a time.
		expect( window.crypto?.subtle ).toBeUndefined();
		const held = Uint8Array.from( bytes );
		const onDisk = {
			name: 'corex-release-acme-0.44.0-20261008-180000.zip',
			size: held.length,
			slice: ( from, to ) => ( {
				arrayBuffer: async () => held.slice( from, to ).buffer,
			} ),
		};
		const { client, sent } = scripted( [
			marked( { received: 10 } ),
			marked( { package: 'kept.zip' } ),
		] );

		await client.upload( onDisk, { partBytes: 4 } );

		expect( sent[ 0 ].url ).toBe(
			`${ REST }/uploads/${ createHash( 'sha256' )
				.update( held )
				.digest( 'hex' ) }`
		);
	} );

	it( 'stops when the site refuses the package, with the site’s words', async () => {
		const { client } = scripted( [
			marked( { received: 0 } ),
			marked( { received: 4 } ),
			marked( { received: 8 } ),
			marked( { received: 10 } ),
			refusal( 'damaged', 'What arrived is not the file that was sent.' ),
		] );

		await expect( upload( client ) ).rejects.toThrow(
			'What arrived is not the file that was sent.'
		);
	} );
} );
