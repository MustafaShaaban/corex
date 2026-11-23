/**
 * Newsletter Signup: a dynamic block drawn in place (spec 063; spec 136, FR-018).
 *
 * The editor draws the form NewsletterSignupRenderer prints, element for element, and the heading,
 * the button label and the consent text are typed where they stand. An empty field shows, greyed,
 * the words the page prints in its place. The three are plain text, as the renderer escapes them:
 * what is typed is kept as typed, with no formatting. The button label is typed in a text field
 * inside the page's button, since a browser types into no button that is itself editable.
 *
 * With the newsletter add-on off, the editor draws the renderer's note that signup is not
 * available, as the page does; the server says which (NewsletterSignupEditor).
 *
 * The form never submits in the editor: its button is a plain button, a submit is stopped, and a
 * click on the consent text places the cursor instead of ticking the box. `parity.json` is the
 * tree both must draw. The renderer draws it on the server, wired to the subscribe route
 * (save: () => null).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { escapeEditableHTML } from '@wordpress/escape-html';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

const stop = ( event ) => event.preventDefault();

/**
 * The plain text of what RichText holds: a paste can bring markup that formats are not, so tags
 * are dropped (a line break becomes a space) and entities read back as characters. The renderer
 * escapes the attribute, so markup kept here would print as text on the page.
 *
 * @param {string} html What RichText holds.
 * @return {string} Its text.
 */
function toPlain( html ) {
	const parsed = new window.DOMParser().parseFromString(
		html.replace( /<br\s*\/?>/gi, ' ' ),
		'text/html'
	);

	return parsed.body.textContent || '';
}

/**
 * Whether the newsletter add-on is on, as the server told the editor
 * (`window.corexNewsletterSignup`, written by NewsletterSignupEditor). With nothing said the form
 * is drawn, as a site with the add-on draws it.
 *
 * @return {boolean} False only when the server said the add-on is off.
 */
const available = () => window.corexNewsletterSignup?.available !== false;

function Edit( { attributes, setAttributes } ) {
	const isAvailable = available();
	const blockProps = useBlockProps( {
		className: isAvailable
			? 'corex-newsletter-signup'
			: 'corex-newsletter-signup corex-newsletter-signup--unavailable',
	} );

	if ( ! isAvailable ) {
		// The renderer's own note, as the page prints it with the add-on off.
		return (
			<div { ...blockProps }>
				<p>
					{ __(
						'Newsletter signup is not available on this site yet.',
						'corex'
					) }
				</p>
			</div>
		);
	}

	// A plain-text field typed on the canvas: RichText holds HTML, the attribute holds the text.
	const field = ( name ) => ( {
		identifier: name,
		value: escapeEditableHTML( attributes[ name ] || '' ),
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
						tabIndex={ -1 }
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
