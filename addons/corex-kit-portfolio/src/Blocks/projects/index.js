/**
 * Corex Projects — editor registration for a DYNAMIC block. The grid is produced by
 * the PHP renderer (ProjectsRenderer); the editor previews it via <ServerSideRender>
 * so the preview matches the front end exactly.
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps();

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Projects', 'corex' ) }>
						<RangeControl
							__nextHasNoMarginBottom
							label={ __( 'Number of projects', 'corex' ) }
							min={ 1 }
							max={ 24 }
							value={ attributes.count }
							onChange={ ( count ) => setAttributes( { count } ) }
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
