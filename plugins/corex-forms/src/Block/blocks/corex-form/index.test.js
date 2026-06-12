/**
 * Jest — Corex Form block selector (spec 029): registers, save() null, edit() renders a
 * SelectControl (not a free-text slug field) and fetches the form list from corex/v1/forms.
 */
import { registerBlockType } from '@wordpress/blocks';
import apiFetch from '@wordpress/api-fetch';
import metadata from './block.json';

const SelectControlMock = () => null;
const ServerSideRenderMock = () => null;

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), { virtual: true } );
jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: ( props ) => props || {},
	InspectorControls: ( { children } ) => children,
} ), { virtual: true } );
jest.mock( '@wordpress/components', () => ( {
	PanelBody: ( { children } ) => children,
	SelectControl: SelectControlMock,
} ), { virtual: true } );
jest.mock( '@wordpress/server-side-render', () => ServerSideRenderMock, { virtual: true } );
jest.mock( '@wordpress/element', () => ( {
	useState: ( init ) => [ init, jest.fn() ],
	useEffect: ( fn ) => fn(),
} ), { virtual: true } );
jest.mock( '@wordpress/api-fetch', () => jest.fn( () => Promise.resolve( [ { slug: 'contact', label: 'Contact' } ] ) ), { virtual: true } );
jest.mock( '@wordpress/i18n', () => ( { __: ( s ) => s } ), { virtual: true } );

import './index.js';

function collect( node, type, out = [] ) {
	if ( ! node || typeof node !== 'object' ) return out;
	if ( node.type === type ) out.push( node );
	const ch = node.props && node.props.children;
	( Array.isArray( ch ) ? ch : [ ch ] ).forEach( ( c ) => collect( c, type, out ) );
	return out;
}

describe( 'form block — form selector', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];

	test( 'registers and saves nothing', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'edit() fetches the form list and renders a SelectControl (not free text)', () => {
		const element = config.edit( { attributes: { formSlug: 'contact' }, setAttributes: jest.fn() } );

		expect( apiFetch ).toHaveBeenCalledWith( { path: '/corex/v1/forms' } );
		expect( collect( element, SelectControlMock ).length ).toBe( 1 );
	} );
} );
