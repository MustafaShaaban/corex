/**
 * Finds a file by name under some folders (spec 136, FR-012): every `parity.json` and every
 * `block.json`, so a new block is covered without a new test. Dependencies and build output are
 * not sources and are skipped: `node_modules`, `build`, `vendor`, and folders whose name starts
 * with a dot.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const SKIPPED = new Set( [ 'node_modules', 'build', 'vendor' ] );

function walk( folder, fileName, found ) {
	let entries;
	try {
		entries = fs.readdirSync( folder, { withFileTypes: true } );
	} catch {
		return found;
	}

	for ( const entry of entries ) {
		const full = path.join( folder, entry.name );
		if ( entry.isDirectory() ) {
			if (
				! SKIPPED.has( entry.name ) &&
				! entry.name.startsWith( '.' )
			) {
				walk( full, fileName, found );
			}
		} else if ( entry.name === fileName ) {
			found.push( full );
		}
	}

	return found;
}

/**
 * @param {Array<string>} folders  The folders to search.
 * @param {string}        fileName The file name to find (`parity.json`).
 * @return {Array<string>} Every match, sorted.
 */
function findFiles( folders, fileName ) {
	return folders.flatMap( ( folder ) => walk( folder, fileName, [] ) ).sort();
}

module.exports = { findFiles, SKIPPED };
