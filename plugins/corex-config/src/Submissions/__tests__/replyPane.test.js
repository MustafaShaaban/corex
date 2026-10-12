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
import { PAUSE_BEFORE_SAVING } from '../detail/useReplyDraft.js';
import { usePending } from '../../admin/components/working.js';
import {
	installDateTimeConfig,
	removeDateTimeConfig,
} from '../../../../../tests/Support/adminDateTimeConfig.js';

// `@wordpress/components` does not load under Jest here; its Button is a button.
jest.mock( '@wordpress/components', () => {
	const { forwardRef } = require( '@wordpress/element' );

	return {
		// As the real one does, it hands a ref on to its button.
		Button: forwardRef(
			( { variant, isDestructive, children, ...rest }, ref ) => (
				<button ref={ ref } type="button" { ...rest }>
					{ children }
				</button>
			)
		),
	};
} );

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

// Whether a draft holds a word: its message is read as a page reads it, not taken apart.
function saysSomething( written ) {
	const message = document.createElement( 'div' );
	message.innerHTML = written.body;

	return ( written.subject + message.textContent ).trim() !== '';
}

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
// By id and not by selector: React's ids hold colons, which a selector would have to escape.
const field = ( label ) =>
	document.getElementById(
		[ ...container.querySelectorAll( 'label' ) ]
			.find( ( candidate ) => candidate.textContent === label )
			.getAttribute( 'for' )
	);

// The message is written in the editor: a text box that is not a form field.
const message = () => container.querySelector( '[role="textbox"]' );

function typeMessage( html ) {
	act( () => {
		message().innerHTML = html;
		message().dispatchEvent(
			new window.Event( 'input', { bubbles: true } )
		);
	} );
}

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

// A submission whose sender already has an email from the site about it.
const IN_A_CONVERSATION = {
	reply_subject: 'Re: Request a quote',
	reply_conversation: {
		available: true,
		subject: 'Re: We received your request',
	},
};
const way = ( label ) =>
	[ ...container.querySelectorAll( 'input[type="radio"]' ) ].find(
		( radio ) => radio.closest( 'label' ).textContent === label
	);

// A request whose answer the test gives when it chooses to.
const held = () => {
	let answer;
	const promise = new Promise( ( resolve ) => {
		answer = resolve;
	} );

	return {
		promise,
		answer: ( value ) => act( async () => answer( value ) ),
	};
};

// The pane's own "one request at a time", which names the control whose request is out.
function Pane( { record } ) {
	const [ pending, during ] = usePending();

	return (
		<Reply record={ record } inbox={ inbox } work={ { pending, during } } />
	);
}
const showInPane = ( record ) =>
	act( () => {
		root.render( <Pane record={ record } /> );
	} );

async function write( subject, said ) {
	type( field( 'Subject' ), subject );
	typeMessage( said );
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
	inbox = {
		reply: jest.fn(),
		resend: jest.fn(),
		log: jest.fn(),
		// As the route answers: what it kept, or nothing where what was written says nothing.
		keepReplyDraft: jest.fn( async ( id, written ) => ( {
			ok: true,
			draft: saysSomething( written )
				? { ...written, saved_at: '2026-10-11T10:00:00+00:00' }
				: null,
		} ) ),
		discardReplyDraft: jest.fn().mockResolvedValue( true ),
	};
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
		expect( message().getAttribute( 'aria-describedby' ) ).toBe(
			notice.id
		);
	} );

	it( 'leaves the box usable while delivery is off, so what was started is not lost', () => {
		show( submission( { reply_delivery: REFUSED } ) );
		type( field( 'Subject' ), 'Re: your message' );
		typeMessage( 'Hello Salma' );

		expect( message().innerHTML ).toBe( 'Hello Salma' );
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
			{
				subject: 'Re: your message',
				body: '<p>Hello Salma</p>',
				// No earlier email to this person is on record: a new one.
				thread: 'new',
			},
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
		typeMessage( 'Hello' );
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
		expect( message() ).toBeNull();
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
		expect( message().innerHTML ).toBe( '' );
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
		expect( message().innerHTML ).toBe( 'Hello Salma' );
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
		expect( message().innerHTML ).toBe( 'Hello Salma' );
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
		expect( message() ).toBeNull();
	} );
} );

