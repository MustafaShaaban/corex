/**
 * A block `make:block` generates meets the editor standard untouched (spec 136, User Story 3,
 * SC-004; DECISIONS #656).
 *
 * The scaffolder runs in PHP (`tests/Support/scaffold-block.php`), once per shape, into a scratch
 * folder inside the repository's git-ignored `.cache/`: inside, so the generated `index.js`
 * resolves React from the repository's `node_modules`. Without editing a generated file:
 * - the in-place block passes the gate's rules, and its `edit`, drawn with the shared stand-ins,
 *   draws the tree of its `parity.json`;
 * - the records block passes the gate's rules.
 * The PHP side (the generated renderer prints the fixture's tree) is proved in
 * `tests/Unit/Cli/BlockScaffolderTest.php`.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { spawnSync } = require( 'child_process' );
const {
	blockIn,
	editorParityProblem,
	gateFailures,
	installStandIns,
} = require( '../packages/build-tools/parity' );

installStandIns( jest );

const ROOT = path.resolve( __dirname, '..' );
const PROBE = path.join( __dirname, 'Support', 'scaffold-block.php' );

let scratch;

/**
 * @param {string} name  The block's name, as `make:block` takes it.
 * @param {string} shape `in-place` or `records`.
 * @return {string} The generated block's folder.
 */
function scaffold( name, shape ) {
	const run = spawnSync( 'php', [ PROBE, scratch, name, shape ], {
		encoding: 'utf8',
	} );
	if ( run.status !== 0 ) {
		throw new Error(
			`the scaffolder failed: ${ run.stderr }${ run.stdout }`
		);
	}
	const result = JSON.parse( run.stdout );
	if ( result.status !== 'created' ) {
		throw new Error(
			`the scaffolder did not create the block: ${ run.stdout }`
		);
	}

	return result.blockDir;
}

beforeAll( () => {
	fs.mkdirSync( path.join( ROOT, '.cache' ), { recursive: true } );
	scratch = fs.mkdtempSync( path.join( ROOT, '.cache', 'make-block-' ) );
} );

afterAll( () => fs.rmSync( scratch, { recursive: true, force: true } ) );

describe( 'SC-004: a generated block passes the parity test and the gate untouched', () => {
	test( 'the in-place block passes the gate and its editor draws its parity.json', () => {
		const folder = scaffold( 'TeamCard', 'in-place' );
		const block = blockIn( folder );

		expect( block.kind ).toBe( 'in-place' );
		expect( gateFailures( block ) ).toEqual( [] );
		expect( editorParityProblem( folder ) ).toBe( '' );
	} );

	test( 'the records block passes the gate', () => {
		const block = blockIn( scaffold( 'LatestNews', 'records' ) );

		expect( block.kind ).toBe( 'records' );
		expect( gateFailures( block ) ).toEqual( [] );
	} );
} );
