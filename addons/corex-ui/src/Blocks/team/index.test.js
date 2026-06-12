/**
 * Jest — Corex Team inline editing (spec 035): registers, save() null, "Add member" appends an
 * empty member, and a member media select stores { id, url, alt }.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

const RichTextMock = () => null;
const ButtonMock = ( { children, onClick } ) => ( { onClick, children } );
const MediaUploadMock = () => null;

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), { virtual: true } );
jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: ( props ) => props || {},
	RichText: RichTextMock,
	InspectorControls: ( { children } ) => children,
	MediaUpload: MediaUploadMock,
	MediaUploadCheck: ( { children } ) => children,
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

describe( 'team block — inline editing', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];

	test( 'registers and saves nothing', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'Add member appends an empty member', () => {
		const setAttributes = jest.fn();
		const element = config.edit( { attributes: { members: [] }, setAttributes } );
		const addBtn = collect( element, ButtonMock ).find( ( b ) => b.props.children === 'Add member' );
		addBtn.props.onClick();
		expect( setAttributes ).toHaveBeenCalledWith( {
			members: [ { name: '', role: '', image: {}, bio: '' } ],
		} );
	} );

	test( 'a member media select stores { id, url, alt }', () => {
		const setAttributes = jest.fn();
		const element = config.edit( {
			attributes: { members: [ { name: 'Sam', role: '', image: {}, bio: '' } ] },
			setAttributes,
		} );
		const media = collect( element, MediaUploadMock )[ 0 ];
		media.props.onSelect( { id: 3, url: 'https://cdn/s.jpg', alt: 'Sam' } );
		expect( setAttributes ).toHaveBeenCalledWith( {
			members: [ { name: 'Sam', role: '', image: { id: 3, url: 'https://cdn/s.jpg', alt: 'Sam' }, bio: '' } ],
		} );
	} );
} );
