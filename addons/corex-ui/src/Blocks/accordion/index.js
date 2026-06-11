/**
 * Corex Accordion — editor registration for a DYNAMIC block. The PHP AccordionRenderer
 * builds native <details>/<summary> disclosures (no JS); the sidebar edits the items (one
 * "Title | Content" per line) and the editor previews via <ServerSideRender>.
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextareaControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps();
		const { items } = attributes;

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Accordion', 'corex' ) }>
						<TextareaControl
							__nextHasNoMarginBottom
							label={ __( 'Items (one "Title | Content" per line)', 'corex' ) }
							value={ items }
							onChange={ ( v ) => setAttributes( { items: v } ) }
						/>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block={ metadata.name } attributes={ attributes } />
			</div>
		);
	},
	save: () => null,
} );
