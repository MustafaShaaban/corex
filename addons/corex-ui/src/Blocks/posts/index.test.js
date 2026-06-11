/**
 * Jest test for a representative Corex block's EDITOR registration (the posts block).
 * It guards the contract every corex/* dynamic block follows (spec 018): the module
 * registers the type under its block.json name, previews the server render via
 * <ServerSideRender>, and saves nothing (server-rendered, so save returns null). This
 * is what stops the editor's "block not supported" error.
 *
 * The @wordpress/* editor packages and the SCSS side-effect import are mocked so the
 * module loads headlessly; we assert against the registered config without a browser.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

// Sentinel component so we can find <ServerSideRender> in the edit() element tree.
const ServerSideRenderMock = () => null;

// The @wordpress/* packages are webpack externals at build time (mapped to window.wp.*),
// not installed in node_modules — so the mocks are virtual.
jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), { virtual: true } );
jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: () => ( {} ),
	InspectorControls: ( { children } ) => children,
} ), { virtual: true } );
jest.mock( '@wordpress/components', () => ( {
	PanelBody: ( { children } ) => children,
	RangeControl: () => null,
} ), { virtual: true } );
jest.mock( '@wordpress/i18n', () => ( { __: ( s ) => s } ), { virtual: true } );
jest.mock( '@wordpress/server-side-render', () => ServerSideRenderMock, { virtual: true } );

// Importing the module runs registerBlockType at load time (the registration side effect).
import './index.js';

/**
 * Depth-first search for an element whose `type` matches in a React element tree.
 */
function findByType( node, type ) {
	if ( ! node || typeof node !== 'object' ) {
		return null;
	}
	if ( node.type === type ) {
		return node;
	}
	const children = node.props && node.props.children;
	const list = Array.isArray( children ) ? children : [ children ];
	for ( const child of list ) {
		const found = findByType( child, type );
		if ( found ) {
			return found;
		}
	}
	return null;
}

describe( 'posts block editor registration', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];

	test( 'registers the type under its block.json name', () => {
		expect( registerBlockType ).toHaveBeenCalledTimes( 1 );
		expect( name ).toBe( metadata.name );
	} );

	test( 'saves nothing (server-rendered block)', () => {
		expect( config.save() ).toBeNull();
	} );

	test( 'edit() previews the matching server render via ServerSideRender', () => {
		const element = config.edit( { attributes: { count: 3 }, setAttributes: () => {} } );
		const ssr = findByType( element, ServerSideRenderMock );

		expect( ssr ).not.toBeNull();
		expect( ssr.props.block ).toBe( metadata.name );
	} );
} );
