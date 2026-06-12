/**
 * Jest — Corex CTA inline editing (spec 035): registers, save() null, edit() renders the
 * title/text/button RichText regions.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

const RichTextMock = () => null;

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), { virtual: true } );
jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: ( props ) => props || {},
	RichText: RichTextMock,
	URLInputButton: () => null,
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

describe( 'cta block — inline editing', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];

	test( 'registers and saves nothing', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'renders title + text + button RichText regions', () => {
		const element = config.edit( { attributes: {}, setAttributes: jest.fn() } );
		expect( collect( element, RichTextMock ).length ).toBe( 3 );
	} );
} );
