/**
 * Jest unit tests for the account block's script (spec 068 US9, FR-161a).
 *
 * Loads the buildless script against jsdom, over the markup `AccountRenderer` prints, with the
 * server answered for. What they hold: a message is written beside the button that asked for
 * it, in one place, and is brought into the window when it would be outside it; and a request
 * that got no answer the script can read says so there too (FR-161c). And what the member
 * view's two lists show under their headings: their rows, or that there is nothing to list,
 * or that the list could not be loaded (FR-161f). A row is the lines the server worded for a
 * person, printed as they came, and both lists are asked for again after a press (FR-161g).
 */

const SCRIPT = '../addons/corex-profile/assets/account.js';

const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

/** The sentence the page hands the script for a request that got no answer it can read. */
const NO_ANSWER = 'No answer came back (as the page worded it).';

const LINE =
	'<p class="corex-account__status" role="status" aria-live="polite"></p>';

function form( kind ) {
	return `
		<form class="corex-account__form" data-corex-account-form="${ kind }" method="post" novalidate>
			<input type="text" name="login" value="" />
			<button type="submit" class="corex-account__submit">Send</button>
			${ LINE }
		</form>
	`;
}

function mountGuest() {
	document.body.innerHTML = `
		<div class="corex-account" data-corex-account data-nonce="nonce-1">
			${ form( 'login' ) }
			${ form( 'register' ) }
			${ form( 'reset-request' ) }
		</div>
	`;
}

function mountMember() {
	document.body.innerHTML = `
		<div class="corex-account" data-corex-account data-nonce="nonce-1">
			${ form( 'profile' ) }
			<section class="corex-account__sessions" data-corex-account-sessions>
				<h3>Active sessions</h3>
				<ul data-corex-account-session-list></ul>
				<div class="corex-account__actions">
					<button type="button" data-corex-account-action="revoke-others">Others</button>
					<button type="button" data-corex-account-action="revoke-all">All</button>
				</div>
				${ LINE }
			</section>
			<section class="corex-account__notifications" data-corex-account-notifications>
				<h3>Recent activity</h3>
				<ul data-corex-account-notification-list></ul>
			</section>
		</div>
	`;
}

function loadScript() {
	jest.isolateModules( () => {
		require( SCRIPT );
	} );
}

/**
 * Answers every request the script makes with one message.
 *
 * @param {string}  message What the server says.
 * @param {boolean} ok      Whether it took what it was sent.
 */
function serverAnswers( message, ok = false ) {
	window.fetch = jest.fn( () =>
		Promise.resolve( {
			ok,
			json: () => Promise.resolve( { success: ok, message } ),
		} )
	);
}

/** The ways a request ends with nothing of the server's to show. */
const WITHOUT_AN_ANSWER = {
	'does not arrive': () =>
		Promise.reject( new TypeError( 'Failed to fetch' ) ),
	'is answered with a page and not with JSON': () =>
		Promise.resolve( {
			ok: true,
			json: () =>
				Promise.reject( new SyntaxError( 'Unexpected token <' ) ),
		} ),
	'is refused with no message': () =>
		Promise.resolve( {
			ok: false,
			json: () => Promise.resolve( {} ),
		} ),
};

function submit( kind ) {
	document
		.querySelector( `[data-corex-account-form="${ kind }"]` )
		.dispatchEvent(
			new Event( 'submit', { cancelable: true, bubbles: true } )
		);
}

/** Where a message is on the page: the form or section of every line that holds one. */
function messages() {
	return Array.from( document.querySelectorAll( '.corex-account__status' ) )
		.filter( ( line ) => line.textContent !== '' )
		.map( ( line ) => ( {
			in:
				line.parentElement.getAttribute( 'data-corex-account-form' ) ||
				line.parentElement.className,
			text: line.textContent,
			state: line.getAttribute( 'data-state' ),
		} ) );
}

let reducedMotion;
let scrolled;

beforeEach( () => {
	reducedMotion = false;
	scrolled = [];
	window.corexAccount = {
		restUrl: 'https://site.test/wp-json/corex/v1/',
		i18n: { noAnswer: NO_ANSWER },
	};
	window.matchMedia = jest.fn( ( query ) => ( {
		matches: reducedMotion && query.includes( 'prefers-reduced-motion' ),
	} ) );
	// jsdom lays nothing out, so it has no scrollIntoView: record what would have been asked.
	window.HTMLElement.prototype.scrollIntoView = function ( options ) {
		scrolled.push( { line: this, options } );
	};
} );

