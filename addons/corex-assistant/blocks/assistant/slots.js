/**
 * The times a visitor may ask the team to reach them at (spec 129, "pick a time").
 *
 * The site sends moments (UTC seconds) inside its own working hours, less the ones other visitors have
 * asked for; here they are shown in the visitor's own time zone, grouped by their day. A chosen one goes
 * back as it came, with the visitor's zone, and the site writes the line the team reads in both zones.
 * A time the team confirms, not a booking.
 */

/**
 * The visitor's time zone, as their browser reports it, or '' when it does not.
 *
 * @return {string} an IANA zone such as Asia/Riyadh
 */
export function visitorZone() {
	try {
		return Intl.DateTimeFormat().resolvedOptions().timeZone || '';
	} catch {
		return '';
	}
}

function format( locale, zone, options ) {
	try {
		return new Intl.DateTimeFormat( locale, {
			...options,
			timeZone: zone || undefined,
		} );
	} catch {
		return new Intl.DateTimeFormat( 'en', {
			...options,
			timeZone: zone || undefined,
		} );
	}
}

/**
 * The slots grouped by the visitor's day, each with its time in the visitor's zone.
 *
 * @param {number[]} slots  UTC seconds, in order
 * @param {string}   locale the page's language
 * @param {string}   zone   the visitor's zone ('' for the browser's own)
 * @return {{day: string, times: {value: number, label: string}[]}[]} the days, in order
 */
export function slotGroups( slots, locale, zone ) {
	const day = format( locale, zone, {
		weekday: 'long',
		day: 'numeric',
		month: 'long',
	} );
	const time = format( locale, zone, { hour: 'numeric', minute: '2-digit' } );
	const groups = [];
	for ( const at of slots ) {
		const moment = new Date( at * 1000 );
		const label = day.format( moment );
		if (
			groups.length === 0 ||
			groups[ groups.length - 1 ].day !== label
		) {
			groups.push( { day: label, times: [] } );
		}
		groups[ groups.length - 1 ].times.push( {
			value: at,
			label: time.format( moment ),
		} );
	}
	return groups;
}

/** How long the times may take to arrive, in milliseconds. */
const SLOTS_TIMEOUT = 5000;

/**
 * The slots the site offers now, or none when they cannot be had.
 *
 * @param {string} url the slots route ('' when the site offers no times)
 * @return {Promise<{zone: string, slots: number[]}>} the business zone and the slots
 */
export async function fetchSlots( url ) {
	if ( ! url ) {
		return { zone: '', slots: [] };
	}
	// The form waits for this before it sends, so a slow answer falls back to the plain time field.
	const controller = new window.AbortController();
	const timer = window.setTimeout( () => controller.abort(), SLOTS_TIMEOUT );
	const body = await window
		.fetch( url, { credentials: 'same-origin', signal: controller.signal } )
		.then( ( response ) => ( response.ok ? response.json() : null ) )
		.catch( () => null );
	window.clearTimeout( timer );

	return {
		zone: typeof body?.zone === 'string' ? body.zone : '',
		slots: Array.isArray( body?.slots )
			? body.slots.filter( Number.isFinite )
			: [],
	};
}
