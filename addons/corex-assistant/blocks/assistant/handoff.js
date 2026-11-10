/**
 * The assistant's hand-off form (spec 129, User Story 2): the visitor asks the team to call or write,
 * and, when the site offers times, picks one.
 *
 * It posts to the CoreX form `corex-assistant-callback` with the page's REST token, so the forms
 * engine's honeypot, checks, storage and the team's email all apply. The way to reach them leads: a
 * call or WhatsApp needs a number, an email needs an address, and the other is optional. The country
 * code is preset from the visitor's clock (dial-codes.js); the times are shown in their own time zone
 * (slots.js).
 */

import { countryOptions, presetCountry } from './dial-codes';
import { el } from './dom';
import { describeSlot, fetchSlots, slotGroups, visitorZone } from './slots';

const HONEYPOT = 'corex_hp';
const ID = 'corex-assistant-';
const CHANNELS = [
	[ 'call', 'channelCall' ],
	[ 'whatsapp', 'channelWhatsapp' ],
	[ 'email', 'channelEmail' ],
];
const NEEDS_PHONE = [ 'call', 'whatsapp' ];
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
		slot: el( 'select' ),
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
 * The times on offer, by the visitor's day; "any time" first.
 *
 * @param {HTMLSelectElement} select
 * @param {number[]}          slots
 * @param {Object}            config the chat's settings
 * @param {Function}          t      a string by key
 */
function fillSlots( select, slots, config, t ) {
	select.append( el( 'option', { value: '', text: t( 'slotAny' ) } ) );
	for ( const group of slotGroups( slots, config.locale, visitorZone() ) ) {
		select.append(
			el(
				'optgroup',
				{ label: group.day },
				group.times.map( ( time ) =>
					el( 'option', {
						value: String( time.value ),
						text: time.label,
					} )
				)
			)
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
	const digits = fields.phone.value.replace( /\D/g, '' ).length;
	if (
		( NEEDS_PHONE.includes( channel ) || digits > 0 ) &&
		digits < MIN_PHONE_DIGITS
	) {
		found.phone = t( 'phoneNeeded' );
	}
	if ( channel === 'email' && ! fields.email.value.trim() ) {
		found.email = t( 'emailNeeded' );
	}
	return found;
}

/**
 * Marks the fields the chosen channel needs as required, and the others as optional, in their labels too.
 *
 * @param {HTMLFormElement}         form
 * @param {Object}                  fields
 * @param {string}                  channel
 * @param {(key: string) => string} t
 */
function followChannel( form, fields, channel, t ) {
	const needs = {
		phone: NEEDS_PHONE.includes( channel ),
		email: channel === 'email',
	};
	for ( const [ field, required ] of Object.entries( needs ) ) {
		fields[ field ].required = required;
		const label = form.querySelector( `label[for="${ ID }${ field }"]` );
		const base = t( field === 'phone' ? 'fieldPhone' : 'fieldEmail' );
		if ( label ) {
			label.textContent = required
				? base
				: `${ base } ${ t( 'optional' ) }`;
		}
	}
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
 * The time the visitor asked for: a picked slot in both zones, else what they typed.
 *
 * @param {Object} fields
 * @param {Object} offer  the site's zone, from the slots route
 * @param {Object} config the chat's settings
 * @return {{time: string, slot: string, zone: string}} the three time fields
 */
function requestedTime( fields, offer, config ) {
	const zone = visitorZone();
	const at = Number( fields.slot.value );
	if ( fields.slot.isConnected && at > 0 ) {
		return {
			time: describeSlot( at, config.locale, zone, offer.zone ),
			slot: new Date( at * 1000 ).toISOString(),
			zone,
		};
	}
	return { time: fields.time.value.trim(), slot: '', zone };
}

/**
 * @param {Object}                  options
 * @param {Object}                  options.config     the chat's settings (form, slots, nonce, locale, countries)
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
	const timeRow = row( 'time', t( 'fieldTime' ), fields.time );

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
			channelChoice( t ),
			phoneRow,
			row( 'email', t( 'fieldEmail' ), fields.email ),
			timeRow,
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
	const channelOf = () =>
		form.querySelector( 'input[name="channel"]:checked' )?.value || 'call';
	followChannel( form, fields, channelOf(), t );
	form.querySelectorAll( 'input[name="channel"]' ).forEach( ( radio ) =>
		radio.addEventListener( 'change', () =>
			followChannel( form, fields, channelOf(), t )
		)
	);

	// With times on offer, the typed time gives way to a list of them in the visitor's own zone.
	let offer = { zone: '', slots: [] };
	const ready = fetchSlots( config.slots ).then( ( found ) => {
		offer = found;
		if ( found.slots.length === 0 ) {
			return;
		}
		fillSlots( fields.slot, found.slots, config, t );
		const slotRow = row( 'slot', t( 'fieldSlot' ), fields.slot );
		slotRow.append(
			el( 'p', {
				class: 'corex-assistant__sub',
				text: t( 'slotZone' ).replace( '%s', visitorZone() ),
			} )
		);
		timeRow.replaceWith( slotRow );
	} );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		await ready;
		clearProblems( form );
		const channel = channelOf();
		const found = problems( fields, channel, t );
		if ( Object.keys( found ).length ) {
			showProblems( form, found );
			form.querySelector( '[aria-invalid="true"]' )?.focus();
			return;
		}

		const phone = fields.phone.value.trim();
		submit.disabled = true;
		submit.textContent = t( 'sending' );
		const result = await post( config, {
			name: fields.name.value.trim(),
			channel,
			phone: phone ? `${ fields.dial.value } ${ phone }` : '',
			email: fields.email.value.trim(),
			...requestedTime( fields, offer, config ),
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
