/**
 * A value somebody has to copy, with a button that copies it (spec 112, FR-023): the React form of
 * `StandalonePage::copyField()` (spec 101, FR-011a), for screens drawn in React.
 *
 * The field reads left to right whatever the page's direction: it holds a line of code, a command
 * or an address. It wraps rather than scrolls, so the whole value can be read before it is copied:
 * a read-only text area, sized to its content where the browser can, two lines where it cannot.
 * What the button did is said on the label's line, in a live region, so the field does not move
 * when the answer comes.
 *
 * A browser gives the Clipboard API only to a page served over HTTPS or from the local machine.
 * Elsewhere, and where it refuses, the field is selected and copied the older way; where that
 * fails too, the value is left selected and the field says to copy it from the keyboard.
 */
import { useEffect, useId, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/** How long "copied" is said for, in milliseconds, before a second press can say it again. */
const SAID_FOR = 4000;

/**
 * Select the field and ask the browser to copy the selection.
 *
 * @param {HTMLTextAreaElement} input The field.
 * @return {boolean} Whether the browser says it copied.
 */
function copySelection( input ) {
	input.focus();
	input.select();

	// Where copying is not allowed, one browser answers false and another throws.
	try {
		return document.execCommand( 'copy' ) === true;
	} catch {
		return false;
	}
}

/**
 * Put the field's value on the clipboard.
 *
 * @param {HTMLTextAreaElement} input The field.
 * @return {Promise<boolean>} Whether it is there.
 */
function copy( input ) {
	const clipboard = window.navigator.clipboard;

	if ( clipboard ) {
		return clipboard.writeText( input.value ).then(
			() => true,
			() => copySelection( input )
		);
	}

	return Promise.resolve( copySelection( input ) );
}

/**
 * @param {Object} props       Component props.
 * @param {string} props.label What the value is, shown above the field.
 * @param {string} props.value The value to copy.
 * @return {Element} The field and its button.
 */
export default function CorexCopyField( { label, value } ) {
	const id = useId();
	const input = useRef( null );
	const [ said, setSaid ] = useState( '' );

	useEffect( () => {
		if ( said === '' ) {
			return undefined;
		}
		const timer = setTimeout( () => setSaid( '' ), SAID_FOR );

		return () => clearTimeout( timer );
	}, [ said ] );

	const press = async () => {
		const copied = await copy( input.current );
		setSaid(
			copied
				? __( 'Copied.', 'corex' )
				: __(
						'It could not be copied for you. It is selected: copy it from the keyboard.',
						'corex'
				  )
		);
	};

	return (
		<div className="corex-copy-field">
			<div className="corex-copy-field__head">
				<label className="corex-copy-field__label" htmlFor={ id }>
					{ label }
				</label>
				<p
					className="corex-copy-field__status"
					role="status"
					aria-live="polite"
				>
					{ said }
				</p>
			</div>
			<div className="corex-copy-field__row">
				<textarea
					ref={ input }
					id={ id }
					className="corex-copy-field__value"
					value={ value }
					rows={ 2 }
					readOnly
					dir="ltr"
					spellCheck={ false }
					autoComplete="off"
				/>
				<button type="button" className="button" onClick={ press }>
					{ __( 'Copy', 'corex' ) }
				</button>
			</div>
		</div>
	);
}
