/**
 * Entity Field — editor registration.
 *
 * The block is DYNAMIC: its markup is produced server-side by the PHP
 * `corex.renderer` (EntityFieldRenderer). The editor only needs to (a) know the
 * block type exists so it stops reporting "not supported", and (b) preview the
 * server render. We use <ServerSideRender> so the editor preview is byte-for-byte
 * the front-end output — never a duplicated JS implementation.
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
					<PanelBody title={ __( 'Field', 'corex' ) }>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Value', 'corex' ) }
							value={ attributes.value || '' }
							onChange={ ( value ) => setAttributes( { value } ) }
							help={ __(
								'Static value, or leave empty when bound to an entity field via Block Bindings.',
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
	// Dynamic block: nothing is saved to post content; the server renders it.
	save: () => null,
} );
