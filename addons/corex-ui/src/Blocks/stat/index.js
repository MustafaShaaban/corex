/**
 * Corex Stat — editor registration for a DYNAMIC block. The markup comes from the PHP
 * StatRenderer; the sidebar edits the value/label/description and the editor previews the
 * server render via <ServerSideRender>.
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
		const { value, label, description } = attributes;

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Stat', 'corex' ) }>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Value', 'corex' ) }
							value={ value }
							onChange={ ( v ) => setAttributes( { value: v } ) }
						/>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Label', 'corex' ) }
							value={ label }
							onChange={ ( v ) => setAttributes( { label: v } ) }
						/>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Description', 'corex' ) }
							value={ description }
							onChange={ ( v ) => setAttributes( { description: v } ) }
						/>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block={ metadata.name } attributes={ attributes } />
			</div>
		);
	},
	save: () => null,
} );