afterEach( () => {
	jest.useRealTimers();
	delete window.corexAccount;
	delete window.fetch;
	delete window.matchMedia;
	delete window.HTMLElement.prototype.scrollIntoView;
	document.body.innerHTML = '';
} );

it.each( [ 'login', 'register', 'reset-request' ] )(
	'writes what the server said about the %s form in that form, and nowhere else',
	async ( kind ) => {
		mountGuest();
		serverAnswers( 'Not accepted.' );
		loadScript();

		submit( kind );
		await flush();

		expect( messages() ).toEqual( [
			{ in: kind, text: 'Not accepted.', state: 'error' },
		] );
	}
);

it( 'writes a saved profile into the profile form, as a success', async () => {
	mountMember();
	serverAnswers( 'Profile saved.', true );
	loadScript();

	submit( 'profile' );
	await flush();

	expect( messages() ).toEqual( [
		{ in: 'profile', text: 'Profile saved.', state: 'ok' },
	] );
} );

it( 'writes the answer to a session button beside the session buttons', async () => {
	mountMember();
	serverAnswers( 'Other sessions signed out.', true );
	loadScript();

	document
		.querySelector( '[data-corex-account-action="revoke-others"]' )
		.click();
	await flush();

	expect( messages() ).toEqual( [
		{
			in: 'corex-account__sessions',
			text: 'Other sessions signed out.',
			state: 'ok',
		},
	] );
} );

it( 'leaves one message on the page when a second form is sent', async () => {
	mountGuest();
	serverAnswers( 'Not accepted.' );
	loadScript();
	submit( 'login' );
	await flush();

	serverAnswers( 'Reset link on its way.', true );
	submit( 'reset-request' );
	await flush();

	expect( messages() ).toEqual( [
		{ in: 'reset-request', text: 'Reset link on its way.', state: 'ok' },
	] );
} );

it( 'takes the old message away while the next answer is awaited', async () => {
	mountGuest();
	serverAnswers( 'Not accepted.' );
	loadScript();
	submit( 'login' );
	await flush();

	submit( 'login' );

	expect( messages() ).toEqual( [] );
} );

it( 'brings the line that got the message into the window by the shortest way', async () => {
	mountGuest();
	serverAnswers( 'Not accepted.' );
	loadScript();

	submit( 'register' );
	await flush();

	const line = document.querySelector(
		'[data-corex-account-form="register"] .corex-account__status'
	);
	expect( scrolled ).toEqual( [
		{
			line,
			options: {
				block: 'nearest',
				inline: 'nearest',
				behavior: 'smooth',
			},
		},
	] );
} );

it( 'moves the page at once for a person who asked for less motion', async () => {
	reducedMotion = true;
	mountGuest();
	serverAnswers( 'Not accepted.' );
	loadScript();

	submit( 'login' );
	await flush();

	expect( scrolled.map( ( call ) => call.options.behavior ) ).toEqual( [
		'instant',
	] );
} );

it( 'does not move the page for an answer that was taken and says nothing', async () => {
	mountGuest();
	serverAnswers( '', true );
	loadScript();

	submit( 'reset-request' );
	await flush();

	expect( scrolled ).toEqual( [] );
} );

it.each( Object.keys( WITHOUT_AN_ANSWER ) )(
	'says in the form that asked when its request %s',
	async ( ending ) => {
		mountGuest();
		window.fetch = jest.fn( WITHOUT_AN_ANSWER[ ending ] );
		loadScript();

		submit( 'reset-request' );
		await flush();

		expect( messages() ).toEqual( [
			{ in: 'reset-request', text: NO_ANSWER, state: 'error' },
		] );
	}
);

it( 'says beside the session buttons when their request does not arrive', async () => {
	mountMember();
	// The lists the member view loads are answered; what a button asks for is not.
	window.fetch = jest.fn( ( url, options = {} ) =>
		options.method === 'POST'
			? Promise.reject( new TypeError( 'Failed to fetch' ) )
			: Promise.resolve( { ok: true, json: () => Promise.resolve( {} ) } )
	);
	loadScript();

	document
		.querySelector( '[data-corex-account-action="revoke-all"]' )
		.click();
	await flush();

	expect( messages() ).toEqual( [
		{ in: 'corex-account__sessions', text: NO_ANSWER, state: 'error' },
	] );
} );

