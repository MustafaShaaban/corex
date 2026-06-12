/**
 * Jest test for the Corex Stat block's INLINE editing (spec 029). Asserts the block
 * registers, saves nothing (dynamic), and that edit() renders a RichText region per text
 * field bound to the matching attribute — i.e. it is edited on the canvas, not the sidebar.
 *
 * The @wordpress/* packages are webpack externals (not in node_modules), so they are mocked
 * virtually; RichText is a sentinel we find in the element tree.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

const RichTextMock = () => null;

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), { virtual: true } );
jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: ( props ) => props || {},
	RichText: RichTextMock,
	InspectorControls: ( { children } ) => children,
} ), { virtual: true } );
jest.mock( '@wordpress/i18n', () => ( { __: ( s ) => s } ), { virtual: true } );

import './index.js';

function collect( node, type, out = [] ) {
	if ( ! node || typeof node !== 'object' ) {
		return out;
	}
	if ( node.type === type ) {
		out.push( node );
	}
	const children = node.props && node.props.children;
	const list = Array.isArray( children ) ? children : [ children ];
	list.forEach( ( c ) => collect( c, type, out ) );
	return out;
}

describe( 'stat block — inline editing', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];

	test( 'registers and saves nothing (dynamic)', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'edit() renders a RichText per text field, bound to its attribute', () => {
		const setAttributes = jest.fn();
		const element = config.edit( {
			attributes: { value: '98%', label: 'Uptime', description: '' },
			setAttributes,
		} );
		const richTexts = collect( element, RichTextMock );

		expect( richTexts.length ).toBe( 3 ); // value, label, description
		expect( richTexts[ 0 ].props.value ).toBe( '98%' );

		// typing into a field updates its attribute
		richTexts[ 0 ].props.onChange( '99%' );
		expect( setAttributes ).toHaveBeenCalledWith( { value: '99%' } );
	} );
} );
