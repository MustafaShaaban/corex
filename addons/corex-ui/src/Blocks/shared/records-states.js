/**
 * The states a records block's preview is drawn in (spec 136, FR-019): what ServerSideRender
 * shows while the server draws the block, when it draws nothing, and when the request fails.
 * Each is WordPress's own component as WordPress draws it, so CoreX draws nothing new in the
 * editor: loading is a Placeholder holding a Spinner, empty is a Placeholder, and a failure is a
 * Notice. Each state says in words what it is.
 *
 * Call it once, at the top of a block's editor, so the same components are used on every render.
 */
import { Notice, Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * @param {Object} words
 * @param {string} words.label   The block's name, the Placeholder's heading.
 * @param {string} words.loading What is drawn while the preview loads.
 * @param {string} words.empty   What is drawn when the server draws nothing.
 * @return {Object} ServerSideRender's LoadingResponsePlaceholder, EmptyResponsePlaceholder and
 *                  ErrorResponsePlaceholder.
 */
export function recordsStates( { label, loading, empty } ) {
	return {
		// While a later preview loads, the last one stays in place.
		LoadingResponsePlaceholder: ( { children } ) =>
			children || (
				<Placeholder label={ label } instructions={ loading }>
					<Spinner />
				</Placeholder>
			),
		EmptyResponsePlaceholder: () => (
			<Placeholder label={ label } instructions={ empty } />
		),
		ErrorResponsePlaceholder: () => (
			<Notice status="warning" isDismissible={ false }>
				{ __( 'The preview did not load.', 'corex' ) }
			</Notice>
		),
	};
}
