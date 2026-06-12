/**
 * Corex Form — editor registration for a DYNAMIC block. You PICK the form from a dropdown
 * of registered forms (spec 029) — no more typing a slug. The list comes from the cap-gated
 * REST route `corex/v1/forms`. The form markup is rendered server-side from the chosen
 * form's schema (FormBlockRenderer), previewed via <ServerSideRender>.
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps();
		const [ forms, setForms ] = useState( null ); // null = loading

		useEffect( () => {
			apiFetch( { path: '/corex/v1/forms' } )
				.then( ( list ) => setForms( Array.isArray( list ) ? list : [] ) )
				.catch( () => setForms( [] ) );
		}, [] );

		const options = forms
			? forms.map( ( f ) => ( { label: f.label, value: f.slug } ) )
			: [];

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Form', 'corex' ) }>
						{ forms !== null && forms.length === 0 ? (
							<p>{ __( 'No forms found.', 'corex' ) }</p>
						) : (
							<SelectControl
								__nextHasNoMarginBottom
								label={ __( 'Form', 'corex' ) }
								value={ attributes.formSlug || '' }
								options={ [
									{ label: __( 'Select a form…', 'corex' ), value: '' },
									...options,
								] }
								onChange={ ( formSlug ) => setAttributes( { formSlug } ) }
							/>
						) }
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block={ metadata.name } attributes={ attributes } />
			</div>
		);
	},
	save: () => null,
} );
