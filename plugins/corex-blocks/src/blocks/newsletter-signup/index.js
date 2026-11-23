/**
 * Newsletter Signup: a dynamic block drawn in place (spec 063; spec 136, FR-018).
 *
 * The editor draws the form NewsletterSignupRenderer prints, element for element, and the heading,
 * the button label and the consent text are typed where they stand. An empty field shows, greyed,
 * the words the page prints in its place. The three are plain text, as the renderer escapes them:
 * what is typed is kept as typed, with no formatting. The button label is typed in a text field
 * inside the page's button, since a browser types into no button that is itself editable.
 *
 * The form never submits in the editor: its button is a plain button, a submit is stopped, and a
 * click on the consent text places the cursor instead of ticking the box. `parity.json` is the
 * tree both must draw. The renderer draws it on the server, wired to the subscribe route
 * (save: () => null).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

const stop = ( event ) => event.preventDefault();

/**
 * @param {string} text A plain text.
 * @return {string} It as RichText holds it: `&` and `<` escaped, as WordPress escapes editable text.
 */
const toEditable = ( text ) =>
	text.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' );

/**
 * @param {string} html What RichText holds, with formats and line breaks turned off.
 * @return {string} Its plain text: entities read back as characters, as WordPress decodes them.
 */
function toPlain( html ) {
	const area = document.createElement( 'textarea' );
	area.innerHTML = html;

	return area.value;
}

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: 'corex-newsletter-signup',
	} );
	// A plain-text field typed on the canvas: RichText holds HTML, the attribute holds the text.
	const field = ( name ) => ( {
		identifier: name,
		value: toEditable( attributes[ name ] || '' ),
		onChange: ( value ) => setAttributes( { [ name ]: toPlain( value ) } ),
		allowedFormats: [],
		disableLineBreaks: true,
		withoutInteractiveFormatting: true,
	} );

	return (
		<div { ...blockProps }>
			<form
				className="corex-newsletter-signup__form"
				onSubmit={ stop }
				noValidate
			>
				<RichText
					{ ...field( 'heading' ) }
					tagName="h2"
					className="corex-newsletter-signup__heading"
					placeholder={ __( 'Subscribe to our newsletter', 'corex' ) }
				/>
				<div className="corex-newsletter-signup__row">
					<input
						className="corex-newsletter-signup__email"
						type="email"
						aria-label={ __( 'Email address', 'corex' ) }
						placeholder={ __( 'you@example.com', 'corex' ) }
						readOnly
						tabIndex={ -1 }
					/>
					<button
						type="button"
						className="corex-newsletter-signup__submit"
					>
						<RichText
							{ ...field( 'buttonLabel' ) }
							tagName="span"
							placeholder={ __( 'Subscribe', 'corex' ) }
						/>
					</button>
				</div>
				{ /* The page's label, holding its box. The click only keeps the box from being ticked while its text is typed. */ }
				{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control, jsx-a11y/click-events-have-key-events, jsx-a11y/no-noninteractive-element-interactions */ }
				<label
					className="corex-newsletter-signup__consent"
					onClick={ stop }
				>
					<input type="checkbox" tabIndex={ -1 } />{ ' ' }
					<RichText
						{ ...field( 'consentText' ) }
						tagName="span"
						placeholder={ __(
							'I agree to receive emails and can unsubscribe at any time.',
							'corex'
						) }
					/>
				</label>
				{ /* The trap field against robots, as on the page: drawn hidden. */ }
				<div
					className="corex-newsletter-signup__hp"
					aria-hidden="true"
				/>
				<p className="corex-newsletter-signup__status" />
			</form>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	// Dynamic block: nothing is saved to post content; the server renders it.
	save: () => null,
} );
