/**
 * Jest — Corex CTA inline editing (spec 035): registers, save() null, edit() renders the
 * title/text/button RichText regions.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

const RichTextMock = () => null;
const URLInputButtonMock = () => null;

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), {
	virtual: true,
} );
jest.mock(
	'@wordpress/block-editor',
	() => ( {
		useBlockProps: ( props ) => props || {},
		RichText: RichTextMock,
		URLInputButton: URLInputButtonMock,
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

describe( 'cta block — inline editing', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];

	test( 'registers and saves nothing', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'renders title + text + button RichText regions', () => {
		const element = config.edit( {
			attributes: {},
			setAttributes: jest.fn(),
		} );
		expect( collect( element, RichTextMock ).length ).toBe( 3 );
	} );

	test( 'FR-017: its button is a link typed in place', () => {
		const element = config.edit( {
			attributes: {},
			setAttributes: jest.fn(),
		} );
		const button = collect( element, RichTextMock ).find(
			( r ) => r.props.className === 'corex-cta__button'
		);

		expect( button.props.tagName ).toBe( 'a' );
	} );
	test( 'FR-017: the link field sits in an element marked editor-only, with no block class', () => {
		const element = config.edit( {
			attributes: {},
			setAttributes: jest.fn(),
		} );
		const holder = holderOf( element, URLInputButtonMock );
		const classes = String( holder.props.className || '' ).split( /\s+/ );

		expect( holder.props[ 'data-corex-editor-only' ] ).toBe( true );
		expect(
			classes.filter( ( className ) =>
				className.startsWith( 'corex-cta' )
			)
		).toEqual( [] );
	} );
} );
