/**
 * The date/time boundary payload, for component tests.
 *
 * Any CoreX component that renders a date now reads `window.corexDateTime` — the single boundary
 * `CorexAdminAssets::enqueue()` localizes onto `corex-runtime` (spec 076, FR-008). A component test
 * that does not install it gets the absent phrase instead of a date, which is correct behaviour and
 * a confusing test failure.
 *
 * One shared fixture rather than a copy per suite, for the same reason the product has one boundary:
 * the values are a dictionary, and two copies of a dictionary drift. The English values here are
 * pinned against the real `AdminDateTimeFormatter::clientConfig()` output by
 * `DateTimeParityTest::it exports a client config the browser can reproduce this with`.
 *
 *   import { installDateTimeConfig } from '../../../../tests/Support/adminDateTimeConfig.js';
 *   beforeEach( installDateTimeConfig );
 */

const ENGLISH_MONTHS = [
	'January',
	'February',
	'March',
	'April',
	'May',
	'June',
	'July',
	'August',
	'September',
	'October',
	'November',
	'December',
];

/**
 * Build the payload for a given site timezone.
 *
 * @param {string} timezoneName  IANA zone name, or null for an offset-configured site.
 * @param {number} offsetMinutes Offset used when there is no named zone.
 * @return {Object} The boundary payload.
 */
export function adminDateTimeConfig( timezoneName = 'UTC', offsetMinutes = 0 ) {
	return {
		timezone: { name: timezoneName, offsetMinutes },
		locale: 'en_US',
		isRtl: false,
		months: ENGLISH_MONTHS,
		monthsShort: ENGLISH_MONTHS.map( ( month ) => month.slice( 0, 3 ) ),
		meridiem: { am: 'am', pm: 'pm', AM: 'AM', PM: 'PM' },
		patterns: {
			date: 'j F Y',
			time: 'g:i A',
			exactTime: 'g:i:s A',
			connector: '%1$s at %2$s',
		},
		relative: {
			justNow: 'Just now',
			minutes: { one: '%d minute ago', other: '%d minutes ago' },
			hours: { one: '%d hour ago', other: '%d hours ago' },
			days: { one: '%d day ago', other: '%d days ago' },
		},
		absent: 'Not recorded',
	};
}

/**
 * Install the payload for the duration of a test. Pair with {@link removeDateTimeConfig}.
 *
 * @param {string} timezoneName IANA zone the site is configured with.
 */
export function installDateTimeConfig( timezoneName = 'UTC' ) {
	window.corexDateTime = adminDateTimeConfig( timezoneName );
}

export function removeDateTimeConfig() {
	delete window.corexDateTime;
}
