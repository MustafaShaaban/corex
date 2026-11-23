/**
 * The language switcher's menu form in the browser (spec 137, FR-032; `design/handoffs/header-system.md`,
 * step 2, "Interaction and state behaviour"): the button opens and closes the kit's Dropdown, the arrow
 * keys move through the languages, Enter follows one, Escape closes it and returns focus to the button,
 * and a click outside or focus leaving closes it. Without script, or where the browser cannot say it
 * runs one, the server's markup stays the list form.
 */
import { initAll, initLanguageMenu } from './view';

/**
 * The menu form as the server prints it, with English current.
 *
 * @param {string} id The list's id.
 * @return {HTMLElement} Its root.
 */
function menu( id = 'corex-language-switcher-1' ) {
	const holder = document.createElement( 'div' );
	holder.innerHTML = `
		<div class="corex-language-switcher corex-language-switcher--menu">
			<button type="button" class="wp-element-button corex-language-switcher__button" aria-expanded="false" aria-controls="${ id }">
				<span lang="en" dir="ltr">English</span>
			</button>
			<ul id="${ id }" class="corex-language-switcher__list corex-dropdown__menu" aria-label="Languages">
				<li><span class="corex-language-switcher__item" aria-current="true"><span lang="en" dir="ltr">English</span></span></li>
				<li><a class="corex-language-switcher__item" href="/ar/" hreflang="ar"><span lang="ar" dir="rtl">العربية</span></a></li>
				<li><a class="corex-language-switcher__item" href="/fr/" hreflang="fr"><span lang="fr" dir="ltr">Français</span></a></li>
			</ul>
		</div>`;
	document.body.appendChild( holder );

	return holder.firstElementChild;
}

const parts = ( root ) => ( {
	button: root.querySelector( 'button' ),
	list: root.querySelector( 'ul' ),
	links: Array.from( root.querySelectorAll( 'a' ) ),
} );

function press( target, key ) {
	const event = new window.KeyboardEvent( 'keydown', {
		key,
		bubbles: true,
		cancelable: true,
	} );
	target.dispatchEvent( event );

	return event;
}

afterEach( () => {
	document.body.innerHTML = '';
} );

describe( 'FR-032: the menu form', () => {
	it( 'starts closed, the list hidden', () => {
		const root = menu();
		initLanguageMenu( root );
		const { button, list } = parts( root );

		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( list.hidden ).toBe( true );
	} );

	it( 'opens and closes on a click of its button', () => {
		const root = menu();
		initLanguageMenu( root );
		const { button, list } = parts( root );

		button.click();
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( list.hidden ).toBe( false );

		button.click();
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( list.hidden ).toBe( true );
	} );

	it( 'opens on Down and Up from the button, on the first and the last language', () => {
		const root = menu();
		initLanguageMenu( root );
		const { button, list, links } = parts( root );

		button.focus();
		expect( press( button, 'ArrowDown' ).defaultPrevented ).toBe( true );
		expect( list.hidden ).toBe( false );
		expect( document.activeElement ).toBe( links[ 0 ] );

		press( links[ 0 ], 'Escape' );
		press( button, 'ArrowUp' );
		expect( document.activeElement ).toBe( links[ links.length - 1 ] );
	} );

	it( 'moves through the languages with the arrow keys, Home and End, and wraps', () => {
		const root = menu();
		initLanguageMenu( root );
		const { button, links } = parts( root );

		press( button, 'ArrowDown' );
		press( links[ 0 ], 'ArrowDown' );
		expect( document.activeElement ).toBe( links[ 1 ] );
		press( links[ 1 ], 'ArrowDown' );
		expect( document.activeElement ).toBe( links[ 0 ] );
		press( links[ 0 ], 'ArrowUp' );
		expect( document.activeElement ).toBe( links[ 1 ] );
		press( links[ 1 ], 'Home' );
		expect( document.activeElement ).toBe( links[ 0 ] );
		press( links[ 0 ], 'End' );
		expect( document.activeElement ).toBe( links[ 1 ] );
	} );

	it( 'leaves Enter to the link, which follows it', () => {
		const root = menu();
		initLanguageMenu( root );
		const { button, links } = parts( root );

		press( button, 'ArrowDown' );
		expect( press( links[ 0 ], 'Enter' ).defaultPrevented ).toBe( false );
	} );

	it( 'closes on Escape and returns focus to its button', () => {
		const root = menu();
		initLanguageMenu( root );
		const { button, list, links } = parts( root );

		press( button, 'ArrowDown' );
		press( links[ 0 ], 'Escape' );

		expect( list.hidden ).toBe( true );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( document.activeElement ).toBe( button );
	} );

	it( 'closes on Escape pressed on its button', () => {
		const root = menu();
		initLanguageMenu( root );
		const { button, list } = parts( root );

		button.click();
		button.focus();
		press( button, 'Escape' );

		expect( list.hidden ).toBe( true );
		expect( document.activeElement ).toBe( button );
	} );

	it( 'closes on a click outside it, and not on a click inside it', () => {
		const root = menu();
		const outside = document.createElement( 'p' );
		document.body.appendChild( outside );
		initLanguageMenu( root );
		const { button, list } = parts( root );

		button.click();
		root.querySelector( '[aria-current]' ).click();
		expect( list.hidden ).toBe( false );

		outside.click();
		expect( list.hidden ).toBe( true );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
	} );

	it( 'closes when focus leaves it', () => {
		const root = menu();
		const after = document.createElement( 'a' );
		after.href = '/next/';
		document.body.appendChild( after );
		initLanguageMenu( root );
		const { button, list, links } = parts( root );

		press( button, 'ArrowDown' );
		links[ 0 ].dispatchEvent(
			new window.FocusEvent( 'focusout', {
				bubbles: true,
				relatedTarget: after,
			} )
		);

		expect( list.hidden ).toBe( true );
	} );

	it( 'stays open when focus leaves for nowhere, as WebKit reports a click on a link, and follows the click', () => {
		const root = menu();
		initLanguageMenu( root );
		const { button, list, links } = parts( root );

		press( button, 'ArrowDown' );
		links[ 0 ].dispatchEvent(
			new window.FocusEvent( 'focusout', {
				bubbles: true,
				relatedTarget: null,
			} )
		);
		expect( list.hidden ).toBe( false );

		// The click that follows lands inside the menu, so it is not dismissed either.
		links[ 0 ].addEventListener( 'click', ( event ) =>
			event.preventDefault()
		);
		links[ 0 ].click();
		expect( list.hidden ).toBe( false );
	} );

	it( 'keeps one menu open at a time on a page with two', () => {
		const first = menu( 'corex-language-switcher-1' );
		const second = menu( 'corex-language-switcher-2' );
		initLanguageMenu( first );
		initLanguageMenu( second );

		parts( first ).button.click();
		parts( second ).button.click();

		expect( parts( first ).list.hidden ).toBe( true );
		expect( parts( second ).list.hidden ).toBe( false );
	} );

	it( 'does nothing to a root that lacks its button or its list', () => {
		const root = menu();
		root.querySelector( 'button' ).remove();

		expect( initLanguageMenu( root ) ).toBe( false );
		expect( root.querySelector( 'ul' ).hidden ).toBe( false );
	} );
} );

