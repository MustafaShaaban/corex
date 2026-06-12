/**
 * Jest — Corex Gallery (spec 035): registers, save() null, an empty gallery shows the media
 * placeholder whose select maps media to images[], and a populated gallery renders a caption
 * RichText per image.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

const RichTextMock = () => null;
const ButtonMock = ( { children, onClick } ) => ( { onClick, children } );
const MediaUploadMock = () => null;
const MediaPlaceholderMock = () => null;

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), { virtual: true } );
jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: ( props ) => props || {},
	RichText: RichTextMock,
	MediaUpload: MediaUploadMock,
	MediaUploadCheck: ( { children } ) => children,
	MediaPlaceholder: MediaPlaceholderMock,
} ), { virtual: true } );
jest.mock( '@wordpress/components', () => ( { Button: ButtonMock } ), { virtual: true } );
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

describe( 'gallery block', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];

	test( 'registers and saves nothing', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'empty gallery: the placeholder select maps media to images[]', () => {
		const setAttributes = jest.fn();
		const element = config.edit( { attributes: { images: [] }, setAttributes } );
		const placeholder = collect( element, MediaPlaceholderMock )[ 0 ];
		placeholder.props.onSelect( [ { id: 1, url: 'https://cdn/1.jpg', alt: 'One', caption: 'c' } ] );
		expect( setAttributes ).toHaveBeenCalledWith( {
			images: [ { id: 1, url: 'https://cdn/1.jpg', alt: 'One', caption: 'c' } ],
		} );
	} );

	test( 'populated gallery renders a caption RichText per image', () => {
		const element = config.edit( {
			attributes: { images: [ { id: 1, url: 'u1' }, { id: 2, url: 'u2' } ] },
			setAttributes: jest.fn(),
		} );
		expect( collect( element, RichTextMock ).length ).toBe( 2 );
	} );
} );
