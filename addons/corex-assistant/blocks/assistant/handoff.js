/**
 * The assistant's hand-off form (spec 129, User Story 2): the visitor asks the team to call or write.
 *
 * It posts to the CoreX form `corex-assistant-callback` with the page's REST token, so the forms
 * engine's honeypot, checks, storage and the team's email all apply. The country code is preset from
 * the visitor's clock (dial-codes.js).
 */

import { countryOptions, presetCountry } from './dial-codes';
import { el } from './dom';

const HONEYPOT = 'corex_hp';
const ID = 'corex-assistant-';
const CHANNELS = [
	[ 'call', 'channelCall' ],
	[ 'whatsapp', 'channelWhatsapp' ],
	[ 'email', 'channelEmail' ],
];
const MIN_PHONE_DIGITS = 6;

function row( name, label, control ) {
	const id = ID + name;
	control.id = id;
	control.name = name;
	return el( 'div', { class: 'corex-assistant__row', 'data-field': name }, [
		el( 'label', { for: id, text: label } ),
		control,
		el( 'span', {
			class: 'corex-assistant__error',
			id: `${ id }-error`,
			hidden: true,
		} ),
	] );
}

function controls( t ) {
	return {
		name: el( 'input', {
			type: 'text',
			autocomplete: 'name',
			required: true,
			maxlength: '120',
		} ),
		dial: el( 'select', {
			class: 'corex-assistant__dial',
			'aria-label': t( 'fieldCountry' ),
			autocomplete: 'tel-country-code',
		} ),
		phone: el( 'input', {
			type: 'tel',
			autocomplete: 'tel-national',
			inputmode: 'tel',
			required: true,
			maxlength: '20',
			dir: 'ltr',
		} ),
		email: el( 'input', {
			type: 'email',
			autocomplete: 'email',
			maxlength: '160',
			dir: 'ltr',
		} ),
		time: el( 'input', { type: 'text', maxlength: '80' } ),
		attach: el( 'input', {
			type: 'checkbox',
			id: `${ ID }attach`,
			checked: true,
		} ),
		// For bots only: out of sight and out of the tab order, and named so a person's browser does not fill it.
		trap: el( 'input', {
			type: 'text',
			name: HONEYPOT,
			tabindex: '-1',
			autocomplete: 'off',
			class: 'corex-assistant__trap',
			'aria-hidden': 'true',
		} ),
	};
}

function channelChoice( t ) {
	const set = el( 'fieldset', { class: 'corex-assistant__channels' }, [
		el( 'legend', { text: t( 'fieldChannel' ) } ),
	] );
	CHANNELS.forEach( ( [ value, key ], index ) => {
		const radio = el( 'input', {
			type: 'radio',
			name: 'channel',
			value,
			checked: index === 0,
		} );
		set.append(
			el( 'label', { class: 'corex-assistant__seg' }, [
				radio,
				el( 'span', { text: t( key ) } ),
			] )
		);
	} );
	return set;
}

function fillCountries( dial, config ) {
	const first = config.countriesFirst;
	const preset = presetCountry( {
		country: config.country,
		siteZone: config.siteZone,
		first,
	} );
	for ( const country of countryOptions( config.locale, first ) ) {
		dial.append(
			el( 'option', {
				value: country.dial,
				text: country.label,
				selected: country.iso === preset,
			} )
		);
	}
}

/**
 * The fields the visitor must fix before anything is sent, with the message for each.
 *
 * @param {Object}                  fields  the form's controls
 * @param {string}                  channel how they asked to be reached
 * @param {(key: string) => string} t       a string by key
 * @return {Object} field => message
 */
export function problems( fields, channel, t ) {
	const found = {};
	if ( ! fields.name.value.trim() ) {
		found.name = t( 'nameNeeded' );
	}
	if ( fields.phone.value.replace( /\D/g, '' ).length < MIN_PHONE_DIGITS ) {
		found.phone = t( 'phoneNeeded' );
	}
	if ( channel === 'email' && ! fields.email.value.trim() ) {
		found.email = t( 'emailNeeded' );
	}
	return found;
}

function showProblems( form, found ) {
	for ( const [ field, message ] of Object.entries( found ) ) {
		const control = form.querySelector( `[data-field="${ field }"] input` );
		const slot = form.querySelector(
			`[data-field="${ field }"] .corex-assistant__error`
		);
		if ( ! control || ! slot ) {
			continue;
		}
		control.setAttribute( 'aria-invalid', 'true' );
		control.setAttribute( 'aria-describedby', slot.id );
		slot.textContent = String( message );
		slot.hidden = false;
	}
}

