/**
 * Jest: the accordion's editor (spec 136 slice 5, User Story 4; FR-030, FR-031, FR-032, FR-039).
 *
 * The accordion holds accordion items, and only them, and saves its inner blocks; its settings are
 * "Open one at a time" (off by default) and the questions' heading level (none by default). An
 * accordion saved in the old shape (its questions and answers in `items`, no inner blocks) is
 * migrated when it is opened in the editor: each old item becomes an accordion item with the same
 * question, holding a paragraph with the old answer, and `items` is emptied.
 */
import metadata from './block.json';

const RichTextMock = () => null;
const InnerBlocksContent = () => null;
const ToggleControlMock = () => null;
const SelectControlMock = () => null;
const innerBlocksOptions = [];

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock(
	'@wordpress/blocks',
	() => ( {
		registerBlockType: jest.fn(),
		createBlock: ( name, attributes = {}, innerBlocks = [] ) => ( {
			name,
			attributes,
			innerBlocks,
		} ),
	} ),
	{ virtual: true }
);
jest.mock(
	'@wordpress/block-editor',
	() => {
		const InnerBlocks = () => null;
		InnerBlocks.Content = InnerBlocksContent;

		return {
			useBlockProps: ( props ) => props || {},
			useInnerBlocksProps: ( props, options ) => {
				innerBlocksOptions.push( options );

				return { ...props, 'data-holds': 'blocks' };
			},
			InnerBlocks,
			RichText: RichTextMock,
			InspectorControls: ( { children } ) => children,
		};
	},
	{ virtual: true }
);
jest.mock(
	'@wordpress/components',
	() => ( {
		PanelBody: ( { children } ) => children,
		ToggleControl: ToggleControlMock,
		SelectControl: SelectControlMock,
	} ),
	{ virtual: true }
);
jest.mock(
	'@wordpress/i18n',
	() => ( {
		__: ( s ) => s,
		sprintf: ( format, ...values ) =>
			values.reduce(
				( text, value ) => text.replace( /%[sd]/, String( value ) ),
				format
			),
	} ),
	{ virtual: true }
);

const { registerBlockType } = require( '@wordpress/blocks' );
require( './index.js' );

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

const [ name, config ] = registerBlockType.mock.calls[ 0 ];

function draw( attributes = {}, setAttributes = jest.fn() ) {
	return config.edit( {
		attributes: {
			items: [],
			oneOpen: false,
			headingLevel: 0,
			...attributes,
		},
		setAttributes,
		clientId: 'a1',
	} );
}

describe( 'FR-030: the accordion holds accordion items', () => {
	test( 'registers by its block.json and saves only its inner blocks', () => {
		expect( name ).toBe( metadata.name );
		expect( metadata.corex.editor ).toBe( 'in-place' );
		expect( config.save().type ).toBe( InnerBlocksContent );
	} );

	test( 'draws its root as the element that holds the items, and allows only items', () => {
		innerBlocksOptions.length = 0;
		const element = draw();
		const holders = collect( element, 'div' ).filter(
			( node ) => node.props[ 'data-holds' ] === 'blocks'
		);

		expect( holders ).toHaveLength( 1 );
		expect( holders[ 0 ].props.className ).toBe( 'corex-accordion' );
		expect( innerBlocksOptions[ 0 ] ).toMatchObject( {
			allowedBlocks: [ 'corex/accordion-item' ],
			template: [ [ 'corex/accordion-item' ] ],
			defaultBlock: { name: 'corex/accordion-item' },
		} );
		expect( metadata.allowedBlocks ).toEqual( [ 'corex/accordion-item' ] );
	} );

	test( 'gives its items the heading level as block context', () => {
		expect( metadata.providesContext ).toEqual( {
			'corex/accordionHeadingLevel': 'headingLevel',
		} );
	} );
} );

