/**
 * What the editor is told under the Title field of a `corex/modal` or `corex/drawer`.
 *
 * A dialog is announced by its name. With a title that is the title. With none the renderer names
 * it by the label of its trigger, and a trigger nobody labelled reads "Open": a name that says
 * what the button does and nothing of what the dialog holds. A title is not required, because a
 * dialog can be meant to show none, so the field says what will be announced without one
 * (DECISIONS #316).
 */
import { __ } from '@wordpress/i18n';

/**
 * The help for the Title field.
 *
 * @param {Object} attributes              The block's attributes.
 * @param {string} attributes.title        Its title.
 * @param {string} attributes.triggerLabel The label of its trigger.
 * @return {string|undefined} What to say under the field. Nothing while the block has a title.
 */
export function titleHelp( { title, triggerLabel } ) {
	if ( title.trim() !== '' ) {
		return undefined;
	}

	if ( triggerLabel.trim() !== '' ) {
		return __(
			'Without a title, a screen reader names this dialog by its trigger label.',
			'corex'
		);
	}

	return __(
		'Without a title or a trigger label, a screen reader names this dialog “Open”, which says nothing of what it holds. Give it a title.',
		'corex'
	);
}
