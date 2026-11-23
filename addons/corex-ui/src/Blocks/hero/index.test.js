/**
 * Jest — Corex Hero inline editing (spec 035): registers, save() null, edit() renders the
 * eyebrow/title/subtitle/CTA RichText regions, and a media select stores { id, url, alt }.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

const RichTextMock = () => null;
const URLInputButtonMock = () => null;
const ButtonMock = ( { children, onClick } ) => ( { onClick, children } );
const MediaUploadMock = () => null;

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), {
	virtual: true,
} );
jest.mock(
	'@wordpress/block-editor',
	() => ( {
		useBlockProps: ( props ) => props || {},
		RichText: RichTextMock,
		InspectorControls: ( { children } ) => children,
		MediaUpload: MediaUploadMock,
		MediaUploadCheck: ( { children } ) => children,
		URLInputButton: URLInputButtonMock,
	} ),
	{ virtual: true }
);
jest.mock(
	'@wordpress/components',
	() => ( {
		PanelBody: ( { children } ) => children,
		Button: ButtonMock,
		RangeControl: () => null,
	} ),
	{ virtual: true }
);
jest.mock( '@wordpress/i18n', () => ( { __: ( s ) => s } ), { virtual: true } );

import './index.js';

function collect( node, type, out = [] ) {
	if ( Array.isArray( node ) ) {
		node.forEach( ( n ) => collect( n, type, out ) );
		return out;
	}
	if ( ! node || typeof node !== 'object' ) {
		return out;
	}
	if ( node.type === type ) {
		out.push( node );
	}
	collect( node.props && node.props.children, type, out );
	return out;
}

/**
 * The element whose children include an element of the given type.
 * @param {Object} node A React element.
 * @param {Object} type A component.
 */
function holderOf( node, type ) {
	const children = node && node.props ? [ node.props.children ].flat() : [];
	if ( children.some( ( child ) => child && child.type === type ) ) {
		return node;
	}
	for ( const child of children ) {
		const found =
			child && typeof child === 'object' && holderOf( child, type );
		if ( found ) {
			return found;
		}
	}

	return null;
}

describe( 'hero block — inline editing', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];

	test( 'registers and saves nothing', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'renders eyebrow + title + subtitle + CTA RichText regions', () => {
		const element = config.edit( {
			attributes: { level: 2, image: {} },
			setAttributes: jest.fn(),
		} );
		expect( collect( element, RichTextMock ).length ).toBe( 4 );
	} );

	test( 'FR-017: is a section, and its button is a link typed in place', () => {
		const element = config.edit( {
			attributes: { level: 2, image: {} },
			setAttributes: jest.fn(),
		} );
		const button = collect( element, RichTextMock ).find(
			( r ) => r.props.className === 'corex-hero__cta'
		);

		expect( element.type ).toBe( 'section' );
		expect( button.props.tagName ).toBe( 'a' );
	} );

	test( 'a media select stores { id, url, alt }', () => {
		const setAttributes = jest.fn();
		const element = config.edit( {
			attributes: { image: {} },
			setAttributes,
		} );
		const media = collect( element, MediaUploadMock )[ 0 ];
		media.props.onSelect( { id: 9, url: 'https://cdn/x.jpg', alt: 'X' } );
		expect( setAttributes ).toHaveBeenCalledWith( {
			image: { id: 9, url: 'https://cdn/x.jpg', alt: 'X' },
		} );
	} );
	test( 'FR-017: the link field sits in an element marked editor-only, with no block class', () => {
		const element = config.edit( {
			attributes: { level: 2, image: {} },
			setAttributes: jest.fn(),
		} );
		const holder = holderOf( element, URLInputButtonMock );
		const classes = String( holder.props.className || '' ).split( /\s+/ );

		expect( holder.props[ 'data-corex-editor-only' ] ).toBe( true );
		expect(
			classes.filter( ( className ) =>
				className.startsWith( 'corex-hero' )
			)
		).toEqual( [] );
	} );
} );