describe( 'FR-032: the open menu stays inside the window', () => {
	const WIDTH = 375;

	/**
	 * Opens a menu whose Dropdown, aligned to the button's inline end, is drawn at `box`.
	 *
	 * @param {{left: number, right: number}} box       Where the Dropdown is drawn when opened.
	 * @param {string}                        direction The page's direction.
	 * @return {HTMLElement} The Dropdown.
	 */
	function openAt( box, direction = 'ltr' ) {
		const root = menu();
		const { button, list } = parts( root );
		list.style.direction = direction;
		list.getBoundingClientRect = () => ( {
			...box,
			top: 0,
			bottom: 100,
			width: box.right - box.left,
			height: 100,
		} );
		initLanguageMenu( root );
		button.click();

		return list;
	}

	beforeEach( () => {
		window.innerWidth = WIDTH;
	} );

	it( 'keeps the Dropdown at the inline end while its start edge is inside the window', () => {
		expect(
			openAt( { left: 120, right: 344 } ).classList.contains(
				'is-aligned-start'
			)
		).toBe( false );
		expect(
			openAt( { left: 30, right: 254 }, 'rtl' ).classList.contains(
				'is-aligned-start'
			)
		).toBe( false );
	} );

	it( 'aligns it to the inline start when its start edge would leave the window, left to right', () => {
		expect(
			openAt( { left: -118, right: 106 } ).classList.contains(
				'is-aligned-start'
			)
		).toBe( true );
	} );

	it( 'aligns it to the inline start when its start edge would leave the window, right to left', () => {
		expect(
			openAt( { left: 269, right: 493 }, 'rtl' ).classList.contains(
				'is-aligned-start'
			)
		).toBe( true );
	} );

	it( 'measures again on each opening', () => {
		const list = openAt( { left: -118, right: 106 } );
		const button = list.previousElementSibling;

		button.click();
		expect( list.classList.contains( 'is-aligned-start' ) ).toBe( false );

		list.getBoundingClientRect = () => ( {
			left: 120,
			right: 344,
			top: 0,
			bottom: 100,
			width: 224,
			height: 100,
		} );
		button.click();
		expect( list.classList.contains( 'is-aligned-start' ) ).toBe( false );
	} );
} );

describe( 'FR-032: without script the menu form is the list form', () => {
	it( 'leaves every menu as the list where the browser cannot say it runs script', () => {
		const root = menu();

		initAll( document, () => ( { matches: false } ) );

		expect( root.querySelector( 'ul' ).hidden ).toBe( false );
		parts( root ).button.click();
		expect( parts( root ).button.getAttribute( 'aria-expanded' ) ).toBe(
			'false'
		);
	} );

	it( 'wires every menu where it does', () => {
		const root = menu();
		const list = document.createElement( 'div' );
		list.className =
			'corex-language-switcher corex-language-switcher--list';
		document.body.appendChild( list );

		initAll( document, () => ( { matches: true } ) );

		expect( root.querySelector( 'ul' ).hidden ).toBe( true );
	} );
} );
