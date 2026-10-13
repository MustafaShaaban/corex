/**
 * CoreX front-office account behavior — buildless, framework-free, jQuery-free.
 *
 * Progressive enhancement over the server-rendered account block: it intercepts the
 * login / register / recovery / profile forms and posts them to the `corex/v1/account/*`
 * REST routes with the REST nonce, and writes the typed result message into the polite
 * live region under the button that asked, which it brings into the window when it is
 * outside it. A request that got no answer it can read is said there too. On the member
 * view it also loads active sessions and recent activity and wires the "sign out
 * other/every session" actions. Under each of those two headings it shows the list, or
 * that there is nothing to list, or that the list could not be loaded. With no JS the
 * block shows a note saying so
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
	const LIST_NOTE_CLASS = 'corex-account__list-note';
	// The member view's two lists: where each is asked for, what the answer names it,
	// where it is drawn, and what stands in its place when there is nothing to draw.
	const SESSIONS = {
		path: 'account/sessions',
		key: 'sessions',
		selector: '[data-corex-account-session-list]',
		nothing:
			i18n.noSessions || 'No sessions are recorded for this account.',
		notLoaded:
			i18n.sessionsNotLoaded ||
			'Your sessions could not be loaded. ' +
				'Load this page again to see them.',
		label( session ) {
			return (
				( session.ua || i18n.session || 'Session' ) +
				( session.current
					? ' — ' + ( i18n.thisDevice || 'this device' )
					: '' )
			);
		},
	};
	const ACTIVITY = {
		path: 'account/notifications',
		key: 'notifications',
		selector: '[data-corex-account-notification-list]',
		nothing: i18n.noActivity || 'No activity yet.',
		notLoaded:
			i18n.activityNotLoaded ||
			'Your recent activity could not be loaded. ' +
				'Load this page again to see it.',
		label( event ) {
			return ( event.kind || '' ) + ' · ' + ( event.occurredAt || '' );
		},
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

	/**
	 * Asks for one of the member view's lists and resolves with its rows.
	 *
	 * It resolves whatever happens, with `null` where there is no list to show: a request
	 * that did not arrive, an answer that is not JSON, a refusal (which WordPress words
	 * for a developer: "Cookie check failed"), and an answer the list is not in.
	 *
	 * @param {string} path  The route, under the `corex/v1` namespace.
	 * @param {string} key   The name the answer gives the list under.
	 * @param {string} token The REST nonce.
	 * @return {Promise<?Array>} The rows, none of them for an empty list; `null` for no list.
	 */
	function get( path, key, token ) {
		return window
			.fetch( base() + path, {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': token },
			} )
			.then( function ( res ) {
				return res.json().then( function ( data ) {
					return res.ok && data && Array.isArray( data[ key ] )
						? data[ key ]
						: null;
				} );
			} )
			.catch( function () {
				return null;
			} );
	}

	// The paragraph that stands in a list's place, made the first time it is needed.
	function noteAfter( list ) {
		const next = list.nextElementSibling;
		if ( next && next.classList.contains( LIST_NOTE_CLASS ) ) {
			return next;
		}
		const note = document.createElement( 'p' );
		note.className = LIST_NOTE_CLASS;
		list.insertAdjacentElement( 'afterend', note );
		return note;
	}

	/**
	 * Draws under a list's heading what a request for the list came back with: its rows,
	 * or a sentence in their place saying that there are none, or that the list could not
	 * be loaded.
	 *
	 * Nobody pressed anything to get the sentence, so it is not an answer: it is written
	 * where the list would be, it empties no message line and it does not move the page.
	 * Rows drawn earlier are taken away either way: they are not what the site holds now.
	 *
	 * @param {Element} list The list's `<ul>`.
	 * @param {Object}  kind SESSIONS or ACTIVITY.
	 * @param {?Array}  rows What `get()` resolved with.
	 */
	function drawList( list, kind, rows ) {
		const note = noteAfter( list );
		const hasRows = rows !== null && rows.length > 0;
		list.textContent = '';
		( rows || [] ).forEach( function ( row ) {
			const li = document.createElement( 'li' );
			li.textContent = kind.label( row );
			list.appendChild( li );
		} );
		list.hidden = ! hasRows;
		note.hidden = hasRows;
		if ( hasRows ) {
			note.textContent = '';
			note.removeAttribute( 'data-state' );
			return;
		}
		note.textContent = rows ? kind.nothing : kind.notLoaded;
		note.setAttribute( 'data-state', rows ? 'empty' : 'error' );
	}

	function loadList( kind, token ) {
		const list = document.querySelector( kind.selector );
		if ( ! list ) {
			return;
		}
		get( kind.path, kind.key, token ).then( function ( rows ) {
			drawList( list, kind, rows );
		} );
	}

	// The page no longer shows who is looking at it: it is loaded again, once the answer
	// that says so has been on it for a moment.
	function reloadSoon() {
		window.setTimeout( function () {
			window.location.reload();
		}, 600 );
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
					loadList( SESSIONS, token );
				}
				if ( out.ok && ( kind === 'login' || kind === 'reset' ) ) {
					reloadSoon();
				}
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
					const everywhere = action === 'revoke-all';
					const path = everywhere
						? 'account/sessions/revoke-all'
						: 'account/sessions/revoke-others';
					post( path, {}, token ).then( function ( out ) {
						answer(
							btn.closest( '[data-corex-account-sessions]' ),
							out
						);
						// Signed out everywhere, this browser too: the site would refuse
						// the list, and the page is the signed-out one from here on.
						if ( out.ok && everywhere ) {
							reloadSoon();
							return;
						}
						loadList( SESSIONS, token );
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
		loadList( SESSIONS, token );
		loadList( ACTIVITY, token );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )( window, document );