it( 'says so in English on a page that handed the script no sentence', async () => {
	delete window.corexAccount.i18n;
	mountGuest();
	window.fetch = jest.fn( WITHOUT_AN_ANSWER[ 'does not arrive' ] );
	loadScript();

	submit( 'login' );
	await flush();

	expect( messages() ).toEqual( [
		{
			in: 'login',
			text: 'The site could not be reached, or its answer could not be read. Please try again.',
			state: 'error',
		},
	] );
} );

/**
 * The member view's two lists: what the server gives for each, and how the page words it.
 * A row is its lines, from the top. A session comes with more than is printed of it.
 */
const LISTS = {
	sessions: {
		section: '[data-corex-account-sessions]',
		route: 'account/sessions',
		given: {
			success: true,
			sessions: [
				{
					device: 'Firefox on Linux',
					signedIn: 'Signed in 13 October 2026 at 09:00',
					ua: 'Mozilla/5.0 (X11; Linux x86_64; rv:143.0) Gecko/20100101 Firefox/143.0',
					ip: '203.0.113.7',
					login: 1791882000,
					current: true,
				},
				{
					device: 'Safari on iPhone',
					signedIn: 'Signed in 12 October 2026 at 18:30',
					ua: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Version/17.5 Safari/604.1',
					ip: '198.51.100.24',
					login: 1791829800,
					current: false,
				},
			],
		},
		rows: [
			[
				'Firefox on Linux — this device',
				'Signed in 13 October 2026 at 09:00',
			],
			[ 'Safari on iPhone', 'Signed in 12 October 2026 at 18:30' ],
		],
		nothing: { success: true, sessions: [] },
		saysNothing: 'No sessions (as the page worded it).',
		saysNotLoaded: 'Sessions not loaded (as the page worded it).',
	},
	activity: {
		section: '[data-corex-account-notifications]',
		route: 'account/notifications',
		given: {
			success: true,
			notifications: [
				{
					label: 'Your profile was updated.',
					when: '13 October 2026 at 09:00',
					kind: 'profile.updated',
					occurredAt: '2026-10-13T06:00:00+00:00',
				},
			],
		},
		rows: [ [ 'Your profile was updated.', '13 October 2026 at 09:00' ] ],
		nothing: { success: true, notifications: [] },
		saysNothing: 'No activity (as the page worded it).',
		saysNotLoaded: 'Activity not loaded (as the page worded it).',
	},
};

/**
 * The ways a request for a list ends with no list to show.
 *
 * @param {Object} list One of LISTS.
 * @return {Object} What `fetch` gives, by how the request ended.
 */
const withoutAList = ( list ) => ( {
	...WITHOUT_AN_ANSWER,
	'is refused, whatever the refusal holds': () =>
		Promise.resolve( {
			ok: false,
			json: () => Promise.resolve( list.given ),
		} ),
	'is refused as WordPress refuses a signed-out person': () =>
		Promise.resolve( {
			ok: false,
			json: () =>
				Promise.resolve( {
					code: 'rest_cookie_invalid_nonce',
					message: 'Cookie check failed',
				} ),
		} ),
	'is answered with no list': () =>
		Promise.resolve( {
			ok: true,
			json: () => Promise.resolve( { success: true } ),
		} ),
} );

function answered( body, ok = true ) {
	return () => Promise.resolve( { ok, json: () => Promise.resolve( body ) } );
}

/**
 * Answers the member view's requests route by route. A list that is not named is given.
 *
 * @param {Object} routes What `fetch` resolves with, by the route under `corex/v1/`.
 */
function serverGives( routes = {} ) {
	const answers = {
		[ LISTS.sessions.route ]: answered( LISTS.sessions.given ),
		[ LISTS.activity.route ]: answered( LISTS.activity.given ),
		...routes,
	};
	window.fetch = jest.fn( ( url ) =>
		answers[ url.replace( window.corexAccount.restUrl, '' ) ]()
	);
}

/**
 * What a person is shown under a list's heading.
 *
 * @param {string} name A key of LISTS.
 * @return {{rows: string[][], says: string, state: ?string}} The rows, each as its lines, or the sentence in their place.
 */
function shown( name ) {
	const section = document.querySelector( LISTS[ name ].section );
	const list = section.querySelector( 'ul' );
	const note = section.querySelector( '.corex-account__list-note' );
	const noted = note !== null && ! note.hidden;

	return {
		rows: list.hidden
			? []
			: Array.from( list.children ).map( ( row ) =>
					Array.from( row.children ).map(
						( line ) => line.textContent
					)
				),
		says: noted ? note.textContent : '',
		state: noted ? note.getAttribute( 'data-state' ) : null,
	};
}

