/**
 * Draws one block's `edit` with the shared stand-ins and compares the tree with its
 * `parity.json` (spec 136, "The parity contract", FR-012; DECISIONS #671). Call
 * `installStandIns( jest )` in the test file first.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { formatTree, normalizeTree, parityTree } = require( './tree' );
const { renderEdit } = require( './render' );
const { registeredBlock } = require( './stand-ins' );

/** What a fixture may say the page needs to draw the block (`needs`). */
const KNOWN_NEEDS = [ 'post' ];

const json = ( tree ) => JSON.stringify( tree, null, 2 );

function editorScriptOf( folder, metadata ) {
	const script = String( metadata.editorScript || '' ).replace(
		/^file:/,
		''
	);

	return path.join( folder, script || 'index.js' );
}

/**
 * @param {string} folder A block folder, holding its `block.json` and its `parity.json`.
 * @return {string} Empty when the editor draws the fixture's tree; otherwise what differs: the
 *                  block, both trees as outlines and the editor's markup.
 */
function editorParityProblem( folder ) {
	const read = ( file ) =>
		JSON.parse( fs.readFileSync( path.join( folder, file ), 'utf8' ) );
	const metadata = read( 'block.json' );
	const fixture = read( 'parity.json' );
	const { name } = metadata;
	const needs = fixture.needs || [];

	const unknownNeeds = needs.filter(
		( need ) => ! KNOWN_NEEDS.includes( need )
	);
	if ( unknownNeeds.length > 0 ) {
		return `${ name }: parity.json needs what no test can give: ${ unknownNeeds.join(
			', '
		) }`;
	}

	require( editorScriptOf( folder, metadata ) );
	const settings = registeredBlock( name );
	if ( ! settings || typeof settings.edit !== 'function' ) {
		return `${ name }: its editor script registered no edit for the block`;
	}

	const markup = renderEdit( settings.edit, {
		metadata,
		attributes: fixture.attributes || {},
		context: needs.includes( 'post' )
			? { postId: 1, postType: 'post' }
			: {},
	} );
	const { tree, errors } = parityTree( markup, { prefix: fixture.prefix } );
	const expected = normalizeTree( fixture.tree );

	if ( errors.length === 0 && json( tree ) === json( expected ) ) {
		return '';
	}

	return [
		`${ name }: the editor does not draw the tree of its parity.json.`,
		...errors,
		'Editor:',
		formatTree( tree ),
		'parity.json:',
		formatTree( expected ),
		'Editor markup:',
		markup,
	].join( '\n' );
}

module.exports = { KNOWN_NEEDS, editorParityProblem };
