/**
 * The language switcher's editor (spec 137, FR-021, FR-031, FR-032a; DECISIONS #656, #702;
 * `design/handoffs/header-system.md`, step 2a). It shows records the editor does not hold (the
 * site's languages), so it previews the server render, inert, inside the block's wrapper. A "Form"
 * choice of Menu, List and Toggle carries the one help line the owner approved, and with fewer than
 * two languages the preview is WordPress's placeholder with one sentence.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

const ServerSideRenderMock = () => null;
const DisabledMock = ( { children } ) => children;
const PlaceholderMock = () => null;
const ToggleGroupControlMock = () => null;
const ToggleGroupControlOptionMock = () => null;
const InspectorControlsMock = ( { children } ) => children;
const PanelBodyMock = ( { children } ) => children;

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), {
	virtual: true,
} );
jest.mock(
	'@wordpress/block-editor',
	() => ( {
		useBlockProps: ( props ) => ( { className: 'block-props', ...props } ),
		InspectorControls: InspectorControlsMock,
	} ),
	{ virtual: true }
);
jest.mock(
	'@wordpress/components',
	() => ( {
		Disabled: DisabledMock,
		PanelBody: PanelBodyMock,
		Placeholder: PlaceholderMock,
		__experimentalToggleGroupControl: ToggleGroupControlMock,
		__experimentalToggleGroupControlOption: ToggleGroupControlOptionMock,
	} ),
	{ virtual: true }
);
jest.mock( '@wordpress/server-side-render', () => ServerSideRenderMock, {
	virtual: true,
} );
jest.mock( '@wordpress/i18n', () => ( { __: ( s ) => s } ), { virtual: true } );

import './index.js';

/**
 * Every element of a type in a React element tree.
 *
 * @param {Object|Array|string|null} node A React node.
 * @param {Object}                   type A component.
 * @param {Array}                    out  What was found.
 * @return {Array} The elements.
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

const settings = () => registerBlockType.mock.calls[ 0 ][ 1 ];
const edit = ( attributes = { form: 'menu' }, setAttributes = jest.fn() ) =>
	settings().edit( { attributes, setAttributes } );

describe( 'the language switcher editor', () => {
	it( 'registers the block by its name and saves nothing', () => {
		expect( registerBlockType.mock.calls[ 0 ][ 0 ] ).toBe(
			'corex/language-switcher'
		);
		expect( metadata.title ).toBe( 'Language switcher' );
		expect( settings().save() ).toBeNull();
	} );

	it( 'FR-021: previews the server render inert, inside the block wrapper', () => {
		const tree = edit( { form: 'list' } );
		expect( tree.props.className ).toBe( 'block-props' );

		const [ disabled ] = collect( tree, DisabledMock );
		const [ preview ] = collect( disabled, ServerSideRenderMock );
		expect( preview.props.block ).toBe( 'corex/language-switcher' );
		expect( preview.props.attributes ).toEqual( { form: 'list' } );
	} );

	it( 'FR-031: shows WordPress placeholder with one sentence when the render is empty', () => {
		const [ preview ] = collect( edit(), ServerSideRenderMock );
		const Empty = preview.props.EmptyResponsePlaceholder;
		const [ placeholder ] = collect( Empty(), PlaceholderMock );

		expect( placeholder.props.label ).toBe( 'Language switcher' );
		expect( placeholder.props.instructions ).toBe(
			'The switcher appears here once the site has a second language.'
		);
	} );

	it( 'FR-032a: offers the form as Menu, List and Toggle, with the approved help line', () => {
		const setAttributes = jest.fn();
		const [ control ] = collect(
			edit( { form: 'toggle' }, setAttributes ),
			ToggleGroupControlMock
		);

		expect( control.props.label ).toBe( 'Form' );
		expect( control.props.value ).toBe( 'toggle' );
		expect( control.props.help ).toBe(
			'With three or more languages, the toggle shows as the list.'
		);
		expect(
			collect( control.props.children, ToggleGroupControlOptionMock ).map(
				( option ) => [ option.props.value, option.props.label ]
			)
		).toEqual( [
			[ 'menu', 'Menu' ],
			[ 'list', 'List' ],
			[ 'toggle', 'Toggle' ],
		] );

		control.props.onChange( 'list' );
		expect( setAttributes ).toHaveBeenCalledWith( { form: 'list' } );
	} );
} );
