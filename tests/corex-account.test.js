/**
 * Jest unit tests for the account block's script (spec 068 US9, FR-161a).
 *
 * Loads the buildless script against jsdom, over the markup `AccountRenderer` prints, with the
 * server answered for. What they hold: a message is written beside the button that asked for
 * it, in one place, and is brought into the window when it would be outside it.
 */

const SCRIPT = '../addons/corex-profile/assets/account.js';

const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

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
				<ul data-corex-account-session-list></ul>
				<div class="corex-account__actions">
					<button type="button" data-corex-account-action="revoke-others">Others</button>
					<button type="button" data-corex-account-action="revoke-all">All</button>
				</div>
				${ LINE }
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
	window.corexAccount = { restUrl: 'https://site.test/wp-json/corex/v1/' };
	window.matchMedia = jest.fn( ( query ) => ( {
		matches: reducedMotion && query.includes( 'prefers-reduced-motion' ),
	} ) );
	// jsdom lays nothing out, so it has no scrollIntoView: record what would have been asked.
	window.HTMLElement.prototype.scrollIntoView = function ( options ) {
		scrolled.push( { line: this, options } );
	};
} );

afterEach( () => {
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

it( 'does not move the page for an answer that says nothing', async () => {
	mountGuest();
	serverAnswers( '' );
	loadScript();

	submit( 'login' );
	await flush();

	expect( scrolled ).toEqual( [] );
} );
