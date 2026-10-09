/**
 * The Releases screen (spec 107, slice 3; T037): what a person is told before anything on the
 * site changes.
 *
 * The screen asks the site through the installer's client. Here the client is the one thing
 * replaced: each question is answered by the test, when the test chooses, so what the screen
 * shows while it waits and what it shows for each answer can both be looked at.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import ReleasesApp from '../ReleasesApp.js';
import {
	ReleaseRefusedError,
	ReleaseUnreachableError,
} from '../releaseClient.js';
import {
	installDateTimeConfig,
	removeDateTimeConfig,
} from '../../../../../tests/Support/adminDateTimeConfig.js';

const A_PACKAGE = 'corex-release-acme-0.44.0-20261008-180000.zip';

const OVERVIEW = {
	installed: { known: true, version: '0.43.5', client: 'acme' },
	blockers: [],
	packages: [ { name: A_PACKAGE, bytes: 36700160 } ],
	part_bytes: 4,
};

const STATEMENT = {
	package: A_PACKAGE,
	version: '0.44.0',
	built_at: '2026-10-08T18:00:00+00:00',
	client: 'acme',
	replaces: '0.43.5',
	older: false,
	client_unconfirmed: false,
	files: 2650,
	bytes: 28228940,
	blockers: [],
};

let container;
let root;
/** Each question the screen has asked, waiting for the test to answer it. */
let asked;

/**
 * A client whose every question waits for the test.
 *
 * @return {Object} What `createReleaseClient` returns, as far as the screen uses it.
 */
function waitingClient() {
	const ask =
		( question ) =>
		( ...given ) =>
			new Promise( ( resolve, reject ) => {
				asked.push( { question, given, resolve, reject } );
			} );

	return {
		overview: ask( 'overview' ),
		inspect: ask( 'inspect' ),
		upload: ask( 'upload' ),
	};
}

// Answers the question of that kind that has waited longest, or the one `later` places after it.
async function answer( question, value, later = 0 ) {
	const waiting = asked.filter(
		( candidate ) => candidate.question === question && ! candidate.done
	)[ later ];
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
	act( () => {
		root.render( <ReleasesApp client={ waitingClient() } /> );
	} );
} );

afterEach( () => {
	act( () => root.unmount() );
	document.body.innerHTML = '';
} );

describe( 'when the screen is opened', () => {
	it( 'shows what is coming, and says nothing about the site before it knows', async () => {
		expect(
			container.querySelector( '.corex-loadable' ).dataset.corexState
		).toBe( 'loading' );
		expect( container.textContent ).not.toContain( 'This site' );

		await answer( 'overview', OVERVIEW );

		expect( container.textContent ).toContain(
			'This site is running CoreX 0.43.5, built for acme.'
		);
	} );

	it.each( [
		[
			'the framework alone',
			{ known: true, version: '0.43.5', client: null },
			'This site is running CoreX 0.43.5, the framework alone.',
		],
		[
			'a site that cannot say',
			{ known: false, version: null, client: null },
			'This site does not say which release it is running.',
		],
	] )( 'says what is running on %s', async ( _site, installed, says ) => {
		await answer( 'overview', { ...OVERVIEW, installed } );

		expect( container.textContent ).toContain( says );
	} );

	it( 'says what stands in the way on this host, in the host’s own terms', async () => {
		await answer( 'overview', {
			...OVERVIEW,
			blockers: [
				{
					reason: 'no_zip',
					message: 'This host’s PHP cannot open a zip.',
				},
			],
		} );

		expect( container.textContent ).toContain(
			'This host’s PHP cannot open a zip.'
		);
	} );

	it( 'says the site could not be asked, and asks again when told to', async () => {
		await answer(
			'overview',
			new Error( 'The site did not answer after 6 tries.' )
		);

		expect( container.textContent ).toContain(
			'The site did not answer after 6 tries.'
		);

		press( 'Try again' );

		expect(
			asked.filter( ( one ) => one.question === 'overview' )
		).toHaveLength( 2 );
	} );
} );

