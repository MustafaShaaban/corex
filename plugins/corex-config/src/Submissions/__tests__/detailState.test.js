/**
 * What the detail pane says about one submission (spec 103, US5).
 */
import {
	answersOf,
	assignmentOf,
	contactOf,
	historyLine,
	identityOf,
	ownerOptions,
	ownerValue,
	technicalGroups,
	untrackedReason,
} from '../detail/detailState.js';

const lead = ( overrides = {} ) => ( {
	id: 41,
	form: 'lead',
	flow: 'Lead form',
	flow_version_id: 0,
	owner_type: 'none',
	owner_key: '',
	owner_name: '',
	submitter_email: 'salma@example.com',
	questions: [
		{ key: 'name', label: 'Name', type: 'text' },
		{ key: 'email', label: 'Email', type: 'email' },
		{ key: 'phone', label: 'Phone', type: 'tel' },
		{
			key: 'looking_for',
			label: 'What are you looking for?',
			type: 'select',
		},
	],
	values: {
		name: 'Salma',
		email: 'salma@example.com',
		phone: '+20 101 699 9700',
		looking_for: 'Branding',
	},
	owners: [
		{ key: '7', label: 'Mona Adel' },
		{ key: '9', label: 'Omar Said' },
	],
	...overrides,
} );

describe( 'the answers', () => {
	it( 'are in the form’s order, under the form’s wording', () => {
		expect(
			answersOf( lead() ).map( ( answer ) => [
				answer.label,
				answer.value,
			] )
		).toEqual( [
			[ 'Name', 'Salma' ],
			[ 'Email', 'salma@example.com' ],
			[ 'Phone', '+20 101 699 9700' ],
			[ 'What are you looking for?', 'Branding' ],
		] );
	} );

	it( 'leave out a question nobody answered', () => {
		const answers = answersOf(
			lead( { values: { name: 'Salma', looking_for: 'Branding' } } )
		);

		expect( answers.map( ( answer ) => answer.key ) ).toEqual( [
			'name',
			'looking_for',
		] );
	} );

	it( 'keep an answer the form no longer asks for, under its key, last', () => {
		const answers = answersOf(
			lead( { values: { old_field: 'kept', name: 'Salma' } } )
		);

		expect( answers.map( ( answer ) => answer.label ) ).toEqual( [
			'Name',
			'old_field',
		] );
	} );

	it( 'fall back to keys when the form’s questions are not known', () => {
		expect(
			answersOf( lead( { questions: [] } ) ).map(
				( answer ) => answer.label
			)
		).toEqual( [ 'name', 'email', 'phone', 'looking_for' ] );
	} );
} );

describe( 'how to reach the sender', () => {
	it( 'is the email and the phone the form asked for', () => {
		expect( contactOf( lead() ) ).toEqual( {
			email: 'salma@example.com',
			phone: '+20 101 699 9700',
			phoneHref: '+201016999700',
		} );
	} );

	it( 'is the email answer when the submission recorded no sender address', () => {
		expect( contactOf( lead( { submitter_email: '' } ) ).email ).toBe(
			'salma@example.com'
		);
	} );

	it( 'is nothing for a form that asked for neither', () => {
		expect(
			contactOf(
				lead( {
					submitter_email: '',
					questions: [ { key: 'name', label: 'Name', type: 'text' } ],
				} )
			)
		).toEqual( { email: '', phone: '', phoneHref: '' } );
	} );
} );

describe( 'the assignment control', () => {
	it( 'lists nobody, then the people who can own a submission', () => {
		expect( ownerOptions( lead() ) ).toEqual( [
			{ value: 'none', label: 'Unassigned' },
			{ value: 'user:7', label: 'Mona Adel' },
			{ value: 'user:9', label: 'Omar Said' },
		] );
		expect( ownerValue( lead() ) ).toBe( 'none' );
	} );

	it( 'shows the person a submission is assigned to as chosen', () => {
		expect(
			ownerValue( lead( { owner_type: 'user', owner_key: '9' } ) )
		).toBe( 'user:9' );
	} );

	it.each( [
		[
			'a team',
			{ owner_type: 'team', owner_key: 'sales' },
			{ value: 'team:sales', label: 'team: sales' },
		],
		[
			'the form’s owner',
			{ owner_type: 'flow_owner', owner_key: '' },
			{ value: 'flow_owner:', label: 'The form’s owner' },
		],
		[
			'a person who can no longer manage submissions',
			{ owner_type: 'user', owner_key: '3', owner_name: 'Former Staff' },
			{ value: 'user:3', label: 'Former Staff' },
		],
	] )(
		'keeps %s in the list when that is the current owner',
		( _name, owner, option ) => {
			const options = ownerOptions( lead( owner ) );

			expect( options ).toHaveLength( 4 );
			expect( options[ 3 ] ).toEqual( option );
			expect( ownerValue( lead( owner ) ) ).toBe( option.value );
		}
	);

	it.each( [
		[ 'none', { owner_type: 'none', owner_key: '' } ],
		[ 'user:9', { owner_type: 'user', owner_key: '9' } ],
		[ 'team:sales', { owner_type: 'team', owner_key: 'sales' } ],
	] )( 'turns "%s" into what the route takes', ( value, assignment ) => {
		expect( assignmentOf( value ) ).toEqual( assignment );
	} );
} );

