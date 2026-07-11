/**
 * Editor registration for corex/drawer (server-rendered). Sidebar controls drive the
 * title / trigger label / slide-from side / content attributes; the preview is the live
 * server render.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';
import './style.scss';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps();

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Drawer', 'corex' ) }>
						<TextControl
							label={ __( 'Title', 'corex' ) }
							value={ attributes.title }
							onChange={ ( title ) => setAttributes( { title } ) }
						/>
						<TextControl
							label={ __( 'Trigger label', 'corex' ) }
							value={ attributes.triggerLabel }
							onChange={ ( triggerLabel ) =>
								setAttributes( { triggerLabel } )
							}
						/>
						<SelectControl
							label={ __( 'Slide from', 'corex' ) }
							value={ attributes.side }
							options={ [
								{ label: __( 'End (right in LTR)', 'corex' ), value: 'end' },
								{ label: __( 'Start (left in LTR)', 'corex' ), value: 'start' },
							] }
							onChange={ ( side ) => setAttributes( { side } ) }
						/>
						<TextareaControl
							label={ __( 'Content', 'corex' ) }
							value={ attributes.content }
							onChange={ ( content ) => setAttributes( { content } ) }
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
