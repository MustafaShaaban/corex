/**
 * The editor draws the page (spec 136, User Story 2, FR-012, SC-003; DECISIONS #671).
 *
 * Every `parity.json` of the framework, found by search under `plugins/` and `addons/`, is drawn
 * by rendering its block's `edit` to markup with the shared stand-ins of `@wordpress/*`, and the
 * tree of the elements that carry a class of the block is compared with the fixture's. The same
 * fixtures are drawn through WordPress by `tests/Integration/Blocks/ParityTest.php`.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const {
	editorParityProblem,
	findFiles,
	installStandIns,
} = require( '../packages/build-tools/parity' );

installStandIns( jest );

const ROOT = path.resolve( __dirname, '..' );
const fixtures = findFiles(
	[ path.join( ROOT, 'plugins' ), path.join( ROOT, 'addons' ) ],
	'parity.json'
).map( ( file ) => {
	const folder = path.dirname( file );
	const { name } = JSON.parse(
		fs.readFileSync( path.join( folder, 'block.json' ), 'utf8' )
	);

	return [ name, folder ];
} );

describe( 'FR-012: every parity fixture, drawn by the block editor', () => {
	test( 'finds the framework fixtures by search', () => {
		expect( fixtures.length ).toBeGreaterThan( 0 );
	} );

	describe.each( fixtures )( '%s', ( _name, folder ) => {
		test( 'its editor draws the tree of its parity.json', () => {
			// Empty when the trees match; otherwise the block, both trees and the markup.
			expect( editorParityProblem( folder ) ).toBe( '' );
		} );
	} );
} );