describe( 'FR-031, FR-032: the settings', () => {
	test( '"Open one at a time" is off by default and sets oneOpen', () => {
		expect( metadata.attributes.oneOpen ).toEqual( {
			type: 'boolean',
			default: false,
		} );
		const setAttributes = jest.fn();
		const [ toggle ] = collect(
			draw( {}, setAttributes ),
			ToggleControlMock
		);

		expect( toggle.props.label ).toBe( 'Open one at a time' );
		expect( toggle.props.checked ).toBe( false );
		toggle.props.onChange( true );
		expect( setAttributes ).toHaveBeenCalledWith( { oneOpen: true } );
		expect(
			collect( draw( { oneOpen: true } ), ToggleControlMock )[ 0 ].props
				.checked
		).toBe( true );
	} );

	test( 'the heading level is none by default, offers headings 2 to 6, and sets a number', () => {
		expect( metadata.attributes.headingLevel ).toMatchObject( {
			type: 'integer',
			default: 0,
		} );
		const setAttributes = jest.fn();
		const [ select ] = collect(
			draw( {}, setAttributes ),
			SelectControlMock
		);

		expect( select.props.label ).toBe( 'Question heading level' );
		expect( select.props.value ).toBe( '0' );
		expect( select.props.options ).toEqual( [
			{ label: 'None: plain text', value: '0' },
			{ label: 'Heading 2', value: '2' },
			{ label: 'Heading 3', value: '3' },
			{ label: 'Heading 4', value: '4' },
			{ label: 'Heading 5', value: '5' },
			{ label: 'Heading 6', value: '6' },
		] );
		select.props.onChange( '3' );
		expect( setAttributes ).toHaveBeenCalledWith( { headingLevel: 3 } );
	} );
} );

describe( 'FR-039: an accordion saved in the old shape migrates when opened', () => {
	const [ legacy ] = config.deprecated;
	const OLD = {
		items: [
			{
				title: 'What is <strong>CoreX</strong>?',
				content: 'A framework.',
			},
			{ title: 'Does it need a script?', content: 'No, <em>never</em>.' },
			{ title: 'A question with no answer', content: '' },
		],
	};

	test( 'is eligible with old items and no inner blocks, and only then', () => {
		expect( legacy.isEligible( OLD, [] ) ).toBe( true );
		expect( legacy.isEligible( OLD, undefined ) ).toBe( true );
		expect(
			legacy.isEligible( OLD, [ { name: 'corex/accordion-item' } ] )
		).toBe( false );
		expect( legacy.isEligible( { items: [] }, [] ) ).toBe( false );
		expect( legacy.isEligible( {}, [] ) ).toBe( false );
	} );

	test( 'saved nothing, as the old block did, with the same attributes and supports', () => {
		expect( legacy.save() ).toBeNull();
		expect( legacy.attributes.items ).toEqual( metadata.attributes.items );
		expect( legacy.supports ).toEqual( metadata.supports );
	} );

	test( 'turns each old item into an accordion item holding a paragraph, losing no question or answer', () => {
		const [ attributes, innerBlocks ] = legacy.migrate( {
			...OLD,
			anchor: 'faq',
			className: 'home-faq',
		} );

		expect( attributes ).toEqual( {
			items: [],
			anchor: 'faq',
			className: 'home-faq',
		} );
		expect( innerBlocks ).toEqual( [
			{
				name: 'corex/accordion-item',
				attributes: { question: 'What is <strong>CoreX</strong>?' },
				innerBlocks: [
					{
						name: 'core/paragraph',
						attributes: { content: 'A framework.' },
						innerBlocks: [],
					},
				],
			},
			{
				name: 'corex/accordion-item',
				attributes: { question: 'Does it need a script?' },
				innerBlocks: [
					{
						name: 'core/paragraph',
						attributes: { content: 'No, <em>never</em>.' },
						innerBlocks: [],
					},
				],
			},
			// An item with no answer keeps its question and holds nothing yet.
			{
				name: 'corex/accordion-item',
				attributes: { question: 'A question with no answer' },
				innerBlocks: [],
			},
		] );
	} );
} );
