/**
 * The rules of the editor standard's gate (spec 136, "The parity contract", FR-013; DECISIONS
 * #656, #671), for one block folder. The framework's gate is `tests/block-editor-kinds.test.js`,
 * which holds the waiting list; a client package runs the same rules on its own blocks.
 *
 * - `in-place`: has a `parity.json` beside its `block.json`, and its editor never imports the
 *   server render;
 * - `records`: its editor previews by the server render, and it has no `parity.json`;
 * - `none`: has no editor script;
 * - `pending`: only a block on the waiting list may say so, and a block on the list must.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const KINDS = [ 'in-place', 'records', 'none', 'pending' ];
const SERVER_RENDER = '@wordpress/server-side-render';

/** The extensions a local import may leave out, in the order they are tried. */
const SCRIPT_EXTENSIONS = [ '.js', '.jsx', '.ts', '.tsx' ];

/** A string literal (kept), or a comment (removed). */
const STRING_OR_COMMENT =
	/("(?:\\.|[^"\\\n])*"|'(?:\\.|[^'\\\n])*'|`(?:\\.|[^`\\])*`)|\/\*[\s\S]*?\*\/|\/\/[^\n]*/g;

/** The module each import or require statement names. */
const IMPORT_STATEMENTS = [
	/\b(?:import|export)\s+(?:[\w$*{}\s,]+\s+from\s+)?['"]([^'"]+)['"]/g,
	/\bimport\s*\(\s*['"]([^'"]+)['"]\s*\)/g,
	/\brequire\s*\(\s*['"]([^'"]+)['"]\s*\)/g,
];

/**
 * @param {string} folder A block folder, holding its `block.json`.
 * @return {{name: string, folder: string, metadata: Object, kind: (string|undefined)}} The block.
 */
function blockIn( folder ) {
	const metadata = JSON.parse(
		fs.readFileSync( path.join( folder, 'block.json' ), 'utf8' )
	);

	return {
		name: metadata.name,
		folder,
		metadata,
		kind: metadata.corex ? metadata.corex.editor : undefined,
	};
}

/**
 * The one rule for which files are a block's editor: every `file:` entry of its `editorScript`,
 * which may be a string or a list. A script handle (`wp-blocks`) is not a file of the block.
 *
 * @param {string} folder   The block folder.
 * @param {Object} metadata The block's `block.json`.
 * @return {Array<string>} The editor files, absolute.
 */
function editorScriptsOf( folder, metadata ) {
	return [ metadata.editorScript ]
		.flat()
		.filter(
			( script ) =>
				typeof script === 'string' && script.startsWith( 'file:' )
		)
		.map( ( script ) => path.resolve( folder, script.slice( 5 ) ) );
}

function isFile( file ) {
	return fs.existsSync( file ) && fs.statSync( file ).isFile();
}

/**
 * The file a local specifier names: as written, with an extension, or its folder's index.
 *
 * @param {string} from      The file that imports it.
 * @param {string} specifier The local specifier (`./edit`).
 * @return {string|undefined} The script file, when there is one.
 */
function localFile( from, specifier ) {
	const target = path.resolve( path.dirname( from ), specifier );
	const candidates = [
		target,
		...SCRIPT_EXTENSIONS.map( ( extension ) => target + extension ),
		...SCRIPT_EXTENSIONS.map( ( extension ) =>
			path.join( target, `index${ extension }` )
		),
	];

	return candidates.find(
		( file ) =>
			isFile( file ) && SCRIPT_EXTENSIONS.includes( path.extname( file ) )
	);
}

/**
 * @param {string} text A script's source.
 * @return {Array<string>} The modules its import and require statements name, comments ignored.
 */
function importsIn( text ) {
	const code = text.replace(
		STRING_OR_COMMENT,
		( match, literal ) => literal ?? ''
	);

	return IMPORT_STATEMENTS.flatMap( ( pattern ) =>
		Array.from( code.matchAll( pattern ), ( [ , specifier ] ) => specifier )
	);
}

/**
 * Every module the block's editor imports: its editor files, and the local modules they import,
 * followed (a cycle is followed once).
 *
 * @param {Object} block A block, as `blockIn()` returns it.
 * @return {Array<string>} The module specifiers, local ones included.
 */
function editorImportsOf( block ) {
	const queue = editorScriptsOf( block.folder, block.metadata );
	const seen = new Set();
	const imports = [];

	while ( queue.length > 0 ) {
		const file = queue.shift();
		if ( seen.has( file ) || ! isFile( file ) ) {
			continue;
		}
		seen.add( file );

		for ( const specifier of importsIn(
			fs.readFileSync( file, 'utf8' )
		) ) {
			imports.push( specifier );
			const local = specifier.startsWith( '.' )
				? localFile( file, specifier )
				: undefined;
			if ( local ) {
				queue.push( local );
			}
		}
	}

	return imports;
}

const importsServerRender = ( block ) =>
	editorImportsOf( block ).includes( SERVER_RENDER );

/**
 * @param {Object} block     A block, as `blockIn()` returns it.
 * @param {Object} [waiting] The waiting list: block name => who takes it off.
 * @return {Array<string>} What the block breaks, each naming the block and the rule.
 */
function gateFailures( block, waiting = {} ) {
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
	if ( kind === 'records' ) {
		if ( ! importsServerRender( block ) ) {
			failures.push(
				`${ name }: is records and its editor does not preview by ${ SERVER_RENDER }`
			);
		}
		if ( fs.existsSync( path.join( folder, 'parity.json' ) ) ) {
			failures.push(
				`${ name }: is records and has a parity.json; only an in-place block has one`
			);
		}
	}
	if (
		kind === 'none' &&
		editorScriptsOf( folder, block.metadata ).length > 0
	) {
		failures.push( `${ name }: is none and has an editor script` );
	}
	if ( kind === 'pending' && ! ( name in waiting ) ) {
		failures.push(
			`${ name }: declares pending and is not on the waiting list; only those blocks may wait`
		);
	}
	if ( kind !== 'pending' && name in waiting ) {
		failures.push(
			`${ name }: no longer declares pending; take it off the waiting list (${ waiting[ name ] } took it)`
		);
	}

	return failures;
}

module.exports = {
	KINDS,
	SERVER_RENDER,
	blockIn,
	editorImportsOf,
	editorScriptsOf,
	gateFailures,
};
