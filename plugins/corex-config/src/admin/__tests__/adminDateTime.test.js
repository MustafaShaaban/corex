/**
 * The browser half of the FR-001 parity check (spec 076, T018/T021).
 *
 * This reads the same committed fixture as `DateTimeParityTest.php` and asserts the same expected
 * strings. Neither suite computes what the other should produce — both are measured against a third
 * thing — so improving one implementation alone turns the other red, which is the only structure
 * that keeps "both sides format dates identically" true over time.
 */
const fixture = require( '../../../../../tests/Fixtures/datetime-parity.json' );
const { formatDateTime, toInstant } = require( '../adminDateTime.js' );

/**
 * The config PHP exports for an English site, as `AdminDateTimeFormatter::clientConfig()` builds
 * it. Asserted against the real thing by `DateTimeParityTest::it exports a client config…`, so
 * these values are not a guess about PHP's output — they are pinned on both sides.
 *
 * @param {string} timezoneName  IANA zone, or null for an offset-configured site.
 * @param {number} offsetMinutes Offset for a site with no named zone.
 * @return {Object} The boundary payload.
 */
function englishConfig( timezoneName, offsetMinutes = 0 ) {
	return {
		timezone: { name: timezoneName, offsetMinutes },
		locale: 'en_US',
		isRtl: false,
		months: [
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
		],
		monthsShort: [
			'Jan',
			'Feb',
			'Mar',
			'Apr',
			'May',
			'Jun',
			'Jul',
			'Aug',
			'Sep',
			'Oct',
			'Nov',
			'Dec',
		],
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

afterEach( () => {
	delete window.corexDateTime;
} );

describe( 'parity with the PHP formatter', () => {
	// Collected rather than asserted case by case, for the same reason the PHP side does it: a
	// format change breaks several cases at once, and seeing all of them names the cause.
	it( 'matches the committed fixture for every case', () => {
		const failures = [];

		for ( const testCase of fixture.cases ) {
			window.corexDateTime = englishConfig( testCase.site );

			for ( const [ kind, expected ] of Object.entries(
				testCase.expected
			) ) {
				const actual = formatDateTime( testCase.value, kind ).human;

				if ( actual !== expected ) {
					failures.push(
						`${ testCase.name } [${ kind }]: expected "${ expected }", got "${ actual }"`
					);
				}
			}
		}

		expect( failures ).toEqual( [] );
	} );

	it( 'treats every absent value in the fixture as absent', () => {
		window.corexDateTime = englishConfig( 'Africa/Cairo' );

		for ( const testCase of fixture.absent ) {
			const formatted = formatDateTime(
				testCase.value,
				'full',
				'Not recorded'
			);

			expect( {
				name: testCase.name,
				isPresent: formatted.isPresent,
				machine: formatted.machine,
			} ).toEqual( {
				name: testCase.name,
				isPresent: false,
				machine: '',
			} );
		}
	} );
} );

describe( 'reading stored timestamps', () => {
	it( 'reads a naive datetime as UTC, because gmdate wrote it', () => {
		// Left to the engine, `new Date('2026-08-01 19:20:00')` is read as LOCAL time and shifts
		// by the reader's own offset — the precise defect this spec removes.
		expect( toInstant( '2026-08-01 19:20:00' ).toISOString() ).toBe(
			'2026-08-01T19:20:00.000Z'
		);
	} );

	it( 'treats a non-positive integer as an absence, not as 1970', () => {
		// `new Date( 0 )` is the epoch and `new Date( null )` is too — both are fabricated dates
		// that look entirely real on a screen.
		expect( toInstant( 0 ) ).toBeNull();
		expect( toInstant( '0' ) ).toBeNull();
		expect( toInstant( -1 ) ).toBeNull();
		expect( toInstant( null ) ).toBeNull();
	} );

	it( 'reads every stored shape as the same instant', () => {
		const expected = 1785612000000;

		expect( toInstant( 1785612000 ).getTime() ).toBe( expected );
		expect( toInstant( '1785612000' ).getTime() ).toBe( expected );
		expect( toInstant( '2026-08-01T19:20:00Z' ).getTime() ).toBe(
			expected
		);
		expect( toInstant( '2026-08-01T21:20:00+02:00' ).getTime() ).toBe(
			expected
		);
		expect( toInstant( '2026-08-01 19:20:00' ).getTime() ).toBe( expected );
	} );
} );

describe( 'the site timezone, not the browser', () => {
	it( 'renders the site zone regardless of what the browser thinks', () => {
		window.corexDateTime = englishConfig( 'Africa/Cairo' );

		// This test runs in whatever timezone the Jest process happens to have. The assertion
		// holds because nothing in the formatter consults it.
		expect( formatDateTime( '2026-08-01T19:20:00Z' ).human ).toBe(
			'1 August 2026 at 10:20 PM'
		);
	} );

	it( 'does epoch arithmetic for a site configured by offset', () => {
		window.corexDateTime = englishConfig( null, 330 ); // UTC+5:30

		expect( formatDateTime( '2026-08-01T12:00:00Z', 'time' ).human ).toBe(
			'5:30 PM'
		);
	} );

	it( 'handles a negative offset', () => {
		window.corexDateTime = englishConfig( null, -180 );

		expect( formatDateTime( '2026-08-01T12:00:00Z', 'time' ).human ).toBe(
			'9:00 AM'
		);
	} );
} );

describe( 'the words come from the config, never from the platform', () => {
	it( 'uses the month names it was given, whatever the platform would say', () => {
		// The Arabic month name WordPress uses. `Intl` under ar would say أغسطس; this must render
		// what PHP sent, or the two halves of the product disagree in Arabic while both "work".
		window.corexDateTime = {
			...englishConfig( 'Africa/Cairo' ),
			months: [
				'كانون الثاني',
				'شباط',
				'آذار',
				'نيسان',
				'أيار',
				'حزيران',
				'تموز',
				'آب',
				'أيلول',
				'تشرين الأول',
				'تشرين الثاني',
				'كانون الأول',
			],
		};

		expect( formatDateTime( '2026-08-01T19:20:00Z', 'date' ).human ).toBe(
			'1 آب 2026'
		);
	} );

	it( 'uses the connector pattern it was given, and lets it reorder', () => {
		window.corexDateTime = {
			...englishConfig( 'Africa/Cairo' ),
			patterns: {
				...englishConfig( 'Africa/Cairo' ).patterns,
				connector: '%2$s — %1$s',
			},
		};

		expect( formatDateTime( '2026-08-01T19:20:00Z' ).human ).toBe(
			'10:20 PM — 1 August 2026'
		);
	} );

	it( 'honours a reordered date pattern', () => {
		window.corexDateTime = {
			...englishConfig( 'Africa/Cairo' ),
			patterns: {
				...englishConfig( 'Africa/Cairo' ).patterns,
				date: 'Y/m/d',
			},
		};

		expect( formatDateTime( '2026-08-01T19:20:00Z', 'date' ).human ).toBe(
			'2026/08/01'
		);
	} );
} );

describe( 'without a config', () => {
	it( 'says nothing rather than guessing', () => {
		// A screen that loaded this outside wp-admin has no site timezone to work from. A wrong
		// date is worse than none, so there is no fallback to the browser's own.
		expect(
			formatDateTime( '2026-08-01T19:20:00Z', 'full', 'Not recorded' )
		).toEqual( { human: 'Not recorded', machine: '', isPresent: false } );
	} );
} );

describe( 'the other presentations', () => {
	beforeEach( () => {
		window.corexDateTime = englishConfig( 'Africa/Cairo' );
	} );

	it( 'includes seconds only in exact', () => {
		const value = '2026-08-01T19:20:24Z';

		expect( formatDateTime( value, 'exact' ).human ).toBe(
			'1 August 2026 at 10:20:24 PM'
		);
		expect( formatDateTime( value, 'full' ).human ).not.toContain( ':24' );
	} );

	it( 'carries a machine value beside the human one', () => {
		expect( formatDateTime( '2026-08-01T19:20:00Z' ).machine ).toBe(
			'2026-08-01T19:20:00+00:00'
		);
	} );

	it( 'phrases recent moments relatively', () => {
		const now = Date.now();

		expect(
			formatDateTime( Math.floor( now / 1000 ) - 10, 'relative' ).human
		).toBe( 'Just now' );
		expect(
			formatDateTime( Math.floor( now / 1000 ) - 7200, 'relative' ).human
		).toBe( '2 hours ago' );
	} );

	it( 'does not describe a future instant as negative time ago', () => {
		expect(
			formatDateTime( Math.floor( Date.now() / 1000 ) + 30, 'relative' )
				.human
		).toBe( 'Just now' );
	} );

	it( 'falls back to the absolute date once relative stops helping', () => {
		const threeWeeksAgo = Math.floor( Date.now() / 1000 ) - 21 * 86400;

		expect( formatDateTime( threeWeeksAgo, 'relative' ).human ).toBe(
			formatDateTime( threeWeeksAgo, 'full' ).human
		);
	} );
} );
