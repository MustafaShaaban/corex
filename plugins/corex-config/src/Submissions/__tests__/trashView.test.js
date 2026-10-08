/**
 * The inbox's trash, as state and words (spec 105, US1).
 *
 * The inbox has two views. What a person can do in each, what they are asked before submissions
 * move, and what they are told after, are decided here so they can be tested without a screen.
 */
import {
	VIEW_INBOX,
	VIEW_TRASH,
	buildInboxUrl,
	bulkActionsFor,
	inboxReducer,
	initialInboxState,
	trashConfirmation,
	trashNotice,
	viewCount,
} from '../inbox.js';

describe( 'the view', () => {
	it( 'is asked of the server only for the trash', () => {
		const base = '/corex/v1/submissions';

		expect(
			buildInboxUrl( base, { view: VIEW_TRASH, page: 1 } )
		).toContain( 'view=trash' );
		expect(
			buildInboxUrl( base, { view: VIEW_INBOX, page: 1 } )
		).not.toContain( 'view=' );
		expect( buildInboxUrl( base, { page: 1 } ) ).not.toContain( 'view=' );
	} );

	it.each( [
		[ VIEW_INBOX, 1, '1 accessible submission' ],
		[ VIEW_INBOX, 12, '12 accessible submissions' ],
		[ VIEW_TRASH, 1, '1 submission in the trash' ],
		[ VIEW_TRASH, 0, '0 submissions in the trash' ],
	] )( 'says how many the %s holds: %d', ( view, total, line ) => {
		expect( viewCount( view, total ) ).toBe( line );
	} );
} );

describe( 'the bulk actions', () => {
	const values = ( view ) =>
		bulkActionsFor( view ).map( ( action ) => action.value );

	it( 'offer moving to the trash in the inbox, last', () => {
		expect( values( VIEW_INBOX ) ).toEqual( [
			'mark_read',
			'assign',
			'mark_spam',
			'archive',
			'trash',
		] );
	} );

	it( 'offer only a restore in the trash: nothing else may change a trashed submission', () => {
		expect( values( VIEW_TRASH ) ).toEqual( [ 'restore' ] );
	} );
} );

describe( 'what a person is asked and told', () => {
	it.each( [
		[
			'trash',
			1,
			'1 submission will leave the inbox. It can be restored from the trash.',
			'Move to trash',
		],
		[
			'trash',
			3,
			'3 submissions will leave the inbox. They can be restored from the trash.',
			'Move to trash',
		],
		[
			'restore',
			2,
			'2 submissions will go back to the inbox, as they were.',
			'Restore',
		],
	] )(
		'before a %s of %d: how many, and what becomes of them',
		( action, count, body, confirm ) => {
			expect( trashConfirmation( action, count ) ).toMatchObject( {
				body,
				confirm,
			} );
		}
	);

	it.each( [
		[ 'trash', 1, '1 submission moved to the trash.' ],
		[ 'trash', 4, '4 submissions moved to the trash.' ],
		[ 'restore', 1, '1 submission restored.' ],
		[ 'restore', 4, '4 submissions restored.' ],
	] )( 'after a %s of %d', ( action, count, notice ) => {
		expect( trashNotice( action, count ) ).toBe( notice );
	} );
} );

describe( 'undo', () => {
	it( 'is offered with the notice that submissions were trashed, and gone at the next load', () => {
		const trashed = inboxReducer( initialInboxState, {
			type: 'message',
			message: '2 submissions moved to the trash.',
			undo: [ 31, 32 ],
		} );

		expect( trashed.undo ).toEqual( [ 31, 32 ] );
		expect( inboxReducer( trashed, { type: 'loading' } ).undo ).toEqual(
			[]
		);
	} );

	it( 'is not offered with any other notice', () => {
		expect(
			inboxReducer( initialInboxState, {
				type: 'message',
				message: 'Submission updated.',
			} ).undo
		).toEqual( [] );
	} );
} );
