/**
 * The assistant's button (spec 129): on every public page, so it does as little as it can.
 *
 * It opens and closes the panel and shows the invitation once per visit. The chat itself (chat.js and
 * its stylesheet) is a separate file fetched on the first open, or a moment earlier when the pointer
 * or focus reaches the button, so a visitor who never asks loads none of it.
 */

import './style.scss';

const TEASER_SEEN = 'corex-assistant-teaser';
const TEASER_DELAY = 6000;
const TEASER_SHOWN_FOR = 14000;
const OPEN = 'corex-assistant-open';

function storage() {
	try {
		return window.sessionStorage;
	} catch {
		return null;
	}
}

export function bindAssistant(
	root,
	loadChat = () => import( /* webpackChunkName: "assistant/chat" */ './chat' )
) {
	const fab = root.querySelector( '.corex-assistant__fab' );
	const panel = root.querySelector( '.corex-assistant__panel' );
	if ( ! fab || ! panel ) {
		return;
	}
	const teaser = root.querySelector( '.corex-assistant__teaser' );

	let chat = null;
	let loading = null;
	const load = () => {
		loading ??= loadChat();
		return loading;
	};
	const preload = () => load().catch( () => ( loading = null ) );

	const hideTeaser = () => {
		if ( teaser ) {
			teaser.hidden = true;
		}
	};

	const close = () => {
		panel.hidden = true;
		root.classList.remove( 'is-open' );
		document.documentElement.classList.remove( OPEN );
		fab.setAttribute( 'aria-expanded', 'false' );
		fab.focus();
	};

	const open = async () => {
		hideTeaser();
		storage()?.setItem( TEASER_SEEN, '1' );
		fab.classList.add( 'is-loading' );
		try {
			const module = await load();
			chat ??= module.mountChat( root, panel, { onClose: close } );
		} catch {
			// The chat file did not arrive (offline, a blocked request): the next click tries again.
			loading = null;
			return;
		} finally {
			fab.classList.remove( 'is-loading' );
		}
		panel.hidden = false;
		root.classList.add( 'is-open' );
		document.documentElement.classList.add( OPEN );
		fab.setAttribute( 'aria-expanded', 'true' );
		chat.focus();
	};

	fab.addEventListener( 'click', () => ( panel.hidden ? open() : close() ) );
	fab.addEventListener( 'pointerenter', preload, { once: true } );
	fab.addEventListener( 'focus', preload, { once: true } );
	teaser
		?.querySelector( '.corex-assistant__dismiss' )
		?.addEventListener( 'click', () => {
			hideTeaser();
			storage()?.setItem( TEASER_SEEN, '1' );
		} );

	const wide = window.matchMedia?.( '(min-width: 768px)' ).matches;
	if ( teaser && wide && ! storage()?.getItem( TEASER_SEEN ) ) {
		window.setTimeout( () => {
			if ( ! panel.hidden ) {
				return;
			}
			teaser.hidden = false;
			storage()?.setItem( TEASER_SEEN, '1' );
			window.setTimeout( hideTeaser, TEASER_SHOWN_FOR );
		}, TEASER_DELAY );
	}
}

function init() {
	document
		.querySelectorAll( '[data-corex-assistant]' )
		.forEach( ( root ) => bindAssistant( root ) );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
