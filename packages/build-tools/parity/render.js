/**
 * Draws a block's `edit` to markup with `react-dom/server` (spec 136, "The parity contract";
 * DECISIONS #671). Run it with the shared stand-ins installed (`./stand-ins`).
 */

/**
 * @param {Object} metadata A block's `block.json`.
 * @return {Object} Each attribute's default, for the attributes that declare one.
 */
function attributeDefaults( metadata ) {
	return Object.fromEntries(
		Object.entries( ( metadata && metadata.attributes ) || {} )
			.filter( ( [ , schema ] ) => schema && 'default' in schema )
			.map( ( [ name, schema ] ) => [ name, schema.default ] )
	);
}

/**
 * @param {import('react').ComponentType} edit                 The block's `edit` component.
 * @param {Object}                        [options]
 * @param {Object}                        [options.metadata]   The block's `block.json`, for its attribute defaults.
 * @param {Object}                        [options.attributes] The attributes to draw with, over the defaults.
 * @param {Object}                        [options.context]    The block context (`postId`, `postType`).
 * @return {string} The markup `edit` draws.
 */
function renderEdit(
	edit,
	{ metadata = {}, attributes = {}, context = {} } = {}
) {
	// React comes from the root's devDependencies, or from the client package that runs the
	// test. The Node build of the server renderer: under jsdom the browser build is picked, and
	// it needs a `TextEncoder` that jsdom does not define.
	/* eslint-disable import/no-extraneous-dependencies */
	const { createElement } = require( 'react' );
	const { renderToStaticMarkup } = require( 'react-dom/server.node' );
	/* eslint-enable import/no-extraneous-dependencies */

	return renderToStaticMarkup(
		createElement( edit, {
			name: metadata.name,
			attributes: { ...attributeDefaults( metadata ), ...attributes },
			setAttributes: () => {},
			isSelected: false,
			clientId: 'corex-parity',
			context,
			insertBlocksAfter: () => {},
			onReplace: () => {},
		} )
	);
}

module.exports = { attributeDefaults, renderEdit };
