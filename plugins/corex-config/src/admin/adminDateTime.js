/**
 * The browser half of the CoreX admin date contract (spec 076, FR-001).
 *
 * It must produce the same characters as `AdminDateTimeFormatter` for the same instant. That is a
 * strong claim, so the design earns it structurally rather than by testing afterwards:
 *
 *   - **The words come from PHP.** Month names, meridiem markers and the format patterns arrive
 *     already translated in `window.corexDateTime`. This file never asks the platform for a month
 *     name, because `Intl` reads CLDR and WordPress reads the translation files, and in Arabic
 *     those disagree (أغسطس against آب). Two dictionaries cannot be reconciled by testing.
 *   - **The pattern comes from PHP too.** Both sides interpret the *same translated* format string,
 *     so a translator who reorders `j F Y` reorders it in both places at once. That is why this
 *     file implements a small subset of PHP's date tokens rather than mapping to `Intl` options.
 *   - **`Intl` is used only for arithmetic** — extracting numeric parts in the site's timezone,
 *     including daylight-saving, which it does unambiguously.
 *
 * With no config (a screen that loaded this outside wp-admin, or a test that did not stub it) every
 * function returns the absent phrase rather than guessing, because a wrong date is worse than none.
 */

/** The PHP date tokens this understands. Anything else is emitted literally. */
const SUPPORTED_TOKENS = 'jdDSNwzWFmMntLoYyaABgGhHisveIOPTZcrU';

/**
 * The config PHP handed across the boundary, or null when there is none.
 *
 * Read at call time rather than at module load: the localized global is printed by
 * `wp_localize_script` before the bundle executes in WordPress, but a test may install it later.
 *
 * @return {Object|null} The date/time config, or null when it is absent or malformed.
 */
function config() {
	const raw = typeof window !== 'undefined' ? window.corexDateTime : null;

	if ( ! raw || typeof raw !== 'object' || ! raw.patterns ) {
		return null;
	}

	return raw;
}

/**
 * Turn any stored or transported timestamp into a Date, or into null.
 *
 * Mirrors `Instant::parse` deliberately, including its two surprising rules: a non-positive integer
 * is an absence rather than 1970, and a relative expression is not a date. `new Date( 'now' )` is
 * invalid in JS where PHP would have parsed it, but `new Date( '' )` and `new Date( null )` are
 * not — `new Date( null )` is the epoch, which is exactly the fabricated date this must never
 * produce.
 *
 * @param {number|string|Date|null} value A timestamp in any shape CoreX stores.
 * @return {Date|null} The instant, or null when the value names none.
 */
export function toInstant( value ) {
	if ( value instanceof Date ) {
		return Number.isNaN( value.getTime() ) ? null : value;
	}

	if ( value === null || value === undefined || value === '' ) {
		return null;
	}

	if ( typeof value === 'number' ) {
		return value > 0 ? new Date( value * 1000 ) : null;
	}

	const text = String( value ).trim();

	if ( text === '' ) {
		return null;
	}

	if ( /^-?\d+$/.test( text ) ) {
		const seconds = Number.parseInt( text, 10 );
		return seconds > 0 ? new Date( seconds * 1000 ) : null;
	}

	// A naive 'Y-m-d H:i:s' was written by gmdate() and is therefore UTC. Left to the engine it
	// would be read as LOCAL time, shifting every stored value by the reader's own offset — the
	// precise defect this spec exists to remove.
	const naive = text.match(
		/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/
	);
	if ( naive ) {
		return new Date(
			`${ naive[ 1 ] }-${ naive[ 2 ] }-${ naive[ 3 ] }T${ naive[ 4 ] }:${
				naive[ 5 ]
			}:${ naive[ 6 ] || '00' }Z`
		);
	}

	const parsed = new Date( text );

	return Number.isNaN( parsed.getTime() ) ? null : parsed;
}

/**
 * The instant's calendar parts, as the site's timezone sees them.
 *
 * @param {Date}   instant  The moment to read.
 * @param {Object} timezone `{ name, offsetMinutes }` from the config.
 * @return {Object} `{ year, month, day, hour, minute, second, weekday }`, month 1-based.
 */
