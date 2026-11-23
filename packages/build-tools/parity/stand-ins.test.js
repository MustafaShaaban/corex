/**
 * The shared stand-ins for the `@wordpress/*` packages a block's editor imports, and the render of
 * a block's `edit` to markup (spec 136, FR-011; DECISIONS #671). A stand-in draws what the real
 * component draws that matters to the parity tree: a text field as its tag and class, inner
 * blocks as the element that holds them, the side panel and the toolbar as nothing.
 */
const { installStandIns, registeredBlock } = require( './stand-ins' );
const { renderEdit } = require( './render' );

installStandIns( jest );

// eslint-disable-next-line import/no-extraneous-dependencies
const { createElement: h } = require( 'react' );
const { registerBlockType } = require( '@wordpress/blocks' );
const {
	useBlockProps,
	RichText,
	InspectorControls,
	BlockControls,
	MediaUpload,
	MediaUploadCheck,
	URLInputButton,
	InnerBlocks,
	useInnerBlocksProps,
} = require( '@wordpress/block-editor' );
const { Button, PanelBody } = require( '@wordpress/components' );
const { __, sprintf } = require( '@wordpress/i18n' );
const { useSelect } = require( '@wordpress/data' );
const { store: coreStore } = require( '@wordpress/core-data' );

describe( 'FR-011: the shared stand-ins', () => {
	test( 'registerBlockType records the settings, so a test can take edit', () => {
		const edit = () => null;
		registerBlockType( 'corex/probe', { edit, save: () => null } );

		expect( registeredBlock( 'corex/probe' ).edit ).toBe( edit );
		expect( registeredBlock( 'corex/unknown' ) ).toBeUndefined();
	} );

	test( 'useBlockProps returns its className; RichText draws its tagName with its className and value', () => {
		const Edit = ( { attributes } ) =>
			h(
				'section',
				useBlockProps( { className: 'corex-probe' } ),
				h( RichText, {
					tagName: 'h2',
					className: 'corex-probe__title',
					value: attributes.title,
					placeholder: 'Headline',
					onChange: () => {},
				} )
			);

		expect( renderEdit( Edit, { attributes: { title: 'Hi' } } ) ).toBe(
			'<section class="corex-probe"><h2 class="corex-probe__title">Hi</h2></section>'
		);
	} );

	test( 'the side panel, the toolbar, the media picker and the link field draw nothing', () => {
		const edit = () =>
			h(
				'div',
				{ className: 'corex-probe' },
				h(
					InspectorControls,
					null,
					h(
						PanelBody,
						null,
						h( 'p', { className: 'corex-probe__x' } )
					)
				),
				h( BlockControls, null, h( 'p', null, 'tool' ) ),
				h(
					MediaUploadCheck,
					null,
					h( MediaUpload, {
						render: () =>
							h( 'img', { className: 'corex-probe__y' } ),
					} )
				),
				h( URLInputButton, { url: 'https://example.test' } )
			);

		expect( renderEdit( edit ) ).toBe( '<div class="corex-probe"></div>' );
	} );

	test( 'Button draws a button that keeps its class and its editor-only mark, and drops the rest', () => {
		const edit = () =>
			h(
				Button,
				{
					variant: 'link',
					isDestructive: true,
					onClick: () => {},
					'data-corex-editor-only': true,
					className: 'probe-remove',
				},
				'Remove'
			);

		expect( renderEdit( edit ) ).toBe(
			'<button type="button" data-corex-editor-only="true" class="probe-remove">Remove</button>'
		);
	} );

	test( 'inner blocks are drawn as the element that holds them', () => {
		const Holder = () =>
			h(
				'div',
				useInnerBlocksProps(
					{ className: 'corex-probe__panel' },
					{ allowedBlocks: [ 'core/paragraph' ] }
				)
			);
		const edit = () =>
			h(
				'div',
				{ className: 'corex-probe' },
				h( Holder ),
				h( InnerBlocks )
			);

		expect( renderEdit( edit ) ).toBe(
			'<div class="corex-probe"><div class="corex-probe__panel"></div><div class="block-editor-inner-blocks"></div></div>'
		);
	} );

	test( 'renderEdit fills the attributes from the block.json defaults first', () => {
		const edit = ( { attributes } ) =>
			h( 'p', null, `${ attributes.level }-${ attributes.title }` );

		expect(
			renderEdit( edit, {
				metadata: {
					attributes: {
						level: { type: 'number', default: 2 },
						title: { type: 'string' },
					},
				},
				attributes: { title: 'T' },
			} )
		).toBe( '<p>2-T</p>' );
	} );

	test( 'the translation functions return the text they were given', () => {
		expect( __( 'Headline', 'corex' ) ).toBe( 'Headline' );
		expect( sprintf( '%1$s of %2$d', 'One', 3 ) ).toBe( 'One of 3' );
	} );

	test( 'a store read through useSelect finds no record, as before the editor has loaded it', () => {
		const Edit = () =>
			h(
				'p',
				null,
				useSelect(
					( select ) =>
						select( coreStore ).getEntityRecord(
							'root',
							'__unstableBase'
						)?.name ?? 'none',
					[]
				)
			);

		expect( coreStore ).toEqual( { name: 'core' } );
		expect( renderEdit( Edit ) ).toBe( '<p>none</p>' );
	} );
} );
