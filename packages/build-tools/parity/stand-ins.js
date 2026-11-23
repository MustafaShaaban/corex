/**
 * One shared set of stand-ins for the `@wordpress/*` packages a block's editor imports (spec 136,
 * "The parity contract"; DECISIONS #671).
 *
 * The real packages are webpack externals that are not installed, and the real components do not
 * render outside the editor. A stand-in draws what the real component draws that matters to the
 * parity tree, and nothing else:
 *
 * - `RichText` draws its `tagName` with its `className` and its value;
 * - `useBlockProps` returns the props it was given (its `className`);
 * - `InnerBlocks` and `useInnerBlocksProps` draw the element that holds the child blocks, empty:
 *   what a child block draws is compared through its own fixture;
 * - `InspectorControls`, `BlockControls`, `MediaUpload`, `URLInputButton` and the like draw
 *   nothing, because they draw outside the block or only when asked;
 * - `Button` draws a button with its class, `aria-*` and `data-*` attributes;
 * - `useSelect` reads stores that hold no records yet, as the editor's do before they have
 *   loaded: every selector answers `undefined`. `@wordpress/core-data`'s `store` is its
 *   descriptor, named `core`.
 *
 * `registerBlockType` records the settings, so a test takes `edit` with `registeredBlock()`.
 *
 * Install them in a test file before the block's `index.js` is required:
 *
 *     const { installStandIns, registeredBlock } = require( '<build-tools>/parity' );
 *     installStandIns( jest );
 *     require( './index.js' );
 *     const { edit } = registeredBlock( 'corex/hero' );
 */

const registered = new Map();

const nothing = () => null;
const childrenOnly = ( { children } ) => children ?? null;

/**
 * Props a DOM element may carry: a class, an id, ARIA and data attributes.
 *
 * @param {Object} props A component's props.
 * @return {Object} The ones a DOM element may carry.
 */
function domProps( props ) {
	return Object.fromEntries(
		Object.entries( props ).filter(
			( [ key ] ) =>
				key === 'className' ||
				key === 'id' ||
				key.startsWith( 'aria-' ) ||
				key.startsWith( 'data-' )
		)
	);
}

/**
 * A module whose unknown capitalised exports draw nothing, so a component a stand-in does not
 * name yet hides itself instead of breaking the render. An unknown hook stays undefined and fails
 * loudly.
 *
 * @param {Object} known The exports the stand-in names.
 * @return {Object} The module.
 */
function moduleOf( known ) {
	return new Proxy(
		{ __esModule: true, ...known },
		{
			get( target, key ) {
				if ( key in target ) {
					return target[ key ];
				}

				return typeof key === 'string' && /^[A-Z]/.test( key )
					? nothing
					: undefined;
			},
		}
	);
}

function blocks() {
	return moduleOf( {
		registerBlockType( nameOrMetadata, settings = {} ) {
			const name =
				typeof nameOrMetadata === 'string'
					? nameOrMetadata
					: nameOrMetadata.name;
			registered.set( name, settings );

			return { name, ...settings };
		},
		registerBlockStyle: nothing,
		registerBlockVariation: nothing,
		createBlock: ( name, attributes = {}, innerBlocks = [] ) => ( {
			name,
			attributes,
			innerBlocks,
		} ),
	} );
}

function blockEditor() {
	// React comes from the root's devDependencies, or from the client package that runs the test.
	// eslint-disable-next-line import/no-extraneous-dependencies
	const { createElement } = require( 'react' );

	const RichText = ( { tagName = 'div', className, value } ) =>
		createElement( tagName, {
			className,
			dangerouslySetInnerHTML: { __html: value ?? '' },
		} );
	RichText.Content = RichText;

	const InnerBlocks = () =>
		createElement( 'div', { className: 'block-editor-inner-blocks' } );
	InnerBlocks.Content = nothing;
	InnerBlocks.ButtonBlockAppender = nothing;
	InnerBlocks.DefaultBlockAppender = nothing;

	const useBlockProps = ( props = {} ) => ( { ...props } );
	useBlockProps.save = useBlockProps;

	const useInnerBlocksProps = ( props = {} ) => ( { ...props } );
	useInnerBlocksProps.save = useInnerBlocksProps;

	return moduleOf( {
		RichText,
		InnerBlocks,
		useBlockProps,
		useInnerBlocksProps,
		InspectorControls: nothing,
		InspectorAdvancedControls: nothing,
		BlockControls: nothing,
		MediaUpload: nothing,
		MediaUploadCheck: childrenOnly,
		MediaPlaceholder: nothing,
		MediaReplaceFlow: nothing,
		URLInputButton: nothing,
		URLInput: nothing,
		LinkControl: nothing,
		__experimentalLinkControl: nothing,
	} );
}

function components() {
	// React comes from the root's devDependencies, or from the client package that runs the test.
	// eslint-disable-next-line import/no-extraneous-dependencies
	const { createElement } = require( 'react' );

	const Button = ( { children, ...props } ) =>
		createElement(
			'button',
			{ type: 'button', ...domProps( props ) },
			children
		);

	return moduleOf( { Button, Disabled: childrenOnly } );
}

function data() {
	// A store whose every selector answers nothing, as a store that has not loaded its records.
	const emptyStore = new Proxy( {}, { get: () => () => undefined } );

	return moduleOf( {
		useSelect: ( mapSelect ) =>
			typeof mapSelect === 'function'
				? mapSelect( () => emptyStore )
				: emptyStore,
		useDispatch: () => new Proxy( {}, { get: () => () => undefined } ),
	} );
}

function coreData() {
	return moduleOf( { store: { name: 'core' } } );
}

function sprintf( format, ...values ) {
	let next = 0;

	return String( format ).replace(
		/%(?:(\d+)\$)?[sd]/g,
		( _match, position ) =>
			String( values[ position ? Number( position ) - 1 : next++ ] )
	);
}

function i18n() {
	return {
		__: ( text ) => text,
		_x: ( text ) => text,
		_n: ( single, plural, count ) => ( count === 1 ? single : plural ),
		_nx: ( single, plural, count ) => ( count === 1 ? single : plural ),
		sprintf,
		isRTL: () => false,
	};
}

/** The stand-ins, by package name. */
const standIns = {
	'@wordpress/blocks': blocks,
	'@wordpress/block-editor': blockEditor,
	'@wordpress/components': components,
	'@wordpress/core-data': coreData,
	'@wordpress/data': data,
	'@wordpress/i18n': i18n,
};

/**
 * Puts every stand-in in place of its package for the test file that calls it.
 *
 * @param {Object} jestObject The test file's `jest`.
 */
function installStandIns( jestObject ) {
	Object.entries( standIns ).forEach( ( [ name, factory ] ) =>
		jestObject.doMock( name, factory, { virtual: true } )
	);
}

/**
 * @param {string} name A block name.
 * @return {Object|undefined} The settings the block registered, if it did.
 */
function registeredBlock( name ) {
	return registered.get( name );
}

module.exports = { standIns, installStandIns, registeredBlock };