// What is typed while a reply is on its way would be emptied with it when it has gone.
// Spec 106, FR-022: a reply that did not go can be sent again, as it was written, by whoever
// picks the lead up.
describe( 'a reply that did not go', () => {
	const kept = ( id, state, more = {} ) => ( {
		id,
		subject: 'Re: your message',
		body_html: '<p>Hello Salma</p>',
		actor_name: 'Mustafa',
		created_at: '2026-10-11T10:00:00+00:00',
		state,
		message: state === 'failed' ? 'The mail server refused it.' : '',
		attempts: [ { state } ],
		// The server says which replies did not go: one rule, and it is not the pane's.
		can_send_again: state === 'failed',
		...more,
	} );
	const listed = ( id ) =>
		[ ...container.querySelectorAll( '.corex-pane__reply' ) ].find(
			( reply ) => reply.dataset.reply === id
		);
	const again = ( id ) =>
		[ ...listed( id ).querySelectorAll( 'button' ) ].find(
			( candidate ) => candidate.textContent.trim() === 'Send again'
		);
	const announced = () =>
		container.querySelector( '[role="status"]' ).textContent;

	it( 'can be sent again where the server says so, whatever its state looks like', () => {
		show(
			submission( {
				replies: [
					kept( 'a', 'failed' ),
					// Being sent by a colleague at this moment.
					kept( 'b', 'failed', { can_send_again: false } ),
					kept( 'c', 'sent' ),
					// A sending that was begun and abandoned.
					kept( 'd', 'sending', { can_send_again: true } ),
					// A reply as an earlier release answered it: nothing said.
					kept( 'e', 'failed', { can_send_again: undefined } ),
				],
			} )
		);

		expect(
			[ 'a', 'b', 'c', 'd', 'e' ]
				.filter( ( id ) => again( id ) )
				.join( ' ' )
		).toBe( 'a d' );
	} );

	it( 'cannot where a reply from this site would be refused, or there is nobody to send it to', () => {
		show(
			submission( {
				replies: [ kept( 'a', 'failed' ) ],
				reply_delivery: REFUSED,
			} )
		);
		expect( again( 'a' ) ).toBeUndefined();

		show(
			submission( {
				replies: [ kept( 'a', 'failed' ) ],
				questions: [],
				values: {},
			} )
		);
		expect( again( 'a' ) ).toBeUndefined();
	} );

	it( 'is sent again when asked: that reply, to the person who wrote in, and what happened is said', async () => {
		inbox.sendReplyAgain = jest.fn().mockResolvedValue( {
			tone: 'success',
			text: 'Reply sent to salma@example.com.',
			detail: '',
			keep: false,
		} );
		showInPane(
			submission( {
				replies: [ kept( 'a', 'sent' ), kept( 'b', 'failed' ) ],
			} )
		);

		await act( async () => {
			again( 'b' ).click();
		} );

		expect( inbox.sendReplyAgain ).toHaveBeenCalledTimes( 1 );
		expect( inbox.sendReplyAgain ).toHaveBeenCalledWith(
			41,
			'b',
			'salma@example.com'
		);
		expect( announced() ).toBe( 'Reply sent to salma@example.com.' );
		// What is in the box is somebody's own, and is not sent or emptied by this.
		expect( inbox.reply ).not.toHaveBeenCalled();
	} );

	it( 'says it is working while it is sent again, holds every other button, and gives the keyboard back', async () => {
		const sending = held();
		inbox.sendReplyAgain = jest.fn().mockReturnValue( sending.promise );
		showInPane(
			submission( {
				replies: [ kept( 'a', 'failed' ), kept( 'b', 'failed' ) ],
			} )
		);
		typeMessage( '<p>Hello</p>' );
		act( () => again( 'a' ).focus() );

		await act( async () => {
			again( 'a' ).click();
		} );
		expect( again( 'a' ).getAttribute( 'aria-busy' ) ).toBe( 'true' );
		expect( again( 'b' ).disabled ).toBe( true );
		expect( again( 'b' ).getAttribute( 'aria-busy' ) ).toBeNull();
		expect( button( 'Send reply' ).disabled ).toBe( true );

		// It did not go this time either: the button is there to be pressed again.
		await sending.answer( {
			tone: 'failure',
			text: 'The reply was not sent.',
			detail: 'The mail server refused it.',
			keep: true,
		} );

		expect( again( 'a' ).disabled ).toBe( false );
		expect( document.activeElement ).toBe( again( 'a' ) );
		expect( container.textContent ).toContain( 'The reply was not sent.' );
		expect( message().textContent ).toBe( 'Hello' );
	} );

	it( 'puts the keyboard on the subject when it has gone, since its button went with it', async () => {
		const record = submission( { replies: [ kept( 'a', 'failed' ) ] } );
		inbox.sendReplyAgain = jest.fn( async () => {
			// As the inbox does before it answers: the submission is read again.
			showInPane( {
				...record,
				replies: [
					kept( 'a', 'sent', {
						attempts: [ { state: 'failed' }, { state: 'sent' } ],
						can_send_again: false,
					} ),
				],
			} );

			return {
				tone: 'success',
				text: 'Reply sent to salma@example.com.',
				detail: '',
				keep: false,
			};
		} );
		showInPane( record );

		await act( async () => {
			again( 'a' ).click();
		} );

		expect( again( 'a' ) ).toBeUndefined();
		expect( document.activeElement ).toBe( field( 'Subject' ) );
	} );

	// A reply sent again by its own writer, with the same words still in their box: the
	// reply has gone, and the box must not hold it to be sent a second time.
	it( 'empties the box of the same reply when the server says its draft went with it, and of nothing else', async () => {
		const record = submission( {
			reply_subject: 'Re: Request a quote',
			replies: [ kept( 'a', 'failed' ) ],
		} );
		const answer = ( draftEnded ) => ( {
			tone: 'success',
			text: 'Reply sent to salma@example.com.',
			detail: '',
			keep: false,
			draftEnded,
		} );
		inbox.sendReplyAgain = jest.fn().mockResolvedValue( answer( false ) );
		showInPane( record );
		type( field( 'Subject' ), 'Re: your message' );
		typeMessage( '<p>Hello Salma</p>' );

		await act( async () => {
			again( 'a' ).click();
		} );
		expect( message().textContent ).toBe( 'Hello Salma' );

		inbox.sendReplyAgain.mockResolvedValue( answer( true ) );
		await act( async () => {
			again( 'a' ).click();
		} );

		expect( message().textContent ).toBe( '' );
		expect( field( 'Subject' ).value ).toBe( 'Re: Request a quote' );
		expect( button( 'Send reply' ).disabled ).toBe( true );
	} );

	it( 'says how many times sending a reply was tried, once that is more than one', () => {
		show(
			submission( {
				replies: [
					kept( 'a', 'sent' ),
					kept( 'b', 'sent', {
						attempts: [ { state: 'failed' }, { state: 'sent' } ],
					} ),
				],
			} )
		);

		expect( listed( 'a' ).textContent ).not.toContain( 'Tried' );
		expect( listed( 'b' ).textContent ).toContain( 'Tried 2 times' );
	} );
} );

it( 'cannot be written in while a reply is being sent, and can while anything else is', () => {
	const during = ( pending ) => {
		act( () => {
			root.render(
				<Reply
					record={ submission() }
					inbox={ inbox }
					work={ { ...work, pending } }
				/>
			);
		} );

		return message().getAttribute( 'contenteditable' );
	};

	expect( during( '' ) ).toBe( 'true' );
	expect( during( 'reply' ) ).toBe( 'false' );
	expect( during( 'preview' ) ).toBe( 'true' );
	expect( during( 'note' ) ).toBe( 'true' );
} );

