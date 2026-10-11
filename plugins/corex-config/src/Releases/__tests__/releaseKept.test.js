/**
 * The release before, going back to it, and what happened here (spec 107, slice 5; T061, T064).
 *
 * What the Releases screen says is kept and until when, what it asks before the site goes back
 * and says afterwards, and the record of each installation, refusal and return.
 *
 * The client is the one thing replaced: each question waits for the test to answer it.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import ReleasesApp from '../ReleasesApp.js';
import { ReleaseRefusedError } from '../releaseClient.js';
import {
	installDateTimeConfig,
	removeDateTimeConfig,
} from '../../../../../tests/Support/adminDateTimeConfig.js';

const COPY = 'wp_corex_activity_events_cxrel_202610111200';

const PREVIOUS = {
	status: 'kept',
	version: '0.44.0',
	replaced_by: '0.45.0',
	at: '2026-10-11T12:00:00+00:00',
	by: 7,
	phrase: '0.44.0',
	copies: [
		{
			table: 'wp_corex_activity_events',
			copy: COPY,
			made_at: '2026-10-11T12:00:00+00:00',
			goes_at: '2026-10-25T12:00:00+00:00',
		},
		{
			table: 'wp_corex_notifications',
			copy: 'wp_corex_notifications_cxrel_202610111200',
			made_at: '2026-10-11T12:00:00+00:00',
			goes_at: '2026-10-25T12:00:00+00:00',
		},
	],
	copies_days: 14,
};

const OVERVIEW = {
	installed: { known: true, version: '0.45.0', client: 'acme' },
	installation: null,
	blockers: [],
	packages: [],
	part_bytes: 4,
	previous: PREVIOUS,
	record: [],
	package_days: 7,
};

let container;
let root;
/** Each question the screen has asked, waiting for the test to answer it. */
let asked;

function waitingClient() {
	const ask =
		( question ) =>
		( ...given ) =>
			new Promise( ( resolve, reject ) => {
				asked.push( { question, given, resolve, reject } );
			} );

	return Object.fromEntries(
		[ 'overview', 'inspect', 'upload', 'goBack' ].map( ( question ) => [
			question,
			ask( question ),
		] )
	);
}

async function answer( question, value ) {
	const waiting = asked.find(
		( candidate ) => candidate.question === question && ! candidate.done
	);
	waiting.done = true;
	await act( async () => {
		if ( value instanceof Error ) {
			waiting.reject( value );
		} else {
			waiting.resolve( value );
		}
	} );
}

const button = ( name ) =>
	[ ...container.querySelectorAll( 'button' ) ].find(
		( candidate ) => candidate.textContent.trim() === name
	);

function press( name ) {
	act( () => {
		button( name ).click();
	} );
}

function type( id, value ) {
	const field = container.querySelector( `#${ id }` );
	const { set } = Object.getOwnPropertyDescriptor(
		window.HTMLInputElement.prototype,
		'value'
	);
	act( () => {
		set.call( field, value );
		field.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
	} );
}

async function openTheScreen( overview = OVERVIEW ) {
	act( () => {
		root.render( <ReleasesApp client={ waitingClient() } /> );
	} );
	await answer( 'overview', overview );
}

const kept = () => container.querySelector( '.corex-releases__kept' );

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	installDateTimeConfig();
} );

afterAll( removeDateTimeConfig );

beforeEach( () => {
	asked = [];
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	document.body.innerHTML = '';
} );

describe( 'the release before', () => {
	it( 'is named, with the release that replaced it', async () => {
		await openTheScreen();

		expect( kept().textContent ).toContain(
			'CoreX 0.44.0 is kept. CoreX 0.45.0 replaced it on'
		);
	} );

	it( 'is not offered on a site that has none to go back to', async () => {
		await openTheScreen( { ...OVERVIEW, previous: null } );

		expect( kept() ).toBeNull();
	} );

	it( 'says the copies of the tables are there, how they are named, and when they go', async () => {
		await openTheScreen();

		expect( kept().textContent ).toContain(
			'A copy of each of 2 of CoreX’s tables'
		);
		expect( kept().textContent ).toContain( COPY );
		expect( kept().textContent ).toContain( 'are kept 14 days' );
		expect( kept().textContent ).toMatch( /removed on .*2026/ );
	} );

	it( 'says so when no copy is kept', async () => {
		await openTheScreen( {
			...OVERVIEW,
			previous: { ...PREVIOUS, copies: [] },
		} );

		expect( kept().textContent ).toContain(
			'No copy of CoreX’s tables is kept'
		);
	} );

	it( 'says a release the site could not name is kept, and asks for the one being left', async () => {
		await openTheScreen( {
			...OVERVIEW,
			previous: { ...PREVIOUS, version: null, phrase: '0.45.0' },
		} );

		expect( kept().textContent ).toContain(
			'It did not say which it was.'
		);

		press( 'Go back to the release before…' );

		expect( kept().textContent ).toContain( 'Type 0.45.0 to confirm' );
	} );

	it( 'says when an earlier attempt to go back left the site part one release and part the other', async () => {
		await openTheScreen( {
			...OVERVIEW,
			previous: { ...PREVIOUS, status: 'stuck' },
		} );

		expect( kept().textContent ).toContain(
			'the site is part one release and part the other'
		);
	} );
} );