function clearProblems( form ) {
	form.querySelectorAll( '[aria-invalid]' ).forEach( ( node ) => {
		node.removeAttribute( 'aria-invalid' );
		node.removeAttribute( 'aria-describedby' );
	} );
	form.querySelectorAll(
		'.corex-assistant__error, .corex-assistant__form-error'
	).forEach( ( node ) => {
		node.hidden = true;
		node.textContent = '';
	} );
}

/**
 * Posts the request; answers whether it was taken and, if not, which fields the engine refused.
 *
 * @param {Object} config the chat's settings
 * @param {Object} body   the answers
 * @return {Promise<{ok: boolean, fields: string[]}>} the outcome
 */
async function post( config, body ) {
	try {
		const response = await window.fetch( config.form, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify( body ),
		} );
		const reply = await response.json().catch( () => ( {} ) );
		return {
			ok: response.ok && reply.ok !== false,
			fields: Object.keys( reply.errors || {} ),
		};
	} catch {
		return { ok: false, fields: [] };
	}
}

/**
 * @param {Object}                  options
 * @param {Object}                  options.config     the chat's settings (form, nonce, locale, countries)
 * @param {(key: string) => string} options.t          a string by key
 * @param {() => string}            options.transcript the conversation as the team reads it
 * @param {(name: string) => void}  options.onSent     called with the visitor's name once the team has the request
 * @return {HTMLFormElement} the form
 */
export function handoffForm( { config, t, transcript, onSent } ) {
	const fields = controls( t );
	const phoneRow = row( 'phone', t( 'fieldPhone' ), fields.phone );
	phoneRow.insertBefore(
		el( 'div', { class: 'corex-assistant__phone' }, [
			fields.dial,
			fields.phone,
		] ),
		phoneRow.querySelector( '.corex-assistant__error' )
	);

	const submit = el( 'button', {
		type: 'submit',
		class: 'corex-assistant__submit',
		text: t( 'submit' ),
	} );
	const formError = el( 'p', {
		class: 'corex-assistant__form-error',
		role: 'alert',
		hidden: true,
	} );
	const form = el(
		'form',
		{ class: 'corex-assistant__handoff', novalidate: true },
		[
			el( 'h3', { text: t( 'handoffTitle' ) } ),
			el( 'p', {
				class: 'corex-assistant__sub',
				text: t( 'handoffText' ),
			} ),
			row( 'name', t( 'fieldName' ), fields.name ),
			phoneRow,
			row( 'email', t( 'fieldEmail' ), fields.email ),
			channelChoice( t ),
			row( 'time', t( 'fieldTime' ), fields.time ),
			el(
				'label',
				{ class: 'corex-assistant__check', for: `${ ID }attach` },
				[ fields.attach, el( 'span', { text: t( 'attach' ) } ) ]
			),
			fields.trap,
			formError,
			submit,
		]
	);

	fillCountries( fields.dial, config );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		clearProblems( form );
		const channel =
			form.querySelector( 'input[name="channel"]:checked' )?.value ||
			'call';
		const found = problems( fields, channel, t );
		if ( Object.keys( found ).length ) {
			showProblems( form, found );
			form.querySelector( '[aria-invalid="true"]' )?.focus();
			return;
		}

		submit.disabled = true;
		submit.textContent = t( 'sending' );
		const result = await post( config, {
			name: fields.name.value.trim(),
			phone: `${ fields.dial.value } ${ fields.phone.value.trim() }`.trim(),
			email: fields.email.value.trim(),
			channel,
			time: fields.time.value.trim(),
			transcript: fields.attach.checked ? transcript() : '',
			page: window.location.href.slice( 0, 300 ),
			[ HONEYPOT ]: fields.trap.value,
		} );
		submit.disabled = false;
		submit.textContent = t( 'submit' );

		if ( result.ok ) {
			onSent( fields.name.value.trim() );
			return;
		}
		showProblems(
			form,
			Object.fromEntries(
				result.fields.map( ( field ) => [ field, t( 'formError' ) ] )
			)
		);
		formError.textContent = t( 'formError' );
		formError.hidden = false;
	} );

	return form;
}
