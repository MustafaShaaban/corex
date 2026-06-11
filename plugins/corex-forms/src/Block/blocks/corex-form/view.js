/**
 * Corex Form — front-end submit handler (the block's viewScript, loaded only where
 * the form renders). Drives the WHOLE submit lifecycle from the schema the server
 * embedded in `data-corex-schema`:
 *
 *   1. Validate on submit with the SHARED rules (validation.js) — instant feedback.
 *   2. If valid, POST the fields (incl. honeypot) as JSON to the secured REST
 *      endpoint with the WP REST nonce.
 *   3. Render server-side field errors the same way (the server re-validates the
 *      same schema and is authoritative).
 *
 * One reusable handler for every Corex form — never a bespoke script per form.
 */
import { __ } from '@wordpress/i18n';
import { validateForm } from './validation';

function collect( form ) {
	const data = {};
	form
		.querySelectorAll( 'input[name], textarea[name], select[name]' )
		.forEach( ( el ) => {
			let name = el.name;
			const isArray = name.endsWith( '[]' );
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

/**
 * Map a rule message key to a translated, human message. Keys match the PHP rules.
 */
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

function fieldWrapper( form, name ) {
	return form.querySelector( '[data-corex-field="' + CSS.escape( name ) + '"]' );
}

function clearErrors( form ) {
	form.querySelectorAll( '.corex-form__error' ).forEach( ( el ) => {
		el.textContent = '';
	} );
	form.querySelectorAll( '[aria-invalid="true"]' ).forEach( ( el ) => {
		el.removeAttribute( 'aria-invalid' );
	} );
}

function showErrors( form, errors ) {
	let firstControl = null;

	Object.keys( errors ).forEach( ( name ) => {
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

function setStatus( form, message ) {
	const status = form.querySelector( '.corex-form__status' );
	if ( status ) {
		status.textContent = message;
	}
}

function schemaOf( form ) {
	try {
		return JSON.parse( form.dataset.corexSchema || '[]' );
	} catch ( e ) {
		return [];
	}
}

function send( form ) {
	const values = collect( form );

	fetch( form.dataset.corexEndpoint, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': form.dataset.corexNonce,
		},
		body: JSON.stringify( values ),
	} )
		.then( ( response ) =>
			response.json().then( ( body ) => ( { ok: response.ok, body } ) )
		)
		.then( ( result ) => {
			if ( result.ok ) {
				form.reset();
				setStatus( form, form.dataset.corexSuccess );
				return;
			}
			if ( result.body && result.body.errors ) {
				showErrors( form, result.body.errors );
			}
			setStatus(
				form,
				( result.body && result.body.message ) || form.dataset.corexError
			);
		} )
		.catch( () => {
			setStatus( form, form.dataset.corexError );
		} );
}

function onSubmit( form, event ) {
	event.preventDefault();
	clearErrors( form );

	const errors = validateForm( schemaOf( form ), collect( form ) );

	if ( Object.keys( errors ).length > 0 ) {
		showErrors( form, errors );
		setStatus( form, form.dataset.corexError );
		return;
	}

	send( form );
}

document.querySelectorAll( '.corex-form' ).forEach( ( form ) => {
	form.addEventListener( 'submit', ( event ) => onSubmit( form, event ) );
} );
