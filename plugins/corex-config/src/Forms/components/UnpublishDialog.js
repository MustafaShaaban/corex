/**
 * The question asked before a flow is unpublished (spec 122, US-FLW-01, FR-006): the form leaves
 * every page that shows it.
 */
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import CorexDialog from '../../admin/components/CorexDialog.js';
import { workingProps } from '../../admin/components/working.js';

/**
 * @param {Object}     props
 * @param {boolean}    props.working   Whether the unpublish asked for is on its way.
 * @param {() => void} props.onConfirm Unpublish.
 * @param {() => void} props.onCancel  Keep it published.
 * @return {import('react').ReactElement} The question.
 */
export function UnpublishDialog( { working, onConfirm, onCancel } ) {
	const keep = useRef( null );

	// The answer that changes nothing has the focus.
	useEffect( () => {
		keep.current.focus();
	}, [] );

	return (
		<CorexDialog
			title={ __( 'Unpublish this flow?', 'corex' ) }
			onClose={ onCancel }
			busy={ working }
			footer={
				<div className="corex-flow-editor__actions">
					<button
						ref={ keep }
						type="button"
						className="button"
						disabled={ working }
						onClick={ onCancel }
					>
						{ __( 'Cancel', 'corex' ) }
					</button>
					<button
						type="button"
						className="button button-primary"
						onClick={ onConfirm }
						{ ...workingProps( working ) }
					>
						{ __( 'Unpublish', 'corex' ) }
					</button>
				</div>
			}
		>
			<p>
				{ __(
					'The form will disappear from every page that shows it, until you publish it again. Visitors who send it in the meantime are told it is not available.',
					'corex'
				) }
			</p>
		</CorexDialog>
	);
}
