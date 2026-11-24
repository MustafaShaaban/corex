/**
 * The mega menu item's behaviour on the page (spec 137 slice 3; US-NAV-04, FR-015, FR-016, FR-017).
 *
 * A panel opens after about 150ms of a mouse resting on its item and closes a short delay after the
 * mouse leaves; a click or a tap opens and closes it; it closes when focus leaves the item, on a click
 * outside and on Escape, which returns focus to the trigger; Down, Up, Home and End move among the
 * panel's links and buttons; Left, Right, Home and End move among the menu's items, mirrored in a
 * right-to-left page; one panel is open at a time. Inside an opened phone menu the script leaves the
 * item alone (slice 4 builds the group there).
 */

const PANEL_LINKS = 3;

/**
 * A menu as the renderer and WordPress draw it: a link, two mega items, a link.
 *
 * @param {Object}  options
 * @param {string}  [options.dir]       The page's direction.
 * @param {boolean} [options.phoneMenu] Whether the menu sits in WordPress's opened phone menu.
 * @return {string} The markup.
 */
function menuMarkup( { dir = 'ltr', phoneMenu = false } = {} ) {
	const item = ( id, label ) => {
		const links = Array.from(
			{ length: PANEL_LINKS },
			( _, index ) =>
				`<p><a href="/${ id }-${ index }" class="${ id }-link">${ label } ${ index }</a></p>`
		).join( '' );
		return (
			`<li class="wp-block-navigation-item corex-mega-menu" data-corex-mega-menu>` +
			`<button type="button" id="${ id }-trigger" class="corex-nav__trigger" aria-expanded="false" aria-controls="${ id }-panel">${ label }</button>` +
			`<div id="${ id }-panel" class="corex-nav__panel" role="group" aria-labelledby="${ id }-trigger" hidden>` +
			`<div class="wp-block-template-part">${ links }<input type="search" class="${ id }-field"></div></div></li>`
		);
	};
	const list =
		'<ul class="wp-block-navigation__container">' +
		'<li class="wp-block-navigation-item"><a class="wp-block-navigation-item__content first-link" href="/work">Work</a></li>' +
		item( 'services', 'Services' ) +
		item( 'products', 'Products' ) +
		'<li class="wp-block-navigation-item has-child open-on-click wp-block-navigation-submenu">' +
		'<button type="button" class="wp-block-navigation-item__content wp-block-navigation-submenu__toggle" aria-expanded="false">Company</button>' +
		'<ul class="wp-block-navigation__submenu-container"><li><a href="/about">About</a></li></ul></li>' +
		'<li class="wp-block-navigation-item"><a class="wp-block-navigation-item__content last-link" href="/blog">Blog</a></li>' +
		'</ul>';
	const container = phoneMenu
		? `<div class="wp-block-navigation__responsive-container is-menu-open">${ list }</div>`
		: `<div class="wp-block-navigation__responsive-container">${ list }</div>`;

	return `<div dir="${ dir }"><nav class="wp-block-navigation">${ container }</nav><a href="/after" class="after">After</a></div>`;
}

function setup( options ) {
	document.body.innerHTML = menuMarkup( options );
	jest.resetModules();
	require( './view.js' );

	const part = ( id ) => ( {
		item: document.getElementById( `${ id }-trigger` ).parentElement,
		trigger: document.getElementById( `${ id }-trigger` ),
		panel: document.getElementById( `${ id }-panel` ),
		links: Array.from( document.querySelectorAll( `.${ id }-link` ) ),
		field: document.querySelector( `.${ id }-field` ),
	} );

	// WordPress's own script opens and closes a sub-menu on a click of its toggle.
	const submenu = document.querySelector(
		'.wp-block-navigation-submenu__toggle'
	);
	submenu.addEventListener( 'click', () =>
		submenu.setAttribute(
			'aria-expanded',
			submenu.getAttribute( 'aria-expanded' ) === 'true'
				? 'false'
				: 'true'
		)
	);

	return {
		services: part( 'services' ),
		products: part( 'products' ),
		submenu,
	};
}

const isOpen = ( part ) =>
	part.trigger.getAttribute( 'aria-expanded' ) === 'true' &&
	! part.panel.hidden;