describe( 'the technical details', () => {
	it( 'are only the groups that hold something', () => {
		const groups = technicalGroups(
			lead( {
				hidden_metadata: {},
				utm: { source: 'newsletter' },
				consent_snapshot: null,
			} )
		);

		expect( groups.map( ( group ) => group.id ) ).toEqual( [ 'utm' ] );
		expect( groups[ 0 ].entries ).toEqual( [ [ 'source', 'newsletter' ] ] );
	} );

	it( 'are none at all when nothing was recorded', () => {
		expect( technicalGroups( lead() ) ).toEqual( [] );
	} );
} );

describe( 'a submission with no delivery recorded', () => {
	it( 'says a form defined in code does not record one', () => {
		expect( untrackedReason( lead() ) ).toMatch( /defined in code/ );
	} );

	it( 'says only that none was recorded for a flow', () => {
		expect( untrackedReason( lead( { flow_version_id: 9 } ) ) ).toBe(
			'No notification was recorded for this submission.'
		);
	} );

	it( 'says nothing when a delivery was recorded', () => {
		expect(
			untrackedReason( lead( { delivery: { status: 'failed' } } ) )
		).toBe( '' );
	} );
} );

it( 'names the form and the submission', () => {
	expect( identityOf( lead() ) ).toBe( 'Lead form · #41' );
	expect( identityOf( lead( { flow: '' } ) ) ).toBe( 'lead · #41' );
} );

describe( 'a line of the history', () => {
	const said = ( event, record = lead() ) => historyLine( event, record );

	it.each( [
		[ { stage: 'submitted', outcome: 'success' }, 'Submitted' ],
		[ { stage: 'read', outcome: 'success' }, 'Marked read' ],
		[ { stage: 'unread', outcome: 'success' }, 'Marked unread' ],
		[ { stage: 'note', outcome: 'success' }, 'Note added' ],
		[ { stage: 'email', outcome: 'success' }, 'Reply sent' ],
		[ { stage: 'email', outcome: 'failure' }, 'Reply could not be sent' ],
	] )( 'says what happened: %j', ( event, line ) => {
		expect( said( event ) ).toBe( line );
	} );

	it( 'names both statuses of a status change', () => {
		expect(
			said( {
				stage: 'status',
				outcome: 'success',
				summary: { from: 'new', to: 'in_progress' },
			} )
		).toBe( 'Status changed from New to In progress' );
	} );

	it( 'names the person a submission was assigned to', () => {
		const record = lead( {
			owners: [ { key: '7', label: 'Salma Adel' } ],
		} );

		expect(
			said( { stage: 'assignment', summary: { to: 'user:7' } }, record )
		).toBe( 'Assigned to Salma Adel' );
		expect(
			said( { stage: 'assignment', summary: { to: 'none' } }, record )
		).toBe( 'Unassigned' );
	} );

	it( 'does not print an owner it cannot name', () => {
		expect(
			said( { stage: 'assignment', summary: { to: 'user:99' } } )
		).toBe( 'Assignment changed' );
	} );

	it( 'says what became of the notification', () => {
		expect(
			said( {
				stage: 'notification',
				outcome: 'failure',
				summary: { delivery_status: 'bounced' },
			} )
		).toBe( 'Notification bounced' );
	} );

	it( 'keeps an event it does not know, as it was recorded', () => {
		expect( said( { stage: 'imported', outcome: 'success' } ) ).toBe(
			'imported: success'
		);
	} );
} );
