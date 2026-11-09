/**
 * The times the hand-off offers for a call (spec 129, User Story 9): asked once when the form opens,
 * shown in the visitor's own time zone by day, then by morning, afternoon and evening, with "Any
 * time" chosen until a time is. A few times at once, never a long list. Ported from Perego's slot
 * picker (spec 058 revision 2).
 */

import { el } from './dom';

/** Parts of the day, by the visitor's local hour: from 00:00, 12:00 and 17:00. */
const PARTS = [
	[ 'morning', 0 ],
	[ 'afternoon', 12 ],
	[ 'evening', 17 ],
];

/**
 * The times on offer, as UTC seconds; none when there is no address or the request fails.
 *
 * @param {string} url the slots route, '' while booking is off
 * @return {Promise<number[]>} the times, in order
 */
export async function loadSlots( url ) {
	if ( ! url ) {
		return [];
	}
	try {
		const response = await window.fetch( url, {
			credentials: 'same-origin',
		} );
		const body = await response.json();
		return Array.isArray( body?.slots )
			? body.slots.filter( Number.isFinite )
			: [];
	} catch {
		return [];
	}
}

/**
 * The visitor's time zone as the browser names it (Asia/Riyadh), or '' when it will not say.
 *
 * @return {string} an IANA name
 */
export function visitorZone() {
	try {
		return Intl.DateTimeFormat().resolvedOptions().timeZone || '';
	} catch {
		return '';
	}
}

/**
 * Dates and times in the page's language and the visitor's zone, with the Latin digits the rest of
 * the chat writes.
 *
 * @param {string} locale  the page's language
 * @param {string} zone    the visitor's time zone, '' for the browser's own clock
 * @param {Object} options Intl.DateTimeFormat options
 * @return {Intl.DateTimeFormat} the formatter
 */
function dateFormat( locale, zone, options ) {
	const withZone = zone ? { ...options, timeZone: zone } : options;
	try {
		return new Intl.DateTimeFormat( `${ locale }-u-nu-latn`, withZone );
	} catch {
		return new Intl.DateTimeFormat( 'en', options );
	}
}

/**
 * The times grouped by the visitor's local day, then by part of the day.
 *
 * @param {number[]} slots UTC seconds, in order
 * @param {string}   zone  the visitor's time zone
 * @return {Map<string, Map<string, number[]>>} day => part => times
 */
export function groupSlots( slots, zone ) {
	const dayKey = dateFormat( 'en', zone, {
		year: 'numeric',
		month: '2-digit',
		day: '2-digit',
	} );
	const hourOf = dateFormat( 'en', zone, {
		hour: 'numeric',
		hourCycle: 'h23',
	} );
	const days = new Map();
	for ( const slot of slots ) {
		const day = dayKey.format( slot * 1000 );
		const hour = Number.parseInt( hourOf.format( slot * 1000 ), 10 );
		const part = [ ...PARTS ]
			.reverse()
			.find( ( [ , from ] ) => hour >= from )[ 0 ];
		if ( ! days.has( day ) ) {
			days.set( day, new Map() );
		}
		const parts = days.get( day );
		if ( ! parts.has( part ) ) {
			parts.set( part, [] );
		}
		parts.get( part ).push( slot );
	}
	return days;
}

/**
 * Marks one button of a group as chosen.
 *
 * @param {HTMLElement} group  the buttons' parent
 * @param {HTMLElement} chosen the pressed one
 */
function press( group, chosen ) {
	group.querySelectorAll( '[aria-pressed]' ).forEach( ( node ) => {
		node.setAttribute( 'aria-pressed', String( node === chosen ) );
	} );
}

function choice( text, className, onClick ) {
	const button = el( 'button', {
		type: 'button',
		class: className,
		'aria-pressed': 'false',
		text,
	} );
	button.addEventListener( 'click', () => onClick( button ) );
	return button;
}

/**
 * The picker: a row of days, the chosen day's parts, the part's times, and "Any time".
 *
 * @param {number[]}                slots  UTC seconds, in order
 * @param {string}                  locale the page's language
 * @param {string}                  zone   the visitor's time zone
 * @param {(key: string) => string} t      a string by key
 * @return {{node: HTMLElement, value: () => string}} the fieldset, and the chosen time ('' for any)
 */
export function slotPicker( slots, locale, zone, t ) {
	const dayName = dateFormat( locale, zone, {
		weekday: 'short',
		day: 'numeric',
		month: 'short',
	} );
	const timeOf = dateFormat( locale, zone, {
		hour: 'numeric',
		minute: '2-digit',
	} );
	const days = groupSlots( slots, zone );
	let chosen = '';

	const dayRow = el( 'div', { class: 'corex-assistant__days' } );
	const partRow = el( 'div', { class: 'corex-assistant__parts' } );
	const timeGrid = el( 'div', { class: 'corex-assistant__times' } );
	const times = el( 'div', { class: 'corex-assistant__slot-times' }, [
		timeGrid,
	] );
	const any = el( 'button', {
		type: 'button',
		class: 'corex-assistant__time corex-assistant__any',
		'aria-pressed': 'true',
		text: t( 'anyTime' ),
	} );
	const set = el( 'fieldset', { class: 'corex-assistant__slots' }, [
		el( 'legend', { text: t( 'fieldSlot' ) } ),
		dayRow,
		partRow,
		times,
		any,
		el( 'p', {
			class: 'corex-assistant__slot-note',
			text: t( 'slotNote' ),
		} ),
	] );

	const showPart = ( partSlots, button ) => {
		press( partRow, button );
		timeGrid.replaceChildren(
			...partSlots.map( ( slot ) =>
				choice(
					timeOf.format( slot * 1000 ),
					'corex-assistant__time',
					( pressed ) => {
						chosen = String( slot );
						press( times, pressed );
						any.setAttribute( 'aria-pressed', 'false' );
					}
				)
			)
		);
	};
	const showDay = ( parts, button ) => {
		press( dayRow, button );
		partRow.replaceChildren(
			...[ ...parts ].map( ( [ part, partSlots ] ) =>
				choice( t( part ), 'corex-assistant__part', ( pressed ) =>
					showPart( partSlots, pressed )
				)
			)
		);
		showPart(
			parts.values().next().value,
			partRow.querySelector( '.corex-assistant__part' )
		);
	};

	for ( const parts of days.values() ) {
		const first = parts.values().next().value[ 0 ];
		dayRow.append(
			choice(
				dayName.format( first * 1000 ),
				'corex-assistant__day',
				( pressed ) => showDay( parts, pressed )
			)
		);
	}
	any.addEventListener( 'click', () => {
		chosen = '';
		press( times, null );
		any.setAttribute( 'aria-pressed', 'true' );
	} );

	const firstDay = dayRow.querySelector( '.corex-assistant__day' );
	if ( firstDay ) {
		showDay( days.values().next().value, firstDay );
	}

	return { node: set, value: () => chosen };
}
