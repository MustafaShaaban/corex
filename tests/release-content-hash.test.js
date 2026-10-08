/**
 * The hash of a release folder (spec 107, plan D2).
 *
 * A site checks a package against this number, so what matters is what changes it and what does
 * not. The last test writes nothing new: it holds the folder in `tests/Fixtures/Releases/hashed`
 * to a recorded description, and `ReleaseContentHashTest.php` holds the PHP side to the same
 * record. That file is how the two languages are kept to one answer.
 */
const { mkdtempSync, mkdirSync, writeFileSync, rmSync } = require( 'node:fs' );
const { tmpdir } = require( 'node:os' );
const { join } = require( 'node:path' );

const FIXTURE = join( __dirname, 'Fixtures', 'Releases' );

let describeFolder;
const made = [];

beforeAll( async () => {
	( { describeFolder } =
		await import( '../scripts/release-content-hash.mjs' ) );
} );

afterEach( () => {
	made.splice( 0 ).forEach( ( dir ) =>
		rmSync( dir, { recursive: true, force: true } )
	);
} );

/**
 * A folder holding the given files.
 *
 * @param {Object<string,string>} files Each file's path from the folder, and its content.
 * @return {string} The folder.
 */
function folderOf( files ) {
	const dir = mkdtempSync( join( tmpdir(), 'corex-hash-' ) );
	made.push( dir );
	for ( const [ path, content ] of Object.entries( files ) ) {
		mkdirSync( join( dir, path, '..' ), { recursive: true } );
		writeFileSync( join( dir, path ), content );
	}
	return dir;
}

const plugin = {
	'corex-core.php': '<?php // the plugin',
	'src/Boot.php': '<?php // boot',
	'assets/js/corex-runtime.js': 'console.log(1);',
};

it( 'counts the files and the bytes of a folder, however deep', () => {
	const described = describeFolder( folderOf( plugin ) );

	expect( described.files ).toBe( 3 );
	expect( described.bytes ).toBe( Object.values( plugin ).join( '' ).length );
	expect( described.hash ).toMatch( /^[0-9a-f]{64}$/ );
} );

it( 'gives the same hash for the same files, in whatever order they were written', () => {
	const reversed = Object.fromEntries( Object.entries( plugin ).reverse() );

	expect( describeFolder( folderOf( reversed ) ).hash ).toBe(
		describeFolder( folderOf( plugin ) ).hash
	);
} );

it.each( [
	[ 'a byte of one file', { ...plugin, 'src/Boot.php': '<?php // b00t' } ],
	[
		'a file moved, with its content as it was',
		{
			'corex-core.php': plugin[ 'corex-core.php' ],
			'src/Kernel.php': plugin[ 'src/Boot.php' ],
			'assets/js/corex-runtime.js':
				plugin[ 'assets/js/corex-runtime.js' ],
		},
	],
	[ 'a file added', { ...plugin, 'src/Extra.php': '' } ],
	[
		'two files that traded contents',
		{
			...plugin,
			'corex-core.php': plugin[ 'src/Boot.php' ],
			'src/Boot.php': plugin[ 'corex-core.php' ],
		},
	],
] )( 'gives another hash when the folder differs by %s', ( _, changed ) => {
	expect( describeFolder( folderOf( changed ) ).hash ).not.toBe(
		describeFolder( folderOf( plugin ) ).hash
	);
} );

it( 'says a folder that is not there holds nothing', () => {
	expect( describeFolder( join( FIXTURE, 'no-such-folder' ) ) ).toEqual( {
		files: 0,
		bytes: 0,
		hash: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
	} );
} );

it( 'describes the recorded folder as the record says, which the PHP side is held to as well', () => {
	const recorded = require( join( FIXTURE, 'hashed.json' ) );

	expect( describeFolder( join( FIXTURE, 'hashed' ) ) ).toEqual( recorded );
} );