function press( action ) {
	document
		.querySelector( `[data-corex-account-action="${ action }"]` )
		.click();
}

describe.each( Object.keys( LISTS ) )( 'the %s list', ( name ) => {
	const list = LISTS[ name ];

	beforeEach( () => {
		window.corexAccount.i18n = {
			noSessions: LISTS.sessions.saysNothing,
			sessionsNotLoaded: LISTS.sessions.saysNotLoaded,
			noActivity: LISTS.activity.saysNothing,
			activityNotLoaded: LISTS.activity.saysNotLoaded,
		};
		mountMember();
	} );

	it( 'shows the rows the server gave, and no sentence in their place', async () => {
		serverGives();
		loadScript();
		await flush();

		expect( shown( name ) ).toEqual( {
			rows: list.rows,
			says: '',
			state: null,
		} );
	} );

	it( 'says there is nothing to list when the server gave an empty list', async () => {
		serverGives( { [ list.route ]: answered( list.nothing ) } );
		loadScript();
		await flush();

		expect( shown( name ) ).toEqual( {
			rows: [],
			says: list.saysNothing,
			state: 'empty',
		} );
	} );

	it.each( Object.keys( withoutAList( list ) ) )(
		'says it could not be loaded when its request %s',
		async ( ending ) => {
			serverGives( { [ list.route ]: withoutAList( list )[ ending ] } );
			loadScript();
			await flush();

			expect( shown( name ) ).toEqual( {
				rows: [],
				says: list.saysNotLoaded,
				state: 'error',
			} );
		}
	);

	it( 'is not an answer to a press: it empties no message line and moves nothing', async () => {
		serverGives( {
			[ list.route ]: WITHOUT_AN_ANSWER[ 'does not arrive' ],
		} );
		loadScript();
		await flush();

		expect( messages() ).toEqual( [] );
		expect( scrolled ).toEqual( [] );
	} );
} );

describe( 'a list asked for again', () => {
	beforeEach( () => {
		window.corexAccount.i18n = {
			sessionsNotLoaded: LISTS.sessions.saysNotLoaded,
		};
		mountMember();
	} );

	it( 'does not go on showing the sessions it had when the next request for them fails', async () => {
		serverGives();
		loadScript();
		await flush();
		serverGives( {
			'account/profile': answered( {
				success: true,
				message: 'Profile saved.',
			} ),
			[ LISTS.sessions.route ]: WITHOUT_AN_ANSWER[ 'does not arrive' ],
		} );

		submit( 'profile' );
		await flush();

		expect( shown( 'sessions' ) ).toEqual( {
			rows: [],
			says: LISTS.sessions.saysNotLoaded,
			state: 'error',
		} );
		// What the form was answered stays where it is, and is the one thing brought into view.
		expect( messages() ).toEqual( [
			{ in: 'profile', text: 'Profile saved.', state: 'ok' },
		] );
		expect( scrolled ).toHaveLength( 1 );
	} );

	it( 'shows the sessions, and takes the sentence away, once a request for them is answered', async () => {
		serverGives( {
			[ LISTS.sessions.route ]: WITHOUT_AN_ANSWER[ 'does not arrive' ],
		} );
		loadScript();
		await flush();
		serverGives( {
			'account/sessions/revoke-others': answered( {
				success: true,
				message: 'Other sessions signed out.',
			} ),
		} );

		press( 'revoke-others' );
		await flush();

		expect( shown( 'sessions' ) ).toEqual( {
			rows: LISTS.sessions.rows,
			says: '',
			state: null,
		} );
	} );

	it( 'is not put in place of the sessions of a person who has just signed out of every session', async () => {
		// The page is loaded again a moment later, which jsdom cannot do: the clock is held.
		jest.useFakeTimers();
		serverGives();
		loadScript();
		await jest.advanceTimersByTimeAsync( 0 );
		// As the site answers from then on: nobody is signed in, and the list is refused.
		serverGives( {
			'account/sessions/revoke-all': answered( {
				success: true,
				message: 'Signed out of every session.',
			} ),
			[ LISTS.sessions.route ]: withoutAList( LISTS.sessions )[
				'is refused as WordPress refuses a signed-out person'
			],
		} );

		press( 'revoke-all' );
		await jest.advanceTimersByTimeAsync( 0 );

		expect( shown( 'sessions' ) ).toEqual( {
			rows: LISTS.sessions.rows,
			says: '',
			state: null,
		} );
	} );

	it( 'shows the sessions the site holds now when signing out of every session was refused', async () => {
		serverGives();
		loadScript();
		await flush();
		serverGives( {
			'account/sessions/revoke-all': answered(
				{ success: false, message: 'Not allowed.' },
				false
			),
			[ LISTS.sessions.route ]: answered( {
				success: true,
				sessions: [ { device: 'Chrome on Windows', current: true } ],
			} ),
		} );

		press( 'revoke-all' );
		await flush();

		expect( shown( 'sessions' ).rows ).toEqual( [
			[ 'Chrome on Windows — this device' ],
		] );
	} );
} );

