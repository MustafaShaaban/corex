/**
 * Jest test for corex/modal's EDITOR registration (spec 054 US3) — guards the contract every
 * corex/* dynamic block follows: registers under its block.json name, previews the server
 * render via <ServerSideRender>, and saves nothing (server-rendered). The @wordpress/* editor
 * packages and the SCSS import are virtual-mocked so the module loads headlessly.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import { titleHelp } from '../shared/title-help';

const ServerSideRenderMock = () => null;
const TextControlMock = () => null;

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), {
	virtual: true,
} );
jest.mock(
	'@wordpress/block-editor',
	() => ( {
		useBlockProps: () => ( {} ),
		InspectorControls: ( { children } ) => children,
	} ),
	{ virtual: true }
);
jest.mock(
	'@wordpress/components',
	() => ( {
		PanelBody: ( { children } ) => children,
		TextControl: TextControlMock,
		TextareaControl: () => null,
	} ),
	{ virtual: true }
);
jest.mock( '@wordpress/i18n', () => ( { __: ( s ) => s } ), { virtual: true } );
jest.mock( '@wordpress/server-side-render', () => ServerSideRenderMock, {
	virtual: true,
} );

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
		expect( registerBlockType ).toHaveBeenCalledWith(
			metadata.name,
			expect.any( Object )
		);
	} );

	it( 'saves nothing (server-rendered)', () => {
		const settings = registerBlockType.mock.calls[ 0 ][ 1 ];
		expect( settings.save() ).toBeNull();
	} );

	it( 'previews the server render via <ServerSideRender>', () => {
		const settings = registerBlockType.mock.calls[ 0 ][ 1 ];
		const tree = settings.edit( {
			attributes: { title: '', triggerLabel: '', content: '' },
			setAttributes: () => {},
		} );
		expect( findByType( tree, ServerSideRenderMock ) ).not.toBeNull();
	} );

	it( 'asks for a title, under the Title field, while the block has none', () => {
		const settings = registerBlockType.mock.calls[ 0 ][ 1 ];
		const tree = settings.edit( {
			attributes: { title: '', triggerLabel: '', content: 'Hello' },
			setAttributes: () => {},
		} );

		// The Title field is the first of the block's text fields.
		const titleField = findByType( tree, TextControlMock );

		expect( titleField.props.label ).toBe( 'Title' );
		expect( titleField.props.help ).toBe(
			titleHelp( { title: '', triggerLabel: '' } )
		);
	} );
} );