// Spec 106, FR-005: read as it will be sent, before it is sent.
describe( 'the preview', () => {
	const EMAIL = {
		html: '<!DOCTYPE html><html><body><p>Hello <strong>Salma</strong></p><script>steal()</script></body></html>',
		text: 'Hello Salma\n\n-- \nThis message was sent by Acme.',
	};
	const frame = () => document.body.querySelector( 'iframe' );

	async function preview() {
		type( field( 'Subject' ), 'Re: your message' );
		typeMessage( '<p>Hello <strong>Salma</strong></p>' );
		await act( async () => {
			button( 'Preview' ).click();
		} );
	}

	it( 'is offered once there is a subject and a message that says something', () => {
		show( submission() );
		expect( button( 'Preview' ).disabled ).toBe( true );

		type( field( 'Subject' ), 'Re: your message' );
		typeMessage( '<p><br></p>' );
		expect( button( 'Preview' ).disabled ).toBe( true );
		expect( button( 'Send reply' ).disabled ).toBe( true );

		typeMessage( '<p>Hello</p>' );
		expect( button( 'Preview' ).disabled ).toBe( false );
	} );

	it( 'asks the server for the email of what is written now, and shows that document', async () => {
		inbox.previewReply = jest.fn().mockResolvedValue( EMAIL );
		show( submission() );

		await preview();

		expect( inbox.previewReply ).toHaveBeenCalledWith( 41, {
			subject: 'Re: your message',
			body: '<p>Hello <strong>Salma</strong></p>',
		} );
		expect( frame().getAttribute( 'srcdoc' ) ).toBe( EMAIL.html );
		expect( frame().getAttribute( 'title' ) ).toBe(
			'The email as it will be sent'
		);
		expect( inbox.reply ).not.toHaveBeenCalled();
	} );

	it( 'shows it in a frame that runs nothing and reaches nothing', async () => {
		inbox.previewReply = jest.fn().mockResolvedValue( EMAIL );
		show( submission() );

		await preview();

		// An empty sandbox: no script, no form, no way out to the page around it.
		expect( frame().getAttribute( 'sandbox' ) ).toBe( '' );
	} );

	it( 'shows it at the width of a wide screen or of a phone, and says which', async () => {
		inbox.previewReply = jest.fn().mockResolvedValue( EMAIL );
		show( submission() );
		await preview();
		const width = () =>
			document.body.querySelector( '.corex-reply-preview__frame' ).dataset
				.width;
		const choice = ( name ) =>
			[ ...document.body.querySelectorAll( 'button' ) ].find(
				( candidate ) => candidate.textContent === name
			);

		expect( width() ).toBe( 'wide' );
		expect( choice( 'Wide screen' ).getAttribute( 'aria-pressed' ) ).toBe(
			'true'
		);

		act( () => choice( 'Phone' ).click() );

		expect( width() ).toBe( 'narrow' );
		expect( choice( 'Phone' ).getAttribute( 'aria-pressed' ) ).toBe(
			'true'
		);
		expect( choice( 'Wide screen' ).getAttribute( 'aria-pressed' ) ).toBe(
			'false'
		);
	} );

	it( 'shows the plain-text version, and what will happen to the reply where it is not simply delivered', async () => {
		inbox.previewReply = jest.fn().mockResolvedValue( EMAIL );
		show(
			submission( {
				reply_delivery: {
					mode: 'capture',
					code: 'development',
					reason: 'This is a development site. A reply is kept in Email Studio.',
				},
			} )
		);

		await preview();
		const dialog = document.body.querySelector( '.corex-reply-preview' );

		expect( dialog.querySelector( 'pre' ).textContent ).toBe( EMAIL.text );
		expect( dialog.textContent ).toContain( 'This is a development site.' );
	} );

	// The button is switched off while its request is out, so nothing has focus when the
	// dialog opens and the browser has nowhere to put it back: it was left on the page itself.
	it( 'puts the keyboard back on the button that opened it when it is left', async () => {
		inbox.previewReply = jest.fn().mockResolvedValue( EMAIL );
		show( submission() );
		await preview();

		act( () => {
			document.body
				.querySelector( '.corex-reply-preview .corex-dialog__close' )
				.click();
		} );

		expect( frame() ).toBeNull();
		expect( document.activeElement ).toBe( button( 'Preview' ) );
	} );

	it( 'says the subject as the server will send it, which is not always as typed', async () => {
		inbox.previewReply = jest
			.fn()
			.mockResolvedValue( { ...EMAIL, subject: 'Re: your message' } );
		show( submission() );
		type( field( 'Subject' ), '  Re:   your <b>message</b>  ' );
		typeMessage( '<p>Hello</p>' );
		await act( async () => {
			button( 'Preview' ).click();
		} );

		expect(
			document.body.querySelector( '.corex-reply-preview bdi' )
				.textContent
		).toBe( 'Re: your message' );
	} );

	it( 'shows nothing when the server would not build it: the pane says why', async () => {
		inbox.previewReply = jest.fn().mockResolvedValue( null );
		show( submission() );

		await preview();

		expect( frame() ).toBeNull();
	} );
} );

