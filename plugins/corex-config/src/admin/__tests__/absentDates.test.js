/**
 * Absent and unusable dates on real surfaces (spec 076, T039 / FR-018).
 *
 * The rule under test is narrow and easy to lose: a date that is missing says which kind of missing
 * it is, in words chosen by the field, and never produces any of the four things that make a broken
 * date look like a working one — `Invalid Date`, `NaN`, the Unix epoch, or today.
 *
 * Driven through the rendered components rather than through `formatDateTime` directly, because the
 * value of a per-field phrase is that the *call site* chose it, and only rendering proves it did.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import CorexTime, { CorexRelativeTime } from '../components/CorexTime.js';
import {
	installDateTimeConfig,
	removeDateTimeConfig,
} from '../../../../../tests/Support/adminDateTimeConfig.js';

let container;
let root;

function render( element ) {
	act( () => root.render( element ) );
}

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

beforeEach( () => {
	installDateTimeConfig();
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
	removeDateTimeConfig();
} );

/** The four ways a broken date normally reaches a screen looking like a real one. */
const FABRICATIONS = [ 'Invalid Date', 'NaN', '1970', 'Jan 1, 1970' ];

describe( 'every shape of absence', () => {
	it.each( [
		[ 'null', null ],
		[ 'undefined', undefined ],
		[ 'an empty string', '' ],
		[ 'whitespace', '   ' ],
		[ 'zero', 0 ],
		[ 'zero as text', '0' ],
		[ 'a negative sentinel', -1 ],
		[ 'malformed text', 'not-a-date' ],
		[ 'a partial date', '2026-08' ],
		[ 'an out-of-range date', '2026-13-45T99:99:99Z' ],
		[ 'a JSON null that became a string', 'null' ],
		[ 'an em dash', '—' ],
	] )( 'renders the field phrase for %s', ( _label, value ) => {
		render( <CorexTime value={ value } absent="No expiry" /> );

		expect( container.textContent ).toBe( 'No expiry' );

		for ( const fabrication of FABRICATIONS ) {
			expect( container.textContent ).not.toContain( fabrication );
		}
	} );

	it( 'never falls back to the current date', () => {
		// The most convincing failure: a null that renders as today looks exactly like a feature
		// that works, on every screen, until somebody checks a record they know is empty.
		const thisYear = String( new Date().getFullYear() );

		render( <CorexTime value={ null } absent="Not recorded" /> );

		expect( container.textContent ).not.toContain( thisYear );
	} );
} );

describe( 'the phrase belongs to the field', () => {
	it.each( [
		[ 'No expiry' ],
		[ 'Never updated' ],
		[ 'Not recorded' ],
		[ 'Time not recorded' ],
		[ 'Never edited' ],
	] )( 'renders "%s" when that is what the field means', ( phrase ) => {
		// These are the real phrases the converted call sites pass. A single shared word would be
		// less work and less true: "Never" and "No expiry" and "Not recorded" answer different
		// questions.
		render( <CorexTime value={ null } absent={ phrase } /> );

		expect( container.textContent ).toBe( phrase );
	} );

	it( 'falls back to the config phrase when a field offers none', () => {
		render( <CorexTime value={ null } /> );

		expect( container.textContent ).toBe( 'Not recorded' );
	} );
} );

describe( 'markup', () => {
	it( 'emits no time element for an absent value', () => {
		// A <time> with an empty or invented `datetime` tells every parser and screen reader that
		// a machine-readable date is present. Absence must not lie about that.
		render( <CorexTime value={ null } absent="Never" /> );

		expect( container.querySelector( 'time' ) ).toBeNull();
	} );

	it( 'keeps the class so the layout does not collapse', () => {
		// FR-018 forbids "a blank that breaks the layout" as much as it forbids a wrong date.
		render(
			<CorexTime
				value={ null }
				absent="Never"
				className="corex-date-cell"
			/>
		);

		expect(
			container.querySelector( '.corex-date-cell' ).textContent
		).toBe( 'Never' );
	} );

	it( 'degrades a relative time to the same phrase', () => {
		render(
			<CorexRelativeTime value={ null } absent="Time not recorded" />
		);

		expect( container.textContent ).toBe( 'Time not recorded' );
		expect( container.querySelector( 'time' ) ).toBeNull();
		expect( container.querySelector( '.corex-time__exact' ) ).toBeNull();
	} );
} );
