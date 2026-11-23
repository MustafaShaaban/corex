/**
 * Corex Jobs: a records block (spec 136, FR-019). The open positions it lists are not its own, so
 * the editor previews the server render (JobsRenderer) inside the block's wrapper, inert: a click
 * in the preview does nothing. While it loads, when the server draws nothing and when the preview
 * fails, it says so in WordPress's own components (`../shared/records-states.js`).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { Disabled, PanelBody, RangeControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import { recordsStates } from '../shared/records-states';

const LABEL = __( 'Jobs', 'corex' );

/** The preview's states, made once so the same components are used on every render. */
const STATES = recordsStates( {
	label: LABEL,
	loading: __( 'Loading open positions…', 'corex' ),
	empty: __( 'No open positions to show.', 'corex' ),
} );

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ LABEL }>
					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Number of positions', 'corex' ) }
						min={ 1 }
						max={ 50 }
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