const isClosed = ( part ) =>
	part.trigger.getAttribute( 'aria-expanded' ) === 'false' &&
	part.panel.hidden;

function pointer( element, type, pointerType = 'mouse' ) {
	const event = new window.MouseEvent( type, { bubbles: false } );
	Object.defineProperty( event, 'pointerType', { value: pointerType } );
	element.dispatchEvent( event );
}

function key( element, name, modifiers = {} ) {
	const event = new window.KeyboardEvent( 'keydown', {
		key: name,
		bubbles: true,
		cancelable: true,
		...modifiers,
	} );
	element.dispatchEvent( event );

	return event;
}

function focusOut( element, to ) {
	element.dispatchEvent(
		new window.FocusEvent( 'focusout', {
			bubbles: true,
			relatedTarget: to,
		} )
	);
}

beforeEach( () => {
	jest.useFakeTimers();
} );

afterEach( () => {
	window.Corex?.megaMenu?.destroy();
	jest.useRealTimers();
	document.body.innerHTML = '';
} );

describe( 'FR-015: the script takes over from the style', () => {
	test( 'marks each item ready and leaves every panel closed', () => {
		const { services, products } = setup();

		expect( services.item.getAttribute( 'data-corex-mega-menu' ) ).toBe(
			'ready'
		);
		expect( isClosed( services ) ).toBe( true );
		expect( isClosed( products ) ).toBe( true );
	} );
} );

describe( 'US-NAV-04 criterion 1: a mouse resting on an item opens its panel', () => {
	test( 'opens about 150ms after the mouse enters, not before', () => {
		const { services } = setup();

		pointer( services.item, 'pointerenter' );
		jest.advanceTimersByTime( 140 );
		expect( isClosed( services ) ).toBe( true );

		jest.advanceTimersByTime( 20 );
		expect( isOpen( services ) ).toBe( true );
	} );

	test( 'closes a short delay after the mouse leaves, and stays open if it comes back', () => {
		const { services } = setup();
		pointer( services.item, 'pointerenter' );
		jest.advanceTimersByTime( 200 );

		pointer( services.item, 'pointerleave' );
		jest.advanceTimersByTime( 100 );
		expect( isOpen( services ) ).toBe( true );

		pointer( services.item, 'pointerenter' );
		jest.advanceTimersByTime( 1000 );
		expect( isOpen( services ) ).toBe( true );

		pointer( services.item, 'pointerleave' );
		jest.advanceTimersByTime( 1000 );
		expect( isClosed( services ) ).toBe( true );
	} );

	test( 'a mouse that only passes over an item opens nothing', () => {
		const { services } = setup();

		pointer( services.item, 'pointerenter' );
		jest.advanceTimersByTime( 60 );
		pointer( services.item, 'pointerleave' );
		jest.advanceTimersByTime( 1000 );

		expect( isClosed( services ) ).toBe( true );
	} );

	test( 'a touch or a pen resting on an item opens nothing (FR-015: hover is for a mouse)', () => {
		const { services } = setup();

		pointer( services.item, 'pointerenter', 'touch' );
		pointer( services.item, 'pointerenter', 'pen' );
		jest.advanceTimersByTime( 1000 );

		expect( isClosed( services ) ).toBe( true );
	} );
} );

