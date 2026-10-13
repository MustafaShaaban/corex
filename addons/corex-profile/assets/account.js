/**
 * CoreX front-office account behavior — buildless, framework-free, jQuery-free.
 *
 * Progressive enhancement over the server-rendered account block: it intercepts the
 * login / register / recovery / profile forms and posts them to the `corex/v1/account/*`
 * REST routes with the REST nonce, and writes the typed result message into the polite
 * live region under the button that asked, which it brings into the window when it is
 * outside it. A request that got no answer it can read is said there too. On the member
 * view it also loads active sessions and recent activity and wires the "sign out
 * other/every session" actions. With no JS the block shows a note saying so
 * (`AccountRenderer`), and a form is sent to the page's own address, which does not
 * answer it (PROGRESS.md, "Open, and not hidden").
 *
 * Config comes from the localized `window.corexAccount = { restUrl, nonce, i18n }`.
 *
 * @param {Window}   window   The browser window the account block lives in.
 * @param {Document} document That window's document.
 */
( function ( window, document ) {
	'use strict';

	const cfg = window.corexAccount || {};
	const i18n = cfg.i18n || {};
	const MESSAGE_LINE = '.corex-account__status';
	const NO_ANSWER =
		i18n.noAnswer ||
		'The site could not be reached, or its answer could not be read. ' +
			'Please try again.';
	const ROUTES = {
		login: 'account/login',
		register: 'account/register',
		'reset-request': 'account/reset-request',
		reset: 'account/reset',
		profile: 'account/profile',
	};

	function root() {
		return document.querySelector( '[data-corex-account]' );
	}

	function nonce( el ) {
		return ( el && el.getAttribute( 'data-nonce' ) ) || cfg.nonce || '';
	}

	// Full base to the corex/v1 namespace, e.g. https://site/wp-json/corex/v1/.
	function base() {
		return (
			( cfg.restUrl || '/wp-json/corex/v1/' ).replace( /\/$/, '' ) + '/'
		);
	}

	function prefersLessMotion() {
		return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	// One message is on the page at a time: the line of every form is emptied.
	function clearMessages() {
		Array.prototype.forEach.call(
			document.querySelectorAll( MESSAGE_LINE ),
			function ( line ) {
				line.textContent = '';
				line.removeAttribute( 'data-state' );
			}
		);
	}

	/**
	 * Writes what the server answered into the message line of the form or section that
	 * asked, and brings that line into the window.
	 *
	 * `nearest` moves the page by the least that shows the whole line, and not at all when
	 * it already shows. Focus is left on the control the person used.
	 *
	 * @param {Element} scope   The form, or the section, whose control was used.
	 * @param {string}  message What the server said.
	 * @param {boolean} ok      Whether it took what it was sent.
	 */
	function say( scope, message, ok ) {
		clearMessages();
		if ( ! message ) {
			return;
		}
		const line = scope.querySelector( MESSAGE_LINE );
		line.textContent = message;
		line.setAttribute( 'data-state', ok ? 'ok' : 'error' );
		line.scrollIntoView( {
			block: 'nearest',
			inline: 'nearest',
			behavior: prefersLessMotion() ? 'instant' : 'smooth',
		} );
	}

	/**
	 * Writes the answer to a request into the line of the form or section that made it.
	 * A refusal that came with no message is one the script has no reading of: it says so.
	 *
	 * @param {Element} scope The form, or the section, whose control was used.
	 * @param {Object}  out   What `post()` resolved with.
	 */
	function answer( scope, out ) {
		say( scope, out.message || ( out.ok ? '' : NO_ANSWER ), out.ok );
	}

	/**
	 * Sends a form or a button's request and resolves with what the server said of it.
	 *
	 * It resolves whatever happens. A request that did not arrive, and an answer that is not
	 * JSON (an error page, a proxy's page), are a refusal with no message: nothing in them
	 * can be shown to a person, and the caller still has to say something.
	 *
	 * @param {string} path  The route, under the `corex/v1` namespace.
	 * @param {Object} body  What is sent, as JSON.
	 * @param {string} token The REST nonce.
	 * @return {Promise<{ok: boolean, message: string}>} Whether it was taken, and the server's message.
	 */
	function post( path, body, token ) {
		return window
			.fetch( base() + path, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': token,
				},
				body: JSON.stringify( body ),
			} )
			.then( function ( res ) {
				return res.json().then( function ( data ) {
					return {
						ok: res.ok,
						message: ( data && data.message ) || '',
					};
				} );
			} )
			.catch( function () {
				return { ok: false, message: '' };
			} );
	}

	function get( path, token ) {
		return window
			.fetch( base() + path, {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': token },
			} )
			.then( function ( res ) {
				return res.json();
			} );
	}

	function serialize( form ) {
		const data = {};
		Array.prototype.forEach.call( form.elements, function ( el ) {
			if ( ! el.name ) {
				return;
			}
			if ( el.type === 'checkbox' ) {
				data[ el.name ] = el.checked ? '1' : '';
				return;
			}
			data[ el.name ] = el.value;
		} );
		return data;
	}

	function onSubmit( form, token ) {
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			const kind = form.getAttribute( 'data-corex-account-form' );
			const path = ROUTES[ kind ];
			if ( ! path ) {
				return;
			}
			clearMessages();
			post( path, serialize( form ), token ).then( function ( out ) {
				answer( form, out );
				if ( out.ok && kind === 'profile' ) {
					loadSessions( token );
				}
				if ( out.ok && ( kind === 'login' || kind === 'reset' ) ) {
					window.setTimeout( function () {
						window.location.reload();
					}, 600 );
				}
			} );
		} );
	}

	function loadSessions( token ) {
		const list = document.querySelector(
			'[data-corex-account-session-list]'
		);
		if ( ! list ) {
			return;
		}
		get( 'account/sessions', token ).then( function ( data ) {
			list.textContent = '';
			( ( data && data.sessions ) || [] ).forEach( function ( s ) {
				const li = document.createElement( 'li' );
				li.textContent =
					( s.ua || i18n.session || 'Session' ) +
					( s.current
						? ' — ' + ( i18n.thisDevice || 'this device' )
						: '' );
				list.appendChild( li );
			} );
		} );
	}

	function loadNotifications( token ) {
		const list = document.querySelector(
			'[data-corex-account-notification-list]'
		);
		if ( ! list ) {
			return;
		}
		get( 'account/notifications', token ).then( function ( data ) {
			list.textContent = '';
			( ( data && data.notifications ) || [] ).forEach( function ( n ) {
				const li = document.createElement( 'li' );
				li.textContent =
					( n.kind || '' ) + ' · ' + ( n.occurredAt || '' );
				list.appendChild( li );
			} );
		} );
	}

	function wireActions( token ) {
		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-corex-account-action]' ),
			function ( btn ) {
				btn.addEventListener( 'click', function () {
					const action = btn.getAttribute(
						'data-corex-account-action'
					);
					const path =
						action === 'revoke-all'
							? 'account/sessions/revoke-all'
							: 'account/sessions/revoke-others';
					post( path, {}, token ).then( function ( out ) {
						answer(
							btn.closest( '[data-corex-account-sessions]' ),
							out
						);
						loadSessions( token );
					} );
				} );
			}
		);
	}

	function init() {
		const el = root();
		if ( ! el ) {
			return;
		}
		const token = nonce( el );

		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-corex-account-form]' ),
			function ( form ) {
				onSubmit( form, token );
			}
		);

		wireActions( token );
		loadSessions( token );
		loadNotifications( token );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )( window, document );
