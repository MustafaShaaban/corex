import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import { flowOptions, normalizePublishedFlows } from '../flowBlockEditor.js';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps();
		const [ choices, setChoices ] = useState( null );
		const source = attributes.source || 'flow';

		useEffect( () => {
			const path = source === 'flow' ? '/corex/v1/flows?state=published' : '/corex/v1/forms';
			apiFetch( { path } ).then( ( response ) => {
				setChoices( source === 'flow' ? normalizePublishedFlows( response ) : ( Array.isArray( response ) ? response : [] ) );
			} ).catch( () => setChoices( [] ) );
		}, [ source ] );

		const options = source === 'flow'
			? flowOptions( choices || [] )
			: [ { label: __( 'Select a registered form…', 'corex' ), value: '' }, ...( choices || [] ).map( ( form ) => ( { label: form.label, value: form.slug } ) ) ];
		const value = source === 'flow' ? String( attributes.flowId || 0 ) : attributes.formSlug || '';

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Form', 'corex' ) }>
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Source', 'corex' ) }
							value={ source }
							options={ [ { label: __( 'Published flow', 'corex' ), value: 'flow' }, { label: __( 'Registered form', 'corex' ), value: 'registered' } ] }
							onChange={ ( nextSource ) => setAttributes( { source: nextSource, flowId: 0, formSlug: '' } ) }
						/>
						<SelectControl
							__nextHasNoMarginBottom
							label={ source === 'flow' ? __( 'Flow', 'corex' ) : __( 'Form', 'corex' ) }
							value={ value }
							options={ options }
							disabled={ choices === null }
							onChange={ ( selected ) => setAttributes( source === 'flow' ? { flowId: Number( selected ) } : { formSlug: selected } ) }
						/>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block={ metadata.name } attributes={ attributes } />
			</div>
		);
	},
	save: () => null,
} );
