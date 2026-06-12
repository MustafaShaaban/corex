/**
 * Jest — Corex Accordion inline editing (spec 029): registers, save() null, edit() renders
 * a title + content RichText per panel, and "Add panel" appends an item.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

const RichTextMock = () => null;
const ButtonMock = ( { children, onClick } ) => ( { onClick, children } );

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), { virtual: true } );
jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: ( props ) => props || {},
	RichText: RichTextMock,
	InspectorControls: ( { children } ) => children,
} ), { virtual: true } );
jest.mock( '@wordpress/components', () => ( {
	PanelBody: ( { children } ) => children,
	Button: ButtonMock,
} ), { virtual: true } );
jest.mock( '@wordpress/i18n', () => ( { __: ( s ) => s } ), { virtual: true } );

import './index.js';

function collect( node, type, out = [] ) {
	if ( Array.isArray( node ) ) {
		node.forEach( ( n ) => collect( n, type, out ) );
		return out;
	}
	if ( ! node || typeof node !== 'object' ) return out;
	if ( node.type === type ) out.push( node );
	collect( node.props && node.props.children, type, out );
	return out;
}

describe( 'accordion block — inline editing', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];

	test( 'registers and saves nothing', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'renders title + content RichText per panel', () => {
		const element = config.edit( {
			attributes: { items: [ { title: 'Q1', content: 'A1' }, { title: 'Q2', content: 'A2' } ] },
			setAttributes: jest.fn(),
		} );
		expect( collect( element, RichTextMock ).length ).toBe( 4 ); // 2 panels × (title + content)
	} );

	test( 'Add panel appends an empty item', () => {
		const setAttributes = jest.fn();
		const element = config.edit( { attributes: { items: [] }, setAttributes } );
		const addBtn = collect( element, ButtonMock ).find( ( b ) => b.props.children === 'Add panel' );
		addBtn.props.onClick();
		expect( setAttributes ).toHaveBeenCalledWith( { items: [ { title: '', content: '' } ] } );
	} );
} );
