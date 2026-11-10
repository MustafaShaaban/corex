/**
 * The assistant's chat (spec 129): what it shows from storage, how it asks and shows a reply, when it
 * opens the hand-off, and what the hand-off sends.
 */

import { TextEncoder } from 'util';

import { historyOf, mountChat, transcriptOf } from '../chat';
import { city, describeSlot, slotGroups } from '../slots';
import { bindAssistant } from '../view';

const STRINGS = {
	name: 'Helper',
	welcome: 'Hello',
	suggestions: [ 'What do you offer?' ],
	busy: 'Busy now',
	expired: 'Refresh',
	error: 'Oops',
	human: 'A person please',
	done: 'Thanks %s',
	submit: 'Send request',
	nameNeeded: 'Name?',
	phoneNeeded: 'Phone?',
	emailNeeded: 'Email?',
	formError: 'Check fields',
	handoffTitle: 'Shall we call?',
	fieldPhone: 'Phone',
	fieldEmail: 'Email',
	optional: '(optional)',
	slotAny: 'Any time',
	fieldSlot: 'When?',
	slotZone: 'Your zone: %s',
};

function root(
	form = '/wp-json/corex/v1/forms/corex-assistant-callback',
	country = ''
) {
	document.body.innerHTML = '';
	const node = document.createElement( 'div' );
	Object.assign( node.dataset, {
		locale: 'en',
		endpoint: '/wp-json/corex/v1/assistant',
		form,
		nonce: 'n0nce',
		countriesFirst: 'AE,SA',
		country,
		strings: JSON.stringify( STRINGS ),
	} );
	node.innerHTML =
		'<button class="corex-assistant__fab"></button><div class="corex-assistant__panel" hidden></div>';
	document.body.append( node );
	return node;
}

function reply( status, body ) {
	return Promise.resolve( {
		status,
		ok: status < 300,
		json: () => Promise.resolve( body ),
	} );
}

const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

async function ask( panel, question ) {
	panel.querySelector( '.corex-assistant__input' ).value = question;
	panel
		.querySelector( '.corex-assistant__compose' )
		.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
	await flush();
	await flush();
}

/**
 * The visitor's clock and languages, as the browser would report them.
 *
 * @param {string}   timeZone
 * @param {string[]} languages
 */
function visitorIn( timeZone, languages = [ 'en-US' ] ) {
	const Real = Intl.DateTimeFormat;
	// The visitor's own zone when asked with no options; real formatting otherwise.
	jest.spyOn( Intl, 'DateTimeFormat' ).mockImplementation(
		( locale, options ) =>
			options
				? new Real( locale, options )
				: { resolvedOptions: () => ( { timeZone } ) }
	);
	jest.spyOn( window.navigator, 'languages', 'get' ).mockReturnValue(
		languages
	);
}

afterEach( () => {
	jest.restoreAllMocks();
} );

beforeEach( () => {
	window.sessionStorage.clear();
	window.fetch = jest.fn();
} );

