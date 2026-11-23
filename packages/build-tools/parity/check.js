/**
 * Draws one block's `edit` with the shared stand-ins and compares the tree with its
 * `parity.json` (spec 136, "The parity contract", FR-012; DECISIONS #671). Call
 * `installStandIns( jest )` in the test file first.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const {
	formatTree,
	isBlockClass,
	normalizeTree,
	parityTree,
} = require( './tree' );
const { renderEdit } = require( './render' );
const { registeredBlock } = require( './stand-ins' );
const { editorScriptsOf } = require( './gate' );

/**
 * What a fixture may say the page needs to draw the block (`needs`): a current post, or a
 * plug-in, named `plugin:<folder>/<file>.php`, that must be active for the page to draw the
 * tree. The editor needs neither: it draws with what it is given.
 */
const KNOWN_NEEDS = [ 'post' ];
const PLUGIN_NEED = /^plugin:[^/\s]+\/[^/\s]+\.php$/;

/**
 * @param {string} need One entry of a fixture's `needs`.
 * @return {boolean} Whether a test can give it.
 */
function isKnownNeed( need ) {
	return KNOWN_NEEDS.includes( need ) || PLUGIN_NEED.test( String( need ) );
}

const json = ( tree ) => JSON.stringify( tree, null, 2 );

/**
 * What makes a fixture unable to fail: no tree, a first root that does not carry the prefix
 * (a wrong prefix gives an empty tree on both sides), or a `pageOnly` class that is a class of
 * the block, which could hide a whole subtree from the page's side.
 *
 * @param {string} name    The block's name, for the messages.
 * @param {Object} fixture The block's `parity.json`.
 * @return {Array<string>} The problems, each naming the block.
 */
function fixtureProblems( name, fixture ) {
	const prefix = String( fixture.prefix || '' );
	const tree = normalizeTree( fixture.tree );
	const problems = [];

	if ( tree.length === 0 ) {
		problems.push(
			`${ name }: parity.json has no tree, so the comparison proves nothing`
		);
	} else if ( ! tree[ 0 ].classes.includes( prefix ) ) {
		problems.push(
			`${ name }: the first root of parity.json does not carry the prefix class ${ prefix }`
		);
	}

	const hiding = ( fixture.pageOnly || [] ).filter(
		( entry ) => prefix !== '' && isBlockClass( entry, prefix )
	);
	if ( hiding.length > 0 ) {
		problems.push(
			`${ name }: pageOnly may not name a class of the block: ${ hiding.join( ', ' ) }`
		);
	}

	return problems;
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

	const problems = fixtureProblems( name, fixture );
	if ( problems.length > 0 ) {
		return problems.join( '\n' );
	}

	const unknownNeeds = needs.filter( ( need ) => ! isKnownNeed( need ) );
	if ( unknownNeeds.length > 0 ) {
		return `${ name }: parity.json needs what no test can give: ${ unknownNeeds.join(
			', '
		) }`;
	}

	const scripts = editorScriptsOf( folder, metadata );
	if ( scripts.length === 0 ) {
		return `${ name }: block.json names no editorScript file`;
	}
	scripts.forEach( ( script ) => require( script ) );
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

module.exports = {
	KNOWN_NEEDS,
	editorParityProblem,
	fixtureProblems,
	isKnownNeed,
};
