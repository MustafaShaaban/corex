/**
 * Social Share — front-end progressive enhancement.
 *
 * The server already rendered working share links (real hrefs, no JS required). This
 * script only enhances: it reveals and wires the copy-link button (Clipboard API) and
 * the native-share button (Web Share API) when the browser supports them. It adds no
 * tracking, loads no third-party code, and never fabricates share counts. Exported for
 * unit testing; auto-initialises on the front end.
 */

const COPY_SELECTOR = '[data-corex-share-copy]';
const NATIVE_SELECTOR = '[data-corex-share-native]';
const COPIED_CLASS = 'is-copied';
const COPIED_RESET_MS = 2000;

/**
 * Enhance one share bar. `nav` is injected (navigator) so the behaviour is testable
 * without a real browser.
 *
 * @param {HTMLElement} root A `.corex-social-share` element.
 * @param {Navigator}   nav  The navigator providing clipboard/share (defaults to window.navigator).
 */
export function initSocialShare(
	root,
	nav = typeof navigator !== 'undefined' ? navigator : {}
) {
	if ( ! root ) {
		return;
	}

	const copyBtn = root.querySelector( COPY_SELECTOR );
	if (
		copyBtn &&
		nav.clipboard &&
		typeof nav.clipboard.writeText === 'function'
	) {
		copyBtn.hidden = false;
		copyBtn.addEventListener( 'click', async () => {
			try {
				await nav.clipboard.writeText(
					copyBtn.getAttribute( 'data-corex-share-copy' ) || ''
				);
				copyBtn.classList.add( COPIED_CLASS );
				window.setTimeout(
					() => copyBtn.classList.remove( COPIED_CLASS ),
					COPIED_RESET_MS
				);
			} catch {
				// Clipboard denied/unavailable — leave the working share links as the fallback.
			}
		} );
	}

	const nativeBtn = root.querySelector( NATIVE_SELECTOR );
	if ( nativeBtn && typeof nav.share === 'function' ) {
		nativeBtn.hidden = false;
		nativeBtn.addEventListener( 'click', () => {
			const copySource = root.querySelector( COPY_SELECTOR );
			const url = copySource
				? copySource.getAttribute( 'data-corex-share-copy' ) || ''
				: '';
			nav.share( { url } ).catch( () => {
				// User dismissed the share sheet, or it is unavailable — no action needed.
			} );
		} );
	}
}

/**
 * Enhance every share bar in the document.
 *
 * @param {Document} doc
 */
export function initAll(
	doc = typeof document !== 'undefined' ? document : null
) {
	if ( ! doc ) {
		return;
	}
	doc.querySelectorAll( '.corex-social-share' ).forEach( ( root ) =>
		initSocialShare( root )
	);
}

if ( typeof document !== 'undefined' ) {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => initAll() );
	} else {
		initAll();
	}
}