// Spec 106, FR-039 to FR-041 and FR-043: a reply joins the emails the person already has,
// or stands alone, and whoever writes it chooses.
describe( 'how a reply is sent', () => {
	const sentAs = () => inbox.reply.mock.calls.at( -1 )[ 1 ];
	const send = () =>
		act( async () => {
			button( 'Send reply' ).click();
		} );

	beforeEach( () => {
		inbox.reply.mockResolvedValue( {
			tone: 'success',
			text: 'Reply sent to salma@example.com.',
			detail: '',
			keep: false,
		} );
	} );

	it( 'is a new email, and says why, where no earlier email to this person is on record', async () => {
		show( submission( { reply_subject: 'Re: Request a quote' } ) );

		expect( container.querySelector( 'input[type="radio"]' ) ).toBeNull();
		expect(
			container.querySelector( '.corex-pane__reply-way' ).textContent
		).toContain( 'This will be a new email' );

		typeMessage( '<p>Hello</p>' );
		await send();

		expect( sentAs() ).toEqual( {
			subject: 'Re: Request a quote',
			body: '<p>Hello</p>',
			thread: 'new',
		} );
	} );

	it( 'offers the conversation they already have, chosen, with the subject that answers it', async () => {
		show( submission( IN_A_CONVERSATION ) );
		const group = container.querySelector( 'fieldset' );

		expect( group.querySelector( 'legend' ).textContent ).toBe(
			'How it is sent'
		);
		expect( way( 'In the conversation they already have' ).checked ).toBe(
			true
		);
		expect( way( 'As a new email' ).checked ).toBe( false );
		expect( field( 'Subject' ).value ).toBe(
			'Re: We received your request'
		);

		typeMessage( '<p>Hello</p>' );
		await send();

		expect( sentAs().thread ).toBe( 'conversation' );
		expect( sentAs().subject ).toBe( 'Re: We received your request' );
	} );

	it( 'takes the subject of the form when a new email is chosen, and the other back again', async () => {
		show( submission( IN_A_CONVERSATION ) );

		act( () => way( 'As a new email' ).click() );
		expect( field( 'Subject' ).value ).toBe( 'Re: Request a quote' );

		act( () => way( 'In the conversation they already have' ).click() );
		expect( field( 'Subject' ).value ).toBe(
			'Re: We received your request'
		);

		act( () => way( 'As a new email' ).click() );
		typeMessage( '<p>Hello</p>' );
		await send();

		expect( sentAs() ).toEqual( {
			subject: 'Re: Request a quote',
			body: '<p>Hello</p>',
			thread: 'new',
		} );
	} );

	it( 'leaves a subject somebody wrote as they wrote it, whichever way is chosen', () => {
		show( submission( IN_A_CONVERSATION ) );
		type( field( 'Subject' ), 'Your kitchen, next week' );

		act( () => way( 'As a new email' ).click() );

		expect( field( 'Subject' ).value ).toBe( 'Your kitchen, next week' );
	} );

	// Gmail keeps emails together by their subject as well as by what they answer.
	it( 'says a rewritten subject may part the reply from the conversation, and only then', () => {
		show( submission( IN_A_CONVERSATION ) );
		const hint = () =>
			[ ...container.querySelectorAll( '.corex-pane__muted' ) ].find(
				( line ) => line.textContent.includes( 'Gmail' )
			);
		expect( hint() ).toBeUndefined();

		type( field( 'Subject' ), 'Your kitchen, next week' );
		expect( hint() ).toBeDefined();
		expect(
			field( 'Subject' ).getAttribute( 'aria-describedby' )
		).toContain( hint().id );

		act( () => way( 'As a new email' ).click() );
		expect( hint() ).toBeUndefined();
	} );

	it( 'says in the preview which way the reply will be sent', async () => {
		inbox.previewReply = jest.fn().mockResolvedValue( {
			html: '<p>Hello</p>',
			text: 'Hello',
		} );
		show( submission( IN_A_CONVERSATION ) );
		typeMessage( '<p>Hello</p>' );

		await act( async () => {
			button( 'Preview' ).click();
		} );

		expect(
			document.body.querySelector( '.corex-reply-preview' ).textContent
		).toContain( 'It will be sent in the conversation they already have.' );
	} );

	it( 'says of each reply already sent which way it went', () => {
		const sent = ( id, thread ) => ( {
			id,
			subject: 'Re: Request a quote',
			body_html: '<p>Hello</p>',
			actor_name: 'Mustafa',
			created_at: '2026-10-12T10:00:00+00:00',
			state: 'sent',
			message: '',
			thread,
		} );
		show(
			submission( {
				replies: [
					sent( 'a', 'conversation' ),
					sent( 'b', 'new' ),
					// Kept before there was a choice.
					sent( 'c', undefined ),
				],
			} )
		);

		expect(
			[ ...container.querySelectorAll( '.corex-pane__reply' ) ].map(
				( listed ) =>
					[ 'In the conversation', 'A new email' ].find( ( said ) =>
						listed.textContent.includes( said )
					)
			)
		).toEqual( [ 'In the conversation', 'A new email', 'A new email' ] );
	} );

	it( 'keeps the way that was chosen when the reply was not sent', async () => {
		inbox.reply.mockResolvedValue( {
			tone: 'failure',
			text: 'Reply not sent.',
			detail: 'The mail server refused it.',
			keep: true,
		} );
		show( submission( IN_A_CONVERSATION ) );
		act( () => way( 'As a new email' ).click() );
		typeMessage( '<p>Hello</p>' );

		await send();

		expect( way( 'As a new email' ).checked ).toBe( true );
		expect( field( 'Subject' ).value ).toBe( 'Re: Request a quote' );
	} );

	// What is chosen while a reply is on its way would be about a reply that has gone.
	it( 'cannot be changed while the reply is on its way', async () => {
		const sending = held();
		inbox.reply.mockReturnValue( sending.promise );
		showInPane( submission( IN_A_CONVERSATION ) );
		typeMessage( '<p>Hello</p>' );
		const ways = () => [
			...container.querySelectorAll( 'input[type="radio"]' ),
		];
		expect( ways().map( ( radio ) => radio.disabled ) ).toEqual( [
			false,
			false,
		] );

		await send();
		expect( ways().map( ( radio ) => radio.disabled ) ).toEqual( [
			true,
			true,
		] );

		await sending.answer( {
			tone: 'failure',
			text: 'Reply not sent.',
			detail: '',
			keep: true,
		} );
		expect( ways().map( ( radio ) => radio.disabled ) ).toEqual( [
			false,
			false,
		] );
	} );

	// A reply sent as a new email is itself the email the next one answers.
	it( 'is the conversation again for the next reply, after one sent as a new email', async () => {
		const record = submission( IN_A_CONVERSATION );
		show( record );
		act( () => way( 'As a new email' ).click() );
		typeMessage( '<p>Hello</p>' );
		inbox.reply.mockImplementation( async () => {
			show( {
				...record,
				reply_conversation: {
					available: true,
					subject: 'Re: Request a quote',
				},
			} );

			return {
				tone: 'success',
				text: 'Reply sent.',
				detail: '',
				keep: false,
			};
		} );

		await send();

		expect( sentAs().thread ).toBe( 'new' );
		expect( way( 'In the conversation they already have' ).checked ).toBe(
			true
		);
		expect( field( 'Subject' ).value ).toBe( 'Re: Request a quote' );
	} );

	// The pane reads the submission again after every change to it. An email to continue
	// from can be there on the second reading and not on the first: a colleague replied.
	describe( 'when an email to continue from appears under an open box', () => {
		const appears = ( record ) =>
			show( { ...record, ...IN_A_CONVERSATION } );

		it( 'takes the subject that answers it, where nobody had written one', () => {
			const record = submission( {
				reply_subject: 'Re: Request a quote',
			} );
			show( record );
			typeMessage( '<p>Hello</p>' );

			appears( record );

			expect(
				way( 'In the conversation they already have' ).checked
			).toBe( true );
			expect( field( 'Subject' ).value ).toBe(
				'Re: We received your request'
			);
			// What was being written is not started over.
			expect( message().textContent ).toBe( 'Hello' );
		} );

		it( 'leaves a subject somebody wrote', () => {
			const record = submission( {
				reply_subject: 'Re: Request a quote',
			} );
			show( record );
			type( field( 'Subject' ), 'Your kitchen, next week' );

			appears( record );

			expect( field( 'Subject' ).value ).toBe(
				'Your kitchen, next week'
			);
		} );
	} );

	// The reply that went is now the email the next one answers: the pane reads the
	// submission again, and the box starts from that.
	it( 'starts the next reply from the one that has just gone', async () => {
		const first = submission( { reply_subject: 'Re: Request a quote' } );
		show( first );
		type( field( 'Subject' ), 'About your kitchen' );
		typeMessage( '<p>Hello</p>' );
		inbox.reply.mockImplementation( async () => {
			// As the inbox does before it answers: the submission is read again.
			show( {
				...first,
				reply_conversation: {
					available: true,
					subject: 'Re: About your kitchen',
				},
			} );

			return {
				tone: 'success',
				text: 'Reply sent.',
				detail: '',
				keep: false,
			};
		} );

		await send();

		expect( field( 'Subject' ).value ).toBe( 'Re: About your kitchen' );
		expect( way( 'In the conversation they already have' ).checked ).toBe(
			true
		);
		expect( message().textContent ).toBe( '' );
	} );
} );

