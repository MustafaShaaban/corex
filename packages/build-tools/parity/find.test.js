/**
 * The search that finds every fixture and every block (spec 136, FR-012).
 */
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { findFiles } = require( './find' );

describe( 'FR-012: finding fixtures by search', () => {
	let root;

	beforeEach( () => {
		root = fs.mkdtempSync( path.join( os.tmpdir(), 'corex-parity-' ) );
		const files = [
			'a/blocks/hero/parity.json',
			'a/blocks/hero/block.json',
			'a/node_modules/x/parity.json',
			'a/build/blocks/hero/parity.json',
			'a/vendor/y/parity.json',
			'a/.cache/parity.json',
			'b/src/Blocks/cta/parity.json',
		];
		files.forEach( ( file ) => {
			fs.mkdirSync( path.join( root, path.dirname( file ) ), {
				recursive: true,
			} );
			fs.writeFileSync( path.join( root, file ), '{}' );
		} );
	} );

	afterEach( () => fs.rmSync( root, { recursive: true, force: true } ) );

	test( 'finds the file in every folder and skips dependencies and build output', () => {
		const found = findFiles(
			[
				path.join( root, 'a' ),
				path.join( root, 'b' ),
				path.join( root, 'none' ),
			],
			'parity.json'
		).map( ( file ) =>
			path.relative( root, file ).split( path.sep ).join( '/' )
		);

		expect( found ).toEqual( [
			'a/blocks/hero/parity.json',
			'b/src/Blocks/cta/parity.json',
		] );
	} );
} );
