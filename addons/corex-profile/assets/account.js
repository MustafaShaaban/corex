/**
 * CoreX front-office account behavior — buildless, framework-free, jQuery-free.
 *
 * Progressive enhancement over the server-rendered account block: it intercepts the
 * login / register / recovery / profile forms and posts them to the `corex/v1/account/*`
 * REST routes with the REST nonce, surfacing the typed result message in a polite live
 * region. On the member view it also loads active sessions and recent activity and
 * wires the "sign out other/every session" actions. With no JS the forms still submit
 * (the REST routes accept a normal POST), so the account remains usable.
 *
 * Config comes from the localized `window.corexAccount = { restUrl, nonce }`.
 */
( function ( window, document ) {
	'use strict';

	var cfg = window.corexAccount || {};
	var i18n = cfg.i18n || {};
	var ROUTES = {
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

	function status( message, ok ) {
		var el = document.querySelector( '.corex-account__status' );
		if ( ! el ) {
			return;
		}
		el.textContent = message;
		el.hidden = ! message;
		el.setAttribute( 'data-state', ok ? 'ok' : 'error' );
	}

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
					return { ok: res.ok, data: data };
				} );
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
		var data = {};
		Array.prototype.forEach.call( form.elements, function ( el ) {
			if ( ! el.name ) {
				return;
			}
			data[ el.name ] =
				el.type === 'checkbox' ? ( el.checked ? '1' : '' ) : el.value;
		} );
		return data;
	}

	function onSubmit( form, token ) {
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			var kind = form.getAttribute( 'data-corex-account-form' );
			var path = ROUTES[ kind ];
			if ( ! path ) {
				return;
			}
			status( '', true );
			post( path, serialize( form ), token ).then( function ( out ) {
				var msg = ( out.data && out.data.message ) || '';
				status( msg, out.ok );
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
		var list = document.querySelector(
			'[data-corex-account-session-list]'
		);
		if ( ! list ) {
			return;
		}
		get( 'account/sessions', token ).then( function ( data ) {
			list.textContent = '';
			( ( data && data.sessions ) || [] ).forEach( function ( s ) {
				var li = document.createElement( 'li' );
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
		var list = document.querySelector(
			'[data-corex-account-notification-list]'
		);
		if ( ! list ) {
			return;
		}
		get( 'account/notifications', token ).then( function ( data ) {
			list.textContent = '';
			( ( data && data.notifications ) || [] ).forEach( function ( n ) {
				var li = document.createElement( 'li' );
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
					var action = btn.getAttribute(
						'data-corex-account-action'
					);
					var path =
						action === 'revoke-all'
							? 'account/sessions/revoke-all'
							: 'account/sessions/revoke-others';
					post( path, {}, token ).then( function ( out ) {
						status(
							( out.data && out.data.message ) || '',
							out.ok
						);
						loadSessions( token );
					} );
				} );
			}
		);
	}

	function init() {
		var el = root();
		if ( ! el ) {
			return;
		}
		var token = nonce( el );

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