describe( 'the chat', () => {
	it( 'shows the welcome and suggestions, then the conversation kept in this tab', () => {
		const node = root();
		const panel = node.querySelector( '.corex-assistant__panel' );
		mountChat( node, panel, { onClose: jest.fn() } );
		expect( panel.textContent ).toContain( 'Hello' );
		expect(
			panel.querySelector( '.corex-assistant__chip' ).textContent
		).toBe( 'What do you offer?' );

		window.sessionStorage.setItem(
			'corex-assistant-chat-en',
			JSON.stringify( { items: [ { role: 'user', content: 'Hi' } ] } )
		);
		mountChat( node, panel, { onClose: jest.fn() } );
		expect( panel.querySelector( '.corex-assistant__chip' ) ).toBeNull();
		expect(
			panel.querySelector( '.corex-assistant__msg--me' ).textContent
		).toBe( 'Hi' );
	} );

	it( 'sends the conversation with the page token and shows the reply with this site’s links only', async () => {
		const node = root();
		const panel = node.querySelector( '.corex-assistant__panel' );
		window.fetch.mockReturnValue(
			reply( 200, {
				reply: 'We build sites.',
				team: false,
				busy: false,
				links: [
					{
						url: `${ window.location.origin }/services/`,
						label: 'Services',
					},
					{ url: 'https://elsewhere.test/', label: 'Elsewhere' },
				],
			} )
		);
		mountChat( node, panel, { onClose: jest.fn() } );

		await ask( panel, 'What do you do?' );

		const [ url, request ] = window.fetch.mock.calls[ 0 ];
		expect( url ).toBe( '/wp-json/corex/v1/assistant' );
		expect( request.headers[ 'X-WP-Nonce' ] ).toBe( 'n0nce' );
		expect( JSON.parse( request.body ) ).toEqual( {
			locale: 'en',
			messages: [ { role: 'user', content: 'What do you do?' } ],
		} );
		expect( panel.textContent ).toContain( 'We build sites.' );
		expect(
			[ ...panel.querySelectorAll( '.corex-assistant__link' ) ].map(
				( a ) => a.textContent
			)
		).toEqual( [ 'Services' ] );
		expect( panel.querySelector( '.corex-assistant__handoff' ) ).toBeNull();
	} );

	it( 'opens the hand-off once when the reply asks for the team, or when nothing could answer', async () => {
		const node = root();
		const panel = node.querySelector( '.corex-assistant__panel' );
		window.fetch.mockReturnValueOnce(
			reply( 200, {
				reply: 'It depends.',
				team: true,
				busy: false,
				links: [],
			} )
		);
		window.fetch.mockReturnValueOnce(
			reply( 200, { reply: '', team: true, busy: true, links: [] } )
		);
		mountChat( node, panel, { onClose: jest.fn() } );

		await ask( panel, 'What is the price?' );
		await ask( panel, 'And now?' );

		expect(
			panel.querySelectorAll( '.corex-assistant__handoff' )
		).toHaveLength( 1 );
		expect( panel.textContent ).toContain( 'Busy now' );
	} );

	it( 'asks the visitor to refresh when the page token expired, and says so on any other failure', async () => {
		const node = root();
		const panel = node.querySelector( '.corex-assistant__panel' );
		window.fetch.mockReturnValueOnce( reply( 403, { code: 'expired' } ) );
		window.fetch.mockReturnValueOnce(
			Promise.reject( new Error( 'offline' ) )
		);
		mountChat( node, panel, { onClose: jest.fn() } );

		await ask( panel, 'One' );
		await ask( panel, 'Two' );

		const notes = [
			...panel.querySelectorAll( '.corex-assistant__msg--note' ),
		].map( ( n ) => n.textContent );
		expect( notes ).toEqual( [ 'Refresh', 'Oops' ] );
	} );

	it( 'offers no hand-off when the site has no forms engine', async () => {
		const node = root( '' );
		const panel = node.querySelector( '.corex-assistant__panel' );
		window.fetch.mockReturnValue(
			reply( 200, { reply: '', team: true, busy: true, links: [] } )
		);
		mountChat( node, panel, { onClose: jest.fn() } );

		await ask( panel, 'Hi' );

		expect( panel.querySelector( '.corex-assistant__human' ) ).toBeNull();
		expect( panel.querySelector( '.corex-assistant__handoff' ) ).toBeNull();
	} );

	it( 'shows a reply as text, never as markup, and drops a link that is not an address on this site', async () => {
		const node = root();
		const panel = node.querySelector( '.corex-assistant__panel' );
		window.fetch.mockReturnValue(
			reply( 200, {
				reply: '<img src=x onerror="window.hacked=1">',
				team: false,
				busy: false,
				links: [ { url: 'javascript:alert(1)', label: 'Click' } ],
			} )
		);
		mountChat( node, panel, { onClose: jest.fn() } );

		await ask( panel, 'Hi' );

		expect( panel.querySelector( 'img[src="x"]' ) ).toBeNull();
		expect( panel.textContent ).toContain( '<img src=x' );
		expect( panel.querySelector( '.corex-assistant__link' ) ).toBeNull();
	} );

	it( 'starts a new chat, forgetting the one kept in this tab', () => {
		window.sessionStorage.setItem(
			'corex-assistant-chat-en',
			JSON.stringify( { items: [ { role: 'user', content: 'Hi' } ] } )
		);
		const node = root();
		const panel = node.querySelector( '.corex-assistant__panel' );
		mountChat( node, panel, { onClose: jest.fn() } );

		panel.querySelector( '.corex-assistant__icon' ).click();

		expect( panel.querySelector( '.corex-assistant__msg--me' ) ).toBeNull();
		expect(
			JSON.parse(
				window.sessionStorage.getItem( 'corex-assistant-chat-en' )
			).items
		).toEqual( [] );
	} );

	it( 'sends the newest twelve messages, each cut, and fewer while the request would be too large', () => {
		const items = Array.from( { length: 30 }, ( _, index ) => ( {
			role: index % 2 ? 'assistant' : 'user',
			content: `${ index } ${ '€'.repeat( 900 ) }`,
		} ) );
		items.push( { role: 'note', content: 'not sent' } );

		const sent = historyOf( items );
		const bytes = new TextEncoder().encode( JSON.stringify( sent ) ).length;

		expect( sent.length ).toBeLessThan( 12 );
		expect(
			sent.every( ( message ) => message.content.length <= 600 )
		).toBe( true );
		expect( sent.at( -1 ).content.startsWith( '29 ' ) ).toBe( true );
		expect( bytes ).toBeLessThanOrEqual( 15000 );
		expect(
			historyOf( [
				{ role: 'user', content: 'Hi' },
				{ role: 'assistant', content: 'Hello' },
			] )
		).toHaveLength( 2 );
	} );

	it( 'never cuts a character in half where a message is cut', () => {
		const sent = historyOf( [
			{ role: 'user', content: `${ 'a'.repeat( 599 ) }😀 and more` },
		] );

		expect( Array.from( sent[ 0 ].content ) ).toHaveLength( 600 );
		expect( sent[ 0 ].content.endsWith( '😀' ) ).toBe( true );
	} );

	it( 'closes on Escape', () => {
		const node = root();
		const panel = node.querySelector( '.corex-assistant__panel' );
		const onClose = jest.fn();
		mountChat( node, panel, { onClose } );

		panel.dispatchEvent(
			new window.KeyboardEvent( 'keydown', {
				key: 'Escape',
				bubbles: true,
			} )
		);

		expect( onClose ).toHaveBeenCalled();
	} );

	it( 'writes the transcript the team reads, the newest part when long', () => {
		const items = [
			{ role: 'user', content: 'Hi' },
			{ role: 'note', content: 'ignored' },
			{ role: 'assistant', content: 'Hello' },
		];
		expect( transcriptOf( items ) ).toBe( 'Visitor: Hi\nAssistant: Hello' );
		expect(
			transcriptOf( [ { role: 'user', content: 'x'.repeat( 7000 ) } ] )
		).toHaveLength( 6000 );
	} );
} );

