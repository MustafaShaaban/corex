/**
 * Language switcher: the menu form's behaviour (spec 137, FR-032; `design/handoffs/header-system.md`,
 * step 2). The kit's Dropdown, as a disclosure: the button opens and closes the list of languages,
 * Down and Up open it on the first or the last language and move through them, Home and End go to
 * the ends, Enter follows a language (the link's own), Escape closes it and returns focus to the
 * button, and a click outside or focus leaving closes it.
 *
 * The server prints the menu with its list showing. Where the browser runs no script, the block's
 * style draws that markup as the list form, and this script does nothing where the browser cannot
 * say it runs script, so the list stays usable there too. The list and toggle forms need no script.
 * Exported for unit testing; starts itself on the page.
 */

const MENU = '.corex-language-switcher--menu';
const BUTTON = ':scope > .corex-language-switcher__button';
const LIST = ':scope > .corex-dropdown__menu';

/**
 * Wire one menu.
 *
 * @param {HTMLElement} root A `.corex-language-switcher--menu` element.
 * @return {boolean} Whether it was wired: false when it lacks its button or its list.
 */
export function initLanguageMenu( root ) {
	const button = root.querySelector( BUTTON );
	const list = root.querySelector( LIST );
	if ( ! button || ! list ) {
		return false;
	}

	const doc = root.ownerDocument;
	const links = () => Array.from( list.querySelectorAll( 'a[href]' ) );
	const isOpen = () => button.getAttribute( 'aria-expanded' ) === 'true';

	const setOpen = ( open ) => {
		button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		list.hidden = ! open;
	};

	const close = ( returnFocus ) => {
		setOpen( false );
		if ( returnFocus ) {
			button.focus();
		}
	};

	const focusAt = ( index ) => {
		const all = links();
		if ( all.length > 0 ) {
			all[ ( index + all.length ) % all.length ].focus();
		}
	};

	setOpen( false );

	button.addEventListener( 'click', () => setOpen( ! isOpen() ) );

	button.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'ArrowDown' || event.key === 'ArrowUp' ) {
			event.preventDefault();
			setOpen( true );
			focusAt( event.key === 'ArrowDown' ? 0 : -1 );
		} else if ( event.key === 'Escape' && isOpen() ) {
			event.preventDefault();
			close( true );
		}
	} );

	list.addEventListener( 'keydown', ( event ) => {
		const index = links().indexOf( doc.activeElement );
		const moves = {
			ArrowDown: index + 1,
			ArrowUp: index - 1,
			Home: 0,
			End: -1,
		};

		if ( event.key in moves ) {
			event.preventDefault();
			focusAt( moves[ event.key ] );
		} else if ( event.key === 'Escape' ) {
			event.preventDefault();
			close( true );
		}
	} );

	root.addEventListener( 'focusout', ( event ) => {
		if ( isOpen() && ! root.contains( event.relatedTarget ) ) {
			close( false );
		}
	} );

	doc.addEventListener( 'click', ( event ) => {
		if ( isOpen() && ! root.contains( event.target ) ) {
			close( false );
		}
	} );

	return true;
}

/**
 * Wire every menu in the document, where the browser says it runs script: the block's style draws
 * the menu by the same media feature, so the two never disagree.
 *
 * @param {Document}                              doc        The document.
 * @param {(query: string) => {matches: boolean}} matchMedia The window's `matchMedia`.
 */
export function initAll(
	doc = typeof document !== 'undefined' ? document : null,
	matchMedia = typeof window !== 'undefined' && window.matchMedia
		? window.matchMedia.bind( window )
		: null
) {
	if (
		! doc ||
		! matchMedia ||
		! matchMedia( '(scripting: enabled)' ).matches
	) {
		return;
	}
	doc.querySelectorAll( MENU ).forEach( ( root ) =>
		initLanguageMenu( root )
	);
}

if ( typeof document !== 'undefined' ) {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => initAll() );
	} else {
		initAll();
	}
}