function partsInSiteZone( instant, timezone ) {
	if ( ! timezone || ! timezone.name ) {
		// A site configured by `gmt_offset` has no daylight-saving rules to apply — that is a
		// property of the configuration, not a shortcut — so plain epoch arithmetic is exact.
		const shifted = new Date(
			instant.getTime() + ( timezone?.offsetMinutes || 0 ) * 60000
		);

		return {
			year: shifted.getUTCFullYear(),
			month: shifted.getUTCMonth() + 1,
			day: shifted.getUTCDate(),
			hour: shifted.getUTCHours(),
			minute: shifted.getUTCMinutes(),
			second: shifted.getUTCSeconds(),
			weekday: shifted.getUTCDay(),
		};
	}

	// Numeric options only. `Intl` is asked for arithmetic, never for a word.
	const parts = new Intl.DateTimeFormat( 'en-US', {
		timeZone: timezone.name,
		year: 'numeric',
		month: 'numeric',
		day: 'numeric',
		hour: 'numeric',
		minute: 'numeric',
		second: 'numeric',
		weekday: 'short',
		hour12: false,
	} ).formatToParts( instant );

	const read = ( type ) =>
		Number.parseInt(
			parts.find( ( part ) => part.type === type )?.value || '0',
			10
		);

	const weekdays = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];

	return {
		year: read( 'year' ),
		month: read( 'month' ),
		day: read( 'day' ),
		// `hour12: false` yields 24 for midnight in some engines; 0 and 24 are the same instant.
		hour: read( 'hour' ) % 24,
		minute: read( 'minute' ),
		second: read( 'second' ),
		weekday: weekdays.indexOf(
			parts.find( ( part ) => part.type === 'weekday' )?.value
		),
	};
}

const pad = ( value ) => String( value ).padStart( 2, '0' );

/**
 * Render one PHP date token.
 *
 * Only the tokens the CoreX patterns actually use are meaningful; the rest are returned as
 * themselves so an unexpected pattern degrades to visible text rather than to a wrong date.
 *
 * @param {string} token The single format character.
 * @param {Object} parts Calendar parts in the site timezone.
 * @param {Object} cfg   The boundary config, for its translated words.
 * @return {string} The rendered token.
 */
function renderToken( token, parts, cfg ) {
	const hour12 = parts.hour % 12 === 0 ? 12 : parts.hour % 12;
	const meridiem = parts.hour < 12 ? 'am' : 'pm';

	switch ( token ) {
		case 'j':
			return String( parts.day );
		case 'd':
			return pad( parts.day );
		case 'n':
			return String( parts.month );
		case 'm':
			return pad( parts.month );
		case 'F':
			return cfg.months?.[ parts.month - 1 ] || String( parts.month );
		case 'M':
			return (
				cfg.monthsShort?.[ parts.month - 1 ] || String( parts.month )
			);
		case 'Y':
			return String( parts.year );
		case 'y':
			return pad( parts.year % 100 );
		case 'g':
			return String( hour12 );
		case 'h':
			return pad( hour12 );
		case 'G':
			return String( parts.hour );
		case 'H':
			return pad( parts.hour );
		case 'i':
			return pad( parts.minute );
		case 's':
			return pad( parts.second );
		case 'a':
			return cfg.meridiem?.[ meridiem ] || meridiem;
		case 'A':
			return (
				cfg.meridiem?.[ meridiem.toUpperCase() ] ||
				meridiem.toUpperCase()
			);
		default:
			return token;
	}
}

/**
 * Apply a PHP date format string to an instant, in the site's timezone.
 *
 * @param {string} pattern A PHP date format, from the config.
 * @param {Date}   instant The moment to render.
 * @param {Object} cfg     The boundary config.
 * @return {string} The formatted text.
 */