describe( 'going back', () => {
	beforeEach( async () => {
		await openTheScreen();
		press( 'Go back to CoreX 0.44.0…' );
	} );

	it( 'says what it does not undo before it asks', () => {
		expect( kept().textContent ).toContain(
			'It reverses no change to the database'
		);
		expect( button( 'Go back to CoreX 0.44.0' ).disabled ).toBe( true );
	} );

	it( 'is one action, once the version is typed', async () => {
		type( 'corex-releases-return', '0.44.0' );
		press( 'Go back to CoreX 0.44.0' );

		expect( asked.at( -1 ) ).toMatchObject( {
			question: 'goBack',
			given: [ '0.44.0' ],
		} );

		await answer( 'goBack', {
			version: '0.44.0',
			left: '0.45.0',
			database_changes_reversed: 0,
		} );

		expect( document.activeElement.textContent ).toBe(
			'The site is back on CoreX 0.44.0'
		);
		expect( container.textContent ).toContain(
			'No change to the database was reversed.'
		);
		// And the screen asks the site what is there now.
		expect( asked.at( -1 ).question ).toBe( 'overview' );
	} );

	it( 'says why the site did not go back, and can be tried again', async () => {
		type( 'corex-releases-return', '0.44.0' );
		press( 'Go back to CoreX 0.44.0' );
		await answer(
			'goBack',
			new ReleaseRefusedError(
				'installation_in_the_way',
				'An installation is in progress and has not moved anything yet.'
			)
		);

		expect( kept().textContent ).toContain(
			'An installation is in progress and has not moved anything yet.'
		);
		expect( button( 'Go back to CoreX 0.44.0' ).disabled ).toBe( false );
	} );

	it( 'is put away when the administrator decides to stay', () => {
		press( 'Stay on this release' );

		expect( button( 'Go back to CoreX 0.44.0…' ) ).toBeDefined();
		expect(
			container.querySelector( '#corex-releases-return' )
		).toBeNull();
	} );
} );

describe( 'what happened here', () => {
	const at = '2026-10-11T12:00:00+00:00';
	const history = () =>
		[ ...container.querySelectorAll( '.corex-releases__entry' ) ].map(
			( entry ) => entry.textContent
		);

	it( 'says so when nothing has', async () => {
		await openTheScreen();

		expect( container.textContent ).toContain(
			'Nothing has been installed, refused or put back from this screen yet.'
		);
	} );

	it.each( [
		[
			'an installation',
			{
				event: 'installed',
				version: '0.45.0',
				from: '0.44.0',
				by: 7,
				by_name: 'Mona',
			},
			[ 'CoreX 0.45.0 was installed, replacing 0.44.0.', 'Mona' ],
		],
		[
			'the first installation the site recorded',
			{
				event: 'installed',
				version: '0.45.0',
				from: null,
				by: 7,
				by_name: 'Mona',
			},
			[ 'CoreX 0.45.0 was installed.' ],
		],
		[
			'a return from the screen',
			{
				event: 'returned',
				version: '0.44.0',
				from: '0.45.0',
				by: 7,
				by_name: 'Mona',
			},
			[ 'The site went back to CoreX 0.44.0 from 0.45.0.', 'Mona' ],
		],
		[
			'a return through the recovery link',
			{
				event: 'returned',
				version: '0.44.0',
				from: '0.45.0',
				by: 0,
				by_name: '',
				through: 'recovery_link',
			},
			[
				'The site went back to CoreX 0.44.0 from 0.45.0.',
				'The recovery link',
			],
		],
		[
			'a return to a release the site could not name',
			{
				event: 'returned',
				version: 'unknown',
				from: '0.45.0',
				by: 7,
				by_name: 'Mona',
			},
			[
				'The site went back from CoreX 0.45.0 to the release before it.',
			],
		],
		[
			'an installation that did not complete',
			{
				event: 'failed',
				version: '0.45.0',
				from: '0.44.0',
				by: 7,
				by_name: 'Mona',
				reason: 'database_change_failed',
			},
			[
				'An installation of CoreX 0.45.0 did not complete.',
				'database_change_failed',
			],
		],
		[
			'an installation that was given up',
			{
				event: 'cancelled',
				version: '0.45.0',
				from: '0.44.0',
				by: 7,
				by_name: 'Mona',
			},
			[ 'An installation of CoreX 0.45.0 was given up.' ],
		],
		[
			'a package that was refused',
			{
				event: 'refused',
				package: 'corex-release-globex-0.45.0.zip',
				reason: 'other_client',
				by: 7,
				by_name: 'Mona',
			},
			[
				'A package was refused: corex-release-globex-0.45.0.zip',
				'other_client',
				'Mona',
			],
		],
		[
			'somebody whose account is gone',
			{
				event: 'cancelled',
				version: '0.45.0',
				from: null,
				by: 31,
				by_name: '',
			},
			[ 'User 31' ],
		],
	] )(
		'says what happened for %s, and who did it',
		async ( _what, entry, says ) => {
			await openTheScreen( {
				...OVERVIEW,
				record: [ { ...entry, at } ],
			} );

			for ( const words of says ) {
				expect( history()[ 0 ] ).toContain( words );
			}
		}
	);
} );

describe( 'what the screen says of itself', () => {
	it( 'is not a backup, and says so', async () => {
		await openTheScreen();

		expect( container.textContent ).toContain(
			'It does not back the site up'
		);
	} );

	it( 'says how long a package that is not installed is kept', async () => {
		await openTheScreen( {
			...OVERVIEW,
			packages: [ { name: 'corex-release-acme-0.46.0.zip', bytes: 1 } ],
		} );

		expect( container.textContent ).toContain(
			'A package that is not installed is removed from the site after 7 days.'
		);
	} );
} );