describe( 'US-NAV-04 criterion 1 and 6: a click or a tap opens and closes', () => {
	test( 'a click opens the panel and a second click closes it', () => {
		const { services } = setup();

		services.trigger.click();
		expect( isOpen( services ) ).toBe( true );

		services.trigger.click();
		expect( isClosed( services ) ).toBe( true );
	} );

	test( 'a tap opens the panel with no hover first', () => {
		const { services } = setup();

		pointer( services.item, 'pointerenter', 'touch' );
		services.trigger.click();

		expect( isOpen( services ) ).toBe( true );
	} );

	test( 'a click on a panel the mouse already opened keeps it open, and the mouse leaving no longer closes it', () => {
		const { services } = setup();
		pointer( services.item, 'pointerenter' );
		jest.advanceTimersByTime( 200 );

		services.trigger.click();
		pointer( services.item, 'pointerleave' );
		jest.advanceTimersByTime( 1000 );

		expect( isOpen( services ) ).toBe( true );
	} );

	test( 'opening one panel closes the other (one open at a time)', () => {
		const { services, products } = setup();

		services.trigger.click();
		products.trigger.click();

		expect( isClosed( services ) ).toBe( true );
		expect( isOpen( products ) ).toBe( true );
	} );

	test( 'a click outside the item closes its panel', () => {
		const { services } = setup();
		services.trigger.click();

		document.querySelector( '.after' ).click();

		expect( isClosed( services ) ).toBe( true );
	} );

	test( 'a click inside the panel leaves it open', () => {
		const { services } = setup();
		services.trigger.click();

		services.panel.querySelector( 'p' ).click();

		expect( isOpen( services ) ).toBe( true );
	} );
} );

describe( 'US-NAV-04 criterion 2: focus leaving an open panel closes it', () => {
	test( 'closes when focus moves outside the item', () => {
		const { services } = setup();
		services.trigger.click();

		focusOut( services.links[ 2 ], document.querySelector( '.last-link' ) );

		expect( isClosed( services ) ).toBe( true );
	} );

	test( 'stays open while focus moves inside the item', () => {
		const { services } = setup();
		services.trigger.click();

		focusOut( services.trigger, services.links[ 0 ] );
		focusOut( services.links[ 0 ], services.links[ 1 ] );

		expect( isOpen( services ) ).toBe( true );
	} );

	test( 'stays open when a press inside the panel takes the focus nowhere', () => {
		const { services } = setup();
		services.trigger.click();
		const text = services.panel.querySelector( 'p' );

		text.dispatchEvent(
			new window.MouseEvent( 'pointerdown', { bubbles: true } )
		);
		focusOut( services.links[ 0 ], null );

		expect( isOpen( services ) ).toBe( true );
	} );
} );

describe( 'US-NAV-04 criterion 3: arrow keys, Home, End and Escape', () => {
	test( 'Down on a trigger opens its panel and focuses its first link; Up, its last', () => {
		const { services, products } = setup();

		expect( key( services.trigger, 'ArrowDown' ).defaultPrevented ).toBe(
			true
		);
		expect( isOpen( services ) ).toBe( true );
		expect( document.activeElement ).toBe( services.links[ 0 ] );

		key( products.trigger, 'ArrowUp' );
		expect( isOpen( products ) ).toBe( true );
		// The last of the panel's links and buttons; a field is not one of them.
		expect( document.activeElement ).toBe(
			products.links[ PANEL_LINKS - 1 ]
		);
	} );

	test( "Down and Up move among the panel's links, Home and End go to the first and last", () => {
		const { services } = setup();
		key( services.trigger, 'ArrowDown' );

		key( services.links[ 0 ], 'ArrowDown' );
		expect( document.activeElement ).toBe( services.links[ 1 ] );

		key( services.links[ 1 ], 'ArrowUp' );
		expect( document.activeElement ).toBe( services.links[ 0 ] );

		key( services.links[ 0 ], 'End' );
		expect( document.activeElement ).toBe(
			services.links[ PANEL_LINKS - 1 ]
		);

		key( services.links[ PANEL_LINKS - 1 ], 'Home' );
		expect( document.activeElement ).toBe( services.links[ 0 ] );
	} );

	test( 'Escape in the panel closes it and returns focus to its trigger', () => {
		const { services } = setup();
		key( services.trigger, 'ArrowDown' );

		key( services.links[ 1 ], 'Escape' );

		expect( isClosed( services ) ).toBe( true );
		expect( document.activeElement ).toBe( services.trigger );
	} );

	test( 'Escape on an open trigger closes its panel and keeps the focus there', () => {
		const { services } = setup();
		services.trigger.focus();
		services.trigger.click();

		key( services.trigger, 'Escape' );

		expect( isClosed( services ) ).toBe( true );
		expect( document.activeElement ).toBe( services.trigger );
	} );

	test( "keys typed in a field of the panel stay the field's", () => {
		const { services } = setup();
		services.trigger.click();
		services.field.focus();

		expect( key( services.field, 'Home' ).defaultPrevented ).toBe( false );
		expect( key( services.field, 'ArrowDown' ).defaultPrevented ).toBe(
			false
		);
		expect( document.activeElement ).toBe( services.field );
	} );

	test( "Right and Left move among the menu's items, Home and End to the first and last", () => {
		const { services, products } = setup();
		const first = document.querySelector( '.first-link' );
		const last = document.querySelector( '.last-link' );

		key( first, 'ArrowRight' );
		expect( document.activeElement ).toBe( services.trigger );

		key( services.trigger, 'ArrowRight' );
		expect( document.activeElement ).toBe( products.trigger );

		key( products.trigger, 'ArrowLeft' );
		expect( document.activeElement ).toBe( services.trigger );

		key( services.trigger, 'End' );
		expect( document.activeElement ).toBe( last );

		key( last, 'Home' );
		expect( document.activeElement ).toBe( first );
	} );

	test( 'in a right-to-left page Left moves forward and Right back', () => {
		const { services, products } = setup( { dir: 'rtl' } );

		key( services.trigger, 'ArrowLeft' );
		expect( document.activeElement ).toBe( products.trigger );

		key( products.trigger, 'ArrowRight' );
		expect( document.activeElement ).toBe( services.trigger );
	} );
} );

