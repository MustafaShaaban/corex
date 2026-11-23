/**
 * Jest: the Corex Form block's editor (spec 029; spec 136, FR-019, FR-020, User Story 2
 * scenarios 3 and 4). With no form chosen the canvas asks which form, in WordPress's own
 * Placeholder and selects, and a choice sets the block's attribute. With one chosen the block
 * previews the server render, inert, with named loading and empty states. A block saved before
 * keeps its form.
 */
import { registerBlockType } from '@wordpress/blocks';
import apiFetch from '@wordpress/api-fetch';
import metadata from './block.json';

const SelectControlMock = () => null;
const ServerSideRenderMock = () => null;
const DisabledMock = ( { children } ) => children;
const PlaceholderMock = ( { children } ) => children;
const SpinnerMock = () => null;
const NoticeMock = () => null;
const mockChoices = { current: null };

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
		SelectControl: SelectControlMock,
		TextControl: () => null,
		TextareaControl: () => null,
		Disabled: DisabledMock,
		Placeholder: PlaceholderMock,
		Spinner: SpinnerMock,
		Notice: NoticeMock,
	} ),
	{ virtual: true }
);
jest.mock( '@wordpress/server-side-render', () => ServerSideRenderMock, {
	virtual: true,
} );
jest.mock(
	'@wordpress/element',
	() => ( {
		useState: ( init ) => [ mockChoices.current ?? init, jest.fn() ],
		useEffect: ( fn ) => fn(),
	} ),
	{ virtual: true }
);
jest.mock(
	'@wordpress/api-fetch',
	() =>
		jest.fn( () =>
			Promise.resolve( {
				data: {
					flows: [ { id: 7, name: 'Contact', state: 'published' } ],
				},
			} )
		),
	{ virtual: true }
);
jest.mock(
	'@wordpress/i18n',
	() => ( { __: ( s ) => s, sprintf: ( s ) => s } ),
	{ virtual: true }
);

import './index.js';

/**
 * Every element of a type in a React element tree; function components are not expanded.
 *
 * @param {Object|Array<Object>}                         node A React element, or a list of them.
 * @param {string|import('react').ComponentType<Object>} type The tag or component to find.
 * @param {Array<Object>}                                out  What has been found.
 * @return {Array<Object>} The elements found.
 */
function collect( node, type, out = [] ) {
	if ( Array.isArray( node ) ) {
		node.forEach( ( child ) => collect( child, type, out ) );
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

/** The attributes a block inserted from the inserter starts with: block.json's, then the variation's. */
const NONE_CHOSEN = {
	source: 'flow',
	formSlug: '',
	flowId: 0,
	flowSlug: '',
};

describe( 'form block: the form is asked for on the canvas (FR-020)', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];
	const edit = ( attributes, setAttributes = jest.fn() ) =>
		config.edit( { attributes, setAttributes } );

	beforeEach( () => {
		mockChoices.current = [
			{ id: 7, name: 'Contact', state: 'published' },
		];
	} );

	test( 'registers and saves nothing', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'FR-020: a block inserted from the inserter has no form chosen', () => {
		const inserted = config.variations.find( ( v ) => v.isDefault );

		expect( inserted.attributes ).toEqual( { formSlug: '' } );
		expect( inserted.scope ).toEqual( [ 'inserter' ] );
	} );

	test( 'FR-020: with no form chosen the canvas asks which, and no preview is drawn', () => {
		const element = edit( NONE_CHOSEN );
		const chooser = chooserOf( element );

		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/corex/v1/flows?state=published',
		} );
		expect( chooser.props.instructions ).toBe(
			'Choose the form this block shows.'
		);
		expect( collect( chooser, SelectControlMock ).length ).toBe( 2 );
		expect( collect( element, ServerSideRenderMock ) ).toEqual( [] );
	} );

	test( 'FR-020: choosing a flow on the canvas sets the attribute', () => {
		const setAttributes = jest.fn();
		const [ , flow ] = collect(
			chooserOf( edit( NONE_CHOSEN, setAttributes ) ),
			SelectControlMock
		);

		flow.props.onChange( '7' );

		expect( setAttributes ).toHaveBeenCalledWith( { flowId: 7 } );
	} );

	test( 'FR-020: choosing a registered form on the canvas sets the attribute', () => {
		mockChoices.current = [ { slug: 'contact', label: 'Contact' } ];
		const setAttributes = jest.fn();
		const [ , form ] = collect(
			chooserOf(
				edit( { ...NONE_CHOSEN, source: 'registered' }, setAttributes )
			),
			SelectControlMock
		);

		form.props.onChange( 'contact' );

		expect( setAttributes ).toHaveBeenCalledWith( { formSlug: 'contact' } );
	} );

	test( 'FR-020: while the forms load the chooser shows a Spinner', () => {
		mockChoices.current = null;
		const chooser = chooserOf( edit( NONE_CHOSEN ) );

		expect( collect( chooser, SpinnerMock ).length ).toBe( 1 );
		expect( collect( chooser, SelectControlMock ).length ).toBe( 1 );
	} );

	test( 'FR-020: with nothing to choose the chooser says so in a Notice', () => {
		mockChoices.current = [];
		const [ notice ] = collect(
			chooserOf( edit( NONE_CHOSEN ) ),
			NoticeMock
		);

		expect( notice.props.children ).toBe( 'No published forms yet.' );
		expect( notice.props.isDismissible ).toBe( false );
	} );
} );

describe( 'form block: a chosen form is a records preview (FR-019)', () => {
	const [ , config ] = registerBlockType.mock.calls[ 0 ];
	const preview = ( attributes ) => {
		const element = config.edit( { attributes, setAttributes: jest.fn() } );
		const [ disabled ] = collect( element, DisabledMock );

		return { element, ssr: collect( disabled, ServerSideRenderMock )[ 0 ] };
	};

	test( 'FR-019: a chosen flow is previewed by the server render, inert, inside the wrapper', () => {
		const attributes = { ...NONE_CHOSEN, flowId: 7 };
		const { element, ssr } = preview( attributes );

		expect( element.type ).toBe( 'div' );
		expect( ssr.props.block ).toBe( 'corex/form' );
		expect( ssr.props.attributes ).toEqual( attributes );
		expect( chooserOf( element ) ).toBeUndefined();
	} );

	test( 'a block saved before, with the form of its default, still previews that form', () => {
		const { ssr } = preview( {
			source: 'flow',
			formSlug: 'contact',
			flowId: 0,
			flowSlug: '',
		} );

		expect( ssr.props.attributes.formSlug ).toBe( 'contact' );
	} );

	test( 'FR-019: loading and a form that no longer exists are named in words', () => {
		const { ssr } = preview( { ...NONE_CHOSEN, flowSlug: 'gone' } );
		const loading = ssr.props.LoadingResponsePlaceholder( {} );
		const empty = ssr.props.EmptyResponsePlaceholder( {} );
		const failed = ssr.props.ErrorResponsePlaceholder( {} );

		expect( loading.props.instructions ).toBe( 'Loading the form…' );
		expect( loading.props.children.type ).toBe( SpinnerMock );
		expect( empty.type ).toBe( PlaceholderMock );
		expect( empty.props.instructions ).toBe(
			'This form is not available. Choose another in the block settings.'
		);
		expect( failed.type ).toBe( NoticeMock );
	} );
} );

/**
 * @param {Object} element The editor's element tree.
 * @return {Object|undefined} The canvas Placeholder that asks which form, if one is drawn.
 */
function chooserOf( element ) {
	return collect( element, PlaceholderMock ).find(
		( placeholder ) => placeholder.props.label === 'Form'
	);
}
