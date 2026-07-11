import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, TextareaControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

export function normalizePublishedFlows( response ) {
	const flows = Array.isArray( response )
		? response
		: response?.data?.flows || response?.flows || [];
	return flows.filter( ( flow ) => flow.state === 'published' );
}

export function flowOptions( flows ) {
	return [
		{ label: __( 'Select a published flow…', 'corex' ), value: '0' },
		...flows.map( ( flow ) => ( { label: flow.name, value: String( flow.id ) } ) ),
	];
}

export function registerFlowBlock( metadata, variant ) {
	registerBlockType( metadata.name, {
		edit: ( { attributes, setAttributes } ) => {
			const blockProps = useBlockProps();
			const [ flows, setFlows ] = useState( null );

			useEffect( () => {
				apiFetch( { path: '/corex/v1/flows?state=published' } )
					.then( ( response ) => setFlows( normalizePublishedFlows( response ) ) )
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
								onChange={ ( flowId ) => setAttributes( { flowId: Number( flowId ), variant } ) }
							/>
							{ variant !== 'success-message' ? <>
								<TextControl label={ __( 'Heading', 'corex' ) } value={ attributes.heading || '' } onChange={ ( heading ) => setAttributes( { heading } ) } />
								<TextareaControl label={ __( 'Introduction', 'corex' ) } value={ attributes.intro || '' } onChange={ ( intro ) => setAttributes( { intro } ) } />
								<TextControl label={ __( 'Submit label', 'corex' ) } value={ attributes.submitLabel || '' } onChange={ ( submitLabel ) => setAttributes( { submitLabel } ) } />
							</> : null }
							{ flows !== null && flows.length === 0 ? <p>{ __( 'Publish a flow before inserting this block.', 'corex' ) }</p> : null }
						</PanelBody>
					</InspectorControls>
					<ServerSideRender block={ metadata.name } attributes={ { ...attributes, variant } } />
				</div>
			);
		},
		save: () => null,
	} );
}
