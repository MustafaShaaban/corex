/**
 * The question asked before the flow editor does something its unsaved changes would be lost
 * to (spec 115, US-FLW-02, FR-023 and FR-027).
 *
 * Leaving can drop the changes, so it offers Discard. Publishing, unpublishing, closing and
 * testing act on the saved version, so for them the choice is to save first or to stay.
 */
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import CorexDialog from '../../admin/components/CorexDialog.js';
import { workingProps } from '../../admin/components/working.js';

/**
 * @param {Object}     props
 * @param {boolean}    props.allowDiscard Whether leaving without saving is one of the answers.
 * @param {boolean}    props.saving       Whether the save asked for is on its way.
 * @param {() => void} props.onSave       Save, then go on.
 * @param {() => void} [props.onDiscard]  Go on without saving.
 * @param {() => void} props.onCancel     Stay, with the changes as they are.
 * @return {import('react').ReactElement} The question.
 */
export function UnsavedChangesDialog( {
	allowDiscard,
	saving,
	onSave,
	onDiscard,
	onCancel,
} ) {
	const save = useRef( null );

	// The answer that loses nothing has the focus. The browser has already given it to the
	// dialog's first control, its close button, when the dialog opened.
	useEffect( () => {
		save.current.focus();
	}, [] );

	return (
		<CorexDialog
			title={ __( 'Save your changes before leaving?', 'corex' ) }
			onClose={ onCancel }
			busy={ saving }
			footer={
				<div className="corex-flow-editor__actions">
					<button
						type="button"
						className="button"
						disabled={ saving }
						onClick={ onCancel }
					>
						{ __( 'Cancel', 'corex' ) }
					</button>
					{ allowDiscard ? (
						<button
							type="button"
							className="button"
							disabled={ saving }
							onClick={ onDiscard }
						>
							{ __( 'Discard', 'corex' ) }
						</button>
					) : null }
					<button
						ref={ save }
						type="button"
						className="button button-primary"
						onClick={ onSave }
						{ ...workingProps( saving ) }
					>
						{ __( 'Save', 'corex' ) }
					</button>
				</div>
			}
		>
			<p>
				{ allowDiscard
					? __(
							'This flow has changes that are not saved. Save them, discard them, or cancel to keep editing.',
							'corex'
						)
					: __(
							'This flow has changes that are not saved. Save them first, so this uses what is on screen, or cancel to keep editing.',
							'corex'
						) }
			</p>
		</CorexDialog>
	);
}
