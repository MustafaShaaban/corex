/**
 * The email a reply will be sent as, shown before it is sent (spec 106, FR-005).
 *
 * What is shown is the document the server built with what builds the sent one, not a drawing
 * of it made here: the two cannot differ. It is shown in a frame that runs no script and
 * cannot reach the admin it stands in (it still loads an image the email names, as a mail
 * client would), at the width of a wide screen or of a phone, with the plain-text version a
 * mail client shows when it shows no HTML.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import CorexDialog from '../../admin/components/CorexDialog.js';

const WIDTHS = [
	{ key: 'wide', label: __( 'Wide screen', 'corex' ) },
	{ key: 'narrow', label: __( 'Phone', 'corex' ) },
];

/**
 * @param {Object}   props
 * @param {Object}   props.email   `{ html, text }`, as the preview route answers.
 * @param {string}   props.subject The reply's subject, as it will be sent.
 * @param {string}   props.note    What will happen to the reply when it is sent, when it is not simply delivered; otherwise ''.
 * @param {Function} props.onClose Called when the person leaves the preview.
 * @return {import('react').ReactElement} The preview.
 */
export default function ReplyPreview( { email, subject, note, onClose } ) {
	const [ width, setWidth ] = useState( 'wide' );

	return (
		<CorexDialog
			className="corex-reply-preview"
			title={ __( 'Preview of the reply', 'corex' ) }
			subtitle={ <bdi>{ subject }</bdi> }
			onClose={ onClose }
		>
			{ note && <p className="corex-pane__muted">{ note }</p> }
			<div
				className="corex-reply-preview__widths"
				role="group"
				aria-label={ __( 'Width of the preview', 'corex' ) }
			>
				{ WIDTHS.map( ( choice ) => (
					<button
						key={ choice.key }
						type="button"
						className="button"
						aria-pressed={ width === choice.key ? 'true' : 'false' }
						onClick={ () => setWidth( choice.key ) }
					>
						{ choice.label }
					</button>
				) ) }
			</div>
			<div className="corex-reply-preview__frame" data-width={ width }>
				<iframe
					title={ __( 'The email as it will be sent', 'corex' ) }
					sandbox=""
					srcDoc={ email.html }
				/>
			</div>
			<details className="corex-pane__more">
				<summary>{ __( 'Plain-text version', 'corex' ) }</summary>
				<pre className="corex-reply-preview__text" dir="auto">
					{ email.text }
				</pre>
			</details>
		</CorexDialog>
	);
}
