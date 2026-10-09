/**
 * The installer's own SHA-256 (spec 107, plan D12), held to Node's.
 *
 * An upload is named by this hash and checked against it when it is whole. One that is wrong
 * for a single length would refuse every package of that length as damaged, on exactly the
 * sites that have no other way to be given one.
 */
import { createHash } from 'node:crypto';

import { createSha256 } from '../sha256.js';

const bytesOf = ( length ) =>
	Uint8Array.from( { length }, ( _, at ) => ( at * 31 + 7 ) % 256 );

const nodes = ( bytes ) =>
	createHash( 'sha256' ).update( bytes ).digest( 'hex' );

function ours( bytes, pieceBytes = bytes.length || 1 ) {
	const sha256 = createSha256();
	for ( let at = 0; at < bytes.length; at += pieceBytes ) {
		sha256.update( bytes.subarray( at, at + pieceBytes ) );
	}
	return sha256.hex();
}

// A block is 64 bytes and its last 9 are the message's end and its length: a message of 55
// bytes ends in the block it is in, and one of 56 needs a block more.
it.each( [ 0, 1, 3, 55, 56, 57, 63, 64, 65, 119, 120, 127, 128, 129, 1000 ] )(
	'agrees with Node for a message of %d bytes',
	( length ) => {
		const bytes = bytesOf( length );

		expect( ours( bytes ) ).toBe( nodes( bytes ) );
	}
);

it.each( [ 1, 7, 63, 64, 65, 200 ] )(
	'gives the same hash however the message is cut: %d bytes at a time',
	( pieceBytes ) => {
		const bytes = bytesOf( 1000 );

		expect( ours( bytes, pieceBytes ) ).toBe( nodes( bytes ) );
	}
);
