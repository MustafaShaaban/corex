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
 */
( function ( window, document ) {
	'use strict';

	var wp = window.wp || {};

	/** Translate via wp.i18n when present; identity fallback keeps it buildless. */
	function t( text ) {
		return wp && wp.i18n && typeof wp.i18n.__ === 'function'
			? wp.i18n.__( text, 'corex' )
			: text;
	}

	function emit( target, name, detail ) {
		target.dispatchEvent( new CustomEvent( name, { detail: detail, bubbles: true } ) );
	}

	/* ----------------------------------------------------------------------- *
	 * Envelope normalisation — every response is coerced to { ok, ... }.
	 * ----------------------------------------------------------------------- */

	function isEnvelope( body ) {
		return body !== null && typeof body === 'object' && typeof body.ok === 'boolean';
	}

	function genericError( message ) {
		return { ok: false, code: 'error', message: message || t( 'Something went wrong. Please try again.' ), details: {} };
	}

	function normalise( body, httpOk ) {
		if ( isEnvelope( body ) ) {
			return body;
		}
		if ( httpOk ) {
			return { ok: true, message: '', data: body && typeof body === 'object' ? body : {} };
		}
		return genericError( body && body.message );
	}

	/* ----------------------------------------------------------------------- *
	 * Corex.api — always resolves to { ok, status, envelope }; never throws.
	 * ----------------------------------------------------------------------- */

	var DEFAULT_TIMEOUT = 15000;

	function nonceFor( opts ) {
		if ( opts && opts.nonce ) {
			return opts.nonce;
		}
		return ( window.corexRuntime && window.corexRuntime.nonce ) || '';
	}

	function viaApiFetch( url, method, data, opts ) {
		var nonce = nonceFor( opts );
		return wp.apiFetch( {
			url: url,
			method: method,
			data: data,
			parse: false,
			headers: nonce ? { 'X-WP-Nonce': nonce } : {},
		} ).then( function ( response ) {
			return response.json().then( function ( body ) {
				return { ok: response.ok, status: response.status, envelope: normalise( body, response.ok ) };
			} );
		} );
	}

	function viaFetch( url, method, data, opts ) {
		var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
		var timeoutMs = ( opts && opts.timeoutMs ) || DEFAULT_TIMEOUT;
		var timer = controller
			? window.setTimeout( function () {
				controller.abort();
			}, timeoutMs )
			: null;

		var headers = { Accept: 'application/json' };
		var nonce = nonceFor( opts );
		if ( nonce ) {
			headers[ 'X-WP-Nonce' ] = nonce;
		}
		var init = { method: method, headers: headers, signal: controller ? controller.signal : undefined };
		if ( data !== undefined && method !== 'GET' ) {
			headers[ 'Content-Type' ] = 'application/json';
			init.body = JSON.stringify( data );
		}

		return window.fetch( url, init ).then( function ( response ) {
			if ( timer ) {
				window.clearTimeout( timer );
			}
			return response
				.json()
				.catch( function () {
					return null; // non-JSON / HTML body → generic error, never a parse throw
				} )
				.then( function ( body ) {
					return { ok: response.ok, status: response.status, envelope: normalise( body, response.ok ) };
				} );
		} );
	}

	function request( url, method, data, opts ) {
		emit( document, 'corex:request:start', { url: url, method: method } );

		var run = wp && typeof wp.apiFetch === 'function' ? viaApiFetch : viaFetch;

		return run( url, method, data, opts )
			.catch( function () {
				// Network failure, timeout/abort, or apiFetch rejection without a parsable body.
				return { ok: false, status: 0, envelope: genericError() };
			} )
			.then( function ( result ) {
				emit( document, 'corex:request:end', { url: url, method: method, ok: result.ok } );
				return result;
			} );
	}

	var api = {
		get: function ( url, opts ) {
			return request( url, 'GET', undefined, opts );
		},
		post: function ( url, data, opts ) {
			return request( url, 'POST', data || {}, opts );
		},
		patch: function ( url, data, opts ) {
			return request( url, 'PATCH', data || {}, opts );
		},
		delete: function ( url, opts ) {
			return request( url, 'DELETE', undefined, opts );
		},
	};

	/* ----------------------------------------------------------------------- *
	 * Corex.loading — disable + aria-busy + spinner + dedupe + restore.
	 * ----------------------------------------------------------------------- */

	var loading = {
		start: function ( region, submitEl ) {
			if ( ! region || region.classList.contains( 'corex-is-loading' ) ) {
				return null; // dedupe: already loading
			}
			region.classList.add( 'corex-is-loading' );
			region.setAttribute( 'aria-busy', 'true' );

			var spinner = document.createElement( 'span' );
			spinner.className = 'corex-spinner';
			spinner.setAttribute( 'aria-hidden', 'true' );
			if ( submitEl ) {
				submitEl.disabled = true;
				submitEl.insertAdjacentElement( 'afterend', spinner );
			} else {
				region.appendChild( spinner );
			}

			return { region: region, submitEl: submitEl, spinner: spinner };
		},
		stop: function ( token ) {
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

	var notices = {
		status: function ( region, message, kind ) {
			var status = region.querySelector( '.corex-form__status' );
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
		return value === null || value === undefined || String( value ).trim() === '';
	}

	function isNumericValue( value ) {
		if ( typeof value === 'number' ) {
			return ! Number.isNaN( value );
		}
		if ( typeof value !== 'string' ) {
			return false;
		}
		var trimmed = value.trim();
		return trimmed !== '' && ! Number.isNaN( Number( trimmed ) );
	}

	function length( value ) {
		return [].concat( Array.prototype.slice.call( String( value ) ) ).length;
	}

	var RULES = {
		required: function ( value ) {
			return isEmpty( value ) ? 'required' : null;
		},
		email: function ( value ) {
			if ( isEmpty( value ) ) {
				return null;
			}
			return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( String( value ) ) ? null : 'email';
		},
		max: function ( value, params ) {
			if ( isEmpty( value ) ) {
				return null;
			}
			var limit = parseInt( ( params && params[ 0 ] ) || '0', 10 );
			return isNumericValue( value )
				? ( Number( value ) > limit ? 'max' : null )
				: ( length( value ) > limit ? 'max' : null );
		},
		min: function ( value, params ) {
			if ( isEmpty( value ) ) {
				return null;
			}
			var limit = parseInt( ( params && params[ 0 ] ) || '0', 10 );
			return isNumericValue( value )
				? ( Number( value ) < limit ? 'min' : null )
				: ( length( value ) < limit ? 'min' : null );
		},
		numeric: function ( value ) {
			if ( isEmpty( value ) ) {
				return null;
			}
			return isNumericValue( value ) ? null : 'numeric';
		},
	};

	function validateField( field, value ) {
		var rules = field.rules || [];
		for ( var i = 0; i < rules.length; i++ ) {
			var spec = rules[ i ];
			var rule = RULES[ spec.rule ];
			if ( ! rule ) {
				continue;
			}
			var error = rule( value, spec.params || [] );
			if ( error ) {
				return error; // bail per field — first failing rule wins (matches PHP)
			}
		}
		return null;
	}

	function validate( schema, values ) {
		var errors = {};
		( schema || [] ).forEach( function ( field ) {
			var present = Object.prototype.hasOwnProperty.call( values, field.name );
			if ( ! present && ! field.required ) {
				return;
			}
			var error = validateField( field, present ? values[ field.name ] : null );
			if ( error ) {
				errors[ field.name ] = error;
			}
		} );
		return errors;
	}

	function messageFor( key ) {
		switch ( key ) {
			case 'required':
				return t( 'This field is required.' );
			case 'email':
				return t( 'Enter a valid email address.' );
			case 'numeric':
				return t( 'Enter a number.' );
			case 'max':
				return t( 'This value is too long.' );
			case 'min':
				return t( 'This value is too short.' );
			default:
				return t( 'Please check this field.' );
		}
	}

	/* ----------------------------------------------------------------------- *
	 * Corex.forms — bind a schema-carrying form to the whole submit lifecycle.
	 * Reuses the spec-020 DOM contract so markup is unchanged.
	 * ----------------------------------------------------------------------- */

	function collect( form ) {
		var data = {};
		form.querySelectorAll( 'input[name], textarea[name], select[name]' ).forEach( function ( el ) {
			var name = el.name;
			var isArray = name.slice( -2 ) === '[]';
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
		return form.querySelector( '[data-corex-field="' + ( window.CSS ? window.CSS.escape( name ) : name ) + '"]' );
	}

	function clearErrors( form ) {
		form.querySelectorAll( '.corex-form__error' ).forEach( function ( el ) {
			el.textContent = '';
		} );
		form.querySelectorAll( '[aria-invalid="true"]' ).forEach( function ( el ) {
			el.removeAttribute( 'aria-invalid' );
		} );
	}

	function showErrors( form, errors ) {
		var firstControl = null;
		Object.keys( errors ).forEach( function ( name ) {
			var wrapper = fieldWrapper( form, name );
			if ( ! wrapper ) {
				return;
			}
			var message = wrapper.querySelector( '.corex-form__error' );
			var control = wrapper.querySelector( 'input, textarea, select' );
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
		} catch ( e ) {
			return [];
		}
	}

	function successOf( form, envelope ) {
		var configured = {};
		try {
			configured = JSON.parse( form.dataset.corexSuccessConfig || '{}' );
		} catch ( e ) {
			configured = {};
		}
		if ( envelope.data && envelope.data.success && typeof envelope.data.success === 'object' ) {
			return Object.assign( {}, configured, envelope.data.success );
		}
		return configured;
	}

	function renderSuccess( form, envelope ) {
		var success = successOf( form, envelope );
		var target = success.target_url || ( success.type === 'url' ? success.url : '' );
		if ( ( success.type === 'url' || success.type === 'page' ) && target ) {
			emit( form, 'corex:form:redirect', { url: target, success: success } );
			window.location.assign( target );
			return;
		}
		if ( success.type && success.type !== 'inline' ) {
			emit( form, 'corex:form:custom-success', { success: success } );
		}
		notices.status( form, success.message || form.dataset.corexSuccess || envelope.message, 'success' );
	}

	function submit( form ) {
		if ( form.dataset.corexBusy === '1' ) {
			return; // dedupe concurrent submits
		}
		form.dataset.corexBusy = '1';

		var submitEl = form.querySelector( '[type="submit"]' );
		var token = loading.start( form, submitEl );

		api.post( form.dataset.corexEndpoint, collect( form ), { nonce: form.dataset.corexNonce } ).then( function ( result ) {
			loading.stop( token );
			delete form.dataset.corexBusy;

			var envelope = result.envelope;
			if ( envelope.ok ) {
				form.reset();
				renderSuccess( form, envelope );
				emit( form, 'corex:form:success', { envelope: envelope } );
				return;
			}
			if ( envelope.errors ) {
				showErrors( form, envelope.errors );
			}
			notices.status( form, envelope.message || form.dataset.corexError, 'error' );
			emit( form, 'corex:form:error', { envelope: envelope } );
		} );
	}

	function onSubmit( form, event ) {
		event.preventDefault();
		clearErrors( form );

		var errors = validate( schemaOf( form ), collect( form ) );
		if ( Object.keys( errors ).length > 0 ) {
			showErrors( form, errors );
			notices.status( form, form.dataset.corexError, 'error' );
			return; // client error → no request leaves the browser
		}
		submit( form );
	}

	var forms = {
		bind: function ( form ) {
			if ( ! form || form.dataset.corexBound === '1' ) {
				return; // idempotent
			}
			form.dataset.corexBound = '1';
			form.addEventListener( 'submit', function ( event ) {
				onSubmit( form, event );
			} );
		},
		validate: validate,
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
