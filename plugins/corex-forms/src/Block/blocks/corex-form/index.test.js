/**
 * Jest: the Corex Form block's editor (spec 029; spec 136, FR-019, FR-020, User Story 2
 * scenarios 3 and 4). With no form chosen the canvas asks which form, in WordPress's own
 * Placeholder and selects, and a choice sets the block's attribute. It tells a list that failed,
 * or that the person may not see, from an empty one. A block saved before keeps its form.
 *
 * The preview of a chosen form (inert, its loading and empty states) is checked with the other
 * records blocks in `tests/block-editor-records.test.js`; the loading of the lists in
 * `choices.test.js`.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

const SelectControlMock = () => null;
const ServerSideRenderMock = () => null;
const DisabledMock = ( { children } ) => children;
const PlaceholderMock = ( { children } ) => children;
const SpinnerMock = () => null;
const NoticeMock = () => null;
const mockChoices = { current: { status: 'loading' }, asked: [] };

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
	() => ( { useState: ( init ) => [ init, () => {} ], useEffect: () => {} } ),
	{ virtual: true }
);
jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );
jest.mock(
	'@wordpress/i18n',
	() => ( { __: ( s ) => s, sprintf: ( s ) => s } ),
	{ virtual: true }
);
jest.mock( './choices', () => ( {
	useChoices: ( source ) => {
		mockChoices.asked.push( source );

		return mockChoices.current;
	},
} ) );

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

/**
 * @param {Object} element The editor's element tree.
 * @return {Object|undefined} The canvas Placeholder that asks which form, if one is drawn.
 */
function chooserOf( element ) {
	return collect( element, PlaceholderMock ).find(
		( placeholder ) => placeholder.props.label === 'Form'
	);
}

const [ name, config ] = registerBlockType.mock.calls[ 0 ];
const inserted = config.variations.find( ( variation ) => variation.isDefault );
const DEFAULTS = Object.fromEntries(
	Object.entries( metadata.attributes ).map( ( [ key, schema ] ) => [
		key,
		schema.default,
	] )
);
/** The attributes a block inserted from the inserter starts with: block.json's, then the variation's. */
const NONE_CHOSEN = { ...DEFAULTS, ...inserted.attributes };

const edit = ( attributes, setAttributes = jest.fn() ) =>
	config.edit( { attributes, setAttributes } );
const READY = ( items ) => ( { status: 'ready', items } );

