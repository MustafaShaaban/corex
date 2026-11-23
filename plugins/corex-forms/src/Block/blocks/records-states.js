/**
 * The states a records block's preview is drawn in (spec 136, FR-019): what ServerSideRender
 * shows while the server draws the block, when it draws nothing, and when the request fails.
 * Each is WordPress's own component as WordPress draws it, so CoreX draws nothing new in the
 * editor: the first load is a Placeholder holding a Spinner, a later load is WordPress's own
 * (the last preview, dimmed under a Spinner after a second), empty is a Placeholder, and a
 * failure is a Notice. Each state says in words what it is.
 *
 * Call it once, at the top of a block's editor, so the same components are used on every render.
 *
 * The careers and forms packages build on their own and hold copies of this file
 * (`addons/corex-careers/blocks/shared/records-states.js`,
 * `plugins/corex-forms/src/Block/blocks/records-states.js`); `tests/block-editor-records.test.js`
 * fails when a copy differs from this one.
 */
import { Notice, Placeholder, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/** How long a later load shows the last preview as it is, before dimming it: WordPress's own. */
const DIM_AFTER = 1000;

/**
 * A later load, drawn as WordPress's ServerSideRender draws it by default: the last preview stays,
 * and after a second it dims under a Spinner.
 *
 * @param {Object}  props
 * @param {Element} props.children The last preview.
 * @return {Element} The preview, with the Spinner once a second has passed.
 */
function Reloading( { children } ) {
	const [ showLoader, setShowLoader ] = useState( false );

	useEffect( () => {
		const timeout = setTimeout( () => setShowLoader( true ), DIM_AFTER );

		return () => clearTimeout( timeout );
	}, [] );

	return (
		<div style={ { position: 'relative' } }>
			{ showLoader && (
				// Centred over the preview, as WordPress centres its 18px Spinner.
				<div
					style={ {
						position: 'absolute',
						insetBlockStart: '50%',
						insetInlineStart: '50%',
						marginBlockStart: '-9px',
						marginInlineStart: '-9px',
					} }
				>
					<Spinner />
				</div>
			) }
			<div style={ { opacity: showLoader ? '0.3' : '1' } }>
				{ children }
			</div>
		</div>
	);
}

/**
 * @param {Object} words
 * @param {string} words.label   The block's name, the Placeholder's heading.
 * @param {string} words.loading What is drawn while the preview loads the first time.
 * @param {string} words.empty   What is drawn when the server draws nothing.
 * @return {Object} ServerSideRender's LoadingResponsePlaceholder, EmptyResponsePlaceholder and
 *                  ErrorResponsePlaceholder.
 */
export function recordsStates( { label, loading, empty } ) {
	return {
		LoadingResponsePlaceholder: ( { children } ) =>
			children ? (
				<Reloading>{ children }</Reloading>
			) : (
				<Placeholder label={ label } instructions={ loading }>
					<Spinner />
				</Placeholder>
			),
		EmptyResponsePlaceholder: () => (
			<Placeholder label={ label } instructions={ empty } />
		),
		// WordPress passes why the request failed; it follows the plain sentence.
		ErrorResponsePlaceholder: ( { message } ) => (
			<Notice status="warning" isDismissible={ false }>
				{ __( 'The preview did not load.', 'corex' ) }
				{ message ? ` ${ message }` : '' }
			</Notice>
		),
	};
}
