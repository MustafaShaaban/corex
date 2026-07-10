/**
 * Jest — Corex Carousel inline editing (spec 068, US9): registers, save() null, edit() renders a
 * RichText per slide, "Add slide" appends an empty slide, and "Remove slide" drops the right one.
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
	RangeControl: () => null,
	ToggleControl: () => null,
	TextControl: () => null,
	Button: ButtonMock,
} ), { virtual: true } );
jest.mock( '@wordpress/i18n', () => ( { __: ( s ) => s } ), { virtual: true } );
jest.mock( '@wordpress/server-side-render', () => () => null, { virtual: true } );

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

describe( 'carousel block — inline editing', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];

	test( 'registers and saves nothing', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'renders a RichText per slide', () => {
		const element = config.edit( {
			attributes: { slides: [ { content: 'a' }, { content: 'b' }, { content: 'c' } ] },
			setAttributes: jest.fn(),
		} );
		expect( collect( element, RichTextMock ).length ).toBe( 3 );
	} );

	test( 'Add slide appends an empty slide', () => {
		const setAttributes = jest.fn();
		const element = config.edit( { attributes: { slides: [] }, setAttributes } );
		const addBtn = collect( element, ButtonMock ).find(
			( b ) => b.props.children === 'Add slide'
		);
		addBtn.props.onClick();
		expect( setAttributes ).toHaveBeenCalledWith( { slides: [ { content: '' } ] } );
	} );

	test( 'Remove slide drops that slide', () => {
		const setAttributes = jest.fn();
		const element = config.edit( {
			attributes: { slides: [ { content: 'a' }, { content: 'b' } ] },
			setAttributes,
		} );
		const removeBtns = collect( element, ButtonMock ).filter(
			( b ) => b.props.children === 'Remove slide'
		);
		removeBtns[ 0 ].props.onClick();
		expect( setAttributes ).toHaveBeenCalledWith( { slides: [ { content: 'b' } ] } );
	} );
} );
