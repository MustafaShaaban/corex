/**
 * What a folder of a release package holds, as three numbers a site can check where the package
 * lands (spec 107, plan D2): how many files, how many bytes, and one hash of all of them.
 *
 * The hash is SHA-256 over one line per file, the lines sorted by path as bytes:
 *
 *     <path, forward slashes, relative to the folder> NUL <size in bytes> NUL <SHA-256 of the file> LF
 *
 * `plugins/corex-config/src/Releases/ReleaseContentHash.php` computes the same thing in PHP over
 * what a site unpacked. A change here is a change there, and to every package already built.
 */
import { createHash } from 'node:crypto';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const sha256 = ( data ) => createHash( 'sha256' ).update( data ).digest( 'hex' );

/**
 * Every file under a folder, as its path from the folder with forward slashes.
 *
 * @param {string} dir The folder.
 * @return {string[]} The files' relative paths, in no particular order.
 */
function filesOf( dir ) {
	return readdirSync( dir, { recursive: true, withFileTypes: true } )
		.filter( ( entry ) => entry.isFile() )
		.map( ( entry ) =>
			join( entry.parentPath ?? entry.path, entry.name )
				.slice( dir.length + 1 )
				.split( '\\' )
				.join( '/' )
		);
}

/**
 * Describe a folder: its files, its bytes, and the hash of both.
 *
 * @param {string} dir The folder, as an absolute path with no trailing separator.
 * @return {{files:number, bytes:number, hash:string}} What the folder holds.
 */
export function describeFolder( dir ) {
	const lines = [];
	let bytes = 0;

	for ( const path of filesOf( dir ) ) {
		const content = readFileSync( join( dir, path ) );
		bytes += content.length;
		lines.push(
			Buffer.from( `${ path }\0${ content.length }\0${ sha256( content ) }\n` )
		);
	}
	// As bytes, which is how PHP's `sort()` orders strings: JavaScript's own order differs for
	// characters outside the basic plane.
	lines.sort( Buffer.compare );

	return {
		files: lines.length,
		bytes,
		hash: sha256( Buffer.concat( lines ) ),
	};
}