describe( 'form block: what a saved block keeps', () => {
	test( 'registers and saves nothing', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( "block.json's form stays the contact form, so a block saved with none named keeps printing it", () => {
		expect( metadata.attributes.formSlug.default ).toBe( 'contact' );
	} );

	test( 'FR-020: only a block inserted from the inserter starts with no form', () => {
		expect( inserted ).toEqual( {
			name: 'corex-form',
			isDefault: true,
			scope: [ 'inserter' ],
			attributes: { formSlug: '' },
		} );
	} );

	test( 'a block saved with the default form previews that form, not the chooser', () => {
		mockChoices.current = READY( [] );
		const element = edit( DEFAULTS );
		const [ ssr ] = collect(
			collect( element, DisabledMock ),
			ServerSideRenderMock
		);

		expect( ssr.props.attributes.formSlug ).toBe( 'contact' );
		expect( chooserOf( element ) ).toBeUndefined();
	} );
} );

describe( 'form block: the source its fields show', () => {
	beforeEach( () => {
		mockChoices.current = READY( [
			{ slug: 'contact', label: 'Contact' },
		] );
		mockChoices.asked = [];
	} );

	test( "a block saved with block.json's defaults shows the registered contact form it prints, and changes nothing", () => {
		const setAttributes = jest.fn();
		const element = edit( DEFAULTS, setAttributes );
		const [ source, form ] = collect( element, SelectControlMock );

		expect( source.props.value ).toBe( 'registered' );
		expect( form.props.label ).toBe( 'Form' );
		expect( form.props.value ).toBe( 'contact' );
		expect( mockChoices.asked ).toEqual( [ 'registered' ] );
		expect( setAttributes ).not.toHaveBeenCalled();
	} );

	test( 'a block naming a flow shows its saved source', () => {
		mockChoices.current = READY( [
			{ id: 7, name: 'Contact', state: 'published' },
		] );
		const [ source, flow ] = collect(
			edit( { ...DEFAULTS, flowId: 7 } ),
			SelectControlMock
		);

		expect( source.props.value ).toBe( 'flow' );
		expect( flow.props.value ).toBe( '7' );
		expect( mockChoices.asked ).toEqual( [ 'flow' ] );
	} );

	test( 'changing the source clears every way of naming a form, the flow slug too', () => {
		const setAttributes = jest.fn();
		const [ source ] = collect(
			edit( { ...DEFAULTS, flowSlug: 'contact-us' }, setAttributes ),
			SelectControlMock
		);

		source.props.onChange( 'registered' );

		expect( setAttributes ).toHaveBeenCalledWith( {
			source: 'registered',
			flowId: 0,
			flowSlug: '',
			formSlug: '',
		} );
	} );
} );

describe( 'form block: the form is asked for on the canvas (FR-020)', () => {
	beforeEach( () => {
		mockChoices.current = READY( [
			{ id: 7, name: 'Contact', state: 'published' },
		] );
	} );

	test( 'FR-020: with no form chosen the canvas asks which, and no preview is drawn', () => {
		const element = edit( NONE_CHOSEN );
		const chooser = chooserOf( element );

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
		mockChoices.current = READY( [
			{ slug: 'contact', label: 'Contact' },
		] );
		const setAttributes = jest.fn();
		const [ , form ] = collect(
			chooserOf(
				edit( { ...NONE_CHOSEN, source: 'registered' }, setAttributes )
			),
			SelectControlMock
		);

		expect( form.props.options.map( ( option ) => option.value ) ).toEqual(
			[ '', 'contact' ]
		);
		form.props.onChange( 'contact' );

		expect( setAttributes ).toHaveBeenCalledWith( { formSlug: 'contact' } );
	} );

	test( 'FR-020: while the forms load the chooser shows a Spinner', () => {
		mockChoices.current = { status: 'loading' };
		const chooser = chooserOf( edit( NONE_CHOSEN ) );

		expect( collect( chooser, SpinnerMock ).length ).toBe( 1 );
		expect( collect( chooser, SelectControlMock ).length ).toBe( 1 );
	} );

	test( 'FR-020: with nothing to choose the chooser says so in a Notice', () => {
		mockChoices.current = READY( [] );
		const [ notice ] = collect(
			chooserOf( edit( NONE_CHOSEN ) ),
			NoticeMock
		);

		expect( notice.props.status ).toBe( 'info' );
		expect( notice.props.children ).toBe( 'No published forms yet.' );
		expect( notice.props.isDismissible ).toBe( false );
	} );

	test( 'FR-020: a person who may not list the forms is told so, not that there are none', () => {
		mockChoices.current = {
			status: 'failed',
			forbidden: true,
			message: 'You do not have permission to perform this action.',
		};
		const chooser = chooserOf( edit( NONE_CHOSEN ) );
		const [ notice ] = collect( chooser, NoticeMock );

		expect( notice.props.status ).toBe( 'warning' );
		expect( notice.props.children ).toBe(
			'You cannot see the list of forms. Ask someone who manages forms to choose one.'
		);
		expect( collect( chooser, SelectControlMock ).length ).toBe( 1 );
	} );

	test( 'FR-020: a list that failed for another reason says so, with its message', () => {
		mockChoices.current = {
			status: 'failed',
			forbidden: false,
			message: 'You are probably offline.',
		};
		const [ notice ] = collect(
			chooserOf( edit( NONE_CHOSEN ) ),
			NoticeMock
		);

		expect( notice.props.status ).toBe( 'warning' );
		expect( notice.props.children ).toEqual( [
			'The list of forms did not load.',
			' You are probably offline.',
		] );
	} );
} );
