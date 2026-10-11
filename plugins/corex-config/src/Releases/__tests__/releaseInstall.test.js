/**
 * Installing a release from the Releases screen (spec 107, slice 4; T054).
 *
 * What an administrator is shown and asked before anything moves, what the screen says while
 * the site works, and how it ends: installed, not installed with the site as it was, or
 * stopped with the files in place and the way back offered.
 *
 * The client is the one thing replaced: each question waits for the test to answer it. Waiting
 * between requests takes no time.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import ReleasesApp from '../ReleasesApp.js';
import {
	ReleaseRefusedError,
	ReleaseUnreachableError,
} from '../releaseClient.js';
import { recoveryText } from '../recoveryFile.js';
import {
	installDateTimeConfig,
	removeDateTimeConfig,
} from '../../../../../tests/Support/adminDateTimeConfig.js';

jest.mock( '../installRun.js', () => {
	const actual = jest.requireActual( '../installRun.js' );

	return {
		runInstallation: ( client, options ) =>
			actual.runInstallation( client, {
				...options,
				wait: async () => {},
			} ),
	};
} );

const A_PACKAGE = 'corex-release-acme-0.45.0-20261011-120000.zip';
const LINK =
	'https://acme.test/?corex_release_key=abc123&corex_release_recover=1';

const OVERVIEW = {
	installed: { known: true, version: '0.44.0', client: 'acme' },
	installation: null,
	blockers: [],
	packages: [ { name: A_PACKAGE, bytes: 36700160 } ],
	part_bytes: 4,
};

const STATEMENT = {
	package: A_PACKAGE,
	version: '0.45.0',
	built_at: '2026-10-11T12:00:00+00:00',
	client: 'acme',
	replaces: '0.44.0',
	older: false,
	client_unconfirmed: false,
	files: 2650,
	bytes: 28228940,
	wordpress: { package: '7.1.3', site: '7.1.3' },
	blockers: [],
};

const PREPARED = {
	...STATEMENT,
	drops: [],
	key: 'abc123',
	recovery_link: LINK,
	copies_days: 14,
};

const standing = ( step, more = {} ) => ( {
	step,
	cursor: 0,
	of: 0,
	version: '0.45.0',
	package: A_PACKAGE,
	by: 1,
	prepared_at: '2026-10-11T12:00:00+00:00',
	before_the_swap: [ 'unpack', 'verify', 'copy_tables' ].includes( step ),
	failed: null,
	...more,
} );

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
		[
			'overview',
			'inspect',
			'upload',
			'prepare',
			'confirm',
			'step',
			'finish',
			'cancel',
		].map( ( question ) => [ question, ask( question ) ] )
	);
}

// Answers the question of that kind that has waited longest.
async function answer( question, value ) {
	const waiting = asked.find(
		( candidate ) => candidate.question === question && ! candidate.done
	);
	if ( ! waiting ) {
		throw new Error( `the screen has not asked: ${ question }` );
	}
	waiting.done = true;
	await act( async () => {
		if ( value instanceof Error ) {
			waiting.reject( value );
		} else {
			waiting.resolve( value );
		}
	} );
}

const questions = () => asked.map( ( { question } ) => question );

const button = ( name ) =>
	[ ...container.querySelectorAll( 'button' ) ].find(
		( candidate ) => candidate.textContent.trim() === name
	);

function press( name ) {
	act( () => {
		button( name ).click();
	} );
}

// Types into a field as a person does, so React hears of it.
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

async function readThePackage( statement = STATEMENT ) {
	press( 'Read this package' );
	await answer( 'inspect', statement );
}

async function askToInstall( prepared = PREPARED ) {
	await readThePackage();
	press( 'Install this release…' );
	await answer( 'prepare', prepared );
}

async function confirm() {
	type( 'corex-releases-version', '0.45.0' );
	press( 'Install CoreX 0.45.0' );
	await answer( 'confirm', standing( 'unpack' ) );
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
} );

afterEach( () => {
	act( () => root.unmount() );
	document.body.innerHTML = '';
} );

describe( 'a package that was read', () => {
	it( 'can be installed where nothing stands in the way', async () => {
		await openTheScreen();
		await readThePackage();

		expect( button( 'Install this release…' ).disabled ).toBe( false );
	} );

	it( 'cannot be installed where something does, and says so', async () => {
		await openTheScreen();
		await readThePackage( {
			...STATEMENT,
			blockers: [
				{
					reason: 'file_changes_off',
					message: 'This site is set not to change its own files.',
				},
			],
		} );

		expect( button( 'Install this release…' ) ).toBeUndefined();
		expect( container.textContent ).toContain(
			'This package cannot be installed on this host until what is listed above is dealt with.'
		);
	} );

	it( 'says when it was built with another WordPress than the site runs', async () => {
		await openTheScreen();
		await readThePackage( {
			...STATEMENT,
			wordpress: { package: '7.1.3', site: '7.2' },
		} );

		expect( container.textContent ).toContain(
			'This package was built with WordPress 7.1.3 and this site runs 7.2.'
		);
	} );

	it( 'gives a refusal to install as the site worded it', async () => {
		await openTheScreen();
		await readThePackage();
		press( 'Install this release…' );
		await answer(
			'prepare',
			new ReleaseRefusedError(
				'installation_in_progress',
				'An installation of 0.45.0 is in progress on this site.'
			)
		);

		expect( container.textContent ).toContain(
			'An installation of 0.45.0 is in progress on this site.'
		);
	} );
} );

describe( 'before it is confirmed', () => {
	beforeEach( () => openTheScreen() );

	it( 'gives the way back first, to keep', async () => {
		await askToInstall();

		const confirmPanel = container.querySelector(
			'.corex-releases__confirm'
		);

		expect(
			confirmPanel.querySelector( '#corex-releases-recovery' ).value
		).toBe( LINK );
		expect( button( 'Save it as a file' ) ).toBeDefined();
		expect( document.activeElement.textContent ).toBe(
			'Install CoreX 0.45.0'
		);
		// The rest of the screen is put away: nothing else is started beside an installation.
		expect( button( 'Send to the site' ) ).toBeUndefined();
	} );

	it( 'says what going back does not undo, and that this is not a backup', async () => {
		await askToInstall();

		expect( container.textContent ).toContain(
			'It reverses no change to the database'
		);
		expect( container.textContent ).toContain( 'kept for 14 days' );
		expect( container.textContent ).toContain(
			'This is not a backup of the site'
		);
	} );

	it( 'names the folders that leave the site with the previous release', async () => {
		await askToInstall( {
			...PREPARED,
			drops: [ 'wp-content/plugins/fluent-smtp' ],
		} );

		expect( container.textContent ).toContain(
			'They leave the site with the previous release'
		);
		expect( container.textContent ).toContain(
			'wp-content/plugins/fluent-smtp'
		);
	} );

	it( 'is not sent until the version is typed as it is', async () => {
		await askToInstall();

		expect( button( 'Install CoreX 0.45.0' ).disabled ).toBe( true );
		type( 'corex-releases-version', '0.45' );
		expect( button( 'Install CoreX 0.45.0' ).disabled ).toBe( true );
		type( 'corex-releases-version', '0.45.0' );
		expect( button( 'Install CoreX 0.45.0' ).disabled ).toBe( false );
	} );

	it( 'asks twice for an older release, and for the client on a site that cannot say whose it is', async () => {
		await askToInstall( {
			...PREPARED,
			older: true,
			client_unconfirmed: true,
		} );
		type( 'corex-releases-version', '0.45.0' );

		expect( button( 'Install CoreX 0.45.0' ).disabled ).toBe( true );

		type( 'corex-releases-client', 'acme' );
		act( () => container.querySelector( '#corex-releases-older' ).click() );

		expect( button( 'Install CoreX 0.45.0' ).disabled ).toBe( false );

		press( 'Install CoreX 0.45.0' );

		expect( asked.at( -1 ).given ).toEqual( [
			'0.45.0',
			{ older: true, client: 'acme' },
		] );
	} );

	it( 'says why a confirmation was not taken, and can be tried again', async () => {
		await askToInstall();
		type( 'corex-releases-version', '0.45.0' );
		press( 'Install CoreX 0.45.0' );
		await answer(
			'confirm',
			new ReleaseRefusedError( 'not_confirmed', 'Not confirmed.' )
		);

		expect( container.textContent ).toContain( 'Not confirmed.' );
		expect( button( 'Install CoreX 0.45.0' ).disabled ).toBe( false );
	} );

	it( 'is given up when the administrator decides not to, and the screen asks what is there', async () => {
		await askToInstall();
		press( 'Do not install' );
		await answer( 'cancel', undefined );

		expect( questions().at( -1 ) ).toBe( 'overview' );
	} );
} );

describe( 'while it runs', () => {
	beforeEach( async () => {
		await openTheScreen();
		await askToInstall();
		await confirm();
	} );

	it( 'says which step the site is at, in words, and how far into it', async () => {
		await answer(
			'step',
			standing( 'unpack', { cursor: 1200, of: 7340 } )
		);

		const now = container.querySelector( '[data-corex-step="now"]' );

		expect( now.textContent ).toContain(
			'Unpack the package beside the site'
		);
		expect( now.getAttribute( 'aria-current' ) ).toBe( 'step' );
		expect(
			container.querySelector( '.corex-releases__run [role="status"]' )
				.textContent
		).toBe(
			'Step 1 of 5: Unpack the package beside the site. 1,200 of 7,340.'
		);
	} );

	it( 'ends by saying the release is installed, and what moved in the database', async () => {
		await answer( 'step', standing( 'finish' ) );
		await answer( 'finish', {
			done: true,
			version: '0.45.0',
			changes: [
				{
					site: 1,
					component: 'product-foundation',
					from: '7',
					to: '8',
				},
				{ site: 1, component: 'newsletter', from: '', to: '1' },
			],
		} );

		expect( document.activeElement.textContent ).toBe(
			'CoreX 0.45.0 is installed'
		);
		expect( container.textContent ).toContain(
			'product-foundation went from version 7 to 8.'
		);
		expect( container.textContent ).toContain(
			'newsletter was made, at version 1.'
		);

		press( 'Done' );

		expect( questions().at( -1 ) ).toBe( 'overview' );
	} );

	it( 'says no change to the database was needed, when none was', async () => {
		await answer( 'step', standing( 'finish' ) );
		await answer( 'finish', {
			done: true,
			version: '0.45.0',
			changes: [],
		} );

		expect( container.textContent ).toContain(
			'No change to the database was needed.'
		);
	} );

	it( 'says the release was not installed and the site is as it was, when a step is refused before the swap', async () => {
		await answer(
			'step',
			new ReleaseRefusedError(
				'differs_from_description',
				'What was unpacked is not what the package says it holds.'
			)
		);

		expect( document.activeElement.textContent ).toBe(
			'The release was not installed'
		);
		expect( container.textContent ).toContain(
			'What was unpacked is not what the package says it holds.'
		);
		expect( container.textContent ).toContain(
			'The site is the release it was.'
		);
		expect( container.querySelector( `a[href="${ LINK }"]` ) ).toBeNull();
	} );

	it( 'offers the way back when the site stops answering with the files in place', async () => {
		await answer( 'step', standing( 'finish' ) );
		await answer( 'finish', new ReleaseUnreachableError( 'No answer.' ) );

		expect( document.activeElement.textContent ).toBe(
			'The installation did not finish'
		);
		expect(
			container.querySelector( `a[href="${ LINK }"]` )
		).not.toBeNull();
	} );
} );

describe( 'an installation somebody’s browser went away from', () => {
	it( 'is finished from here when its files are in place', async () => {
		await openTheScreen( {
			...OVERVIEW,
			installation: standing( 'finish' ),
		} );

		expect( container.textContent ).toContain(
			'The files of CoreX 0.45.0 are in place, and the installation was not finished'
		);
		expect( button( 'Send to the site' ) ).toBeUndefined();

		press( 'Finish the installation' );
		await answer( 'finish', {
			done: true,
			version: '0.45.0',
			changes: [],
		} );

		expect( container.textContent ).toContain(
			'CoreX 0.45.0 is installed'
		);
		// The key went with the other browser, and nothing asked for it.
		expect( questions() ).toEqual( [ 'overview', 'finish' ] );
	} );

	it( 'says why its database step stopped', async () => {
		await openTheScreen( {
			...OVERVIEW,
			installation: standing( 'finish', {
				failed: 'A change to the database did not complete on site 1.',
			} ),
		} );

		expect( container.textContent ).toContain(
			'its database step stopped. A change to the database did not complete on site 1.'
		);
	} );

	it( 'can be gone on with or given up before anything has moved', async () => {
		await openTheScreen( {
			...OVERVIEW,
			installation: standing( 'verify' ),
		} );

		expect( container.textContent ).toContain(
			'Nothing of the site has changed yet.'
		);
		expect( button( 'Go on with it' ) ).toBeDefined();

		press( 'Give it up' );
		await answer( 'cancel', undefined );

		expect( questions().at( -1 ) ).toBe( 'overview' );
	} );

	it( 'is not offered when it was only prepared: its key went with the page that asked', async () => {
		await openTheScreen( {
			...OVERVIEW,
			installation: standing( 'awaiting' ),
		} );

		expect( container.textContent ).not.toContain(
			'An installation is in progress'
		);
		expect( button( 'Send to the site' ) ).toBeDefined();
	} );

	it( 'offers nothing to press when a folder would neither move nor go back', async () => {
		await openTheScreen( {
			...OVERVIEW,
			installation: standing( 'stuck' ),
		} );

		expect( container.textContent ).toContain(
			'could be neither finished nor undone'
		);
		expect( button( 'Go on with it' ) ).toBeUndefined();
		expect( button( 'Give it up' ) ).toBeUndefined();
	} );
} );

describe( 'the way back, as a file', () => {
	it( 'holds the address, what it does and does not do, and that it is to be kept private', () => {
		const text = recoveryText( { link: LINK, version: '0.45.0' } );

		expect( text ).toContain( LINK );
		expect( text ).toContain( '0.45.0' );
		expect( text ).toContain( 'It reverses no change to the database.' );
		expect( text ).toContain( 'Keep this file to yourself' );
	} );
} );
