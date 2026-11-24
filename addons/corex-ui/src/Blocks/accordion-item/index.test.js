/**
 * Jest: the accordion item's editor (spec 136 slice 5, User Story 4; FR-030, FR-032).
 *
 * An item exists only inside an accordion. Its question is typed in place, in the `summary`, as a
 * heading of the accordion's level or as plain text; its answer holds any blocks. The item is
 * drawn open, and pressing its question does not close it, so both stay in reach while editing.
 * The kit's chevron sits beside the question, hidden from assistive technology.
 */
import metadata from './block.json';

const RichTextMock = () => null;
const InnerBlocksContent = () => null;
const innerBlocksOptions = [];

jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), {
	virtual: true,
} );
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
		};
	},
	{ virtual: true }
);
jest.mock( '@wordpress/i18n', () => ( { __: ( s ) => s } ), {
	virtual: true,
} );

const { registerBlockType } = require( '@wordpress/blocks' );
require( './index.js' );

function collect( node, test, out = [] ) {
	if ( Array.isArray( node ) ) {
		node.forEach( ( n ) => collect( n, test, out ) );
		return out;
	}
	if ( ! node || typeof node !== 'object' ) {
		return out;
	}
	if ( test( node ) ) {
		out.push( node );
	}
	collect( node.props && node.props.children, test, out );
	return out;
}

const byType = ( type ) => ( node ) => node.type === type;

const [ name, config ] = registerBlockType.mock.calls[ 0 ];

function draw( { question = 'What is CoreX?', context = {} } = {} ) {
	const setAttributes = jest.fn();
	const element = config.edit( {
		attributes: { question },
		setAttributes,
		context,
	} );

	return { element, setAttributes };
}

describe( 'FR-030: an accordion item', () => {
	test( 'exists only inside an accordion and saves only its inner blocks', () => {
		expect( name ).toBe( metadata.name );
		expect( metadata.parent ).toEqual( [ 'corex/accordion' ] );
		expect( metadata.corex.editor ).toBe( 'in-place' );
		expect( config.save().type ).toBe( InnerBlocksContent );
	} );

	test( 'declares the anchor and a margin, as every block a person can insert does', () => {
		expect( metadata.supports.anchor ).toBe( true );
		expect( metadata.supports.spacing.margin ).toEqual( [
			'top',
			'bottom',
		] );
	} );

	test( 'the question is typed in place, in the summary', () => {
		const { element, setAttributes } = draw();
		const [ summary ] = collect( element, byType( 'summary' ) );
		const [ question ] = collect( summary, byType( RichTextMock ) );

		expect( summary.props.className ).toBe( 'corex-accordion__summary' );
		expect( question.props ).toMatchObject( {
			tagName: 'span',
			className: 'corex-accordion__question',
			value: 'What is CoreX?',
			placeholder: 'Question',
		} );
		question.props.onChange( 'Why CoreX?' );
		expect( setAttributes ).toHaveBeenCalledWith( {
			question: 'Why CoreX?',
		} );
	} );

	test( "FR-032: the question is a heading of the accordion's level", () => {
		const { element } = draw( {
			context: { 'corex/accordionHeadingLevel': 3 },
		} );

		expect(
			collect( element, byType( RichTextMock ) )[ 0 ].props.tagName
		).toBe( 'h3' );
		expect(
			collect(
				draw( { context: { 'corex/accordionHeadingLevel': 9 } } )
					.element,
				byType( RichTextMock )
			)[ 0 ].props.tagName
		).toBe( 'span' );
		expect( metadata.usesContext ).toContain(
			'corex/accordionHeadingLevel'
		);
	} );

	test( 'is drawn open, and pressing the question does not close it', () => {
		const { element } = draw();
		const [ details ] = collect( element, byType( 'details' ) );
		const [ summary ] = collect( element, byType( 'summary' ) );
		const event = { preventDefault: jest.fn() };

		expect( details.props.className ).toBe( 'corex-accordion__item' );
		expect( details.props.open ).toBe( true );
		summary.props.onClick( event );
		summary.props.onKeyUp( event );
		expect( event.preventDefault ).toHaveBeenCalledTimes( 2 );
		// Not a stop of its own for the keyboard: Tab goes to the question's text field.
		expect( summary.props.tabIndex ).toBe( -1 );
	} );

	test( 'the answer holds any blocks and starts with a paragraph', () => {
		innerBlocksOptions.length = 0;
		const { element } = draw();
		const [ answer ] = collect(
			element,
			( node ) => node.props && node.props[ 'data-holds' ] === 'blocks'
		);

		expect( answer.type ).toBe( 'div' );
		expect( answer.props.className ).toBe( 'corex-accordion__content' );
		expect( innerBlocksOptions[ 0 ].allowedBlocks ).toBeUndefined();
		expect( innerBlocksOptions[ 0 ].template ).toEqual( [
			[ 'core/paragraph' ],
		] );
	} );

	test( "FR-032: the kit's chevron follows the question, hidden from assistive technology", () => {
		const [ summary ] = collect( draw().element, byType( 'summary' ) );
		const [ icon ] = collect( summary, byType( 'svg' ) );

		expect( icon.props ).toMatchObject( {
			className: 'corex-accordion__icon',
			'aria-hidden': 'true',
			focusable: 'false',
		} );
	} );
} );
