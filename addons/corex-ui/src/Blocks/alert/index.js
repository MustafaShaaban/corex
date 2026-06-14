/**
 * Corex Alert — DYNAMIC block (spec 051). The message is an inline RichText region; the
 * variant (info/success/warning/error) is set from the sidebar. Rendered server-side by
 * AlertRenderer (save: () => null).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: `corex-alert corex-alert--${ attributes.variant }` } );

		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Alert', 'corex' ) }>
						<SelectControl
							label={ __( 'Variant', 'corex' ) }
							value={ attributes.variant }
							options={ [
								{ label: __( 'Info', 'corex' ), value: 'info' },
								{ label: __( 'Success', 'corex' ), value: 'success' },
								{ label: __( 'Warning', 'corex' ), value: 'warning' },
								{ label: __( 'Error', 'corex' ), value: 'error' },
							] }
							onChange={ ( variant ) => setAttributes( { variant } ) }
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...blockProps } role="alert">
					<RichText
						tagName="span"
						value={ attributes.message }
						onChange={ ( message ) => setAttributes( { message } ) }
						placeholder={ __( 'Alert message…', 'corex' ) }
					/>
				</div>
			</>
		);
	},
	save: () => null,
} );
