/**
 * SHA-256 where the browser will not compute it (spec 107, plan D12).
 *
 * `crypto.subtle` exists only on a page served over HTTPS or from the machine itself. A site
 * whose certificate has not been issued yet is a site that has just moved to a new host, which
 * is a site that needs a release installed from its admin. There, the hash that names an upload
 * is computed here, and a part of the file at a time, so a large package is never held whole.
 *
 * FIPS 180-4, section 6.2. Checked against Node's own for every length around a block's edge.
 */

/* eslint-disable no-bitwise -- the algorithm is defined in rotations and shifts of 32-bit words. */

const BLOCK_BYTES = 64;

/** What the length takes at the end of the last block, after the one bit that ends the message. */
const LENGTH_BYTES = 8;

// The first 32 bits of the fractional parts of the cube roots of the first 64 primes.
const ROUND_CONSTANTS = new Uint32Array( [
	0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1,
	0x923f82a4, 0xab1c5ed5, 0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3,
	0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174, 0xe49b69c1, 0xefbe4786,
	0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
	0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147,
	0x06ca6351, 0x14292967, 0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13,
	0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85, 0xa2bfe8a1, 0xa81a664b,
	0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
	0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a,
	0x5b9cca4f, 0x682e6ff3, 0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208,
	0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2,
] );

// The first 32 bits of the fractional parts of the square roots of the first 8 primes.
const INITIAL_STATE = [
	0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c,
	0x1f83d9ab, 0x5be0cd19,
];

const rotated = ( word, by ) => ( word >>> by ) | ( word << ( 32 - by ) );

/**
 * The sixty-four words one block is expanded to.
 *
 * @param {Uint32Array} words Where they are written.
 * @param {Uint8Array}  bytes The bytes holding the block.
 * @param {number}      at    Where in them it starts.
 */
function expand( words, bytes, at ) {
	for ( let i = 0; i < 16; i++ ) {
		const from = at + i * 4;
		words[ i ] =
			( bytes[ from ] << 24 ) |
			( bytes[ from + 1 ] << 16 ) |
			( bytes[ from + 2 ] << 8 ) |
			bytes[ from + 3 ];
	}
	for ( let i = 16; i < 64; i++ ) {
		const early = words[ i - 15 ];
		const late = words[ i - 2 ];
		words[ i ] =
			words[ i - 16 ] +
			( rotated( early, 7 ) ^ rotated( early, 18 ) ^ ( early >>> 3 ) ) +
			words[ i - 7 ] +
			( rotated( late, 17 ) ^ rotated( late, 19 ) ^ ( late >>> 10 ) );
	}
}

/**
 * One block taken into the state.
 *
 * @param {Uint32Array} state The eight words the hash is.
 * @param {Uint32Array} words The block, expanded.
 */
function compress( state, words ) {
	let [ a, b, c, d, e, f, g, h ] = state;

	for ( let i = 0; i < 64; i++ ) {
		const first =
			( h +
				( rotated( e, 6 ) ^ rotated( e, 11 ) ^ rotated( e, 25 ) ) +
				( ( e & f ) ^ ( ~e & g ) ) +
				ROUND_CONSTANTS[ i ] +
				words[ i ] ) |
			0;
		const second =
			( ( rotated( a, 2 ) ^ rotated( a, 13 ) ^ rotated( a, 22 ) ) +
				( ( a & b ) ^ ( a & c ) ^ ( b & c ) ) ) |
			0;
		h = g;
		g = f;
		f = e;
		e = ( d + first ) | 0;
		d = c;
		c = b;
		b = a;
		a = ( first + second ) | 0;
	}

	[ a, b, c, d, e, f, g, h ].forEach( ( word, i ) => {
		state[ i ] += word;
	} );
}

/**
 * What ends a message: a one bit, zeros to the end of a block less eight bytes, and the
 * message's length in bits in those eight. Two blocks when the length does not fit in the one
 * the message ends in.
 *
 * @param {number} length The message's length in bytes.
 * @return {Uint8Array} The bytes to take in last.
 */
function ending( length ) {
	const inLastBlock = length % BLOCK_BYTES;
	const fits = inLastBlock + 1 + LENGTH_BYTES <= BLOCK_BYTES;
	const end = new Uint8Array( ( fits ? 1 : 2 ) * BLOCK_BYTES - inLastBlock );
	const view = new DataView( end.buffer );

	end[ 0 ] = 0x80;
	// The length in bits is past 32 of them for any file over 512 MB: written as two words.
	view.setUint32( end.length - 8, Math.floor( length / 2 ** 29 ) );
	view.setUint32( end.length - 4, ( length * 8 ) >>> 0 );

	return end;
}

/**
 * A SHA-256 that is given its message in pieces.
 *
 * @return {Object} `{ update( bytes ), hex() }`: take in a `Uint8Array`; then, once, the hash as sixty-four lower-case hexadecimal digits.
 */
export function createSha256() {
	const state = new Uint32Array( INITIAL_STATE );
	const words = new Uint32Array( 64 );
	// The bytes that did not fill a block, kept until the next piece completes one.
	const kept = new Uint8Array( BLOCK_BYTES );
	let keptBytes = 0;
	let length = 0;

	function take( bytes ) {
		let at = 0;

		if ( keptBytes > 0 ) {
			at = Math.min( BLOCK_BYTES - keptBytes, bytes.length );
			kept.set( bytes.subarray( 0, at ), keptBytes );
			keptBytes += at;
			if ( keptBytes < BLOCK_BYTES ) {
				return;
			}
			expand( words, kept, 0 );
			compress( state, words );
			keptBytes = 0;
		}

		for ( ; at + BLOCK_BYTES <= bytes.length; at += BLOCK_BYTES ) {
			expand( words, bytes, at );
			compress( state, words );
		}

		kept.set( bytes.subarray( at ) );
		keptBytes = bytes.length - at;
	}

	return {
		update( bytes ) {
			length += bytes.length;
			take( bytes );
		},
		hex() {
			take( ending( length ) );
			return Array.from( state, ( word ) =>
				word.toString( 16 ).padStart( 8, '0' )
			).join( '' );
		},
	};
}
