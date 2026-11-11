import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

/**
 * The flows a block can show: every flow that was ever published. A closed, expired or
 * not-yet-open flow is listed too, so a block that shows one still names it (spec 122, US-VIS-01);
 * a draft is not, since it has nothing to show.
 *
 * @param {Object|Array} response The flows route's answer.
 * @return {Array} The flows.
 */
export function normalizePublishedFlows( response ) {
	const flows = Array.isArray( response )
		? response
		: response?.data?.flows || response?.flows || [];
	return flows.filter( ( flow ) => flow.state !== 'draft' );
}

/**
 * Why a listed flow takes no answers, in words: '' for one that does.
 *
 * @param {Object} flow A flow from the flows route.
 * @return {string} Closed, Expired or Scheduled; or ''.
 */
function unavailableLabel( flow ) {
	const status = flow.status?.state || flow.state;
	if ( status === 'closed' ) {
		return __( 'Closed', 'corex' );
	}
	if ( status === 'expired' ) {
		return __( 'Expired', 'corex' );
	}
	if ( status === 'scheduled' ) {
		return __( 'Scheduled', 'corex' );
	}
	return '';
}

export function flowOptions( flows ) {
	return [
		{ label: __( 'Select a published flow…', 'corex' ), value: '0' },
		...flows.map( ( flow ) => {
			const unavailable = unavailableLabel( flow );
			return {
				label: unavailable
					? sprintf(
							/* translators: 1: a form's name, 2: why it takes no answers (Closed, Expired, Scheduled). */
							__( '%1$s (%2$s)', 'corex' ),
							flow.name,
							unavailable
						)
					: flow.name,
				value: String( flow.id ),
			};
		} ),
	];
}

export function registerFlowBlock( metadata, variant ) {
	function Edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();
		const [ flows, setFlows ] = useState( null );

		useEffect( () => {
			apiFetch( { path: '/corex/v1/flows' } )
				.then( ( response ) =>
					setFlows( normalizePublishedFlows( response ) )
				)
				.catch( () => setFlows( [] ) );
		}, [] );

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Published flow', 'corex' ) }>
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Flow', 'corex' ) }
							value={ String( attributes.flowId || 0 ) }
							options={ flowOptions( flows || [] ) }
							disabled={ flows === null }
							onChange={ ( flowId ) =>
								setAttributes( {
									flowId: Number( flowId ),
									variant,
								} )
							}
						/>
						{ variant !== 'success-message' ? (
							<>
								<TextControl
									label={ __( 'Heading', 'corex' ) }
									value={ attributes.heading || '' }
									onChange={ ( heading ) =>
										setAttributes( { heading } )
									}
								/>
								<TextareaControl
									label={ __( 'Introduction', 'corex' ) }
									value={ attributes.intro || '' }
									onChange={ ( intro ) =>
										setAttributes( { intro } )
									}
								/>
								<TextControl
									label={ __( 'Submit label', 'corex' ) }
									value={ attributes.submitLabel || '' }
									onChange={ ( submitLabel ) =>
										setAttributes( { submitLabel } )
									}
								/>
							</>
						) : null }
						{ flows !== null && flows.length === 0 ? (
							<p>
								{ __(
									'Publish a flow before inserting this block.',
									'corex'
								) }
							</p>
						) : null }
					</PanelBody>
				</InspectorControls>
				<ServerSideRender
					block={ metadata.name }
					attributes={ { ...attributes, variant } }
				/>
			</div>
		);
	}

	registerBlockType( metadata.name, {
		edit: Edit,
		save: () => null,
	} );
}
