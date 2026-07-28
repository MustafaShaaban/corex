/**
 * FieldValue — one stored value, rendered for an operator (#149 item 2).
 *
 * A careers form records the applicant's CV as a URL on the submission. Before this, the reviewer
 * looking at that submission had a document they could see and not open: every admin surface
 * stringified and stopped, so the link was text to select and paste into the address bar. That was
 * a client report, not a hypothetical.
 *
 * **Only `http(s)` becomes a link, and that restriction is the point.** These values are supplied by
 * visitors. A `javascript:` or `data:` string that became an anchor would be a stored-XSS vector
 * aimed squarely at the person reviewing submissions — the one user on the site with a session
 * worth stealing. Anything that is not an absolute http(s) URL stays inert text.
 *
 * `rel="noopener noreferrer"` for the same reason: a followed link must not reach back into the
 * admin session through `window.opener`, and the admin URL is not a referrer worth leaking.
 */
import { __ } from '@wordpress/i18n';

/**
 * An absolute http(s) URL and nothing else.
 *
 * Anchored at both ends, and no whitespace anywhere: a value like
 * `https://example.test javascript:alert(1)` must not match on its prefix.
 */
const LINKABLE = /^https?:\/\/\S+$/i;

export default function FieldValue( { value } ) {
	const text = value === null || value === undefined ? '' : String( value );

	if ( text.trim() === '' ) {
		return <span className="corex-field-value is-empty">—</span>;
	}

	if ( ! LINKABLE.test( text.trim() ) ) {
		return <span className="corex-field-value">{ text }</span>;
	}

	return (
		<a
			className="corex-field-value corex-field-value--link"
			href={ text.trim() }
			target="_blank"
			rel="noopener noreferrer"
		>
			{ text.trim() }
			<span className="screen-reader-text">
				{ ' ' }
				{ __( '(opens in a new tab)', 'corex' ) }
			</span>
		</a>
	);
}
