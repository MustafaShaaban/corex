/**
 * The Reply section of the pane, rendered (spec 106, slice 1; T013).
 *
 * What is under test is what a person meets: told before typing when a reply will not be
 * delivered, told afterwards what happened to theirs, and shown what was already answered.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import Reply from '../detail/Reply.js';
import {
	installDateTimeConfig,
	removeDateTimeConfig,
} from '../../../../../tests/Support/adminDateTimeConfig.js';

// `@wordpress/components` does not load under Jest here; its Button is a button.
jest.mock( '@wordpress/components', () => ( {
	Button: ( { variant, isDestructive, children, ...rest } ) => (
		<button type="button" { ...rest }>
			{ children }
		</button>
	),
} ) );

const SETTINGS = 'https://example.test/wp-admin/admin.php?page=corex-settings';

const submission = ( more = {} ) => ( {
	id: 41,
	form: 'lead',
	questions: [ { key: 'email', label: 'Email', type: 'email' } ],
	values: { email: 'salma@example.com' },
	replies: [],
	reply_delivery: { mode: 'deliver', code: 'live', reason: '' },
	...more,
} );

const REFUSED = {
	mode: 'refuse',
	code: 'live_off',
	reason: 'Replies are not being delivered: live delivery is switched off in CoreX → Settings → Mail.',
};

let container;
let root;
let inbox;

// The pane's own "one request at a time": here every request is let through at once.
const work = { pending: '', during: ( name, task ) => task() };

function show( record, settingsUrl = SETTINGS ) {
	act( () => {
		root.render(
			<Reply
				record={ record }
				inbox={ inbox }
				work={ work }
				mailSettingsUrl={ settingsUrl }
			/>
		);
	} );
}

const button = ( name ) =>
	[ ...container.querySelectorAll( 'button' ) ].find(
		( candidate ) => candidate.textContent.trim() === name
	);
const field = ( label ) =>
	container.querySelector(
		`#${ [ ...container.querySelectorAll( 'label' ) ]
			.find( ( candidate ) => candidate.textContent === label )
			.getAttribute( 'for' )
			.replace( /:/g, '\\:' ) }`
	);

function type( control, text ) {
	const { set } = Object.getOwnPropertyDescriptor(
		Object.getPrototypeOf( control ),
		'value'
	);
	act( () => {
		set.call( control, text );
		control.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
	} );
}

async function write( subject, message ) {
	type( field( 'Subject' ), subject );
	type( field( 'Message' ), message );
	await act( async () => {
		button( 'Send reply' ).click();
	} );
}

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	installDateTimeConfig();
} );

afterAll( removeDateTimeConfig );

beforeEach( () => {
	inbox = { reply: jest.fn(), resend: jest.fn(), log: jest.fn() };
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	document.body.innerHTML = '';
} );

// The region the outcome is announced from is there before there is anything to announce.
it( 'has its status region from the start, empty', () => {
	show( submission() );

	expect( container.querySelector( '[role="status"]' ).textContent ).toBe(
		''
	);
} );

describe( 'before anything is typed', () => {
	it( 'says replies are not being delivered, links to the setting, and offers no sending', () => {
		show( submission( { reply_delivery: REFUSED } ) );
		const notice = container.querySelector( '.corex-pane__reply-notice' );
		const link = notice.querySelector( 'a' );
		const send = button( 'Send reply' );

		expect( notice.textContent ).toContain(
			'live delivery is switched off'
		);
		expect( notice.classList.contains( 'is-warning' ) ).toBe( true );
		expect( link.textContent ).toBe( 'Open Mail settings' );
		expect( link.getAttribute( 'href' ) ).toBe( SETTINGS );
		expect( send.disabled ).toBe( true );
		// The fields carry the description: a button that is off is not reached by keyboard.
		expect( field( 'Subject' ).getAttribute( 'aria-describedby' ) ).toBe(
			notice.id
		);
		expect( field( 'Message' ).getAttribute( 'aria-describedby' ) ).toBe(
			notice.id
		);
	} );

	it( 'leaves the box usable while delivery is off, so what was started is not lost', () => {
		show( submission( { reply_delivery: REFUSED } ) );
		type( field( 'Subject' ), 'Re: your message' );
		type( field( 'Message' ), 'Hello Salma' );

		expect( field( 'Message' ).value ).toBe( 'Hello Salma' );
		expect( button( 'Send reply' ).disabled ).toBe( true );
	} );

	it( 'tells somebody who may not change settings who can', () => {
		show( submission( { reply_delivery: REFUSED } ), '' );
		const notice = container.querySelector( '.corex-pane__reply-notice' );

		expect( notice.querySelector( 'a' ) ).toBeNull();
		expect( notice.textContent ).toContain(
			'Somebody who manages CoreX settings can change this.'
		);
	} );

	it( 'says a reply is kept and not delivered on a development site, and lets it be sent', async () => {
		inbox.reply.mockResolvedValue( {
			tone: 'info',
			text: 'Reply kept on this site, not delivered to salma@example.com.',
			detail: 'Development mail is captured.',
			keep: false,
		} );
		show(
			submission( {
				reply_delivery: {
					mode: 'capture',
					code: 'development',
					reason: 'This is a development site. A reply is kept in Email Studio.',
				},
			} )
		);
		const notice = container.querySelector( '.corex-pane__reply-notice' );

		expect( notice.textContent ).toContain( 'development site' );
		expect( notice.classList.contains( 'is-info' ) ).toBe( true );

		await write( 'Re: your message', 'Hello Salma' );

		expect( inbox.reply ).toHaveBeenCalledWith(
			41,
			{ subject: 'Re: your message', body: 'Hello Salma' },
			'salma@example.com'
		);
	} );

	it( 'says nothing where a reply is simply delivered', () => {
		show( submission() );

		expect(
			container.querySelector( '.corex-pane__reply-notice' )
		).toBeNull();
		expect( button( 'Send reply' ).disabled ).toBe( true );
		type( field( 'Subject' ), 'Re: your message' );
		type( field( 'Message' ), 'Hello' );
		expect( button( 'Send reply' ).disabled ).toBe( false );
	} );

	it( 'says replies need the email add-on, and shows no box', () => {
		show(
			submission( {
				reply_delivery: {
					mode: 'unavailable',
					code: 'no_email_addon',
					reason: 'Replies need the CoreX Email add-on, which is not active on this site.',
				},
			} )
		);

		expect( container.textContent ).toContain(
			'Replies need the CoreX Email add-on'
		);
		expect( container.querySelector( 'textarea' ) ).toBeNull();
		expect( button( 'Send reply' ) ).toBeUndefined();
	} );
} );

describe( 'after a reply is sent', () => {
	it( 'says it was sent and to whom, and empties the box', async () => {
		inbox.reply.mockResolvedValue( {
			tone: 'success',
			text: 'Reply sent to salma@example.com.',
			detail: '',
			keep: false,
		} );
		show( submission() );

		await write( 'Re: your message', 'Hello Salma' );

		expect(
			container.querySelector( '.corex-pane__reply-outcome' ).textContent
		).toBe( 'Reply sent to salma@example.com.' );
		expect( container.querySelector( '[role="status"]' ).textContent ).toBe(
			'Reply sent to salma@example.com.'
		);
		expect( field( 'Subject' ).value ).toBe( '' );
		expect( field( 'Message' ).value ).toBe( '' );
		// The pressed button is off again; focus goes where the next reply starts.
		expect( document.activeElement ).toBe( field( 'Subject' ) );
	} );

	it( 'says it was not sent and why, as a failure, and keeps what was written', async () => {
		inbox.reply.mockResolvedValue( {
			tone: 'failure',
			text: 'The reply was not sent.',
			detail: 'The provider rejected or failed the email.',
			keep: true,
		} );
		show( submission() );

		await write( 'Re: your message', 'Hello Salma' );
		const said = container.querySelector( '[role="alert"]' );

		expect( said.textContent ).toContain( 'The reply was not sent.' );
		expect( said.textContent ).toContain(
			'The provider rejected or failed the email.'
		);
		expect( container.textContent ).not.toContain( 'Reply sent' );
		expect( container.querySelector( '[role="status"]' ).textContent ).toBe(
			''
		);
		expect( field( 'Subject' ).value ).toBe( 'Re: your message' );
		expect( field( 'Message' ).value ).toBe( 'Hello Salma' );
	} );

	it( 'says nothing of its own when the request itself was refused, and keeps what was written', async () => {
		// The pane's own failure line says why; this section must not add "sent".
		inbox.reply.mockResolvedValue( null );
		show( submission() );

		await write( 'Re: your message', 'Hello Salma' );

		expect(
			container.querySelector( '.corex-pane__reply-outcome' )
		).toBeNull();
		expect( container.querySelector( '[role="alert"]' ) ).toBeNull();
		expect( field( 'Message' ).value ).toBe( 'Hello Salma' );
	} );
} );

describe( 'what was already answered', () => {
	const answered = submission( {
		replies: [
			{
				id: 'one',
				subject: 'Re: your message',
				body_html:
					'<p>Hello <strong>Salma</strong></p><script>steal()</script><img src="x" onerror="steal()">',
				actor_name: 'Mustafa',
				created_at: '2026-10-11T09:00:00+00:00',
				state: 'sent',
				message: 'The provider accepted the email.',
			},
			{
				id: 'two',
				subject: 'A second try',
				body_html: '<p>Did you get it?</p>',
				actor_name: '',
				created_at: '2026-10-11T10:00:00+00:00',
				state: 'failed',
				message: 'The provider rejected or failed the email.',
			},
		],
	} );

	it( 'lists each reply with its subject, its text, who sent it and its state in words', () => {
		show( answered );
		const [ first, second ] =
			container.querySelectorAll( '.corex-pane__reply' );

		expect( first.textContent ).toContain( 'Re: your message' );
		expect( first.textContent ).toContain( 'Mustafa' );
		expect( first.textContent ).toContain( 'Sent' );
		expect(
			first.querySelector( '.corex-pane__reply-body strong' ).textContent
		).toBe( 'Salma' );
		// The mail system's sentence is for a reply that did not go.
		expect( first.textContent ).not.toContain( 'accepted the email' );

		expect( second.textContent ).toContain( 'Not sent' );
		expect( second.textContent ).toContain(
			'The provider rejected or failed the email.'
		);
		expect( second.textContent ).toContain(
			'Somebody no longer on this site'
		);
	} );

	it( 'draws a stored reply as a reply and nothing else', () => {
		show( answered );
		const body = container.querySelector( '.corex-pane__reply-body' );

		expect( body.querySelector( 'script' ) ).toBeNull();
		expect( body.querySelector( 'img' ) ).toBeNull();
		expect( body.innerHTML ).not.toContain( 'steal' );
	} );

	it( 'still lists them where the submission has no address to answer', () => {
		show( { ...answered, questions: [], values: {} } );

		expect(
			container.querySelectorAll( '.corex-pane__reply' )
		).toHaveLength( 2 );
		expect( container.textContent ).toContain(
			'This submission has no email address to reply to.'
		);
		expect( container.querySelector( 'textarea' ) ).toBeNull();
	} );
} );
