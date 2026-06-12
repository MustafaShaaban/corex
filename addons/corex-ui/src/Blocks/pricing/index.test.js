/**
 * Jest — Corex Pricing inline editing (spec 029): registers, save() null, edit() renders
 * RichText for plan/price/period/CTA + one RichText per feature row, and "Add feature"
 * appends to the features array.
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
	TextControl: () => null,
	Button: ButtonMock,
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

describe( 'pricing block — inline editing', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];

	test( 'registers and saves nothing', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'renders a RichText per feature and inline fields', () => {
		const element = config.edit( {
			attributes: { plan: 'Pro', price: '$29', period: '/mo', features: [ 'A', 'B' ], ctaText: 'Buy', ctaUrl: '' },
			setAttributes: jest.fn(),
		} );
		// plan, price, period, ctaText (4) + one per feature (2) = 6
		expect( collect( element, RichTextMock ).length ).toBe( 6 );
	} );

	test( 'Add feature appends an empty feature', () => {
		const setAttributes = jest.fn();
		const element = config.edit( { attributes: { features: [ 'A' ] }, setAttributes } );
		const addBtn = collect( element, ButtonMock ).find( ( b ) => b.props.children === 'Add feature' );
		addBtn.props.onClick();
		expect( setAttributes ).toHaveBeenCalledWith( { features: [ 'A', '' ] } );
	} );
} );
