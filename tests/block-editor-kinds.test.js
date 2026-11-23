/**
 * The gate of the editor standard (spec 136, "The parity contract", FR-010 and FR-013, User
 * Story 2 scenario 5; DECISIONS #656, #671).
 *
 * Every `block.json` under `plugins/` and `addons/` declares its kind in `corex.editor`:
 * - `in-place`: has a `parity.json` beside it, and its editor never imports the server render;
 * - `records`: its editor previews by the server render, and it has no `parity.json`;
 * - `none`: has no editor script;
 * - `pending`: its turn has not come, and only the blocks on the waiting list below may say so.
 * The waiting list may only get shorter: a block on it that no longer declares `pending` fails
 * until it is taken off.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const {
	blockIn,
	findFiles,
	gateFailures,
} = require( '../packages/build-tools/parity' );

const ROOT = path.resolve( __dirname, '..' );

/**
 * The blocks whose turn has not come, and who takes each off the list. When spec 136 is done
 * the list holds the 13 of spec 150 (SC-003).
 */
const WAITING = {
	// Spec 136 slices 5 to 8: blocks that hold blocks.
	'corex/accordion': 'spec 136 slice 5',
	'corex/tabs': 'spec 136 slice 6a',
	'corex/carousel': 'spec 136 slice 7',
	'corex/modal': 'spec 136 slice 8',
	'corex/drawer': 'spec 136 slice 8',
	// Spec 150: the other blocks' editors.
	'corex/alert': 'spec 150',
	'corex/badge': 'spec 150',
	'corex/gallery': 'spec 150',
	'corex/pricing': 'spec 150',
	'corex/testimonial': 'spec 150',
	'corex/entity-field': 'spec 150',
	'corex/project-meta': 'spec 150',
	'corex/projects': 'spec 150',
	'corex/flow': 'spec 150',
	'corex/cta-flow': 'spec 150',
	'corex/subscribe': 'spec 150',
	'corex/success-message': 'spec 150',
	'corex/survey': 'spec 150',
};

const blocks = findFiles(
	[ path.join( ROOT, 'plugins' ), path.join( ROOT, 'addons' ) ],
	'block.json'
).map( ( file ) => blockIn( path.dirname( file ) ) );

/**
 * @param {Object} block A block, as `blockIn()` returns it.
 * @return {Array<string>} What the block breaks against the gate and this waiting list.
 */
const failuresOf = ( block ) => gateFailures( block, WAITING );

describe( 'FR-010, FR-013: the gate of the editor standard', () => {
	test( 'finds the framework blocks by search', () => {
		expect( blocks.length ).toBeGreaterThan( 0 );
	} );

	test( 'every block declares a known kind and keeps its rule', () => {
		expect( blocks.flatMap( failuresOf ) ).toEqual( [] );
	} );

	test( 'every block on the waiting list still exists', () => {
		const names = new Set( blocks.map( ( block ) => block.name ) );

		expect(
			Object.keys( WAITING ).filter( ( name ) => ! names.has( name ) )
		).toEqual( [] );
	} );
} );

describe( 'FR-013: each rule of the gate fails naming the block and the rule', () => {
	const os = require( 'os' );
	let folder;

	/**
	 * A block in a scratch folder, checked by the gate's rules.
	 *
	 * @param {Object}          probed
	 * @param {string}          [probed.name]   The block's name.
	 * @param {string}          [probed.kind]   Its `corex.editor`.
	 * @param {string}          [probed.editor] The text of its `index.js`.
	 * @param {boolean}         [probed.parity] Whether it has a `parity.json`.
	 * @param {Object}          [probed.extra]  Other files of its folder, by name.
	 * @param {?(string|Array)} [probed.script] Its `editorScript`; `null` for a block with none.
	 * @return {Array<string>} What the gate says it breaks.
	 */
	function probe( {
		name = 'corex/probe',
		kind,
		editor = '',
		parity = false,
		extra = {},
		script = 'file:./index.js',
	} ) {
		fs.writeFileSync( path.join( folder, 'index.js' ), editor );
		fs.writeFileSync( path.join( folder, 'block.json' ), '{}' );
		if ( parity ) {
			fs.writeFileSync( path.join( folder, 'parity.json' ), '{}' );
		} else {
			fs.rmSync( path.join( folder, 'parity.json' ), { force: true } );
		}
		Object.entries( extra ).forEach( ( [ file, text ] ) =>
			fs.writeFileSync( path.join( folder, file ), text )
		);
		const metadata = {
			name,
			editorScript: script,
			corex: kind === undefined ? {} : { editor: kind },
		};

		return failuresOf( { name, kind, folder, metadata } );
	}

	const SSR = "import ServerSideRender from '@wordpress/server-side-render';";

	beforeEach( () => {
		folder = fs.mkdtempSync( path.join( os.tmpdir(), 'corex-kinds-' ) );
	} );

	afterEach( () => fs.rmSync( folder, { recursive: true, force: true } ) );

	test( 'a block with no kind, or an unknown one', () => {
		expect( probe( {} ) ).toEqual( [
			'corex/probe: declares no kind (corex.editor in block.json); give it one of in-place, records, none, pending',
		] );
		expect( probe( { kind: 'inline' } ) ).toEqual( [
			'corex/probe: declares the unknown kind "inline"; the kinds are in-place, records, none, pending',
		] );
	} );

	test( 'an in-place block with no parity.json, or whose editor imports the server render', () => {
		expect( probe( { kind: 'in-place', parity: true } ) ).toEqual( [] );
		expect( probe( { kind: 'in-place', editor: '' } ) ).toEqual( [
			'corex/probe: is in-place and has no parity.json beside its block.json',
		] );
		expect(
			probe( {
				kind: 'in-place',
				parity: true,
				editor: "import Edit from './edit';",
				extra: { 'edit.js': SSR },
			} )
		).toEqual( [
			'corex/probe: is in-place and its editor imports @wordpress/server-side-render',
		] );
	} );

	test( 'a records block whose editor does not preview by the server render', () => {
		expect( probe( { kind: 'records', editor: SSR } ) ).toEqual( [] );
		expect( probe( { kind: 'records', editor: '' } ) ).toEqual( [
			'corex/probe: is records and its editor does not preview by @wordpress/server-side-render',
		] );
	} );

	test( 'a records block that keeps a parity.json, which its editor cannot draw', () => {
		expect(
			probe( { kind: 'records', editor: SSR, parity: true } )
		).toEqual( [
			'corex/probe: is records and has a parity.json; only an in-place block has one',
		] );
	} );

	test( 'a none block with an editor script', () => {
		expect( probe( { kind: 'none', script: null } ) ).toEqual( [] );
		expect( probe( { kind: 'none' } ) ).toEqual( [
			'corex/probe: is none and has an editor script',
		] );
		expect(
			probe( {
				kind: 'none',
				script: [ 'wp-blocks', 'file:./index.js' ],
			} )
		).toEqual( [ 'corex/probe: is none and has an editor script' ] );
	} );

	test( 'pending outside the waiting list, and a block on the list that no longer waits', () => {
		expect( probe( { kind: 'pending' } ) ).toEqual( [
			'corex/probe: declares pending and is not on the waiting list; only those blocks may wait',
		] );
		expect( probe( { name: 'corex/alert', kind: 'pending' } ) ).toEqual(
			[]
		);
		expect(
			probe( { name: 'corex/alert', kind: 'none', script: null } )
		).toEqual( [
			'corex/alert: no longer declares pending; take it off the waiting list (spec 150 took it)',
		] );
	} );
} );
