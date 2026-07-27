/**
 * Corex client runtime (spec 043) — one buildless, jQuery-free `window.Corex` that
 * every Corex form and admin screen speaks through. No build step is required to use
 * it: it reads the WordPress globals (`wp.apiFetch`, `wp.i18n`) when present and
 * degrades to `fetch` + an identity translator otherwise (Principle IX).
 *
 *   Corex.api.{get,post,delete}  — nonce-attaching request → normalised envelope Result
 *   Corex.forms.bind(form)       — schema-mirrored validate → submit → render server errors
 *   Corex.loading                — disable/spinner/aria-busy/dedupe/restore
 *   Corex.notices                — accessible global status
 *
 * Events on `document`: corex:request:start, corex:request:end.
 * Events on the form:    corex:form:success, corex:form:error.
 *
 * @param {Window}   window   The browser window the runtime attaches to.
 * @param {Document} document That window's document.
 */
( function ( window, document ) {
	'use strict';

	const wp = window.wp || {};

	/*
	 * `wp-i18n` is a declared dependency of this script, so `wp.i18n.__` is there in
	 * WordPress. The identity fallback is for the buildless cases the runtime also has to
	 * survive — a page that loaded it directly, and the Jest suite. Binding the real
	 * function rather than wrapping a call around it is what lets `wp i18n make-pot` see
	 * the literals at every call site below; a `t( text )` wrapper hid all of them.
	 */
	const __ =
		wp.i18n && typeof wp.i18n.__ === 'function'
			? wp.i18n.__
			: function ( text ) {
					return text;
			  };

	function emit( target, name, detail ) {
		target.dispatchEvent(
			new CustomEvent( name, { detail, bubbles: true } )
		);
	}

	/* ----------------------------------------------------------------------- *
	 * Envelope normalisation — every response is coerced to { ok, ... }.
	 * ----------------------------------------------------------------------- */

	function isEnvelope( body ) {
		return (
			body !== null &&
			typeof body === 'object' &&
			typeof body.ok === 'boolean'
		);
	}

	function genericError( message ) {
		return {
			ok: false,
			code: 'error',
			message:
				message ||
				__( 'Something went wrong. Please try again.', 'corex' ),
			details: {},
		};
	}

	/**
	 * Describe a failure that carried no message of its own — a blank 5xx, an HTML error
	 * page, a proxy timeout. Naming the status is the difference between "the server broke"
	 * and "the network broke", which are not the same problem to chase.
	 *
	 * @param {number} status The HTTP status of the failed response, 0 when there was none.
	 * @return {string} A translated message naming the status, or '' when there is none.
	 */
	function statusMessage( status ) {
		if ( ! status ) {
			return ''; // No response at all — the caller's generic default is the honest one.
		}
		/* translators: %d: HTTP status code of the failed response. */
		const template = __(
			'The server returned an unexpected response (%d).',
			'corex'
		);

		return template.replace( '%d', String( status ) );
	}

	function normalise( body, httpOk, status ) {
		if ( isEnvelope( body ) ) {
			return body;
		}
		if ( httpOk ) {
			return {
				ok: true,
				message: '',
				data: body && typeof body === 'object' ? body : {},
			};
		}
		return genericError(
			( body && body.message ) || statusMessage( status )
		);
	}

	/* ----------------------------------------------------------------------- *
	 * Corex.api — always resolves to { ok, status, envelope }; never throws.
	 * ----------------------------------------------------------------------- */

	const DEFAULT_TIMEOUT = 15000;

	function nonceFor( opts ) {
		if ( opts && opts.nonce ) {
			return opts.nonce;
		}
		return ( window.corexRuntime && window.corexRuntime.nonce ) || '';
	}

	/**
	 * A Response is only useful to us if we can read a status and a body off it.
	 *
	 * @param {*} value The thing a request handler resolved or rejected with.
	 * @return {boolean} Whether it behaves like a Response.
	 */
	function isResponse( value ) {
		return (
			value !== null &&
			typeof value === 'object' &&
			typeof value.status === 'number' &&
			typeof value.json === 'function'
		);
	}

	function fromResponse( response ) {
		return response
			.json()
			.catch( function () {
				return null; // non-JSON / HTML body → described by status, never a parse throw
			} )
			.then( function ( body ) {
				return {
					ok: response.ok,
					status: response.status,
					envelope: normalise( body, response.ok, response.status ),
				};
			} );
	}

	function viaApiFetch( url, method, data, opts ) {
		const nonce = nonceFor( opts );
		return wp
			.apiFetch( {
				url,
				method,
				data,
				parse: false,
				headers: nonce ? { 'X-WP-Nonce': nonce } : {},
			} )
			.then( fromResponse, function ( error ) {
				// With parse:false core does NOT resolve on a non-2xx — parseAndThrowError()
				// rethrows the raw Response (wp-includes/js/dist/api-fetch.js). Reading it here
				// is what keeps the server's own message: without this every 4xx/5xx fell into
				// request()'s blanket catch and surfaced as "Something went wrong", which is how
				// a plain 404 from the Email Studio spent a release looking like a mystery.
				if ( isResponse( error ) ) {
					return fromResponse( error );
				}
				throw error; // genuine transport failure — request() owns it
			} );
	}

	function viaFetch( url, method, data, opts ) {
		const controller =
			typeof AbortController !== 'undefined'
				? new AbortController()
				: null;
		const timeoutMs = ( opts && opts.timeoutMs ) || DEFAULT_TIMEOUT;
		const timer = controller
			? window.setTimeout( function () {
					controller.abort();
			  }, timeoutMs )
			: null;

		const headers = { Accept: 'application/json' };
		const nonce = nonceFor( opts );
		if ( nonce ) {
			headers[ 'X-WP-Nonce' ] = nonce;
		}
		const init = {
			method,
			headers,
			signal: controller ? controller.signal : undefined,
		};
		if ( data !== undefined && method !== 'GET' ) {
			headers[ 'Content-Type' ] = 'application/json';
			init.body = JSON.stringify( data );
		}

		return window.fetch( url, init ).then( function ( response ) {
			if ( timer ) {
				window.clearTimeout( timer );
			}
			return fromResponse( response );
		} );
	}

	function request( url, method, data, opts ) {
		emit( document, 'corex:request:start', { url, method } );

		const run =
			wp && typeof wp.apiFetch === 'function' ? viaApiFetch : viaFetch;

		return run( url, method, data, opts )
			.catch( function () {
				// Genuine transport failure only: no response ever arrived (network down,
				// timeout/abort, DNS). An error *response* is read by fromResponse() and keeps
				// its own message, so status 0 now means exactly "nothing came back".
				return { ok: false, status: 0, envelope: genericError() };
			} )
			.then( function ( result ) {
				emit( document, 'corex:request:end', {
					url,
					method,
					ok: result.ok,
				} );
				return result;
			} );
	}

	const api = {
		get( url, opts ) {
			return request( url, 'GET', undefined, opts );
		},
		post( url, data, opts ) {
			return request( url, 'POST', data || {}, opts );
		},
		patch( url, data, opts ) {
			return request( url, 'PATCH', data || {}, opts );
		},
		delete( url, opts ) {
			return request( url, 'DELETE', undefined, opts );
		},
	};

	/* ----------------------------------------------------------------------- *
	 * Corex.loading — disable + aria-busy + spinner + dedupe + restore.
	 * ----------------------------------------------------------------------- */

	const loading = {
		start( region, submitEl ) {
			if ( ! region || region.classList.contains( 'corex-is-loading' ) ) {
				return null; // dedupe: already loading
			}
			region.classList.add( 'corex-is-loading' );
			region.setAttribute( 'aria-busy', 'true' );

			const spinner = document.createElement( 'span' );
			spinner.className = 'corex-spinner';
			spinner.setAttribute( 'aria-hidden', 'true' );
			if ( submitEl ) {
				submitEl.disabled = true;
				submitEl.insertAdjacentElement( 'afterend', spinner );
			} else {
				region.appendChild( spinner );
			}

			return { region, submitEl, spinner };
		},
		stop( token ) {
			if ( ! token ) {
				return;
			}
			token.region.classList.remove( 'corex-is-loading' );
			token.region.removeAttribute( 'aria-busy' );
			if ( token.submitEl ) {
				token.submitEl.disabled = false;
			}
			if ( token.spinner && token.spinner.parentNode ) {
				token.spinner.parentNode.removeChild( token.spinner );
			}
		},
	};

	/* ----------------------------------------------------------------------- *
	 * Corex.notices — write the accessible global status.
	 * ----------------------------------------------------------------------- */

	const notices = {
		status( region, message, kind ) {
			const status = region.querySelector( '.corex-form__status' );
			if ( ! status ) {
				return;
			}
			status.textContent = message || '';
			status.classList.toggle( 'is-error', kind === 'error' );
			status.classList.toggle( 'is-success', kind === 'success' );
		},
	};

	/* ----------------------------------------------------------------------- *
	 * Validation — mirrors the PHP rules (Corex\Forms\Validation\Rules\*), driven
	 * by the schema the server embeds in data-corex-schema (spec 020). Returns the
	 * failing rule KEY per field; the server re-validates and stays authoritative.
	 * ----------------------------------------------------------------------- */

	function isEmpty( value ) {
		return (
			value === null ||
			value === undefined ||
			String( value ).trim() === ''
		);
	}

	function isNumericValue( value ) {
		if ( typeof value === 'number' ) {
			return ! Number.isNaN( value );
		}
		if ( typeof value !== 'string' ) {
			return false;
		}
		const trimmed = value.trim();
		return trimmed !== '' && ! Number.isNaN( Number( trimmed ) );
	}

	function length( value ) {
		return [].concat( Array.prototype.slice.call( String( value ) ) )
			.length;
	}

	const RULES = {
		required( value ) {
			return isEmpty( value ) ? 'required' : null;
		},
		email( value ) {
			if ( isEmpty( value ) ) {
				return null;
			}
			return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( String( value ) )
				? null
				: 'email';
		},
		max( value, params ) {
			if ( isEmpty( value ) ) {
				return null;
			}
			const limit = parseInt( ( params && params[ 0 ] ) || '0', 10 );
			const measured = isNumericValue( value )
				? Number( value )
				: length( value );

			return measured > limit ? 'max' : null;
		},
		min( value, params ) {
			if ( isEmpty( value ) ) {
				return null;
			}
			const limit = parseInt( ( params && params[ 0 ] ) || '0', 10 );
			const measured = isNumericValue( value )
				? Number( value )
				: length( value );

			return measured < limit ? 'min' : null;
		},
		numeric( value ) {
			if ( isEmpty( value ) ) {
				return null;
			}
			return isNumericValue( value ) ? null : 'numeric';
		},
	};

	function validateField( field, value ) {
		const rules = field.rules || [];
		for ( let i = 0; i < rules.length; i++ ) {
			const spec = rules[ i ];
			const rule = RULES[ spec.rule ];
			if ( ! rule ) {
				continue;
			}
			const error = rule( value, spec.params || [] );
			if ( error ) {
				return error; // bail per field — first failing rule wins (matches PHP)
			}
		}
		return null;
	}

	function validate( schema, values ) {
		const errors = {};
		( schema || [] ).forEach( function ( field ) {
			const present = Object.prototype.hasOwnProperty.call(
				values,
				field.name
			);
			if ( ! present && ! field.required ) {
				return;
			}
			const error = validateField(
				field,
				present ? values[ field.name ] : null
			);
			if ( error ) {
				errors[ field.name ] = error;
			}
		} );
		return errors;
	}

	function messageFor( key ) {
		switch ( key ) {
			case 'required':
				return __( 'This field is required.', 'corex' );
			case 'email':
				return __( 'Enter a valid email address.', 'corex' );
			case 'numeric':
				return __( 'Enter a number.', 'corex' );
			case 'max':
				return __( 'This value is too long.', 'corex' );
			case 'min':
				return __( 'This value is too short.', 'corex' );
			default:
				return __( 'Please check this field.', 'corex' );
		}
	}

	/* ----------------------------------------------------------------------- *
	 * Corex.forms — bind a schema-carrying form to the whole submit lifecycle.
	 * Reuses the spec-020 DOM contract so markup is unchanged.
	 * ----------------------------------------------------------------------- */

	function collect( form ) {
		const data = {};
		form.querySelectorAll(
			'input[name], textarea[name], select[name]'
		).forEach( function ( el ) {
			let name = el.name;
			const isArray = name.slice( -2 ) === '[]';
			if ( isArray ) {
				name = name.slice( 0, -2 );
			}
			if ( el.type === 'checkbox' ) {
				if ( isArray ) {
					if ( ! Array.isArray( data[ name ] ) ) {
						data[ name ] = [];
					}
					if ( el.checked ) {
						data[ name ].push( el.value );
					}
				} else {
					data[ name ] = el.checked ? el.value : '';
				}
				return;
			}
			if ( el.type === 'radio' ) {
				if ( el.checked ) {
					data[ name ] = el.value;
				} else if ( ! ( name in data ) ) {
					data[ name ] = '';
				}
				return;
			}
			data[ name ] = el.value;
		} );
		return data;
	}

	function fieldWrapper( form, name ) {
		return form.querySelector(
			'[data-corex-field="' +
				( window.CSS ? window.CSS.escape( name ) : name ) +
				'"]'
		);
	}

	function clearErrors( form ) {
		form.querySelectorAll( '.corex-form__error' ).forEach( function ( el ) {
			el.textContent = '';
		} );
		form.querySelectorAll( '[aria-invalid="true"]' ).forEach(
			function ( el ) {
				el.removeAttribute( 'aria-invalid' );
			}
		);
	}

	function showErrors( form, errors ) {
		let firstControl = null;
		Object.keys( errors ).forEach( function ( name ) {
			const wrapper = fieldWrapper( form, name );
			if ( ! wrapper ) {
				return;
			}
			const message = wrapper.querySelector( '.corex-form__error' );
			const control = wrapper.querySelector( 'input, textarea, select' );
			if ( message ) {
				message.textContent = messageFor( errors[ name ] );
			}
			if ( control ) {
				control.setAttribute( 'aria-invalid', 'true' );
				firstControl = firstControl || control;
			}
		} );
		if ( firstControl ) {
			firstControl.focus();
		}
	}

	function schemaOf( form ) {
		try {
			return JSON.parse( form.dataset.corexSchema || '[]' );
		} catch {
			return [];
		}
	}

	function successOf( form, envelope ) {
		let configured = {};
		try {
			configured = JSON.parse( form.dataset.corexSuccessConfig || '{}' );
		} catch {
			configured = {};
		}
		if (
			envelope.data &&
			envelope.data.success &&
			typeof envelope.data.success === 'object'
		) {
			return Object.assign( {}, configured, envelope.data.success );
		}
		return configured;
	}

	function renderSuccess( form, envelope ) {
		const success = successOf( form, envelope );
		const target =
			success.target_url || ( success.type === 'url' ? success.url : '' );
		if ( ( success.type === 'url' || success.type === 'page' ) && target ) {
			emit( form, 'corex:form:redirect', {
				url: target,
				success,
			} );
			window.location.assign( target );
			return;
		}
		if ( success.type && success.type !== 'inline' ) {
			emit( form, 'corex:form:custom-success', { success } );
		}
		notices.status(
			form,
			success.message || form.dataset.corexSuccess || envelope.message,
			'success'
		);
	}

	function submit( form ) {
		if ( form.dataset.corexBusy === '1' ) {
			return; // dedupe concurrent submits
		}
		form.dataset.corexBusy = '1';

		const submitEl = form.querySelector( '[type="submit"]' );
		const token = loading.start( form, submitEl );

		api.post( form.dataset.corexEndpoint, collect( form ), {
			nonce: form.dataset.corexNonce,
		} ).then( function ( result ) {
			loading.stop( token );
			delete form.dataset.corexBusy;

			const envelope = result.envelope;
			if ( envelope.ok ) {
				form.reset();
				renderSuccess( form, envelope );
				emit( form, 'corex:form:success', { envelope } );
				return;
			}
			if ( envelope.errors ) {
				showErrors( form, envelope.errors );
			}
			notices.status(
				form,
				envelope.message || form.dataset.corexError,
				'error'
			);
			emit( form, 'corex:form:error', { envelope } );
		} );
	}

	function onSubmit( form, event ) {
		event.preventDefault();
		clearErrors( form );

		const errors = validate( schemaOf( form ), collect( form ) );
		if ( Object.keys( errors ).length > 0 ) {
			showErrors( form, errors );
			notices.status( form, form.dataset.corexError, 'error' );
			return; // client error → no request leaves the browser
		}
		submit( form );
	}

	const forms = {
		bind( form ) {
			if ( ! form || form.dataset.corexBound === '1' ) {
				return; // idempotent
			}
			form.dataset.corexBound = '1';
			form.addEventListener( 'submit', function ( event ) {
				onSubmit( form, event );
			} );
		},
		validate,
	};

	function autoBind() {
		document.querySelectorAll( '.corex-form' ).forEach( forms.bind );
	}

	window.Corex = window.Corex || {};
	window.Corex.api = api;
	window.Corex.forms = forms;
	window.Corex.loading = loading;
	window.Corex.notices = notices;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', autoBind );
	} else {
		autoBind();
	}
} )( window, document );
