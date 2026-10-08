/**
 * Deleting a submission for good, as state and words (spec 105, US2).
 *
 * Who is offered it, what they are told goes with a submission, and what they are told when some
 * could not be deleted, are decided here so they can be tested without a screen.
 */
import {
	VIEW_INBOX,
	VIEW_TRASH,
	bulkActionsFor,
	cannotDeleteReason,
	deleteConfirmation,
	deleteNotice,
	normalizeInboxPage,
} from '../inbox.js';

describe( 'who is offered a permanent delete', () => {
	const values = ( view, mayDelete ) =>
		bulkActionsFor( view, mayDelete ).map( ( action ) => action.value );

	it( 'is somebody who may, and only in the trash', () => {
		expect( values( VIEW_TRASH, true ) ).toEqual( [ 'restore', 'delete' ] );
		expect( values( VIEW_TRASH, false ) ).toEqual( [ 'restore' ] );
		// A submission is trashed first (FR-009): the inbox never offers it.
		expect( values( VIEW_INBOX, true ) ).not.toContain( 'delete' );
	} );

	it( 'is decided by what the server says of this person, and "no" until it has', () => {
		expect( normalizeInboxPage( {} ).mayDelete ).toBe( false );
		expect(
			normalizeInboxPage( { can_delete_permanently: true } ).mayDelete
		).toBe( true );
	} );

	it( 'is told why not, and what to do about it', () => {
		expect( cannotDeleteReason() ).toMatch( /permission you do not have/ );
		expect( cannotDeleteReason() ).toMatch( /Access & Abilities/ );
	} );
} );

describe( 'what a person is told before deleting', () => {
	it.each( [
		[
			1,
			'1 submission will be deleted for good. This cannot be undone.',
			'Delete 1 submission',
		],
		[
			3,
			'3 submissions will be deleted for good. This cannot be undone.',
			'Delete 3 submissions',
		],
	] )(
		'says how many, and that it cannot be undone: %d',
		( count, body, confirm ) => {
			expect( deleteConfirmation( count ) ).toMatchObject( {
				body,
				confirm,
			} );
		}
	);

	it( 'lists everything that goes with a submission', () => {
		const removes = deleteConfirmation( 1 ).removes.join( ' | ' );

		for ( const part of [
			/answers/i,
			/hidden fields/i,
			/consent/i,
			/notes/i,
			/history/i,
			/files/i,
			/emails/i,
		] ) {
			expect( removes ).toMatch( part );
		}
	} );

	it( 'says where a copy may still be, and how long for', () => {
		expect( deleteConfirmation( 1 ).exports ).toMatch( /30 days/ );
		expect( deleteConfirmation( 1 ).exports ).toMatch( /Recent exports/ );
	} );
} );

describe( 'what a person is told after', () => {
	it.each( [
		[ 1, 0, '1 submission deleted for good.' ],
		[ 4, 0, '4 submissions deleted for good.' ],
	] )( 'when all %d were deleted', ( deleted, failed, notice ) => {
		expect( deleteNotice( deleted, failed ) ).toBe( notice );
	} );

	it( 'says how many were not deleted, why, and that they are still in the trash', () => {
		const notice = deleteNotice( 2, 1 );

		expect( notice ).toMatch( /^2 submissions deleted for good\. / );
		expect( notice ).toMatch( /1 was not deleted/ );
		expect( notice ).toMatch(
			/a file uploaded with it could not be removed/
		);
	} );
} );