/** What the script asked the server for, by method and route, in the order it asked. */
function asked() {
	return window.fetch.mock.calls.map(
		( [ url, options = {} ] ) =>
			( options.method || 'GET' ) +
			' ' +
			url.replace( window.corexAccount.restUrl, '' )
	);
}

describe( 'what was just done', () => {
	const SAVED = {
		label: 'Your profile was updated.',
		when: '13 October 2026 at 09:05',
	};
	const SIGNED_OUT = {
		label: 'Your other sessions were signed out.',
		when: '13 October 2026 at 09:06',
	};

	beforeEach( () => {
		mountMember();
	} );

	it( 'is listed under Recent activity once a profile is saved, without the page being loaded again', async () => {
		serverGives();
		loadScript();
		await flush();
		serverGives( {
			'account/profile': answered( {
				success: true,
				message: 'Profile saved.',
			} ),
			[ LISTS.activity.route ]: answered( {
				success: true,
				notifications: [ SAVED ],
			} ),
		} );

		submit( 'profile' );
		await flush();

		expect( shown( 'activity' ).rows ).toEqual( [
			[ SAVED.label, SAVED.when ],
		] );
	} );

	it( 'is listed under Recent activity once the other sessions are signed out', async () => {
		serverGives();
		loadScript();
		await flush();
		serverGives( {
			'account/sessions/revoke-others': answered( {
				success: true,
				message: 'Other sessions signed out.',
			} ),
			[ LISTS.activity.route ]: answered( {
				success: true,
				notifications: [ SIGNED_OUT ],
			} ),
		} );

		press( 'revoke-others' );
		await flush();

		expect( shown( 'activity' ).rows ).toEqual( [
			[ SIGNED_OUT.label, SIGNED_OUT.when ],
		] );
	} );

	it( 'is not asked about after a profile that was not saved: nothing was done', async () => {
		serverGives();
		loadScript();
		await flush();
		serverGives( {
			'account/profile': answered(
				{ success: false, message: 'Enter a display name.' },
				false
			),
		} );

		submit( 'profile' );
		await flush();

		expect( asked() ).toEqual( [ 'POST account/profile' ] );
	} );
} );

it( 'draws a session WordPress recorded no sign-in time for as its device alone', async () => {
	mountMember();
	serverGives( {
		[ LISTS.sessions.route ]: answered( {
			success: true,
			sessions: [
				{ device: 'Chrome on Android', signedIn: '', current: false },
			],
		} ),
	} );
	loadScript();
	await flush();

	expect( shown( 'sessions' ).rows ).toEqual( [ [ 'Chrome on Android' ] ] );
} );

it.each( [
	[
		'could not be loaded',
		{},
		{
			sessions: {
				rows: [],
				says: 'Your sessions could not be loaded. Load this page again to see them.',
				state: 'error',
			},
			activity: {
				rows: [],
				says: 'Your recent activity could not be loaded. Load this page again to see it.',
				state: 'error',
			},
		},
	],
	[
		'has nothing in it',
		{ sessions: [], notifications: [] },
		{
			sessions: {
				rows: [],
				says: 'No sessions are recorded for this account.',
				state: 'empty',
			},
			activity: {
				rows: [],
				says: 'No activity yet.',
				state: 'empty',
			},
		},
	],
] )(
	'says in English that a list %s on a page that handed the script no sentence',
	async ( _case, body, expected ) => {
		delete window.corexAccount.i18n;
		mountMember();
		serverGives( {
			[ LISTS.sessions.route ]: answered( body ),
			[ LISTS.activity.route ]: answered( body ),
		} );
		loadScript();
		await flush();

		expect( {
			sessions: shown( 'sessions' ),
			activity: shown( 'activity' ),
		} ).toEqual( expected );
	}
);