describe( 'a package on the site', () => {
	beforeEach( () => answer( 'overview', OVERVIEW ) );

	it( 'cannot be sent before a file is chosen', () => {
		expect( button( 'Send to the site' ).disabled ).toBe( true );
	} );

	it( 'is listed with its size, and read when asked', async () => {
		expect( container.textContent ).toContain( A_PACKAGE );
		expect( container.textContent ).toContain( '35 MB' );

		press( 'Read this package' );

		expect( button( 'Read this package' ).dataset.corexWorking ).toBe(
			'true'
		);
		expect( asked.at( -1 ).given ).toEqual( [ A_PACKAGE ] );

		await answer( 'inspect', STATEMENT );

		const statement = container.querySelector(
			'.corex-releases__statement'
		);
		expect( statement.textContent ).toContain( '0.44.0' );
		expect( statement.textContent ).toContain( 'acme' );
		expect( statement.textContent ).toContain( '0.43.5' );
		expect( statement.textContent ).toContain( '2,650' );
	} );

	it( 'says an older release will need saying twice', async () => {
		press( 'Read this package' );
		await answer( 'inspect', {
			...STATEMENT,
			version: '0.42.0',
			older: true,
		} );

		expect( container.textContent ).toContain(
			'This is an older release than the one this site is running.'
		);
	} );

	it( 'says whose the package is, on a site that cannot say whose it is itself', async () => {
		press( 'Read this package' );
		await answer( 'inspect', { ...STATEMENT, client_unconfirmed: true } );

		expect( container.textContent ).toContain(
			'This site cannot say whose it is, so nothing was compared.'
		);
	} );

	it( 'gives a refusal as the site worded it, and asks what the site holds now', async () => {
		press( 'Read this package' );
		await answer(
			'inspect',
			new ReleaseRefusedError(
				'other_client',
				'This package is for globex, and this site is acme.'
			)
		);

		expect(
			container.querySelector( '.corex-releases__statement' )
		).toBeNull();
		expect( container.textContent ).toContain(
			'The site did not take this package.'
		);
		expect( container.textContent ).toContain(
			'This package is for globex, and this site is acme.'
		);
		expect(
			button( 'Read this package' ).hasAttribute( 'data-corex-working' )
		).toBe( false );
		// A package the site refuses, it removes: the list is asked for again.
		expect( asked.at( -1 ).question ).toBe( 'overview' );
	} );

	it( 'says the site could not be asked, where that is what happened', async () => {
		press( 'Read this package' );
		await answer(
			'inspect',
			new ReleaseUnreachableError(
				'The site did not answer after 6 tries.'
			)
		);

		expect( container.textContent ).toContain(
			'The site could not be asked about this package.'
		);
		expect( container.textContent ).toContain(
			'The site did not answer after 6 tries.'
		);
		// Nothing is known to have changed, so nothing is asked for again.
		expect( asked.at( -1 ).question ).toBe( 'inspect' );
	} );
} );

describe( 'sending a package', () => {
	const chosen = { name: A_PACKAGE, size: 10 };

	beforeEach( async () => {
		await answer( 'overview', { ...OVERVIEW, packages: [] } );
		const input = container.querySelector( 'input[type="file"]' );
		Object.defineProperty( input, 'files', { value: [ chosen ] } );
		act( () => {
			input.dispatchEvent(
				new window.Event( 'change', { bubbles: true } )
			);
		} );
	} );

	it( 'says how far it has got, and reads the package when it is all there', async () => {
		press( 'Send to the site' );

		const sending = asked.at( -1 );
		expect( sending.question ).toBe( 'upload' );
		expect( sending.given[ 0 ] ).toBe( chosen );
		expect( sending.given[ 1 ].partBytes ).toBe( 4 );

		act( () => sending.given[ 1 ].onProgress( 4 ) );

		const progress = container.querySelector( 'progress' );
		expect( progress.value ).toBe( 4 );
		expect( progress.max ).toBe( 10 );

		await answer( 'upload', A_PACKAGE );

		// It is on the site now: the list is asked for again, and the package is read.
		expect( asked.map( ( one ) => one.question ).slice( -2 ) ).toEqual( [
			'overview',
			'inspect',
		] );
	} );

	it( 'keeps what the site said last, when an earlier answer comes back after it', async () => {
		press( 'Send to the site' );
		// Sent: the list is asked for, and the package is read. Refused: the list is asked
		// for again, while the first asking is still out.
		await answer( 'upload', A_PACKAGE );
		await answer(
			'inspect',
			new ReleaseRefusedError( 'not_a_package', 'This is not a package.' )
		);

		await answer( 'overview', { ...OVERVIEW, packages: [] }, 1 );
		await answer( 'overview', OVERVIEW );

		expect( container.textContent ).not.toContain( A_PACKAGE );
	} );

	it( 'says why a package was not kept', async () => {
		press( 'Send to the site' );
		await answer(
			'upload',
			new ReleaseRefusedError(
				'damaged',
				'What arrived is not the file that was sent.'
			)
		);

		expect( container.textContent ).toContain(
			'What arrived is not the file that was sent.'
		);
		expect( container.querySelector( 'progress' ) ).toBeNull();
	} );
} );