// Spec 106, FR-035 and FR-036: the box opens with a subject from the form the person filled
// in, to keep or to change; and a subject nobody wrote is not something somebody began.
describe( 'the subject a reply starts with', () => {
	const WITH_A_SUBJECT = { reply_subject: 'Re: Request a quote' };
	const pause = () =>
		act( async () => {
			jest.advanceTimersByTime( PAUSE_BEFORE_SAVING * 4 );
		} );
	const leave = () => {
		act( () => root.unmount() );
		root = createRoot( container );
	};

	beforeEach( () => jest.useFakeTimers() );
	afterEach( () => jest.useRealTimers() );

	it( 'is there when the box opens, and can be changed', () => {
		show( submission( WITH_A_SUBJECT ) );

		expect( field( 'Subject' ).value ).toBe( 'Re: Request a quote' );
		// A subject alone is not a reply: there is nothing to send yet.
		expect( button( 'Send reply' ).disabled ).toBe( true );

		type( field( 'Subject' ), 'Your quote' );
		typeMessage( '<p>Hello</p>' );

		expect( field( 'Subject' ).value ).toBe( 'Your quote' );
		expect( button( 'Send reply' ).disabled ).toBe( false );
	} );

	it( 'is not kept as a draft while it is all the box holds', async () => {
		show( submission( WITH_A_SUBJECT ) );

		await pause();
		leave();
		await pause();

		expect( inbox.keepReplyDraft ).not.toHaveBeenCalled();
		expect(
			container.querySelector( '.corex-pane__reply-draft' )
		).toBeNull();
	} );

	it( 'is kept with the message once there is one', async () => {
		show( submission( WITH_A_SUBJECT ) );

		typeMessage( '<p>Hello</p>' );
		await pause();

		expect( inbox.keepReplyDraft ).toHaveBeenCalledWith( 41, {
			subject: 'Re: Request a quote',
			body: '<p>Hello</p>',
			thread: '',
		} );
	} );

	// Back to how the box opened: what was kept is discarded, and not kept as a draft of a
	// subject nobody wrote.
	it( 'leaves no draft when the message is taken out again', async () => {
		show( submission( WITH_A_SUBJECT ) );
		typeMessage( '<p>Hello</p>' );
		await pause();

		typeMessage( '' );
		await pause();

		expect( inbox.keepReplyDraft ).toHaveBeenLastCalledWith( 41, {
			subject: '',
			body: '',
		} );
		expect(
			container.querySelector( '.corex-pane__reply-draft' )
		).toBeNull();
	} );

	// Clearing it to write another is the first thing most people do. Nothing of theirs
	// is in the box yet, and nothing is kept: there is nothing to ask the server for.
	it.each( [
		[ 'cleared', '' ],
		[ 'followed by a space', 'Re: Request a quote ' ],
	] )(
		'asks the server for nothing while it is only %s',
		async ( _, typed ) => {
			inbox.keepReplyDraft.mockResolvedValue( {
				ok: false,
				draft: null,
			} );
			show( submission( WITH_A_SUBJECT ) );

			type( field( 'Subject' ), typed );
			await pause();
			type( field( 'Subject' ), 'Re: Request a quote' );
			await pause();

			expect( inbox.keepReplyDraft ).not.toHaveBeenCalled();
			expect(
				container.querySelector( '.corex-pane__reply-draft' )
			).toBeNull();
		}
	);

	it( 'gives way to the subject of a draft the person had begun', () => {
		show(
			submission( {
				...WITH_A_SUBJECT,
				reply_draft: {
					subject: 'About your kitchen',
					body: '<p>Hello</p>',
					saved_at: '2026-10-11T10:00:00+00:00',
				},
			} )
		);

		expect( field( 'Subject' ).value ).toBe( 'About your kitchen' );
	} );

	it( 'is what the box goes back to when the reply has gone, or its draft was discarded', async () => {
		inbox.reply.mockResolvedValue( {
			tone: 'success',
			text: 'Reply sent to salma@example.com.',
			detail: '',
			keep: false,
		} );
		act( () => {
			root.render( <Pane record={ submission( WITH_A_SUBJECT ) } /> );
		} );
		type( field( 'Subject' ), 'Your quote' );
		typeMessage( '<p>Hello</p>' );

		await act( async () => {
			button( 'Send reply' ).click();
		} );

		expect( field( 'Subject' ).value ).toBe( 'Re: Request a quote' );
		expect( message().textContent ).toBe( '' );

		type( field( 'Subject' ), 'Another' );
		typeMessage( '<p>Again</p>' );
		await pause();
		await act( async () => {
			button( 'Discard draft' ).click();
		} );

		expect( field( 'Subject' ).value ).toBe( 'Re: Request a quote' );
		await pause();
		leave();
		await pause();
		// The reply that went, one save of what was written after it, and nothing since.
		expect( inbox.keepReplyDraft ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does not start over when the pane reads the submission again', () => {
		show( submission( WITH_A_SUBJECT ) );
		type( field( 'Subject' ), 'Your quote' );

		// As after a note is added: the same submission, read again.
		show( submission( { reply_subject: 'Re: Renamed form' } ) );

		expect( field( 'Subject' ).value ).toBe( 'Your quote' );
	} );
} );

// Spec 106, FR-006: an unsent reply is the person's draft until it is sent or discarded.
describe( 'the draft', () => {
	const STORED = {
		subject: 'Re: your message',
		body: '<p>Hello <strong>Salma</strong>,</p>',
		saved_at: '2026-10-11T10:00:00+00:00',
	};
	const SENT = {
		tone: 'success',
		text: 'Reply sent to salma@example.com.',
		detail: '',
		keep: false,
	};
	const line = () => container.querySelector( '.corex-pane__reply-draft' );
	const announced = () =>
		container.querySelector( '[role="status"]' ).textContent;
	// Writing stops for as long as it takes for what is written to be written down.
	const pause = ( milliseconds = PAUSE_BEFORE_SAVING ) =>
		act( async () => {
			jest.advanceTimersByTime( milliseconds );
		} );
	const leave = () => {
		act( () => root.unmount() );
		root = createRoot( container );
	};

	beforeEach( () => jest.useFakeTimers() );
	afterEach( () => jest.useRealTimers() );

	it( 'is where the person left it when they come back, and can be sent', () => {
		show( submission( { reply_draft: STORED } ) );

		expect( field( 'Subject' ).value ).toBe( 'Re: your message' );
		expect( message().querySelector( 'strong' ).textContent ).toBe(
			'Salma'
		);
		expect( line().textContent ).toContain(
			'the reply you began earlier and did not send'
		);
		expect( button( 'Send reply' ).disabled ).toBe( false );
	} );

	// The sentence stands after the buttons. Somebody who reads the page in order meets two
	// filled fields first, so the fields say why they are filled.
	it( 'says on the fields themselves that what is in them was written earlier', () => {
		show( submission( { reply_draft: STORED } ) );
		const said = line().querySelector( 'span' );

		expect( field( 'Subject' ).getAttribute( 'aria-describedby' ) ).toBe(
			said.id
		);
		expect( message().getAttribute( 'aria-describedby' ) ).toBe( said.id );
	} );

	it( 'is not written down again while nothing in it has changed', async () => {
		show( submission( { reply_draft: STORED } ) );

		await pause( 10000 );
		leave();

		expect( inbox.keepReplyDraft ).not.toHaveBeenCalled();
	} );

	it( 'is written down once writing has paused, and not while it goes on', async () => {
		show( submission() );
		expect( line() ).toBeNull();

		type( field( 'Subject' ), 'Re: your' );
		await pause( PAUSE_BEFORE_SAVING - 500 );
		type( field( 'Subject' ), 'Re: your message' );
		await pause( PAUSE_BEFORE_SAVING - 500 );
		typeMessage( '<p>Hello</p>' );
		await pause( PAUSE_BEFORE_SAVING - 1 );
		expect( inbox.keepReplyDraft ).not.toHaveBeenCalled();

		await pause( 1 );

		expect( inbox.keepReplyDraft ).toHaveBeenCalledTimes( 1 );
		expect( inbox.keepReplyDraft ).toHaveBeenCalledWith( 41, {
			subject: 'Re: your message',
			body: '<p>Hello</p>',
			thread: '',
		} );
		expect( line().textContent ).toContain( 'Draft saved.' );
		// Said in the page, not announced each time writing pauses.
		expect( announced() ).toBe( '' );

		await pause( 10000 );
		expect( inbox.keepReplyDraft ).toHaveBeenCalledTimes( 1 );
	} );

	it.each( [
		[ 'the server refused it', async () => ( { ok: false, draft: null } ) ],
		[
			'the request could not be made',
			async () => {
				throw new Error( 'offline' );
			},
		],
		// Answered, and nothing kept, for a message that did say something.
		[
			'the server kept nothing',
			async () => ( { ok: true, draft: null } ),
		],
	] )( 'says so, and announces it, when %s', async ( _, answer ) => {
		inbox.keepReplyDraft.mockImplementation( answer );
		show( submission() );

		typeMessage( '<p>Hello</p>' );
		await pause();

		const sentence =
			'The draft could not be saved. What you have written is still here.';
		expect( line().textContent ).toContain( sentence );
		expect( announced() ).toBe( sentence );
		expect( message().textContent ).toBe( 'Hello' );
	} );

	it( 'is discarded when asked: the server is told, the box is emptied, and it is said', async () => {
		showInPane( submission( { reply_draft: STORED } ) );
		act( () => button( 'Discard draft' ).focus() );

		await act( async () => {
			button( 'Discard draft' ).click();
		} );

		expect( inbox.discardReplyDraft ).toHaveBeenCalledWith( 41 );
		expect( field( 'Subject' ).value ).toBe( '' );
		expect( message().textContent ).toBe( '' );
		expect( line() ).toBeNull();
		expect( announced() ).toBe( 'Draft discarded.' );
		// The button that was pressed went with the draft.
		expect( document.activeElement ).toBe( field( 'Subject' ) );
		await pause( 10000 );
		expect( inbox.keepReplyDraft ).not.toHaveBeenCalled();
	} );

	it( 'says it is working while the draft is being discarded, and holds the other buttons', async () => {
		const discarding = held();
		inbox.discardReplyDraft.mockReturnValue( discarding.promise );
		showInPane( submission( { reply_draft: STORED } ) );

		await act( async () => {
			button( 'Discard draft' ).click();
		} );

		expect( button( 'Discard draft' ).getAttribute( 'aria-busy' ) ).toBe(
			'true'
		);
		expect( button( 'Discard draft' ).disabled ).toBe( true );
		expect( button( 'Send reply' ).disabled ).toBe( true );

		await discarding.answer( true );
		expect( line() ).toBeNull();
	} );

	it( 'stays in the box when it could not be discarded, says that, and gives the button back', async () => {
		inbox.discardReplyDraft.mockResolvedValue( false );
		showInPane( submission( { reply_draft: STORED } ) );

		await act( async () => {
			button( 'Discard draft' ).click();
		} );

		expect( field( 'Subject' ).value ).toBe( 'Re: your message' );
		expect( line().textContent ).toContain(
			'The draft could not be discarded.'
		);
		expect( announced() ).toBe( 'The draft could not be discarded.' );
		expect( document.activeElement ).toBe( button( 'Discard draft' ) );
	} );

	it( 'is discarded by emptying what was written', async () => {
		show( submission( { reply_draft: STORED } ) );

		type( field( 'Subject' ), '' );
		typeMessage( '<p><br></p>' );
		await pause();

		// An empty paragraph is what an emptied box holds, and is given back as nothing.
		expect( inbox.keepReplyDraft ).toHaveBeenCalledWith( 41, {
			subject: '',
			body: '',
		} );
		expect( line() ).toBeNull();
	} );

	it( 'is written down at once when the pane is left before the pause is over', async () => {
		show( submission() );
		type( field( 'Subject' ), 'Re: your message' );
		typeMessage( '<p>Hello</p>' );
		await pause( 200 );

		leave();
		await pause( 0 );

		expect( inbox.keepReplyDraft ).toHaveBeenCalledTimes( 1 );
		expect( inbox.keepReplyDraft ).toHaveBeenCalledWith( 41, {
			subject: 'Re: your message',
			body: '<p>Hello</p>',
			thread: '',
		} );
	} );

	it( 'is not written down a second time when the pane is left while it is being written down', async () => {
		const saving = held();
		inbox.keepReplyDraft.mockReturnValue( saving.promise );
		show( submission() );
		typeMessage( '<p>Hello</p>' );
		await pause();

		leave();
		await saving.answer( { ok: true, draft: STORED } );

		expect( inbox.keepReplyDraft ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'is no longer a draft once the reply has gone: nothing is written down after it', async () => {
		inbox.reply.mockResolvedValue( SENT );
		show( submission( { reply_draft: STORED } ) );

		await act( async () => {
			button( 'Send reply' ).click();
		} );
		await pause( 10000 );
		leave();
		await pause( 0 );

		expect( inbox.keepReplyDraft ).not.toHaveBeenCalled();
	} );

	it( 'is still a draft when the reply was not sent, with what was written', async () => {
		inbox.reply.mockResolvedValue( {
			tone: 'failure',
			text: 'The reply was not sent.',
			detail: 'The mail server refused it.',
			keep: true,
		} );
		// In the pane, whose sending and having sent are what the draft waits on.
		showInPane( submission() );
		type( field( 'Subject' ), 'Re: your message' );
		typeMessage( '<p>Hello</p>' );

		await act( async () => {
			button( 'Send reply' ).click();
		} );
		expect( inbox.keepReplyDraft ).not.toHaveBeenCalled();
		await pause();

		expect( inbox.keepReplyDraft ).toHaveBeenCalledWith( 41, {
			subject: 'Re: your message',
			body: '<p>Hello</p>',
			thread: '',
		} );
		expect( message().textContent ).toBe( 'Hello' );
	} );

	// Each of these made a draft of a reply that had gone, or of one that was discarded: the
	// next visit showed it as "the reply you began earlier and did not send", ready to be
	// sent a second time.
	// Spec 106, FR-039: how it was to be sent is part of what was begun. Somebody who chose a
	// new email must not come back to a draft set to join the conversation.
	describe( 'and the way it is to be sent', () => {
		const AS_A_NEW_EMAIL = {
			...STORED,
			subject: 'Your kitchen, next week',
			thread: 'new',
		};

		it( 'comes back set to be sent the way it was', () => {
			show(
				submission( {
					...IN_A_CONVERSATION,
					reply_draft: AS_A_NEW_EMAIL,
				} )
			);

			expect( way( 'As a new email' ).checked ).toBe( true );
			expect( field( 'Subject' ).value ).toBe(
				'Your kitchen, next week'
			);
			// Nothing is said about parting from a conversation it is not to join.
			expect( container.textContent ).not.toContain( 'Gmail' );
		} );

		it( 'is not written down again for coming back that way', async () => {
			show(
				submission( {
					...IN_A_CONVERSATION,
					reply_draft: AS_A_NEW_EMAIL,
				} )
			);

			await pause( 10000 );
			leave();

			expect( inbox.keepReplyDraft ).not.toHaveBeenCalled();
		} );

		it( 'is written down again when only the way changes', async () => {
			show(
				submission( {
					...IN_A_CONVERSATION,
					reply_draft: AS_A_NEW_EMAIL,
				} )
			);

			act( () => way( 'In the conversation they already have' ).click() );
			await pause();

			expect( inbox.keepReplyDraft ).toHaveBeenCalledTimes( 1 );
			expect( inbox.keepReplyDraft ).toHaveBeenCalledWith( 41, {
				subject: 'Your kitchen, next week',
				body: STORED.body,
				thread: 'conversation',
			} );
		} );

		it( 'is a new email where there is no longer an email to continue from', () => {
			show(
				submission( {
					reply_draft: { ...STORED, thread: 'conversation' },
				} )
			);

			expect(
				container.querySelector( 'input[type="radio"]' )
			).toBeNull();
			expect(
				container.querySelector( '.corex-pane__reply-way' ).textContent
			).toContain( 'This will be a new email' );
		} );

		// Begun before there was an email to continue from: nobody chose a new email, and
		// the draft must not come back as if somebody had.
		it( 'keeps no way where nobody chose one, and joins the conversation once there is one', async () => {
			show( submission() );
			type( field( 'Subject' ), 'Your kitchen' );
			typeMessage( '<p>Hello</p>' );
			await pause();
			const kept = inbox.keepReplyDraft.mock.calls.at( -1 )[ 1 ];
			leave();

			show(
				submission( {
					...IN_A_CONVERSATION,
					reply_draft: { ...kept, saved_at: STORED.saved_at },
				} )
			);

			expect( kept.thread ).toBe( '' );
			expect(
				way( 'In the conversation they already have' ).checked
			).toBe( true );
		} );

		// Kept before the way was kept with it.
		it( 'is in the conversation where the draft does not say', () => {
			show( submission( { ...IN_A_CONVERSATION, reply_draft: STORED } ) );

			expect(
				way( 'In the conversation they already have' ).checked
			).toBe( true );
		} );
	} );

	describe( 'does not come back', () => {
		it( 'when the pane is left while the reply is on its way', async () => {
			const sending = held();
			inbox.reply.mockReturnValue( sending.promise );
			showInPane( submission() );
			type( field( 'Subject' ), 'Re: your message' );
			typeMessage( '<p>Hello</p>' );
			await act( async () => {
				button( 'Send reply' ).click();
			} );

			leave();
			await pause( 10000 );
			await sending.answer( SENT );

			expect( inbox.keepReplyDraft ).not.toHaveBeenCalled();
		} );

		it( 'when it is discarded before the pause in writing is over', async () => {
			const discarding = held();
			inbox.discardReplyDraft.mockReturnValue( discarding.promise );
			showInPane( submission( { reply_draft: STORED } ) );
			type( field( 'Subject' ), 'Re: your message, changed' );
			await pause( PAUSE_BEFORE_SAVING - 500 );

			await act( async () => {
				button( 'Discard draft' ).click();
			} );
			// The pause would have ended here, with the discard still out.
			await pause( 10000 );
			await discarding.answer( true );
			await pause( 10000 );
			leave();
			await pause( 0 );

			expect( inbox.keepReplyDraft ).not.toHaveBeenCalled();
		} );

		it( 'when it is discarded while it is being written down: the discard goes after', async () => {
			const saving = held();
			const order = [];
			inbox.keepReplyDraft.mockImplementation( () => {
				order.push( 'keep' );
				return saving.promise;
			} );
			inbox.discardReplyDraft.mockImplementation( async () => {
				order.push( 'discard' );
				return true;
			} );
			showInPane( submission( { reply_draft: STORED } ) );
			type( field( 'Subject' ), 'Re: your message, changed' );
			await pause();

			await act( async () => {
				button( 'Discard draft' ).click();
			} );
			// The save is still out: a discard sent now could reach the server first.
			expect( order ).toEqual( [ 'keep' ] );

			await saving.answer( { ok: true, draft: STORED } );

			expect( order ).toEqual( [ 'keep', 'discard' ] );
			// And the late answer is about a draft that is over: it is not read.
			expect( line() ).toBeNull();
			expect( field( 'Subject' ).value ).toBe( '' );
			await pause( 10000 );
			leave();
			await pause( 0 );
			expect( order ).toEqual( [ 'keep', 'discard' ] );
		} );

		it( 'when the reply is sent while it is being written down: what the save left is removed', async () => {
			const saving = held();
			const order = [];
			inbox.keepReplyDraft.mockImplementation( () => {
				order.push( 'keep' );
				return saving.promise;
			} );
			inbox.discardReplyDraft.mockImplementation( async () => {
				order.push( 'discard' );
				return true;
			} );
			inbox.reply.mockResolvedValue( SENT );
			showInPane( submission() );
			type( field( 'Subject' ), 'Re: your message' );
			typeMessage( '<p>Hello</p>' );
			await pause();

			await act( async () => {
				button( 'Send reply' ).click();
			} );
			await saving.answer( { ok: true, draft: STORED } );

			// The save may have reached the server after the reply did, so it is undone.
			expect( order ).toEqual( [ 'keep', 'discard' ] );
			expect( line() ).toBeNull();
			expect( field( 'Subject' ).value ).toBe( '' );
		} );

		it( 'and no draft is removed after a reply when none was being written down', async () => {
			inbox.reply.mockResolvedValue( SENT );
			showInPane( submission( { reply_draft: STORED } ) );

			await act( async () => {
				button( 'Send reply' ).click();
			} );
			await pause( 10000 );

			expect( inbox.discardReplyDraft ).not.toHaveBeenCalled();
		} );
	} );

	it( 'is not written down while the reply is on its way', async () => {
		const sending = ( pending ) =>
			act( () => {
				root.render(
					<Reply
						record={ submission() }
						inbox={ inbox }
						work={ { ...work, pending } }
					/>
				);
			} );
		sending( '' );
		type( field( 'Subject' ), 'Re: your message' );
		await pause( PAUSE_BEFORE_SAVING - 500 );

		sending( 'reply' );
		await pause( 10000 );

		expect( inbox.keepReplyDraft ).not.toHaveBeenCalled();
	} );
} );
