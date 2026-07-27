/**
 * Tests for CorexTime (spec 076, T019).
 *
 * The claims worth testing are the ones the component exists to make automatic: a visible date is
 * always semantic, an absent one never pretends to be, and a relative time's exact value is real
 * text rather than a hover.
 *
 * Rendered through `createRoot` + `act`, the same way the Blog Pro panel tests do it — the repo has
 * no testing-library dependency and this spec adds none.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import CorexTime, { CorexRelativeTime } from '../CorexTime.js';

const CONFIG = {
	timezone: { name: 'Africa/Cairo', offsetMinutes: 0 },
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
	monthsShort: [],
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

let container;
let root;

const find = ( selector ) => container.querySelector( selector );

function render( element ) {
	act( () => root.render( element ) );
}

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	window.corexDateTime = CONFIG;
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
	delete window.corexDateTime;
} );

describe( 'a present date', () => {
	it( 'renders human text inside a time element carrying the machine value', () => {
		render( <CorexTime value="2026-08-01T19:20:00Z" /> );

		const element = find( 'time' );

		expect( element ).not.toBeNull();
		expect( element.textContent ).toBe( '1 August 2026 at 10:20 PM' );
		expect( element.getAttribute( 'datetime' ) ).toBe(
			'2026-08-01T19:20:00+00:00'
		);
	} );

	it( 'never renders the machine value as the visible text', () => {
		// The defect this replaces, stated as a test: twelve surfaces rendered the ISO string
		// itself to the operator.
		render( <CorexTime value="2026-08-01T19:20:00Z" /> );

		expect( container.textContent ).not.toMatch(
			/\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/
		);
	} );
} );

describe( 'an absent date', () => {
	it( 'renders the caller phrase, not a fabricated date', () => {
		render( <CorexTime value={ null } absent="No expiry" /> );

		expect( container.textContent ).toBe( 'No expiry' );
	} );

	it( 'does not claim a machine-readable date it does not have', () => {
		// A <time> with an empty datetime is a lie told to every parser and screen reader that
		// trusts the element.
		render( <CorexTime value={ null } absent="Never" /> );

		expect( find( 'time' ) ).toBeNull();
		expect( find( 'span' ).textContent ).toBe( 'Never' );
	} );

	it( 'treats zero as absent rather than as 1970', () => {
		render( <CorexTime value={ 0 } absent="Not recorded" /> );

		expect( container.textContent ).toBe( 'Not recorded' );
		expect( container.textContent ).not.toContain( '1970' );
	} );
} );

describe( 'relative time', () => {
	it( 'shows the exact date as text, not behind a hover', () => {
		// FR-013. The surface this replaces put the exact value in a `title` attribute, which a
		// touch user cannot open and a screen reader does not reliably announce.
		const twoHoursAgo = Math.floor( Date.now() / 1000 ) - 7200;

		render( <CorexRelativeTime value={ twoHoursAgo } /> );

		expect( container.textContent ).toContain( '2 hours ago' );
		expect( find( '.corex-time__exact' ).textContent ).toMatch(
			/\d{1,2} \w+ \d{4} at \d{1,2}:\d{2} [AP]M/
		);
		expect( find( '[title]' ) ).toBeNull();
	} );

	it( 'still marks the relative phrase up semantically', () => {
		const twoHoursAgo = Math.floor( Date.now() / 1000 ) - 7200;

		render( <CorexRelativeTime value={ twoHoursAgo } /> );

		expect( find( 'time' ).getAttribute( 'datetime' ) ).toMatch(
			/^\d{4}-\d{2}-\d{2}T/
		);
	} );

	it( 'renders only the phrase when there is no instant', () => {
		render( <CorexRelativeTime value={ null } absent="Never" /> );

		expect( container.textContent ).toBe( 'Never' );
		expect( find( 'time' ) ).toBeNull();
	} );
} );

describe( 'the other presentations', () => {
	it( 'renders date only', () => {
		render( <CorexTime value="2026-08-01T19:20:00Z" kind="date" /> );

		expect( container.textContent ).toBe( '1 August 2026' );
	} );

	it( 'renders time only', () => {
		render( <CorexTime value="2026-08-01T19:20:00Z" kind="time" /> );

		expect( container.textContent ).toBe( '10:20 PM' );
	} );
} );
