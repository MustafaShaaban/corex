/**
 * Corex Form — editor registration for a DYNAMIC block. The form markup is built
 * server-side from the registered form schema (FormBlockRenderer); the editor
 * previews it via <ServerSideRender>. The front-end submit + shared-schema
 * validation live in view.js (the block's viewScript).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps();

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Form', 'corex' ) }>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Form slug', 'corex' ) }
							value={ attributes.formSlug || '' }
							onChange={ ( formSlug ) => setAttributes( { formSlug } ) }
							help={ __(
								'The slug of a registered Corex form (e.g. "contact").',
								'corex'
							) }
						/>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
				/>
			</div>
		);
	},
	save: () => null,
} );
