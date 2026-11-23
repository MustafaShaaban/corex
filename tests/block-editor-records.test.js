/**
 * The records blocks' editors (spec 136, FR-019, User Story 2 scenario 3; DECISIONS #671, #673).
 *
 * Posts, breadcrumbs, jobs and a chosen `corex/form` preview their server render inside the
 * block's wrapper, inert (WordPress's Disabled), with their own words for loading and for an empty
 * render, drawn by `records-states.js`. How those states draw is that file's own test
 * (`addons/corex-ui/src/Blocks/shared/records-states.test.js`); the careers and forms packages build
 * on their own and hold copies of it, which must not drift from it.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const mockRegistered = new Map();
const ServerSideRenderMock = () => null;
const DisabledMock = ( { children } ) => children;
const PlaceholderMock = () => null;

jest.mock(
	'@wordpress/blocks',
	() => ( {
		registerBlockType: ( metadata, settings ) =>
			mockRegistered.set(
				typeof metadata === 'string' ? metadata : metadata.name,
				settings
			),
	} ),
	{ virtual: true }
);
jest.mock(
	'@wordpress/block-editor',
	() => ( {
		useBlockProps: ( props ) => props || {},
		InspectorControls: () => null,
	} ),
	{ virtual: true }
);
jest.mock(
	'@wordpress/components',
	() => ( {
		Disabled: DisabledMock,
		Placeholder: PlaceholderMock,
		Spinner: () => null,
		Notice: () => null,
		PanelBody: () => null,
		RangeControl: () => null,
		SelectControl: () => null,
		TextControl: () => null,
		TextareaControl: () => null,
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
jest.mock( '@wordpress/api-fetch', () => () => new Promise( () => {} ), {
	virtual: true,
} );
jest.mock(
	'@wordpress/i18n',
	() => ( { __: ( s ) => s, sprintf: ( s ) => s } ),
	{ virtual: true }
);

const ROOT = path.resolve( __dirname, '..' );
const SHARED = 'addons/corex-ui/src/Blocks/shared/records-states.js';
const COPIES = [
	'addons/corex-careers/blocks/shared/records-states.js',
	'plugins/corex-forms/src/Block/blocks/records-states.js',
];

/** Each records editor: its folder, attributes to draw with, and its words. */
const RECORDS = [
	[
		'corex/posts',
		'addons/corex-ui/src/Blocks/posts',
		{ count: 3 },
		'Loading posts…',
		'No posts to show.',
	],
	[
		'corex/breadcrumbs',
		'addons/corex-ui/src/Blocks/breadcrumbs',
		{},
		'Loading the trail…',
		'No trail to show.',
	],
	[
		'corex/jobs',
		'addons/corex-careers/blocks/jobs',
		{ count: 4 },
		'Loading open positions…',
		'No open positions to show.',
	],
	[
		'corex/form',
		'plugins/corex-forms/src/Block/blocks/corex-form',
		{ source: 'flow', flowId: 7, flowSlug: '', formSlug: '' },
		'Loading the form…',
		'This form is not available. Choose another in the block settings.',
	],
];

RECORDS.forEach( ( [ , folder ] ) => {
	jest.doMock( path.join( ROOT, folder, 'style.scss' ), () => ( {} ), {
		virtual: true,
	} );
	require( path.join( ROOT, folder, 'index.js' ) );
} );

/**
 * The first element of a type in a React element tree; function components are not expanded.
 *
 * @param {Object|Array<Object>}                  node A React element, or a list of them.
 * @param {import('react').ComponentType<Object>} type The component to find.
 * @return {Object|null} The element, if there is one.
 */
function find( node, type ) {
	if ( Array.isArray( node ) ) {
		return (
			node.map( ( child ) => find( child, type ) ).find( Boolean ) || null
		);
	}
	if ( ! node || typeof node !== 'object' ) {
		return null;
	}

	return node.type === type ? node : find( node.props?.children, type );
}

describe( 'FR-019: a records block previews its server render, inert, with its own words', () => {
	describe.each( RECORDS )(
		'%s',
		( name, _folder, attributes, loading, empty ) => {
			const element = mockRegistered
				.get( name )
				.edit( { attributes, setAttributes: () => {} } );
			const ssr = find(
				find( element, DisabledMock ),
				ServerSideRenderMock
			);

			test( 'the server render of the block, inside its wrapper and inside Disabled', () => {
				expect( element.type ).toBe( 'div' );
				expect( ssr.props.block ).toBe( name );
				expect( ssr.props.attributes ).toEqual( attributes );
			} );

			test( 'its loading and empty states say what they are', () => {
				expect(
					ssr.props.LoadingResponsePlaceholder( {} ).props
						.instructions
				).toBe( loading );
				expect(
					ssr.props.EmptyResponsePlaceholder( {} ).props.instructions
				).toBe( empty );
				expect( typeof ssr.props.ErrorResponsePlaceholder ).toBe(
					'function'
				);
			} );
		}
	);

	test.each( COPIES )( '%s is the same file as the shared one', ( copy ) => {
		expect( fs.readFileSync( path.join( ROOT, copy ), 'utf8' ) ).toBe(
			fs.readFileSync( path.join( ROOT, SHARED ), 'utf8' )
		);
	} );
} );
