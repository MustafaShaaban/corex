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
 * waiting takes no time. The clock moves only by what the client waits.
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
// WordPress's own answer while a release is put in place, to a request it did not let through.
const maintenancePage = response( 503, {
	code: 'wp_die',
	message:
		'Briefly unavailable for scheduled maintenance. Check back in a minute.',
	data: { status: 503 },
} );
const TEN_MINUTES = 10 * 60 * 1000;

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
	let clock = 0;
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
			wait: async ( ms ) => {
				waited.push( ms );
				clock += ms;
			},
			now: () => clock,
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

describe( 'an installation', () => {
	const KEY = 'a'.repeat( 64 );
	const prepared = marked( { version: '0.45.0', key: KEY } );
	const standing = marked( { step: 'verify', cursor: 0, of: 3 } );
	const keyOf = ( request ) => request.headers[ 'X-Corex-Release-Key' ];
	const keyed = ( route ) => `${ REST }${ route }?corex_release_key=${ KEY }`;
	const done = marked( { done: true, version: '0.45.0', changes: [] } );

	it( 'carries the installation’s key on every request once the site has given one, in its header and its address', async () => {
		const { client, sent } = scripted( [
			marked( { packages: [] } ),
			prepared,
			standing,
			standing,
			marked( { done: true, version: '0.45.0', changes: [] } ),
		] );

		await client.overview();
		await client.prepare( 'corex-release-acme-0.45.0.zip' );
		await client.confirm( '0.45.0', { older: false } );
		await client.step();
		await client.finish();

		// WordPress's maintenance answer lets through the request that carries it, and no
		// other; a shared host may not hand the header to PHP, and the address always arrives.
		expect( sent.map( keyOf ) ).toEqual( [
			undefined,
			undefined,
			KEY,
			KEY,
			KEY,
		] );
		expect( sent.map( ( request ) => request.url ) ).toEqual( [
			REST,
			`${ REST }/install/prepare`,
			keyed( '/install/confirm' ),
			keyed( '/install/step' ),
			keyed( '/install/finish' ),
		] );
	} );

	it( 'adds the key to the address as the site spells it, where its routes are a query', async () => {
		const plain =
			'https://acme.test/index.php?rest_route=/corex/v1/releases';
		const { client, sent } = scripted( [ prepared, standing ], plain );

		await client.prepare( 'corex-release-acme-0.45.0.zip' );
		await client.step();

		expect( sent[ 1 ].url ).toBe(
			`${ plain }/install/step&corex_release_key=${ KEY }`
		);
	} );

	it( 'goes on asking through WordPress’s maintenance, and finishes when the site answers', async () => {
		// Thirty-two of WordPress's maintenance answers: nine minutes of them, at most fifteen
		// seconds apart. That is the host of 2026-10-18, where the key did not get through
		// and WordPress let the request in by itself when its ten minutes were up.
		const { client, sent, waited } = scripted( [
			prepared,
			...Array( 32 ).fill( maintenancePage ),
			done,
		] );
		const waits = [];

		await client.prepare( 'corex-release-acme-0.45.0.zip' );

		await expect(
			client.finish( { onWait: ( ms ) => waits.push( ms ) } )
		).resolves.toEqual( { done: true, version: '0.45.0', changes: [] } );
		expect( sent ).toHaveLength( 34 );
		expect( Math.max( ...waited ) ).toBe( 15000 );
		expect( waits ).toEqual( waited );
	} );

	it( 'gives up only after WordPress’s maintenance would have ended by itself, saying how many times it asked', async () => {
		const { client, sent, waited } = scripted( [
			prepared,
			...Array( 60 ).fill( maintenancePage ),
		] );

		await client.prepare( 'corex-release-acme-0.45.0.zip' );

		const failure = client.step();
		await expect( failure ).rejects.toThrow( ReleaseUnreachableError );
		const asked = sent.length - 1;
		await expect( failure ).rejects.toThrow(
			`The site did not answer after ${ asked } tries.`
		);
		const waitedInAll = waited.reduce( ( sum, ms ) => sum + ms, 0 );
		expect( waitedInAll ).toBeGreaterThan( TEN_MINUTES );
		// The last wait was the one that reached past them: no try is wasted after.
		expect( waitedInAll - waited.at( -1 ) ).toBeLessThan(
			TEN_MINUTES + 60 * 1000
		);
	} );

	it( 'lets go of the key once the installation is finished', async () => {
		const { client, sent } = scripted( [
			prepared,
			done,
			marked( { packages: [] } ),
		] );

		await client.prepare( 'corex-release-acme-0.45.0.zip' );
		await client.finish();
		await client.overview();

		expect( keyOf( sent[ 2 ] ) ).toBeUndefined();
		expect( sent[ 2 ].url ).toBe( REST );
	} );

	it( 'does not let go of the key while a network’s sites are still being brought to the release', async () => {
		const { client, sent } = scripted( [
			prepared,
			marked( { step: 'finish', cursor: 20, of: 0, done: false } ),
			done,
		] );

		await client.prepare( 'corex-release-acme-0.45.0.zip' );
		await client.finish();
		await client.finish();

		expect( keyOf( sent[ 2 ] ) ).toBe( KEY );
	} );

	it( 'sends what was typed, and what else the confirmation needs', async () => {
		const { client, sent } = scripted( [ prepared, standing ] );

		await client.prepare( 'corex-release-acme-0.45.0.zip' );
		await client.confirm( '0.45.0', { client: 'acme', older: true } );

		expect( JSON.parse( sent[ 0 ].body ) ).toEqual( {
			package: 'corex-release-acme-0.45.0.zip',
		} );
		expect( JSON.parse( sent[ 1 ].body ) ).toEqual( {
			phrase: '0.45.0',
			client: 'acme',
			older: true,
		} );
	} );

	it( 'lets go of the key when the installation is given up', async () => {
		const { client, sent } = scripted( [
			prepared,
			marked( { cancelled: true } ),
			marked( { packages: [] } ),
		] );

		await client.prepare( 'corex-release-acme-0.45.0.zip' );
		await client.cancel();
		await client.overview();

		expect( keyOf( sent[ 2 ] ) ).toBeUndefined();
	} );

	it( 'asks for the next piece again when a host’s own page comes back in its place', async () => {
		const { client, waited } = scripted( [
			prepared,
			challengePage,
			standing,
		] );

		await client.prepare( 'corex-release-acme-0.45.0.zip' );

		await expect( client.step() ).resolves.toEqual( {
			step: 'verify',
			cursor: 0,
			of: 3,
		} );
		expect( waited ).toHaveLength( 1 );
	} );
} );
