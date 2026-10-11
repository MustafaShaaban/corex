/**
 * What the pane says about replying, as plain functions (spec 106, slice 1).
 */
import { cleanReplyHtml } from '../detail/replyHtml.js';
import {
	canSendReply,
	readinessNotice,
	replyOutcome,
	replyStateOf,
} from '../detail/replyState.js';
import { historyLine } from '../detail/detailState.js';

const SETTINGS = 'https://example.test/wp-admin/admin.php?page=corex-settings';

describe( 'what a reply may hold', () => {
	it( 'keeps paragraphs, emphasis, lists and links', () => {
		const written =
			'<p>Hello <strong>Salma</strong>, <em>thank you</em>.<br>Soon.</p>' +
			'<ul><li>One</li></ul><ol><li>First</li></ol>' +
			'<p><a href="https://example.com/offer">The offer</a></p>';

		expect( cleanReplyHtml( written ) ).toBe( written );
	} );

	it( 'removes what a reply does not have and keeps its words', () => {
		const cleaned = cleanReplyHtml(
			'<h1 style="color:red">Big</h1><p class="lead" onclick="steal()">Hello</p>' +
				'<img src="x" onerror="steal()"><span>loud</span>'
		);

		expect( cleaned ).toBe( 'Big<p>Hello</p>loud' );
	} );

	it( 'removes a script and a style sheet with what is inside them', () => {
		expect(
			cleanReplyHtml(
				'<p>Before</p><script>steal()</script><style>p{}</style><p>After</p>'
			)
		).toBe( '<p>Before</p><p>After</p>' );
	} );

	it.each( [
		[ 'javascript:steal()' ],
		[ 'JaVaScRiPt:steal()' ],
		[ 'data:text/html,x' ],
		[ 'file:///etc/passwd' ],
	] )( 'keeps the text of a link to %s and not the link', ( address ) => {
		expect(
			cleanReplyHtml( `<p>See <a href="${ address }">this</a>.</p>` )
		).toBe( '<p>See this.</p>' );
	} );

	it.each( [
		[ 'https://example.com/a' ],
		[ 'http://example.com' ],
		[ 'mailto:sam@example.com' ],
		[ 'tel:+201001234567' ],
	] )(
		'keeps a link to %s, and nothing of it but where it goes',
		( address ) => {
			expect(
				cleanReplyHtml(
					`<a href="${ address }" style="color:red" onclick="x()">go</a>`
				)
			).toBe( `<a href="${ address }">go</a>` );
		}
	);

	it( 'opens a link in a new tab where the reply is only being read', () => {
		expect(
			cleanReplyHtml( '<a href="https://example.com">go</a>', {
				forReading: true,
			} )
		).toBe(
			'<a href="https://example.com" target="_blank" rel="noopener noreferrer">go</a>'
		);
	} );

	it( 'answers nothing for nothing', () => {
		expect( cleanReplyHtml( '' ) ).toBe( '' );
		expect( cleanReplyHtml( undefined ) ).toBe( '' );
	} );
} );

describe( 'what the reply box says before anything is typed', () => {
	it( 'says nothing where a reply is delivered, or where the server did not say', () => {
		expect( readinessNotice( { mode: 'deliver' }, SETTINGS ) ).toBeNull();
		expect( readinessNotice( undefined, SETTINGS ) ).toBeNull();
		expect( canSendReply( { mode: 'deliver' } ) ).toBe( true );
		expect( canSendReply( undefined ) ).toBe( true );
	} );

	it( 'says replies are not being delivered, and links somebody who may change it', () => {
		const notice = readinessNotice(
			{
				mode: 'refuse',
				code: 'live_off',
				reason: 'Replies are not being delivered: live delivery is switched off.',
			},
			SETTINGS
		);

		expect( notice.tone ).toBe( 'warning' );
		expect( notice.text ).toContain( 'live delivery is switched off' );
		expect( notice.link ).toEqual( {
			href: SETTINGS,
			label: 'Open Mail settings',
		} );
		expect( notice.hint ).toBe( '' );
		expect( canSendReply( { mode: 'refuse' } ) ).toBe( false );
	} );

	it( 'tells anybody else who can change it', () => {
		const notice = readinessNotice(
			{ mode: 'refuse', code: 'no_provider', reason: 'No provider.' },
			''
		);

		expect( notice.link ).toBeNull();
		expect( notice.hint ).toBe(
			'Somebody who manages CoreX settings can change this.'
		);
	} );

	it( 'says a reply is kept and not delivered on a development site, and still lets it be sent', () => {
		const notice = readinessNotice(
			{
				mode: 'capture',
				code: 'development',
				reason: 'This is a development site.',
			},
			SETTINGS
		);

		expect( notice.tone ).toBe( 'info' );
		expect( notice.link ).toBeNull();
		expect( notice.hint ).toBe( '' );
		expect( canSendReply( { mode: 'capture' } ) ).toBe( true );
	} );

	it( 'links a staging site that only has to switch delivery on', () => {
		const notice = readinessNotice(
			{ mode: 'capture', code: 'staging_capture', reason: 'Staging.' },
			SETTINGS
		);

		expect( notice.link.href ).toBe( SETTINGS );
	} );

	it( 'says replies need the email add-on, with nothing to link to', () => {
		const notice = readinessNotice(
			{
				mode: 'unavailable',
				code: 'no_email_addon',
				reason: 'Replies need the CoreX Email add-on.',
			},
			SETTINGS
		);

		expect( notice.tone ).toBe( 'warning' );
		expect( notice.link ).toBeNull();
		expect( notice.hint ).toBe( '' );
		expect( canSendReply( { mode: 'unavailable' } ) ).toBe( false );
	} );
} );

