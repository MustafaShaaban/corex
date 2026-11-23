/**
 * Corex Posts: a records block (spec 136, FR-019). The posts it lists are not its own, so the
 * editor previews the server render (PostsRenderer) inside the block's wrapper, inert: a click in
 * the preview does nothing. While it loads, and when the server draws nothing, it says so in
 * WordPress's own Placeholder. Registering the type here is what stops the editor reporting
 * "your site doesn't include support for this block".
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { Disabled, PanelBody, RangeControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import { recordsStates } from '../shared/records-states';

const STATES = recordsStates( {
	label: __( 'Posts', 'corex' ),
	loading: __( 'Loading posts…', 'corex' ),
	empty: __( 'No posts to show.', 'corex' ),
} );

function Edit( { attributes, setAttributes } ) {
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
			<Disabled>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
					{ ...STATES }
				/>
			</Disabled>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
