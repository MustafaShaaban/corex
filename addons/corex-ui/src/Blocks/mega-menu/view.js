/**
 * corex/mega-menu on the page (spec 137; US-NAV-04, FR-015, FR-016, FR-018; DECISIONS #691, #705).
 *
 * Without this script a panel opens while the pointer or the focus is inside its item (the block's
 * style). With it: a mouse resting on an item for about 150ms opens its panel and leaving closes it
 * a short delay later, unless keyboard focus is inside; a click or a tap opens and closes it, and a
 * click on a panel the mouse opened keeps it open; focus leaving the item, a click outside it and
 * Escape close it, Escape returning focus to the trigger; Down, Up, Home and End move among the
 * panel's links and buttons; Left, Right, Home and End move among the menu's items, mirrored in a
 * right-to-left page. A key pressed with Alt, Ctrl or Meta is the browser's. One panel is open at a
 * time, and opening one closes the menu's open WordPress sub-menu. Inside WordPress's opened phone
 * menu the item is left alone (spec 137 slice 4).
 *
 * Loaded only where the block is (its block.json `viewScript`). It reads no width (FR-003).
 * Exposed as `window.Corex.megaMenu = { init, destroy }` for its tests.
 *
 * @param {Window}   window   The window the menu lives in.
 * @param {Document} document That window's document.
 */
