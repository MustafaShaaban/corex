/**
 * The mega menu item's panel controls in the editor (spec 137 slice 3; FR-013, Edge Cases).
 *
 * The panel is chosen from the template parts of the `mega-menu` area; a chosen panel has a way to
 * open it for editing; a new panel is started from a panel pattern and chosen at once; an item with
 * no panel, or whose panel is gone, says so. The controls are WordPress's own components, stood in
 * for here by plain elements that carry what the test reads.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

const mockStores = {};
const mockSave = jest.fn();

jest.mock( '@wordpress/data', () => ( {
	useSelect: ( select ) => select( ( name ) => mockStores[ name ] ),
	useDispatch: () => ( { saveEntityRecord: mockSave } ),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
	sprintf: ( format, ...values ) =>
		values.reduce( ( text, value ) => text.replace( '%s', value ), format ),
} ) );

jest.mock( '@wordpress/components', () => {
	// eslint-disable-next-line import/no-extraneous-dependencies
	const { createElement } = require( 'react' );

	return {
		PanelBody: ( { title, children } ) =>
			createElement( 'section', { 'data-title': title }, children ),
		SelectControl: ( { label, value, options, onChange, help } ) =>
			createElement(
				'div',
				null,
				createElement(
					'select',
					{
						'aria-label': label,
						value,
						onChange: ( event ) => onChange( event.target.value ),
					},
					options.map( ( option ) =>
						createElement(
							'option',
							{ key: option.value, value: option.value },
							option.label
						)
					)
				),
				help ? createElement( 'p', { className: 'help' }, help ) : null
			),
		Button: ( { children, onClick, href, disabled } ) =>
			href
				? createElement( 'a', { href }, children )
				: createElement(
						'button',
						{ type: 'button', onClick, disabled },
						children
					),
		Notice: ( { status, children } ) =>
			createElement( 'div', { 'data-status': status }, children ),
	};
} );

const {
	default: PanelControls,
	AREA,
	PATTERN_BLOCK_TYPE,
	freeSlug,
} = require( './panel-controls' );

const PARTS = [
	{
		id: 'corex//services',
		slug: 'services',
		area: AREA,
		title: { rendered: 'Services panel' },
	},
	{
		id: 'corex//header',
		slug: 'header',
		area: 'header',
		title: { rendered: 'Header' },
	},
	{
		id: 'corex//products',
		slug: 'products',
		area: AREA,
		title: { rendered: '' },
	},
];

const PATTERNS = [
	{
		name: 'corex/megamenu-services',
		title: 'Mega menu panel: services',
		blockTypes: [ PATTERN_BLOCK_TYPE ],
		content: '<!-- wp:paragraph --><p>Services</p><!-- /wp:paragraph -->',
	},
	{ name: 'corex/hero', title: 'Hero', blockTypes: [], content: '' },
	{
		name: 'corex/header-saas',
		title: 'Header',
		blockTypes: [ 'core/template-part/header' ],
		content: '',
	},
];

let container;
let root;
let navigate;

function stores( {
	parts = PARTS,
	patterns = PATTERNS,
	canNavigate = true,
} = {} ) {
	navigate = jest.fn();
	mockStores.core = {
		getEntityRecords: ( kind, name ) =>
			kind === 'postType' && name === 'wp_template_part' ? parts : null,
		getBlockPatterns: () => patterns,
	};
	mockStores[ 'core/block-editor' ] = {
		getSettings: () =>
			canNavigate ? { onNavigateToEntityRecord: navigate } : {},
	};
}

function render( panel, setPanel = jest.fn() ) {
	act( () => {
		root.render( <PanelControls panel={ panel } setPanel={ setPanel } /> );
	} );

	return setPanel;
}

const select = ( label ) =>
	container.querySelector( `select[aria-label="${ label }"]` );
const buttonNamed = ( name ) =>
	Array.from( container.querySelectorAll( 'button' ) ).find(
		( button ) => button.textContent === name
	);
const notices = ( status ) =>
	Array.from(
		container.querySelectorAll( `[data-status="${ status }"]` ),
		( notice ) => notice.textContent
	);

function choose( element, value ) {
	act( () => {
		element.value = value;
		element.dispatchEvent(
			new window.Event( 'change', { bubbles: true } )
		);
	} );
}

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	stores();
	mockSave.mockReset();
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
} );

describe( 'FR-013: the panel is chosen from the parts of the mega-menu area', () => {
	test( "lists the area's parts only, by title or else by slug, after an empty choice", () => {
		render( '' );

		expect(
			Array.from( select( 'Panel' ).options, ( option ) => [
				option.value,
				option.textContent,
			] )
		).toEqual( [
			[ '', 'Choose a panel' ],
			[ 'services', 'Services panel' ],
			[ 'products', 'products' ],
		] );
	} );

	test( "choosing a part sets it as the item's panel", () => {
		const setPanel = render( '' );

		choose( select( 'Panel' ), 'products' );

		expect( setPanel ).toHaveBeenCalledWith( 'products' );
	} );

	test( 'opens the chosen panel for editing through the editor', () => {
		render( 'services' );

		act( () => buttonNamed( 'Edit panel' ).click() );

		expect( navigate ).toHaveBeenCalledWith( {
			postId: 'corex//services',
			postType: 'wp_template_part',
		} );
	} );

	test( 'links to the panel in the Site Editor where the editor cannot open it itself', () => {
		stores( { canNavigate: false } );
		render( 'services' );

		const link = Array.from( container.querySelectorAll( 'a' ) ).find(
			( anchor ) => anchor.textContent === 'Edit panel'
		);

		expect( link.getAttribute( 'href' ) ).toBe(
			'site-editor.php?p=%2Fwp_template_part%2Fcorex%2F%2Fservices&canvas=edit'
		);
	} );
} );

describe( 'Edge Cases: an item with no panel says so in the editor', () => {
	test( 'an item with no panel shows a notice and no edit link', () => {
		render( '' );

		expect( notices( 'warning' ) ).toEqual( [
			'Choose a panel. Until one is chosen, this item is not shown on the page.',
		] );
		expect( buttonNamed( 'Edit panel' ) ).toBeUndefined();
	} );

	test( 'an item whose panel was deleted says so', () => {
		render( 'deleted-panel' );

		expect( notices( 'warning' ) ).toEqual( [
			'The chosen panel no longer exists. Choose another; until then this item is not shown on the page.',
		] );
	} );

	test( 'says nothing while the parts are still loading', () => {
		stores( { parts: null } );
		render( 'services' );

		expect( notices( 'warning' ) ).toEqual( [] );
	} );

	test( 'an item with a panel that exists shows no notice', () => {
		render( 'services' );

		expect( notices( 'warning' ) ).toEqual( [] );
	} );
} );

describe( 'FR-013: a new panel is started from a panel pattern', () => {
	test( 'offers the patterns made for the mega-menu area only', () => {
		render( '' );

		expect(
			Array.from( select( 'Start from' ).options, ( option ) => [
				option.value,
				option.textContent,
			] )
		).toEqual( [
			[ '', 'Choose a pattern' ],
			[ 'corex/megamenu-services', 'Mega menu panel: services' ],
		] );
	} );

	test( 'creates a part of the area from the pattern and chooses it', async () => {
		mockSave.mockResolvedValue( { slug: 'megamenu-services-2' } );
		stores( {
			parts: [
				...PARTS,
				{
					id: 'corex//megamenu-services',
					slug: 'megamenu-services',
					area: AREA,
					title: { rendered: 'x' },
				},
			],
		} );
		const setPanel = render( '' );

		choose( select( 'Start from' ), 'corex/megamenu-services' );
		await act( async () => buttonNamed( 'Create panel' ).click() );

		expect( mockSave ).toHaveBeenCalledWith(
			'postType',
			'wp_template_part',
			{
				slug: 'megamenu-services-2',
				title: 'Mega menu panel: services',
				area: AREA,
				content: PATTERNS[ 0 ].content,
			},
			{ throwOnError: true }
		);
		expect( setPanel ).toHaveBeenCalledWith( 'megamenu-services-2' );
	} );

	test( 'cannot create before a pattern is chosen', () => {
		render( '' );

		expect( buttonNamed( 'Create panel' ).disabled ).toBe( true );
	} );

	test( 'says so when the part could not be created, and chooses nothing', async () => {
		mockSave.mockRejectedValue( new Error( 'rest_forbidden' ) );
		const setPanel = render( '' );

		choose( select( 'Start from' ), 'corex/megamenu-services' );
		await act( async () => buttonNamed( 'Create panel' ).click() );

		expect( notices( 'error' ) ).toEqual( [
			'The panel could not be created.',
		] );
		expect( setPanel ).not.toHaveBeenCalled();
	} );

	test( 'says when no panel pattern is available', () => {
		stores( { patterns: [ PATTERNS[ 1 ] ] } );
		render( '' );

		expect( select( 'Start from' ) ).toBeNull();
		expect( container.textContent ).toContain(
			'No panel pattern is available.'
		);
	} );
} );

describe( "a new panel's slug", () => {
	test( "is the pattern's name without its namespace, numbered past the slugs taken", () => {
		expect( freeSlug( 'corex/megamenu-docs', [] ) ).toBe( 'megamenu-docs' );
		expect(
			freeSlug( 'corex/megamenu-docs', [
				'megamenu-docs',
				'megamenu-docs-2',
			] )
		).toBe( 'megamenu-docs-3' );
	} );
} );
