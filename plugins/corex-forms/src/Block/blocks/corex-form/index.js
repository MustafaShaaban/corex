/**
 * Corex Form: a records block (spec 029; spec 136, FR-019, FR-020; DECISIONS #673).
 *
 * The block names one form, a published flow or a registered form, whose fields are edited in the
 * form builder or in code. With none chosen the canvas asks which, in WordPress's own Placeholder
 * and selects, and the page prints nothing. With one chosen the editor previews the server render
 * (FormBlockRenderer) inside the block's wrapper, inert: the preview cannot be clicked or sent.
 * The chooser tells a list that failed, or that the person may not see, from an empty one
 * (`./choices.js`).
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
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import { flowOptions } from '../flowBlockEditor.js';
import { recordsStates } from '../records-states';
import { useChoices } from './choices';

const LABEL = __( 'Form', 'corex' );

/** The preview's states, made once so the same components are used on every render. */
const STATES = recordsStates( {
	label: LABEL,
	loading: __( 'Loading the form…', 'corex' ),
	empty: __(
		'This form is not available. Choose another in the block settings.',
		'corex'
	),
} );

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
 * The source the block's fields show: the one its form comes from. A block saved with block.json's
 * defaults says `flow` and names no flow, yet prints the registered form its `formSlug` names (the
 * contact form), so its fields show that form. Only what is shown changes; no attribute is set.
 *
 * @param {Object} attributes          The block's attributes.
 * @param {string} attributes.source   The saved source.
 * @param {number} attributes.flowId   The flow's id; 0 for none.
 * @param {string} attributes.flowSlug The flow's slug; '' for none.
 * @param {string} attributes.formSlug The registered form's slug; '' for none.
 * @return {string} `flow` or `registered`.
 */
function sourceOf( { source, flowId, flowSlug, formSlug } ) {
	const namesFlow =
		Number( flowId || 0 ) > 0 || String( flowSlug || '' ).trim() !== '';
	if ( ! namesFlow && String( formSlug || '' ).trim() !== '' ) {
		return 'registered';
	}

	return source || 'flow';
}

/**
 * The two selects that choose the form: its source, then the flow or the registered form. Drawn
 * in the side panel always, and on the canvas while no form is chosen.
 *
 * @param {Object}                       props
 * @param {Object}                       props.attributes    The block's attributes.
 * @param {(attributes: Object) => void} props.setAttributes Sets them.
 * @param {Object}                       props.choices       What the source offers, as `useChoices()` gives it.
 * @return {Element} The selects; the second waits for the choices.
 */
function formFields( { attributes, setAttributes, choices } ) {
	const source = sourceOf( attributes );
	const items = choices.status === 'ready' ? choices.items : [];
	const options =
		source === 'flow'
			? flowOptions( items )
			: [
					{
						label: __( 'Select a registered form…', 'corex' ),
						value: '',
					},
					...items.map( ( form ) => ( {
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
					// Every way of naming a form is cleared: the renderer goes to a flow whenever
					// `flowSlug` is set.
					setAttributes( {
						source: nextSource,
						flowId: 0,
						flowSlug: '',
						formSlug: '',
					} )
				}
			/>
			{ choices.status === 'loading' && <Spinner /> }
			{ choices.status === 'ready' && (
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

/**
 * What the chooser says about the list it offers: that there is nothing to choose, that the person
 * may not see the list, or that it failed. Nothing while it loads or offers something.
 *
 * @param {string} source  `flow` or `registered`.
 * @param {Object} choices What the source offers, as `useChoices()` gives it.
 * @return {?Element} A Notice, or nothing.
 */
function choicesNotice( source, choices ) {
	if ( choices.status === 'ready' && choices.items.length === 0 ) {
		return (
			<Notice status="info" isDismissible={ false }>
				{ source === 'flow'
					? __( 'No published forms yet.', 'corex' )
					: __( 'No registered forms yet.', 'corex' ) }
			</Notice>
		);
	}
	if ( choices.status !== 'failed' ) {
		return null;
	}
	if ( choices.forbidden ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'You cannot see the list of forms. Ask someone who manages forms to choose one.',
					'corex'
				) }
			</Notice>
		);
	}

	return (
		<Notice status="warning" isDismissible={ false }>
			{ __( 'The list of forms did not load.', 'corex' ) }
			{ choices.message ? ` ${ choices.message }` : '' }
		</Notice>
	);
}

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const source = sourceOf( attributes );
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
					{ choicesNotice( source, choices ) }
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
			isDefault: true,
			scope: [ 'inserter' ],
			attributes: { formSlug: '' },
		},
	],
} );