( function ( window, document ) {
	'use strict';

	const OPEN_DELAY = 150;
	const CLOSE_DELAY = 300;
	const PHONE_MENU =
		'.wp-block-navigation__responsive-container.is-menu-open';
	const PANEL_ITEMS = 'a[href], button:not([disabled])';
	const TOP_LEVEL = ':scope > a[href], :scope > button';
	const TYPING = 'input, textarea, select, [contenteditable]';
	const OPEN_SUBMENU =
		'.wp-block-navigation-submenu__toggle[aria-expanded="true"]';

	let menus = [];
	let listeners = [];
	let pressed = null;

	function listen( target, type, handler, options ) {
		target.addEventListener( type, handler, options );
		listeners.push( { target, type, handler, options } );
	}

	function isOpen( menu ) {
		return menu.trigger.getAttribute( 'aria-expanded' ) === 'true';
	}

	function inPhoneMenu( menu ) {
		return !! menu.item.closest( PHONE_MENU );
	}

	function hasFocus( menu ) {
		return menu.item.contains( menu.item.ownerDocument.activeElement );
	}

	/**
	 * @param {KeyboardEvent} event A key press.
	 * @return {boolean} Whether it is a shortcut the browser or the system owns.
	 */
	function isShortcut( event ) {
		return event.altKey || event.ctrlKey || event.metaKey;
	}

	function stopTimer( menu ) {
		window.clearTimeout( menu.timer );
		menu.timer = null;
	}

	function closeOthers( menu ) {
		menus.forEach( function ( other ) {
			if ( other !== menu && isOpen( other ) ) {
				setOpen( other, false );
			}
		} );
	}

	/**
	 * Closes the open WordPress sub-menus of the menu the item is in, through their own toggles, so
	 * WordPress's script keeps its state.
	 *
	 * @param {Object} menu The mega menu item.
	 */
	function closeSubmenus( menu ) {
		const navigation = menu.item.closest( '.wp-block-navigation' );
		if ( ! navigation ) {
			return;
		}
		navigation
			.querySelectorAll( OPEN_SUBMENU )
			.forEach( function ( toggle ) {
				toggle.click();
			} );
	}

	function setOpen( menu, open ) {
		stopTimer( menu );
		if ( open ) {
			closeOthers( menu );
			closeSubmenus( menu );
		} else {
			menu.pinned = false;
		}
		menu.trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		menu.panel.hidden = ! open;
	}

	function setOpenLater( menu, open, delay ) {
		stopTimer( menu );
		menu.timer = window.setTimeout( function () {
			setOpen( menu, open );
		}, delay );
	}

	function isRtl( element ) {
		const directed = element.closest( '[dir]' );

		return (
			( directed
				? directed.getAttribute( 'dir' )
				: document.documentElement.getAttribute( 'dir' ) ) === 'rtl'
		);
	}

	function panelItems( menu ) {
		return Array.prototype.slice.call(
			menu.panel.querySelectorAll( PANEL_ITEMS )
		);
	}

	function topLevelItems( list ) {
		return Array.prototype.slice
			.call( list.children )
			.map( function ( item ) {
				return item.querySelector( TOP_LEVEL );
			} )
			.filter( Boolean );
	}

	/**
	 * The index a key moves focus to among `count` items from `at`, or -1 for a key that does not
	 * move it. `next` and `previous` name the keys that go forward and back.
	 *
	 * @param {string}  name     The key.
	 * @param {number}  at       Where focus is.
	 * @param {number}  count    How many items there are.
	 * @param {string}  next     The key that goes forward.
	 * @param {string}  previous The key that goes back.
	 * @param {boolean} wrap     Whether going past an end comes round to the other.
	 * @return {number} The index, or -1.
	 */
	function moveTo( name, at, count, next, previous, wrap ) {
		const steps = { Home: 0, End: count - 1 };
		steps[ next ] = at + 1;
		steps[ previous ] = at - 1;
		if ( ! ( name in steps ) ) {
			return -1;
		}
		if ( wrap ) {
			return ( steps[ name ] + count ) % count;
		}

		return Math.max( 0, Math.min( count - 1, steps[ name ] ) );
	}

	function focusPanelItem( menu, index ) {
		const items = panelItems( menu );
		if ( items.length ) {
			items[ index < 0 ? items.length - 1 : index ].focus();
		}
	}

	function onTriggerClick( menu ) {
		if ( inPhoneMenu( menu ) ) {
			return;
		}
		if ( isOpen( menu ) && ! menu.pinned ) {
			// The mouse opened it; the click keeps it open.
			stopTimer( menu );
			menu.pinned = true;
			return;
		}
		setOpen( menu, ! isOpen( menu ) );
		menu.pinned = isOpen( menu );
	}

	function onPointerEnter( menu, event ) {
		if ( event.pointerType !== 'mouse' || inPhoneMenu( menu ) ) {
			return;
		}
		if ( isOpen( menu ) ) {
			stopTimer( menu );
		} else {
			setOpenLater( menu, true, OPEN_DELAY );
		}
	}

	function onPointerLeave( menu, event ) {
		if ( event.pointerType !== 'mouse' || menu.pinned ) {
			return;
		}
		if ( ! isOpen( menu ) ) {
			stopTimer( menu );
		} else if ( ! hasFocus( menu ) ) {
			// Keyboard focus inside keeps it open: closing would drop the focus on the page.
			setOpenLater( menu, false, CLOSE_DELAY );
		}
	}

	function onFocusOut( menu, event ) {
		const to = event.relatedTarget || pressed;
		if ( isOpen( menu ) && ! ( to && menu.item.contains( to ) ) ) {
			setOpen( menu, false );
		}
	}

	function onEscape( menu, event ) {
		if ( isOpen( menu ) ) {
			event.preventDefault();
			setOpen( menu, false );
			menu.trigger.focus();
		}
	}

	function onTriggerKey( menu, event ) {
		if ( event.key !== 'ArrowDown' && event.key !== 'ArrowUp' ) {
			return;
		}
		event.preventDefault();
		setOpen( menu, true );
		menu.pinned = true;
		focusPanelItem( menu, event.key === 'ArrowDown' ? 0 : -1 );
	}

	function onPanelKey( menu, event ) {
		if ( event.target.matches( TYPING ) ) {
			return;
		}
		const items = panelItems( menu );
		const to = moveTo(
			event.key,
			items.indexOf( event.target ),
			items.length,
			'ArrowDown',
			'ArrowUp',
			true
		);
		if ( to >= 0 ) {
			event.preventDefault();
			items[ to ].focus();
		}
	}

	function onItemKeydown( menu, event ) {
		if (
			event.defaultPrevented ||
			isShortcut( event ) ||
			inPhoneMenu( menu )
		) {
			return;
		}
		if ( event.key === 'Escape' ) {
			onEscape( menu, event );
		} else if ( event.target === menu.trigger ) {
			onTriggerKey( menu, event );
		} else if ( menu.panel.contains( event.target ) ) {
			onPanelKey( menu, event );
		}
	}

	function onListKeydown( list, event ) {
		if (
			event.defaultPrevented ||
			isShortcut( event ) ||
			list.closest( PHONE_MENU )
		) {
			return;
		}
		const items = topLevelItems( list );
		const at = items.indexOf( event.target );
		if ( at < 0 ) {
			return;
		}
		const rtl = isRtl( list );
		const to = moveTo(
			event.key,
			at,
			items.length,
			rtl ? 'ArrowLeft' : 'ArrowRight',
			rtl ? 'ArrowRight' : 'ArrowLeft',
			false
		);
		if ( to >= 0 ) {
			event.preventDefault();
			items[ to ].focus();
		}
	}

	function onDocumentClick( event ) {
		menus.forEach( function ( menu ) {
			if ( isOpen( menu ) && ! menu.item.contains( event.target ) ) {
				setOpen( menu, false );
			}
		} );
	}

	function menuFor( item ) {
		const trigger = item.querySelector( ':scope > button' );
		const id = trigger ? trigger.getAttribute( 'aria-controls' ) : '';
		const panel = id ? document.getElementById( id ) : null;

		return trigger && panel
			? { item, trigger, panel, timer: null, pinned: false }
			: null;
	}

	function bindMenu( menu ) {
		menus.push( menu );
		setOpen( menu, false );
		menu.item.setAttribute( 'data-corex-mega-menu', 'ready' );

		listen( menu.trigger, 'click', function () {
			onTriggerClick( menu );
		} );
		listen( menu.item, 'pointerenter', function ( event ) {
			onPointerEnter( menu, event );
		} );
		listen( menu.item, 'pointerleave', function ( event ) {
			onPointerLeave( menu, event );
		} );
		listen( menu.item, 'focusout', function ( event ) {
			onFocusOut( menu, event );
		} );
		listen( menu.item, 'keydown', function ( event ) {
			onItemKeydown( menu, event );
		} );
	}

	function bindList( list ) {
		listen( list, 'keydown', function ( event ) {
			onListKeydown( list, event );
		} );
	}

	function bindDocument() {
		listen( document, 'click', onDocumentClick );
		listen(
			document,
			'pointerdown',
			function ( event ) {
				pressed = event.target;
			},
			true
		);
		listen( document, 'pointerup', function () {
			window.setTimeout( function () {
				pressed = null;
			}, 0 );
		} );
	}

	function init( root ) {
		const found = Array.prototype.slice
			.call(
				( root || document ).querySelectorAll(
					'[data-corex-mega-menu]'
				)
			)
			.map( menuFor )
			.filter( Boolean );
		const lists = [];

		found.forEach( function ( menu ) {
			bindMenu( menu );
			const list = menu.item.parentElement;
			if ( list && lists.indexOf( list ) < 0 ) {
				lists.push( list );
				bindList( list );
			}
		} );

		if ( found.length ) {
			bindDocument();
		}
	}

	function destroy() {
		listeners.forEach( function ( entry ) {
			entry.target.removeEventListener(
				entry.type,
				entry.handler,
				entry.options
			);
		} );
		menus.forEach( stopTimer );
		listeners = [];
		menus = [];
		pressed = null;
	}

	window.Corex = window.Corex || {};
	window.Corex.megaMenu = { init, destroy };

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init( document );
		} );
	} else {
		init( document );
	}
} )( window, document );