describe( 'what the pane says after a reply is sent', () => {
	it( 'says a queued reply is waiting, not that it was sent', () => {
		expect(
			replyOutcome(
				{ state: 'queued', message: 'Queued.' },
				'salma@example.com'
			)
		).toEqual( {
			tone: 'info',
			text: 'Reply is waiting to be sent to salma@example.com.',
			detail: '',
			keep: false,
		} );
	} );

	it.each( [ [ 'sent' ], [ 'accepted' ], [ 'opened' ] ] )(
		'says a %s reply was sent, to whom, and empties the box',
		( state ) => {
			expect(
				replyOutcome(
					{ state, message: 'Accepted.' },
					'salma@example.com'
				)
			).toEqual( {
				tone: 'success',
				text: 'Reply sent to salma@example.com.',
				detail: '',
				keep: false,
			} );
		}
	);

	it( 'says a captured reply was kept and not delivered; why is said before typing', () => {
		expect(
			replyOutcome(
				{ state: 'captured', message: 'Development mail is captured.' },
				'salma@example.com'
			)
		).toEqual( {
			tone: 'info',
			text: 'Reply kept on this site, not delivered to salma@example.com.',
			detail: '',
			keep: false,
		} );
	} );

	it.each( [ [ 'failed' ], [ 'rejected' ], [ 'bounced' ], [ 'mystery' ] ] )(
		'says a %s reply was not sent, why, and keeps what was written',
		( state ) => {
			expect(
				replyOutcome(
					{
						state,
						message: 'The provider rejected or failed the email.',
					},
					'salma@example.com'
				)
			).toEqual( {
				tone: 'failure',
				text: 'The reply was not sent.',
				detail: 'The provider rejected or failed the email.',
				keep: true,
			} );
		}
	);

	it( 'says a reply that went and could not be kept is not to be sent again', () => {
		const said = replyOutcome(
			{ state: 'sent', message: 'Accepted.' },
			'salma@example.com',
			false
		);

		expect( said.tone ).toBe( 'success' );
		expect( said.text ).toBe( 'Reply sent to salma@example.com.' );
		expect( said.detail ).toContain( 'Do not send it again.' );
		expect( said.keep ).toBe( false );
	} );

	it( 'never says a reply was sent when nothing came back', () => {
		expect( replyOutcome( undefined, 'salma@example.com' ).tone ).toBe(
			'failure'
		);
	} );
} );

describe( "a reply's state, as a word", () => {
	it.each( [
		[ 'sent', 'Sent', 'success' ],
		[ 'accepted', 'Sent', 'success' ],
		[ 'opened', 'Sent', 'success' ],
		[ 'queued', 'Waiting to be sent', 'info' ],
		[ 'captured', 'Kept, not delivered', 'info' ],
		[ 'failed', 'Not sent', 'warning' ],
		[ 'rejected', 'Not sent', 'warning' ],
		[ 'bounced', 'Returned undelivered', 'warning' ],
		[ '', 'Not sent', 'warning' ],
	] )( '%s reads "%s"', ( state, label, tone ) => {
		expect( replyStateOf( state ) ).toMatchObject( { label, tone } );
	} );
} );

describe( 'the history', () => {
	it.each( [
		[ { action: 'reply', state: 'sent' }, 'success', 'Reply sent' ],
		[
			{ action: 'reply', state: 'captured' },
			'success',
			'Reply kept on this site, not delivered',
		],
		[
			{ action: 'reply', state: 'failed' },
			'failure',
			'Reply could not be sent',
		],
		[ { action: 'resend', state: 'sent' }, 'success', 'Email sent again' ],
		[
			{ action: 'resend', state: 'failed' },
			'failure',
			'Email could not be sent again',
		],
	] )( 'names what happened to an email: %j', ( summary, outcome, line ) => {
		expect( historyLine( { stage: 'email', outcome, summary }, {} ) ).toBe(
			line
		);
	} );

	it( 'still reads an entry written before a reply had a state', () => {
		expect(
			historyLine( { stage: 'email', outcome: 'success' }, {} )
		).toBe( 'Reply sent' );
		expect(
			historyLine( { stage: 'email', outcome: 'failure' }, {} )
		).toBe( 'Reply could not be sent' );
	} );
} );