describe( 'the hand-off', () => {
	async function openHandoff() {
		const node = root();
		const panel = node.querySelector( '.corex-assistant__panel' );
		mountChat( node, panel, { onClose: jest.fn() } );
		panel.querySelector( '.corex-assistant__human' ).click();
		return panel;
	}

	it( 'says what is missing and sends nothing', async () => {
		const panel = await openHandoff();
		const form = panel.querySelector( '.corex-assistant__handoff' );
		form.querySelector( 'input[value="email"]' ).checked = true;

		form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
		await flush();

		const errors = [
			...form.querySelectorAll( '.corex-assistant__error:not([hidden])' ),
		].map( ( e ) => e.textContent );
		// By email: the address is needed and the number is not.
		expect( errors ).toEqual( [ 'Name?', 'Email?' ] );
		expect(
			form
				.querySelector( '#corex-assistant-name' )
				.getAttribute( 'aria-invalid' )
		).toBe( 'true' );
		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	it( 'needs the number for a call, and says the address is optional then', async () => {
		const panel = await openHandoff();
		const form = panel.querySelector( '.corex-assistant__handoff' );
		form.querySelector( '#corex-assistant-name' ).value = 'Sara';

		form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
		await flush();

		const errors = [
			...form.querySelectorAll( '.corex-assistant__error:not([hidden])' ),
		].map( ( e ) => e.textContent );
		expect( errors ).toEqual( [ 'Phone?' ] );
		expect( form.querySelector( '#corex-assistant-phone' ).required ).toBe(
			true
		);
		expect(
			form.querySelector( 'label[for="corex-assistant-email"]' )
				.textContent
		).toBe( 'Email (optional)' );
	} );

	it( 'offers the site’s times in the visitor’s zone, and sends the picked one in both zones', async () => {
		visitorIn( 'Asia/Riyadh' );
		const slot = Date.UTC( 2026, 10, 12, 16, 0 ) / 1000;
		window.fetch.mockImplementation( ( url ) =>
			String( url ).includes( '/slots' )
				? reply( 200, { zone: 'Africa/Cairo', slots: [ slot ] } )
				: reply( 200, { ok: true } )
		);
		const node = root();
		node.dataset.slots = '/wp-json/corex/v1/assistant/slots';
		const panel = node.querySelector( '.corex-assistant__panel' );
		mountChat( node, panel, { onClose: jest.fn() } );
		panel.querySelector( '.corex-assistant__human' ).click();
		await flush();
		await flush();
		const form = panel.querySelector( '.corex-assistant__handoff' );
		const picker = form.querySelector( '#corex-assistant-slot' );
		expect( form.querySelector( '#corex-assistant-time' ) ).toBeNull();
		expect( picker.options ).toHaveLength( 2 );

		picker.value = String( slot );
		form.querySelector( '#corex-assistant-name' ).value = 'Sara';
		form.querySelector( '#corex-assistant-phone' ).value = '50 123 4567';
		form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
		await flush();
		await flush();

		const sent = window.fetch.mock.calls.find( ( [ url ] ) =>
			String( url ).includes( '/forms/' )
		);
		const body = JSON.parse( sent[ 1 ].body );
		expect( body.slot ).toBe( '2026-11-12T16:00:00.000Z' );
		expect( body.zone ).toBe( 'Asia/Riyadh' );
		expect( body.time ).toMatch( /7:00\s?PM Riyadh = .*6:00\s?PM Cairo$/ );
	} );

	it( 'posts the answers to the CoreX form with the token, the honeypot and the chat, then confirms', async () => {
		visitorIn( 'Asia/Dubai' );
		window.sessionStorage.setItem(
			'corex-assistant-chat-en',
			JSON.stringify( {
				items: [ { role: 'user', content: 'How much is a site?' } ],
			} )
		);
		const panel = await openHandoff();
		const form = panel.querySelector( '.corex-assistant__handoff' );
		window.fetch.mockReturnValue( reply( 200, { ok: true } ) );
		form.querySelector( '#corex-assistant-name' ).value = 'Sara';
		form.querySelector( '#corex-assistant-phone' ).value = '50 123 4567';

		form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
		await flush();
		await flush();

		const [ url, request ] = window.fetch.mock.calls[ 0 ];
		const body = JSON.parse( request.body );
		expect( url ).toBe(
			'/wp-json/corex/v1/forms/corex-assistant-callback'
		);
		expect( request.headers[ 'X-WP-Nonce' ] ).toBe( 'n0nce' );
		expect( body ).toMatchObject( {
			name: 'Sara',
			phone: '+971 50 123 4567',
			channel: 'call',
			transcript: 'Visitor: How much is a site?',
			corex_hp: '',
		} );
		expect(
			panel.querySelector( '.corex-assistant__msg--done' ).textContent
		).toBe( 'Thanks Sara' );
		expect( panel.querySelector( '.corex-assistant__handoff' ) ).toBeNull();
	} );

	it( 'sends no chat when the visitor unticks it', async () => {
		const panel = await openHandoff();
		const form = panel.querySelector( '.corex-assistant__handoff' );
		window.fetch.mockReturnValue( reply( 200, { ok: true } ) );
		form.querySelector( '#corex-assistant-name' ).value = 'Sara';
		form.querySelector( '#corex-assistant-phone' ).value = '50 123 4567';
		form.querySelector( '#corex-assistant-attach' ).checked = false;

		form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
		await flush();

		expect(
			JSON.parse( window.fetch.mock.calls[ 0 ][ 1 ].body ).transcript
		).toBe( '' );
	} );

	it( 'presets the site’s country when the visitor’s clock and languages name none', async () => {
		visitorIn( 'Etc/Unknown', [ 'ar' ] );
		const node = root(
			'/wp-json/corex/v1/forms/corex-assistant-callback',
			'SA'
		);
		const panel = node.querySelector( '.corex-assistant__panel' );
		mountChat( node, panel, { onClose: jest.fn() } );
		panel.querySelector( '.corex-assistant__human' ).click();

		expect( panel.querySelector( '.corex-assistant__dial' ).value ).toBe(
			'+966'
		);
	} );

	it( 'lists the site’s first countries first', async () => {
		const panel = await openHandoff();
		const options = [
			...panel.querySelectorAll( '.corex-assistant__dial option' ),
		].map( ( o ) => o.value );
		expect( options.slice( 0, 2 ) ).toEqual( [ '+971', '+966' ] );
		expect( options.length ).toBeGreaterThan( 50 );
	} );
} );

describe( 'the button', () => {
	it( 'loads the chat once, on first open, and tries again after a failed load', async () => {
		const node = root();
		const fab = node.querySelector( '.corex-assistant__fab' );
		const panel = node.querySelector( '.corex-assistant__panel' );
		const mount = jest.fn( () => ( { focus: jest.fn() } ) );
		const load = jest
			.fn()
			.mockReturnValueOnce( Promise.reject( new Error( 'offline' ) ) )
			.mockReturnValue( Promise.resolve( { mountChat: mount } ) );
		bindAssistant( node, load );

		fab.click();
		await flush();
		expect( panel.hidden ).toBe( true );

		fab.click();
		await flush();
		expect( panel.hidden ).toBe( false );
		expect( fab.getAttribute( 'aria-expanded' ) ).toBe( 'true' );

		fab.click();
		fab.click();
		await flush();
		expect( load ).toHaveBeenCalledTimes( 2 );
		expect( mount ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'the times', () => {
	it( 'groups the slots by the visitor’s day and names each time in their zone', () => {
		const nine = Date.UTC( 2026, 10, 12, 6, 0 ) / 1000;
		const groups = slotGroups(
			[ nine, nine + 3600, nine + 86400 ],
			'en',
			'Asia/Riyadh'
		);

		expect( groups.map( ( group ) => group.times.length ) ).toEqual( [
			2, 1,
		] );
		expect( groups[ 0 ].day ).toMatch( /Thursday/ );
		expect( groups[ 0 ].times[ 0 ].label ).toMatch( /9:00\s?AM/ );
	} );

	it( 'describes a slot once when the visitor is in the site’s own zone', () => {
		const at = Date.UTC( 2026, 10, 12, 16, 0 ) / 1000;

		const line = describeSlot( at, 'en', 'Africa/Cairo', 'Africa/Cairo' );

		expect( line ).toMatch( /6:00\s?PM Cairo$/ );
		expect( line ).not.toContain( ' = ' );
	} );

	it( 'names a zone by its city, the last part, with spaces', () => {
		expect( city( 'America/Argentina/Buenos_Aires' ) ).toBe(
			'Buenos Aires'
		);
	} );
} );
