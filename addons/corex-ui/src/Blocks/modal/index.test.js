/**
 * Jest test for corex/modal's EDITOR registration (spec 054 US3) — guards the contract every
 * corex/* dynamic block follows: registers under its block.json name, previews the server
 * render via <ServerSideRender>, and saves nothing (server-rendered). The @wordpress/* editor
 * packages and the SCSS import are virtual-mocked so the module loads headlessly.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

const ServerSideRenderMock = () => null;

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), { virtual: true } );
jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: () => ( {} ),
	InspectorControls: ( { children } ) => children,
} ), { virtual: true } );
jest.mock( '@wordpress/components', () => ( {
	PanelBody: ( { children } ) => children,
	TextControl: () => null,
	TextareaControl: () => null,
} ), { virtual: true } );
jest.mock( '@wordpress/i18n', () => ( { __: ( s ) => s } ), { virtual: true } );
jest.mock( '@wordpress/server-side-render', () => ServerSideRenderMock, { virtual: true } );

import './index.js';

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

describe( 'corex/modal block registration', () => {
	it( 'registers under the block.json name', () => {
		expect( registerBlockType ).toHaveBeenCalledWith( metadata.name, expect.any( Object ) );
	} );

	it( 'saves nothing (server-rendered)', () => {
		const settings = registerBlockType.mock.calls[ 0 ][ 1 ];
		expect( settings.save() ).toBeNull();
	} );

	it( 'previews the server render via <ServerSideRender>', () => {
		const settings = registerBlockType.mock.calls[ 0 ][ 1 ];
		const tree = settings.edit( { attributes: { title: '', triggerLabel: '', content: '' }, setAttributes: () => {} } );
		expect( findByType( tree, ServerSideRenderMock ) ).not.toBeNull();
	} );
} );
