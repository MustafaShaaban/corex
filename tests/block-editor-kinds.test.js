/**
 * The gate of the editor standard (spec 136, "The parity contract", FR-010 and FR-013, User
 * Story 2 scenario 5; DECISIONS #656, #671).
 *
 * Every `block.json` under `plugins/` and `addons/` declares its kind in `corex.editor`:
 * - `in-place`: has a `parity.json` beside it, and its editor never imports the server render;
 * - `records`: its editor previews by the server render;
 * - `none`: has no editor;
 * - `pending`: its turn has not come, and only the blocks on the waiting list below may say so.
 * The waiting list may only get shorter: a block on it that no longer declares `pending` fails
 * until it is taken off.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { findFiles } = require( '../packages/build-tools/parity' );

const ROOT = path.resolve( __dirname, '..' );
const KINDS = [ 'in-place', 'records', 'none', 'pending' ];
const SERVER_RENDER = '@wordpress/server-side-render';

/**
 * The blocks whose turn has not come, and who takes each off the list. When spec 136 is done
 * the list holds the 13 of spec 150 (SC-003).
 */
const WAITING = {
	// Spec 136 slice 4: drawn in place.
	'corex/copyright': 'spec 136 slice 4',
	'corex/newsletter-signup': 'spec 136 slice 4',
	'corex/social-share': 'spec 136 slice 4',
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
).map( ( file ) => {
	const metadata = JSON.parse( fs.readFileSync( file, 'utf8' ) );

	return {
		name: metadata.name,
		folder: path.dirname( file ),
		metadata,
		kind: metadata.corex ? metadata.corex.editor : undefined,
	};
} );

/**
 * The editor's source: the `editorScript` file and every local module it imports, followed.
 *
 * @param {Object} block A block found above.
 * @return {string} Their text, joined.
 */
function editorSourceOf( block ) {
	const scripts = [ block.metadata.editorScript ]
		.flat()
		.filter(
			( script ) =>
				typeof script === 'string' && script.startsWith( 'file:' )
		)
		.map( ( script ) => path.resolve( block.folder, script.slice( 5 ) ) );
	const seen = new Set();
	const texts = [];

	while ( scripts.length > 0 ) {
		const file = scripts.shift();
		if ( seen.has( file ) || ! fs.existsSync( file ) ) {
			continue;
		}
		seen.add( file );

		const text = fs.readFileSync( file, 'utf8' );
		texts.push( text );
		for ( const [ , specifier ] of text.matchAll(
			/(?:from\s+|import\s*\(\s*|require\s*\(\s*|import\s+)['"](\.{1,2}\/[^'"]+)['"]/g
		) ) {
			const target = path.resolve( path.dirname( file ), specifier );
			scripts.push(
				...[
					target,
					`${ target }.js`,
					path.join( target, 'index.js' ),
				].filter(
					( candidate ) =>
						fs.existsSync( candidate ) &&
						fs.statSync( candidate ).isFile() &&
						candidate.endsWith( '.js' )
				)
			);
		}
	}

	return texts.join( '\n' );
}

const importsServerRender = ( block ) =>
	new RegExp( `['"]${ SERVER_RENDER }['"]` ).test( editorSourceOf( block ) );

/**
 * @param {Object} block A block found above.
 * @return {Array<string>} What the block breaks, each naming the block and the rule.
 */
function failuresOf( block ) {
	const { name, kind, folder } = block;

	if ( kind === undefined ) {
		return [
			`${ name }: declares no kind (corex.editor in block.json); give it one of ${ KINDS.join( ', ' ) }`,
		];
	}
	if ( ! KINDS.includes( kind ) ) {
		return [
			`${ name }: declares the unknown kind "${ kind }"; the kinds are ${ KINDS.join( ', ' ) }`,
		];
	}

	const failures = [];
	if ( kind === 'in-place' ) {
		if ( ! fs.existsSync( path.join( folder, 'parity.json' ) ) ) {
			failures.push(
				`${ name }: is in-place and has no parity.json beside its block.json`
			);
		}
		if ( importsServerRender( block ) ) {
			failures.push(
				`${ name }: is in-place and its editor imports ${ SERVER_RENDER }`
			);
		}
	}
	if ( kind === 'records' && ! importsServerRender( block ) ) {
		failures.push(
			`${ name }: is records and its editor does not preview by ${ SERVER_RENDER }`
		);
	}
	if ( kind === 'pending' && ! ( name in WAITING ) ) {
		failures.push(
			`${ name }: declares pending and is not on the waiting list; only those blocks may wait`
		);
	}
	if ( kind !== 'pending' && name in WAITING ) {
		failures.push(
			`${ name }: no longer declares pending; take it off the waiting list (${ WAITING[ name ] } took it)`
		);
	}

	return failures;
}

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
	 * @param {Object}  probed
	 * @param {string}  [probed.name]   The block's name.
	 * @param {string}  [probed.kind]   Its `corex.editor`.
	 * @param {string}  [probed.editor] The text of its `index.js`.
	 * @param {boolean} [probed.parity] Whether it has a `parity.json`.
	 * @param {Object}  [probed.extra]  Other files of its folder, by name.
	 * @return {Array<string>} What the gate says it breaks.
	 */
	function probe( {
		name = 'corex/probe',
		kind,
		editor = '',
		parity = false,
		extra = {},
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
			editorScript: 'file:./index.js',
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

	test( 'pending outside the waiting list, and a block on the list that no longer waits', () => {
		expect( probe( { kind: 'pending' } ) ).toEqual( [
			'corex/probe: declares pending and is not on the waiting list; only those blocks may wait',
		] );
		expect( probe( { name: 'corex/alert', kind: 'pending' } ) ).toEqual(
			[]
		);
		expect( probe( { name: 'corex/alert', kind: 'none' } ) ).toEqual( [
			'corex/alert: no longer declares pending; take it off the waiting list (spec 150 took it)',
		] );
	} );
} );
