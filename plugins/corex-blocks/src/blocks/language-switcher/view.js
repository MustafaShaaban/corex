/**
 * Language switcher: the menu form's behaviour (spec 137, FR-032; `design/handoffs/header-system.md`,
 * step 2). The kit's Dropdown, as a disclosure: the button opens and closes the list of languages,
 * Down and Up open it on the first or the last language and move through them, Home and End go to
 * the ends, Enter follows a language (the link's own), Escape closes it and returns focus to the
 * button, and a click outside or focus moving elsewhere closes it.
 *
 * The Dropdown opens at the button's inline end. Where that would put its start edge outside the
 * window (a switcher at the start of a line, on a phone), it opens at the button's inline start.
 *
 * The server prints the menu with its list showing. Where the browser runs no script, the block's
 * style draws that markup as the list form, and this script does nothing where the browser cannot
 * say it runs script, so the list stays usable there too. The list and toggle forms need no script.
 * Exported for unit testing; starts itself on the page.
 */

const MENU = '.corex-language-switcher--menu';
const BUTTON = ':scope > .corex-language-switcher__button';
const LIST = ':scope > .corex-dropdown__menu';
const ALIGNED_START = 'is-aligned-start';

/**
 * One menu's state and the moves the handlers share.
 *
 * @param {HTMLElement} root   The menu's root.
 * @param {HTMLElement} button Its button.
 * @param {HTMLElement} list   Its Dropdown.
 * @return {Object} The menu.
 */
function menuOf( root, button, list ) {
	const doc = root.ownerDocument;
	const view = doc.defaultView;
	const links = () => Array.from( list.querySelectorAll( 'a[href]' ) );

	const menu = {
		root,
		button,
		list,
		doc,
		isOpen: () => button.getAttribute( 'aria-expanded' ) === 'true',
		open() {
			button.setAttribute( 'aria-expanded', 'true' );
			list.hidden = false;
			keepInside( list, view );
		},
		close( returnFocus = false ) {
			button.setAttribute( 'aria-expanded', 'false' );
			list.hidden = true;
			list.classList.remove( ALIGNED_START );
			if ( returnFocus ) {
				button.focus();
			}
		},
		focusAt( index ) {
			const all = links();
			if ( all.length > 0 ) {
				all[ ( index + all.length ) % all.length ].focus();
			}
		},
		indexOfFocus: () => links().indexOf( doc.activeElement ),
	};

	return menu;
}

/**
 * Opens the Dropdown at the button's inline start when, at its inline end, its start edge would be
 * outside the window. Measured on every opening, so a turned phone gets the right side.
 *
 * @param {HTMLElement} list The open Dropdown.
 * @param {Window}      view Its window.
 */
function keepInside( list, view ) {
	list.classList.remove( ALIGNED_START );

	const box = list.getBoundingClientRect();
	const width = view.document.documentElement.clientWidth || view.innerWidth;
	const rtl = view.getComputedStyle( list ).direction === 'rtl';

	if ( rtl ? box.right > width : box.left < 0 ) {
		list.classList.add( ALIGNED_START );
	}
}

/**
 * The button's keys: Down and Up open the menu on the first or the last language; Escape closes it.
 *
 * @param {Object}        menu  The menu.
 * @param {KeyboardEvent} event The key.
 */
function onButtonKey( menu, event ) {
	if ( event.key === 'ArrowDown' || event.key === 'ArrowUp' ) {
		event.preventDefault();
		menu.open();
		menu.focusAt( event.key === 'ArrowDown' ? 0 : -1 );
	} else if ( event.key === 'Escape' && menu.isOpen() ) {
		event.preventDefault();
		menu.close( true );
	}
}

/**
 * The list's keys: the arrows, Home and End move among the languages; Escape closes the menu.
 * Enter is the link's own.
 *
 * @param {Object}        menu  The menu.
 * @param {KeyboardEvent} event The key.
 */
function onListKey( menu, event ) {
	const index = menu.indexOfFocus();
	const moves = {
		ArrowDown: index + 1,
		ArrowUp: index - 1,
		Home: 0,
		End: -1,
	};

	if ( event.key in moves ) {
		event.preventDefault();
		menu.focusAt( moves[ event.key ] );
	} else if ( event.key === 'Escape' ) {
		event.preventDefault();
		menu.close( true );
	}
}

/**
 * Closes the menu when focus moves to something else on the page, or a click lands outside it.
 *
 * A focus change with no new target is not one: WebKit does not focus a link or a button it is
 * clicked on, so pressing on a language reports focus leaving for nowhere. Closing then would hide
 * the link before the click lands. A click outside is the document's listener's to close.
 *
 * @param {Object} menu The menu.
 */
function closeFromOutside( menu ) {
	menu.root.addEventListener( 'focusout', ( event ) => {
		const target = event.relatedTarget;
		if ( menu.isOpen() && target && ! menu.root.contains( target ) ) {
			menu.close();
		}
	} );

	menu.doc.addEventListener( 'click', ( event ) => {
		if ( menu.isOpen() && ! menu.root.contains( event.target ) ) {
			menu.close();
		}
	} );
}

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

	const menu = menuOf( root, button, list );
	menu.close();

	button.addEventListener( 'click', () =>
		menu.isOpen() ? menu.close() : menu.open()
	);
	button.addEventListener( 'keydown', ( event ) =>
		onButtonKey( menu, event )
	);
	list.addEventListener( 'keydown', ( event ) => onListKey( menu, event ) );
	closeFromOutside( menu );

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
