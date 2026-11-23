/**
 * Corex Jobs: a records block (spec 136, FR-019). The open positions it lists are not its own, so
 * the editor previews the server render (JobsRenderer) inside the block's wrapper, inert: a click
 * in the preview does nothing. While it loads, and when the server draws nothing, it says so in
 * WordPress's own Placeholder; a preview that failed is WordPress's Notice.
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	Disabled,
	Notice,
	PanelBody,
	Placeholder,
	RangeControl,
	Spinner,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

const LABEL = __( 'Jobs', 'corex' );

/** The preview's states, made once so the same components are used on every render. */
const STATES = {
	// While a later preview loads, the last one stays in place.
	LoadingResponsePlaceholder: ( { children } ) =>
		children || (
			<Placeholder
				label={ LABEL }
				instructions={ __( 'Loading open positions…', 'corex' ) }
			>
				<Spinner />
			</Placeholder>
		),
	EmptyResponsePlaceholder: () => (
		<Placeholder
			label={ LABEL }
			instructions={ __( 'No open positions to show.', 'corex' ) }
		/>
	),
	ErrorResponsePlaceholder: () => (
		<Notice status="warning" isDismissible={ false }>
			{ __( 'The preview did not load.', 'corex' ) }
		</Notice>
	),
};

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
