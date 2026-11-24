/**
 * The parity tree (spec 136, "The parity contract"; DECISIONS #671).
 *
 * Turns markup into the tree the contract compares: every element that carries a class of the
 * block, with its tag, those classes sorted, and its nesting. A class of the block is the prefix
 * itself or the prefix followed by `__` or `--`. An element with no such class is transparent:
 * its children take its place. An element marked `data-corex-editor-only` is skipped with
 * everything inside it, and may not carry a class of the block. An element that carries a class
 * named in `pageOnly` is removed with everything inside it. An element marked
 * `data-corex-parity-child` is what a child block drew, and is skipped with everything inside it:
 * where a block holds blocks, the element that holds them is compared, and what the child blocks
 * draw is compared through their own fixtures. The page's side of the test puts the mark around
 * each child block; the editor's stand-ins draw no child block at all. A marked child whose parent
 * element carries no class of the block is reported: a child printed outside its holder.
 *
 * The PHP twin is `tests/Support/ParityTree.php`; the two must compute the same tree.
 *
 * CommonJS on purpose: a client package requires it from `node_modules`, where Jest does not
 * transform files.
 */

const EDITOR_ONLY = 'data-corex-editor-only';
const CHILD_BLOCK = 'data-corex-parity-child';

function isBlockClass( name, prefix ) {
	return (
		name === prefix ||
		name.startsWith( `${ prefix }__` ) ||
		name.startsWith( `${ prefix }--` )
	);
}

function blockClassesOf( element, prefix ) {
	return Array.from( element.classList )
		.filter( ( name ) => isBlockClass( name, prefix ) )
		.sort();
}

function bodyOf( markup ) {
	const Parser = globalThis.DOMParser;
	if ( typeof Parser !== 'function' ) {
		throw new Error(
			'parityTree needs a DOM (DOMParser): run it under the jsdom test environment.'
		);
	}

	return new Parser().parseFromString( String( markup ), 'text/html' ).body;
}

function editorOnlyErrors( element, prefix ) {
	return [ element, ...element.querySelectorAll( '*' ) ].flatMap( ( node ) =>
		blockClassesOf( node, prefix ).map(
			( name ) =>
				`<${ node.tagName.toLowerCase() }> is editor-only (${ EDITOR_ONLY }) and carries the block class ${ name }`
		)
	);
}

function nodesOf( parent, context ) {
	return Array.from( parent.children ).flatMap( ( element ) =>
		nodeFor( element, context )
	);
}

function nodeFor( element, context ) {
	const { prefix, pageOnly, errors } = context;

	if ( element.hasAttribute( EDITOR_ONLY ) ) {
		errors.push( ...editorOnlyErrors( element, prefix ) );
		return [];
	}

	if ( element.hasAttribute( CHILD_BLOCK ) ) {
		// The element that holds a child block is the block's own: one drawn outside it fails.
		const parent = element.parentElement;
		if ( ! parent || blockClassesOf( parent, prefix ).length === 0 ) {
			errors.push(
				`a child block (${ CHILD_BLOCK }) is drawn in a <${ (
					parent?.tagName ?? 'document'
				).toLowerCase() }> that carries no class of the block ${ prefix }`
			);
		}
		return [];
	}

	if (
		Array.from( element.classList ).some( ( name ) =>
			pageOnly.includes( name )
		)
	) {
		return [];
	}

	const children = nodesOf( element, context );
	const classes = blockClassesOf( element, prefix );
	if ( classes.length === 0 ) {
		return children;
	}

	const node = { tag: element.tagName.toLowerCase(), classes };
	if ( children.length > 0 ) {
		node.children = children;
	}

	return [ node ];
}

/**
 * @param {string} markup                The markup to read.
 * @param {Object} options
 * @param {string} options.prefix        The block's class prefix (`corex-hero`).
 * @param {Array}  [options.pageOnly=[]] Classes of elements only the page draws.
 * @return {{tree: Array, errors: Array}} The tree, and the editor-only elements that carry a
 *                                         class of the block.
 */
function parityTree( markup, { prefix, pageOnly = [] } ) {
	if ( typeof prefix !== 'string' || prefix === '' ) {
		throw new Error( 'parityTree needs the block class prefix.' );
	}

	const errors = [];
	const tree = nodesOf( bodyOf( markup ), { prefix, pageOnly, errors } );

	return { tree, errors };
}

/**
 * A fixture's tree in the shape `parityTree` returns: keys in order, tags in lower case, classes
 * sorted, no empty `children`.
 *
 * @param {Array} tree The tree as written in a `parity.json`.
 * @return {Array} The same tree, normalized.
 */
function normalizeTree( tree ) {
	return ( Array.isArray( tree ) ? tree : [] ).map( ( node ) => {
		const normal = {
			tag: String( node.tag || '' ).toLowerCase(),
			classes: [ ...( node.classes || [] ) ].sort(),
		};
		const children = normalizeTree( node.children );
		if ( children.length > 0 ) {
			normal.children = children;
		}

		return normal;
	} );
}

/**
 * A tree as an outline a person reads in a failure: one element per line, indented by its depth,
 * as `tag.class.class`.
 *
 * @param {Array}  tree    A parity tree.
 * @param {number} [depth] The indent of the first level.
 * @return {string} The outline; `(empty)` for an empty tree.
 */
function formatTree( tree, depth = 0 ) {
	if ( depth === 0 && tree.length === 0 ) {
		return '(empty)';
	}

	return tree
		.map( ( node ) =>
			[
				`${ '  '.repeat( depth ) }${ node.tag }${ node.classes
					.map( ( name ) => `.${ name }` )
					.join( '' ) }`,
				...( node.children
					? [ formatTree( node.children, depth + 1 ) ]
					: [] ),
			].join( '\n' )
		)
		.join( '\n' );
}

module.exports = {
	CHILD_BLOCK,
	EDITOR_ONLY,
	isBlockClass,
	parityTree,
	normalizeTree,
	formatTree,
};
