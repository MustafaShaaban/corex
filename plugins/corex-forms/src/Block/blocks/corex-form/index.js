/**
 * Corex Form: a records block (spec 029; spec 136, FR-019, FR-020; DECISIONS #673).
 *
 * The block names one form, a published flow or a registered form, whose fields are edited in the
 * form builder or in code. With none chosen the canvas asks which, in WordPress's own Placeholder
 * and selects, and the page prints nothing. With one chosen the editor previews the server render
 * (FormBlockRenderer) inside the block's wrapper, inert: the preview cannot be clicked or sent.
 *
 * A block inserted from the inserter starts with no form (its default variation empties
 * `formSlug`). A block saved before keeps block.json's defaults, so one saved with no form named
 * still shows the contact form, on the page and here. Only this editor decides what is asked; the
 * front end markup is the renderer's and does not change.
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	Disabled,
	Notice,
	PanelBody,
	Placeholder,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import { flowOptions, normalizePublishedFlows } from '../flowBlockEditor.js';

const LABEL = __( 'Form', 'corex' );

/** The preview's states, made once so the same components are used on every render. */
const STATES = {
	// While a later preview loads, the last one stays in place.
	LoadingResponsePlaceholder: ( { children } ) =>
		children || (
			<Placeholder
				label={ LABEL }
				instructions={ __( 'Loading the form…', 'corex' ) }
			>
				<Spinner />
			</Placeholder>
		),
	EmptyResponsePlaceholder: () => (
		<Placeholder
			label={ LABEL }
			instructions={ __(
				'This form is not available. Choose another in the block settings.',
				'corex'
			) }
		/>
	),
	ErrorResponsePlaceholder: () => (
		<Notice status="warning" isDismissible={ false }>
			{ __( 'The preview did not load.', 'corex' ) }
		</Notice>
	),
};

/**
 * Whether the block names a form the renderer can look for: a flow by id or slug, or a
 * registered form by slug. The renderer prints nothing for a block that names none.
 *
 * @param {Object} attributes          The block's attributes.
 * @param {number} attributes.flowId   The flow's id; 0 for none.
 * @param {string} attributes.flowSlug The flow's slug; '' for none.
 * @param {string} attributes.formSlug The registered form's slug; '' for none.
 * @return {boolean} True when a form is chosen.
 */
function hasForm( { flowId, flowSlug, formSlug } ) {
	return (
		Number( flowId || 0 ) > 0 ||
		String( flowSlug || '' ).trim() !== '' ||
		String( formSlug || '' ).trim() !== ''
	);
}

/**
 * The forms the block can name from its source, fetched once per source.
 *
 * @param {string} source `flow` or `registered`.
 * @return {Array|null} The flows or forms; null while they load.
 */
function useChoices( source ) {
	const [ choices, setChoices ] = useState( null );

	useEffect( () => {
		const path =
			source === 'flow'
				? '/corex/v1/flows?state=published'
				: '/corex/v1/forms';
		apiFetch( { path } )
			.then( ( response ) => {
				if ( source === 'flow' ) {
					setChoices( normalizePublishedFlows( response ) );
					return;
				}
				setChoices( Array.isArray( response ) ? response : [] );
			} )
			.catch( () => setChoices( [] ) );
	}, [ source ] );

	return choices;
}

/**
 * The two selects that choose the form: its source, then the flow or the registered form. Drawn
 * in the side panel always, and on the canvas while no form is chosen.
 *
 * @param {Object}                       props
 * @param {Object}                       props.attributes    The block's attributes.
 * @param {(attributes: Object) => void} props.setAttributes Sets them.
 * @param {Array|null}                   props.choices       What the source offers; null while it loads.
 * @return {Element} The selects; the second waits for the choices.
 */
function formFields( { attributes, setAttributes, choices } ) {
	const source = attributes.source || 'flow';
	const options =
		source === 'flow'
			? flowOptions( choices || [] )
			: [
					{
						label: __( 'Select a registered form…', 'corex' ),
						value: '',
					},
					...( choices || [] ).map( ( form ) => ( {
						label: form.label,
						value: form.slug,
					} ) ),
				];
	const value =
		source === 'flow'
			? String( attributes.flowId || 0 )
			: attributes.formSlug || '';

	return (
		<>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Source', 'corex' ) }
				value={ source }
				options={ [
					{
						label: __( 'Published flow', 'corex' ),
						value: 'flow',
					},
					{
						label: __( 'Registered form', 'corex' ),
						value: 'registered',
					},
				] }
				onChange={ ( nextSource ) =>
					setAttributes( {
						source: nextSource,
						flowId: 0,
						formSlug: '',
					} )
				}
			/>
			{ choices === null ? (
				<Spinner />
			) : (
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={
						source === 'flow'
							? __( 'Flow', 'corex' )
							: __( 'Form', 'corex' )
					}
					value={ value }
					options={ options }
					onChange={ ( selected ) =>
						setAttributes(
							source === 'flow'
								? { flowId: Number( selected ) }
								: { formSlug: selected }
						)
					}
				/>
			) }
		</>
	);
}

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const source = attributes.source || 'flow';
	const choices = useChoices( source );
	const fields = formFields( { attributes, setAttributes, choices } );

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ LABEL }>{ fields }</PanelBody>
			</InspectorControls>
			{ hasForm( attributes ) ? (
				<Disabled>
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
						{ ...STATES }
					/>
				</Disabled>
			) : (
				<Placeholder
					icon={ metadata.icon }
					label={ LABEL }
					instructions={ __(
						'Choose the form this block shows.',
						'corex'
					) }
				>
					{ fields }
					{ choices && choices.length === 0 && (
						<Notice status="info" isDismissible={ false }>
							{ source === 'flow'
								? __( 'No published forms yet.', 'corex' )
								: __( 'No registered forms yet.', 'corex' ) }
						</Notice>
					) }
				</Placeholder>
			) }
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
	// Inserted from the inserter, the block starts with no form, so the canvas asks for one.
	variations: [
		{
			name: 'corex-form',
			title: __( 'Corex Form', 'corex' ),
			description: __(
				'Renders a registered Corex form (server-rendered, secured submit).',
				'corex'
			),
			icon: metadata.icon,
			isDefault: true,
			scope: [ 'inserter' ],
			attributes: { formSlug: '' },
		},
	],
} );