function applyPattern( pattern, instant, cfg ) {
	const parts = partsInSiteZone( instant, cfg.timezone );
	let out = '';

	for ( let index = 0; index < pattern.length; index++ ) {
		const character = pattern[ index ];

		// PHP's own escape: a backslash means "the next character is literal".
		if ( character === '\\' ) {
			out += pattern[ index + 1 ] || '';
			index++;
			continue;
		}

		out += SUPPORTED_TOKENS.includes( character )
			? renderToken( character, parts, cfg )
			: character;
	}

	return out;
}

/**
 * `sprintf`-style `%1$s`/`%2$s` substitution, which is all the connector pattern needs.
 *
 * Positional rather than sequential on purpose: a translator must be able to write `%2$s — %1$s`
 * and have the time lead the date.
 *
 * @param {string} pattern The connector pattern from the config.
 * @param {string} first   The first substitution, conventionally the date.
 * @param {string} second  The second, conventionally the time.
 * @return {string} The joined text.
 */
function fillPattern( pattern, first, second ) {
	return pattern.replace( /%1\$s/g, first ).replace( /%2\$s/g, second );
}

const MINUTE = 60;
const HOUR = 3600;
const DAY = 86400;
const WEEK = 604800;

/**
 * "2 hours ago", from the strings PHP sent.
 *
 * @param {Date}   instant The moment.
 * @param {Object} cfg     The boundary config.
 * @return {string} The phrase, or the absolute date once relative stops helping.
 */
function relative( instant, cfg ) {
	const elapsed = Math.round( ( Date.now() - instant.getTime() ) / 1000 );
	const strings = cfg.relative || {};

	// A negative elapsed time means clock skew, not a future event worth describing. A negative
	// duration on a screen is always a bug being shown to an operator.
	if ( elapsed < MINUTE ) {
		return strings.justNow || '';
	}

	const phrase = ( group, count ) => {
		const forms = strings[ group ] || {};
		const template = count === 1 ? forms.one : forms.other;

		return ( template || '%d' ).replace( '%d', String( count ) );
	};

	if ( elapsed < HOUR ) {
		return phrase( 'minutes', Math.round( elapsed / MINUTE ) );
	}
	if ( elapsed < DAY ) {
		return phrase( 'hours', Math.round( elapsed / HOUR ) );
	}
	if ( elapsed < WEEK ) {
		return phrase( 'days', Math.round( elapsed / DAY ) );
	}

	return full( instant, cfg );
}

function full( instant, cfg ) {
	return fillPattern(
		cfg.patterns.connector,
		applyPattern( cfg.patterns.date, instant, cfg ),
		applyPattern( cfg.patterns.time, instant, cfg )
	);
}

/**
 * Present a timestamp the way every CoreX admin surface should.
 *
 * @param {number|string|Date|null} value  Any stored or transported timestamp.
 * @param {string}                  kind   full | date | time | relative | exact.
 * @param {string}                  absent What to say when there is no instant. The caller
 *                                         chooses, because "Never", "No expiry" and "Not
 *                                         recorded" are different statements.
 * @return {{human: string, machine: string, isPresent: boolean}} The prepared date.
 */
export function formatDateTime( value, kind = 'full', absent = '' ) {
	const cfg = config();
	const instant = toInstant( value );

	if ( ! cfg || ! instant ) {
		return {
			human: absent || cfg?.absent || '',
			machine: '',
			isPresent: false,
		};
	}

	const machine = instant.toISOString().replace( /\.\d{3}Z$/, '+00:00' );

	const human = ( () => {
		switch ( kind ) {
			case 'date':
				return applyPattern( cfg.patterns.date, instant, cfg );
			case 'time':
				return applyPattern( cfg.patterns.time, instant, cfg );
			case 'relative':
				return relative( instant, cfg );
			case 'exact':
				return fillPattern(
					cfg.patterns.connector,
					applyPattern( cfg.patterns.date, instant, cfg ),
					applyPattern( cfg.patterns.exactTime, instant, cfg )
				);
			default:
				return full( instant, cfg );
		}
	} )();

	return { human, machine, isPresent: true };
}
