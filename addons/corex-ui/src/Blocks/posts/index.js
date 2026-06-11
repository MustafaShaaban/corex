/**
 * Corex Posts — editor registration for a DYNAMIC block. The markup comes from
 * the PHP renderer (PostsRenderer); the editor previews it via <ServerSideRender>
 * so the preview matches the front end exactly. Registering the type here is what
 * stops the editor reporting "your site doesn't include support for this block".
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
					<PanelBody title={ __( 'Posts', 'corex' ) }>
						<RangeControl
							__nextHasNoMarginBottom
							label={ __( 'Number of posts', 'corex' ) }
							min={ 1 }
							max={ 12 }
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
