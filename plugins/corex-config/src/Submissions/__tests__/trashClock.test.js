/**
 * How long the trash keeps a submission, as words and a date (spec 105, US3).
 */
import { deletesAt, normalizeInboxPage, trashKeeps } from '../inbox.js';

describe( 'how long the trash keeps a submission', () => {
	it.each( [
		[
			30,
			'A submission in the trash is deleted for good 30 days after it was moved there.',
		],
		[
			1,
			'A submission in the trash is deleted for good 1 day after it was moved there.',
		],
		[
			0,
			'A submission in the trash stays there until somebody restores it or deletes it for good.',
		],
	] )( 'is said in a line: %d', ( days, line ) => {
		expect( trashKeeps( days ) ).toBe( line );
	} );

	it( 'is what the server says, and no limit until it has', () => {
		expect( normalizeInboxPage( {} ).trashDays ).toBe( 0 );
		expect( normalizeInboxPage( { trash_days: 30 } ).trashDays ).toBe( 30 );
	} );
} );

describe( 'the day a trashed submission is deleted for good', () => {
	it( 'is the trash’s period counted from the day it went in', () => {
		expect( deletesAt( '2026-10-01T09:30:00+00:00', 30 ) ).toBe(
			'2026-10-31T09:30:00.000Z'
		);
	} );

	it.each( [
		[
			'the trash keeps it until somebody deletes it',
			'2026-10-01T09:30:00+00:00',
			0,
		],
		[ 'when it went in was not recorded', null, 30 ],
		[ 'what was recorded is not a date', 'yesterday-ish', 30 ],
	] )( 'is not given when %s', ( _name, trashedAt, days ) => {
		expect( deletesAt( trashedAt, days ) ).toBe( '' );
	} );
} );
