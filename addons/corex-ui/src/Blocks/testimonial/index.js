/**
 * Corex Testimonial — editor registration for a DYNAMIC block. The PHP
 * TestimonialRenderer builds the figure/blockquote/figcaption; the sidebar edits the
 * quote/author/role and the editor previews via <ServerSideRender>.
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps();
		const { quote, author, role } = attributes;

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Testimonial', 'corex' ) }>
						<TextareaControl
							__nextHasNoMarginBottom
							label={ __( 'Quote', 'corex' ) }
							value={ quote }
							onChange={ ( v ) => setAttributes( { quote: v } ) }
						/>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Author', 'corex' ) }
							value={ author }
							onChange={ ( v ) => setAttributes( { author: v } ) }
						/>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Role', 'corex' ) }
							value={ role }
							onChange={ ( v ) => setAttributes( { role: v } ) }
						/>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block={ metadata.name } attributes={ attributes } />
			</div>
		);
	},
	save: () => null,
} );
