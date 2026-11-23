/**
 * Jest: Corex Breadcrumbs is a records block (spec 136, FR-019, User Story 2 scenario 3). Its editor
 * previews the server render inside the block's wrapper, inert (WordPress's Disabled), with a
 * loading and an empty state named in words, and saves nothing.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

const ServerSideRenderMock = () => null;
const DisabledMock = ( { children } ) => children;
const PlaceholderMock = () => null;
const SpinnerMock = () => null;
const NoticeMock = () => null;

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), {
	virtual: true,
} );
jest.mock(
	'@wordpress/block-editor',
	() => ( {
		useBlockProps: ( props ) => props || {},
		InspectorControls: ( { children } ) => children,
	} ),
	{ virtual: true }
);
jest.mock(
	'@wordpress/components',
	() => ( {
		PanelBody: ( { children } ) => children,
		RangeControl: () => null,
		Disabled: DisabledMock,
		Placeholder: PlaceholderMock,
		Spinner: SpinnerMock,
		Notice: NoticeMock,
	} ),
	{ virtual: true }
);
jest.mock( '@wordpress/i18n', () => ( { __: ( s ) => s } ), { virtual: true } );
jest.mock( '@wordpress/server-side-render', () => ServerSideRenderMock, {
	virtual: true,
} );

import './index.js';

function findByType( node, type ) {
	if ( Array.isArray( node ) ) {
		return (
			node
				.map( ( child ) => findByType( child, type ) )
				.find( Boolean ) || null
		);
	}
	if ( ! node || typeof node !== 'object' ) {
		return null;
	}
	if ( node.type === type ) {
		return node;
	}

	return findByType( node.props && node.props.children, type );
}

describe( 'Breadcrumbs block: a records block (FR-019)', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];
	const element = config.edit( {
		attributes: {},
		setAttributes: () => {},
	} );
	const disabled = findByType( element, DisabledMock );
	const ssr = findByType( disabled, ServerSideRenderMock );

	test( 'registers and saves nothing (dynamic)', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'FR-019: previews the server render inside the block wrapper, inert', () => {
		expect( element.type ).toBe( 'div' );
		expect( ssr.props.block ).toBe( metadata.name );
		expect( ssr.props.attributes ).toEqual( {} );
	} );

	test( 'FR-019: loading is a Placeholder named in words, holding a Spinner', () => {
		const loading = ssr.props.LoadingResponsePlaceholder( {} );

		expect( loading.type ).toBe( PlaceholderMock );
		expect( loading.props.instructions ).toBe( 'Loading the trail…' );
		expect( loading.props.children.type ).toBe( SpinnerMock );
	} );

	test( 'FR-019: empty is a Placeholder that says so; a failed preview is a Notice', () => {
		const empty = ssr.props.EmptyResponsePlaceholder( {} );
		const failed = ssr.props.ErrorResponsePlaceholder( {} );

		expect( empty.type ).toBe( PlaceholderMock );
		expect( empty.props.instructions ).toBe( 'No trail to show.' );
		expect( failed.type ).toBe( NoticeMock );
		expect( failed.props.isDismissible ).toBe( false );
	} );
} );
