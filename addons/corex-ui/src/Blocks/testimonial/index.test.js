/**
 * Jest — Corex Testimonial inline editing (spec 029): registers, save() null, and edit()
 * renders a RichText per text field (quote/author/role) bound to its attribute.
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
	if ( ! node || typeof node !== 'object' ) return out;
	if ( node.type === type ) out.push( node );
	const ch = node.props && node.props.children;
	( Array.isArray( ch ) ? ch : [ ch ] ).forEach( ( c ) => collect( c, type, out ) );
	return out;
}

describe( 'testimonial block — inline editing', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];

	test( 'registers and saves nothing', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'edit() renders RichText for quote/author/role', () => {
		const element = config.edit( {
			attributes: { quote: 'Great', author: 'Sam', role: 'CTO' },
			setAttributes: jest.fn(),
		} );
		expect( collect( element, RichTextMock ).length ).toBe( 3 );
	} );
} );
