/**
 * The rules of the editor standard's gate (spec 136, "The parity contract", FR-013; DECISIONS
 * #656, #671), for one block folder. The framework's gate is `tests/block-editor-kinds.test.js`,
 * which holds the waiting list; a client package runs the same rules on its own blocks.
 *
 * - `in-place`: has a `parity.json` beside its `block.json`, and its editor never imports the
 *   server render;
 * - `records`: its editor previews by the server render;
 * - `none`: has no editor;
 * - `pending`: only a block on the waiting list may say so, and a block on the list must.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const KINDS = [ 'in-place', 'records', 'none', 'pending' ];
const SERVER_RENDER = '@wordpress/server-side-render';
const LOCAL_IMPORT =
	/(?:from\s+|import\s*\(\s*|require\s*\(\s*|import\s+)['"](\.{1,2}\/[^'"]+)['"]/g;

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

function isScript( file ) {
	return (
		fs.existsSync( file ) &&
		fs.statSync( file ).isFile() &&
		file.endsWith( '.js' )
	);
}

/**
 * The editor's source: the `editorScript` file and every local module it imports, followed.
 *
 * @param {Object} block A block, as `blockIn()` returns it.
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
		if ( seen.has( file ) || ! isScript( file ) ) {
			continue;
		}
		seen.add( file );

		const text = fs.readFileSync( file, 'utf8' );
		texts.push( text );
		for ( const [ , specifier ] of text.matchAll( LOCAL_IMPORT ) ) {
			const target = path.resolve( path.dirname( file ), specifier );
			scripts.push(
				...[
					target,
					`${ target }.js`,
					path.join( target, 'index.js' ),
				].filter( isScript )
			);
		}
	}

	return texts.join( '\n' );
}

const importsServerRender = ( block ) =>
	new RegExp( `['"]${ SERVER_RENDER }['"]` ).test( editorSourceOf( block ) );

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
	if ( kind === 'records' && ! importsServerRender( block ) ) {
		failures.push(
			`${ name }: is records and its editor does not preview by ${ SERVER_RENDER }`
		);
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
	editorSourceOf,
	gateFailures,
};