describe( "FR-018: inside an opened phone menu the item is left to WordPress's menu", () => {
	test( 'a click, the mouse and the keys open nothing there', () => {
		const { services } = setup( { phoneMenu: true } );

		services.trigger.click();
		pointer( services.item, 'pointerenter' );
		jest.advanceTimersByTime( 1000 );
		const down = key( services.trigger, 'ArrowDown' );

		expect( isClosed( services ) ).toBe( true );
		expect( down.defaultPrevented ).toBe( false );
	} );

	test( 'Escape is left to WordPress, which closes the phone menu', () => {
		const { services } = setup( { phoneMenu: true } );

		expect( key( services.trigger, 'Escape' ).defaultPrevented ).toBe(
			false
		);
	} );
} );

describe( 'review fixes: the mouse, browser shortcuts and WordPress sub-menus', () => {
	test( 'the mouse leaving a panel it opened does not close it while keyboard focus is inside', () => {
		const { services } = setup();
		pointer( services.item, 'pointerenter' );
		jest.advanceTimersByTime( 200 );
		services.links[ 1 ].focus();

		pointer( services.item, 'pointerleave' );
		jest.advanceTimersByTime( 1000 );

		expect( isOpen( services ) ).toBe( true );
		expect( document.activeElement ).toBe( services.links[ 1 ] );
	} );

	test( 'a shortcut with Alt, Ctrl or Meta is left to the browser', () => {
		const { services, products } = setup();

		const back = key( services.trigger, 'ArrowLeft', { altKey: true } );
		const open = key( services.trigger, 'ArrowDown', { metaKey: true } );
		expect( back.defaultPrevented ).toBe( false );
		expect( open.defaultPrevented ).toBe( false );
		expect( isClosed( services ) ).toBe( true );

		products.trigger.click();
		products.links[ 0 ].focus();
		const end = key( products.links[ 0 ], 'End', { ctrlKey: true } );
		const next = key( products.trigger, 'ArrowRight', { metaKey: true } );
		expect( end.defaultPrevented ).toBe( false );
		expect( next.defaultPrevented ).toBe( false );
		expect( document.activeElement ).toBe( products.links[ 0 ] );
	} );

	test( "opening a mega panel closes the menu's open WordPress sub-menu", () => {
		const { services, submenu } = setup();
		submenu.click();
		expect( submenu.getAttribute( 'aria-expanded' ) ).toBe( 'true' );

		services.trigger.click();

		expect( isOpen( services ) ).toBe( true );
		expect( submenu.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
	} );

	test( 'opening a WordPress sub-menu closes the open mega panel', () => {
		const { services, submenu } = setup();
		services.trigger.click();

		submenu.click();

		expect( isClosed( services ) ).toBe( true );
		expect( submenu.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
	} );
} );
