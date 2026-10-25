/**
 * `npm run release:push` (spec 123, User Story 4; FR-013 to FR-015d; DECISIONS #512, #513).
 *
 * The command drives the Releases routes with the screen's own client and loop of steps, so these
 * tests use the real ones, against a fake site that answers as `ReleasesController` does. Nothing
 * waits: every pause is given a function that returns at once.
 */
import { createReleaseClient } from '../plugins/corex-config/src/Releases/releaseClient.js';
import { runInstallation } from '../plugins/corex-config/src/Releases/installRun.js';

const SITE = 'https://acme.example';
const USER = 'release-push';
const PASSWORD = 'abcd EFGH 1234 ijkl MNOP 5678';
const PACKAGE = 'corex-release-acme-0.48.0-20261025-120000.zip';
const BYTES = Buffer.from( 'twelve bytes' );
const HASH = 'f'.repeat( 64 );
const KEY = 'k'.repeat( 64 );
const RECOVERY = `${ SITE }/?corex_release_key=${ KEY }&corex_release_recover=1`;

let pushRelease, parseArguments, resolveSettings, EXIT;

beforeAll( async () => {
	( { pushRelease, parseArguments, resolveSettings, EXIT } =
		await import( '../scripts/release-push.mjs' ) );
} );

const marked = ( status, body ) => ( {
	status,
	text: async () => JSON.stringify( { corex_release: 1, ...body } ),
	clone() {
		return this;
	},
} );
const ok = ( data ) => marked( 200, { ok: true, data } );
const no = ( reason, message = `refused: ${ reason }` ) =>
	marked( 422, { ok: false, reason, message } );

/**
 * A site, as the Releases routes answer: what it runs, what it holds of the package, and the
 * steps of an installation. A route the test gives an answer for answers that instead.
 *
 * @param {Object} [given] Changes to the site, and `answers` by route.
 * @return {Object} `{ fetch, calls, held }`.
 */
function fakeSite( given = {} ) {
	const site = {
		runs: '0.47.0',
		held: 0,
		standing: null,
		steps: [ 'verify', 'copy_tables', 'swap', 'finish' ],
		statement: {
			package: PACKAGE,
			version: '0.48.0',
			client: 'acme',
			replaces: '0.47.0',
			older: false,
			client_unconfirmed: false,
			downgrades: [],
			blockers: [],
		},
		home: 200,
		silentAfterInstall: false,
		answers: {},
		...given,
	};
	const calls = [];

	const fetch = async ( url, init = {} ) => {
		const { searchParams } = new URL( url );
		const route = ( searchParams.get( 'rest_route' ) ?? '' ).replace(
			'/corex/v1/releases',
			''
		);
		const method = init.method ?? 'GET';
		const body =
			typeof init.body === 'string' ? JSON.parse( init.body ) : null;
		calls.push( { method, route, url, headers: init.headers ?? {}, body } );

		if ( url === `${ SITE }/` ) {
			return { status: site.home, text: async () => '<html>' };
		}
		if (
			init.headers?.Authorization !==
			`Basic ${ Buffer.from( `${ USER }:${ PASSWORD }` ).toString( 'base64' ) }`
		) {
			return {
				status: 401,
				text: async () =>
					JSON.stringify( {
						code: 'incorrect_password',
						message:
							'The provided password is an invalid application password.',
					} ),
				clone() {
					return this;
				},
			};
		}
		const answer = site.answers[ `${ method } ${ route }` ];
		if ( answer ) {
			return typeof answer === 'function' ? answer( init ) : answer;
		}

		const page = {
			status: 200,
			text: async () => '<html>a host page</html>',
			clone() {
				return this;
			},
		};
		switch ( `${ method } ${ route.replace( HASH, '<hash>' ) }` ) {
			case 'GET /wp/v2/users/me':
				return { ...page, text: async () => '{"id":7}' };
			case 'GET ':
				if ( site.silentAfterInstall && site.runs === '0.48.0' ) {
					return page;
				}
				return ok( {
					installed: {
						known: true,
						version: site.runs,
						client: 'acme',
					},
					blockers: [],
					part_bytes: 5,
					installation: site.standing,
				} );
			case 'GET /uploads/<hash>':
				return ok( { received: site.held } );
			case 'POST /uploads/<hash>':
				site.held += init.body.length;
				return ok( { received: site.held } );
			case 'POST /uploads/<hash>/complete':
				return ok( { package: PACKAGE } );
			case 'POST /inspect':
				return ok( site.statement );
			case 'POST /install/prepare':
				return ok( { key: KEY, recovery_link: RECOVERY, drops: [] } );
			case 'POST /install/confirm':
				return ok( { step: 'unpack' } );
			case 'POST /install/step':
				return ok( { step: site.steps.shift() } );
			case 'POST /install/finish':
				site.runs = '0.48.0';
				return ok( { done: true, version: '0.48.0', changes: [] } );
		}
		throw new Error( `The fake site has no route ${ method } ${ route }` );
	};

	return { fetch, calls, site };
}

/**
 * Run the command against a fake site, with nothing built and nothing kept on disk.
 *
 * @param {Object} site    The fake site.
 * @param {Object} [asked] Changes to what the arguments asked.
 * @param {Object} [kept]  What the state file holds before the run.
 * @return {Promise<Object>} `{ code, output, state }`.
 */
async function push( site, asked = {}, kept = null ) {
	const lines = [];
	const state = {
		held: kept,
		path: '/home/owner/.corex/release-push/state/acme.example.json',
		read: () => state.held,
		write: ( value ) => ( state.held = value ),
		clear: () => ( state.held = null ),
	};

	const code = await pushRelease(
		{
			client: 'acme',
			withPlugins: [],
			packagePath: `/packages/${ PACKAGE }`,
			acceptOlder: false,
			acceptDowngrades: false,
			resume: false,
			uploadOnly: false,
			...asked,
		},
		{ site: SITE, user: USER, password: PASSWORD, from: 'a test' },
		{
			fetch: site.fetch,
			createReleaseClient,
			runInstallation,
			buildPackage: async () => {
				throw new Error( 'nothing is built in these tests' );
			},
			openPackage: async ( path ) => ( {
				name: PACKAGE,
				size: BYTES.length,
				path,
				slice: ( from, to ) => BYTES.subarray( from, to ),
			} ),
			sha256OfFile: async () => HASH,
			state,
			out: ( line ) => lines.push( line ),
			wait: async () => {},
		}
	);

	return { code, output: lines.join( '\n' ), state: state.held };
}

const routesOf = ( calls ) =>
	calls.map(
		( c ) => `${ c.method } ${ c.route.replace( HASH, '<hash>' ) }`
	);

describe( 'release:push', () => {
	it( 'installs a package from the upload to the version the site runs, in the order the screen does', async () => {
		const site = fakeSite();

		const { code, output, state } = await push( site );

		expect( code ).toBe( EXIT.DONE );
		expect( routesOf( site.calls ) ).toEqual( [
			'GET /wp/v2/users/me',
			'GET ',
			'GET /uploads/<hash>',
			'POST /uploads/<hash>',
			'POST /uploads/<hash>',
			'POST /uploads/<hash>',
			'POST /uploads/<hash>/complete',
			'POST /inspect',
			'POST /install/prepare',
			'POST /install/confirm',
			'POST /install/step',
			'POST /install/step',
			'POST /install/step',
			'POST /install/step',
			'POST /install/finish',
			'GET ',
			'GET ',
		] );
		expect(
			site.calls.find( ( c ) => c.route === '/install/confirm' ).body
		).toEqual( {
			phrase: '0.48.0',
			client: 'acme',
			older: false,
			downgrade: false,
		} );
		expect( output ).toContain(
			'The site says it runs CoreX 0.48.0; its home page answers 200.'
		);
		// The installation is over, so its key and recovery link are no longer kept.
		expect( state ).toBeNull();
	} );

	it( 'sends the password only in the Authorization header, and never prints it or the key', async () => {
		const site = fakeSite();

		const { output } = await push( site );

		const routeCalls = site.calls.filter( ( c ) => c.url !== `${ SITE }/` );
		expect(
			routeCalls.every( ( c ) =>
				c.headers.Authorization?.startsWith( 'Basic ' )
			)
		).toBe( true );
		expect( routeCalls.every( ( c ) => ! c.headers[ 'X-WP-Nonce' ] ) ).toBe(
			true
		);
		expect(
			routeCalls.every( ( c ) => ! c.url.includes( PASSWORD ) )
		).toBe( true );
		expect( output ).not.toContain( PASSWORD );
		expect( output ).not.toContain( KEY );
	} );

	it( 'sends only what the site does not already hold', async () => {
		const site = fakeSite( { held: 10 } );

		await push( site );

		const parts = site.calls.filter(
			( c ) => c.method === 'POST' && c.route === `/uploads/${ HASH }`
		);
		expect(
			parts.map( ( c ) => new URL( c.url ).searchParams.get( 'offset' ) )
		).toEqual( [ '10' ] );
	} );

	it.each( [
		[ 'an older release', { older: true }, '--accept-older' ],
		[
			'older plugins',
			{
				downgrades: [
					{
						path: 'wp-content/plugins/akismet',
						name: 'Akismet',
						site: '5.3',
						package: '5.1',
					},
				],
			},
			'--accept-downgrades',
		],
		[ 'another client', { client: 'other' }, 'built for other, not acme' ],
	] )(
		'stops before anything starts for %s it was not told is meant',
		async ( _, statement, said ) => {
			const site = fakeSite();
			site.site.statement = { ...site.site.statement, ...statement };

			const { code, output } = await push( site );

			expect( code ).toBe( EXIT.USAGE );
			expect( output ).toContain( said );
			expect( output ).toContain( 'Nothing on the site changed.' );
			expect( routesOf( site.calls ) ).not.toContain(
				'POST /install/prepare'
			);
		}
	);

	it( 'says an installation stands and changes nothing, unless told to go on with it', async () => {
		const site = fakeSite( {
			standing: { step: 'finish', version: '0.48.0', package: PACKAGE },
		} );

		const { code, output } = await push( site );

		expect( code ).toBe( EXIT.REFUSED );
		expect( output ).toContain( 'stands on the site' );
		expect( output ).toContain( '--resume' );
		expect( routesOf( site.calls ) ).toEqual( [
			'GET /wp/v2/users/me',
			'GET ',
		] );
	} );

	it( 'goes on with a standing installation, carrying the key its first run kept past the maintenance answer', async () => {
		const site = fakeSite( {
			standing: { step: 'finish', version: '0.48.0', package: PACKAGE },
		} );

		const { code, state } = await push(
			site,
			{ resume: true },
			{ package: PACKAGE, key: KEY, recovery_link: RECOVERY }
		);

		const finish = site.calls.find(
			( c ) => c.route === '/install/finish'
		);
		expect( code ).toBe( EXIT.DONE );
		expect( finish.headers[ 'X-Corex-Release-Key' ] ).toBe( KEY );
		expect(
			new URL( finish.url ).searchParams.get( 'corex_release_key' )
		).toBe( KEY );
		expect( routesOf( site.calls ) ).not.toContain( 'POST /install/step' );
		expect( state ).toBeNull();
	} );

	it( 'gives the recovery link and the way back when the installation fails after the swap, and keeps them', async () => {
		const site = fakeSite( {
			answers: {
				'POST /install/finish': no(
					'database_change_failed',
					'The database step of 0.48.0 failed.'
				),
			},
		} );

		const { code, output, state } = await push( site );

		expect( code ).toBe( EXIT.REFUSED );
		expect( output ).toContain(
			'The database step of 0.48.0 failed. (database_change_failed)'
		);
		expect( output ).toContain( '--resume' );
		expect( output ).toContain( RECOVERY );
		expect( state ).toEqual(
			expect.objectContaining( { key: KEY, recovery_link: RECOVERY } )
		);
	} );

	it( 'says nothing changed when the site refuses before the swap, and keeps nothing', async () => {
		const site = fakeSite( {
			answers: { 'POST /install/step': no( 'not_writable' ) },
		} );

		const { code, output, state } = await push( site );

		expect( code ).toBe( EXIT.REFUSED );
		expect( output ).toContain( 'No folder of the site was moved' );
		expect( output ).not.toContain( RECOVERY );
		expect( state ).toBeNull();
	} );

	it( "passes on the site's own words when it does not allow the push, and sends nothing", async () => {
		const site = fakeSite( {
			answers: {
				'GET ': marked( 403, {
					ok: false,
					reason: 'push_off',
					message:
						"To allow it, add define( 'COREX_ALLOW_RELEASE_PUSH', true ); to its wp-config.php.",
				} ),
			},
		} );

		const { code, output } = await push( site );

		expect( code ).toBe( EXIT.REFUSED );
		expect( output ).toContain(
			"define( 'COREX_ALLOW_RELEASE_PUSH', true ); to its wp-config.php. (push_off)"
		);
		expect( routesOf( site.calls ) ).toEqual( [
			'GET /wp/v2/users/me',
			'GET ',
		] );
	} );

	it( "names WordPress's refusal of a wrong Application Password", async () => {
		const site = fakeSite();

		const { code, output } = await push( {
			...site,
			fetch: ( url, init ) =>
				site.fetch( url, {
					...init,
					headers: {
						...init.headers,
						Authorization: 'Basic d3Jvbmc=',
					},
				} ),
		} );

		expect( code ).toBe( EXIT.REFUSED );
		expect( output ).toContain(
			'WordPress refused the account (401 incorrect_password)'
		);
		// Said at once: the installer's client would take the 401 for a host's page and ask again.
		expect( routesOf( site.calls ) ).toEqual( [ 'GET /wp/v2/users/me' ] );
	} );

	it( 'says the installation finished when only the check after it gets no answer', async () => {
		const site = fakeSite( { silentAfterInstall: true } );

		const { code, output } = await push( site );

		expect( code ).toBe( EXIT.UNREACHABLE );
		expect( output ).toContain( 'Installed: CoreX 0.48.0.' );
		expect( output ).toContain(
			'The site finished the installation, then did not answer the check'
		);
		expect( output ).not.toContain( 'No folder of the site was moved' );
	} );

	it( 'reports a home page in maintenance mode without counting it as a failure', async () => {
		const site = fakeSite( { home: 503 } );

		const { code, output } = await push( site );

		expect( code ).toBe( EXIT.DONE );
		expect( output ).toContain(
			'is what a site in maintenance mode shows'
		);
	} );

	it( 'keeps no key when the site refuses the confirmation', async () => {
		const site = fakeSite( {
			answers: { 'POST /install/confirm': no( 'not_confirmed' ) },
		} );

		const { code, output, state } = await push( site );

		expect( code ).toBe( EXIT.REFUSED );
		expect( output ).toContain( 'Nothing on the site changed.' );
		expect( state ).toBeNull();
	} );
} );

describe( 'release:push arguments and settings', () => {
	// Paths as POSIX spells them, whatever the machine: Windows adds a drive and backslashes.
	const posix = ( path ) =>
		path.replace( /\\/g, '/' ).replace( /^[A-Za-z]:/, '' );
	const settingsFrom = ( env, files = {}, asked = { client: 'acme' } ) =>
		resolveSettings( {
			asked,
			env,
			home: '/home/owner',
			exists: ( path ) => posix( path ) in files,
			readFile: ( path ) => files[ posix( path ) ],
		} );

	it( 'reads the three variables', () => {
		expect(
			settingsFrom( {
				COREX_PUSH_SITE: 'https://acme.example/',
				COREX_PUSH_USER: USER,
				COREX_PUSH_APP_PASSWORD: PASSWORD,
			} )
		).toEqual( {
			site: SITE,
			user: USER,
			password: PASSWORD,
			from: 'the COREX_PUSH_* environment variables',
		} );
	} );

	it( "reads the client's own file in ~/.corex/release-push when nothing else is given", () => {
		const settings = settingsFrom(
			{},
			{
				'/home/owner/.corex/release-push/acme.json': JSON.stringify( {
					site: SITE,
					user: USER,
					application_password: PASSWORD,
				} ),
			}
		);

		expect( settings.site ).toBe( SITE );
		expect( posix( settings.from ) ).toBe(
			'/home/owner/.corex/release-push/acme.json'
		);
	} );

	it( 'refuses a settings file inside a git checkout', () => {
		expect( () =>
			settingsFrom(
				{},
				{ '/work/corex/push.json': '{}', '/work/corex/.git': '' },
				{ settingsPath: '/work/corex/push.json' }
			)
		).toThrow( 'inside a git checkout' );
	} );

	const siteSettings = ( site ) =>
		settingsFrom( {
			COREX_PUSH_SITE: site,
			COREX_PUSH_USER: USER,
			COREX_PUSH_APP_PASSWORD: PASSWORD,
		} );

	it.each( [
		'https://acme.example',
		'http://acme.test',
		'http://127.0.0.1:8080',
	] )( 'sends a password to %s', ( site ) => {
		expect( siteSettings( site ).site ).toBe( site );
	} );

	it( 'sends no password over plain HTTP to a host that is not local', () => {
		expect( () => siteSettings( 'http://acme.example' ) ).toThrow(
			'https://'
		);
	} );

	it( 'takes no secret and no unknown option from the command line', () => {
		expect( () =>
			parseArguments( [ '--client=acme', `--password=${ PASSWORD }` ] )
		).toThrow( 'Not an option' );
		expect(
			parseArguments( [
				'--client=acme',
				'--with-plugin=fluent-smtp',
				'--accept-older',
				'--resume',
			] )
		).toEqual(
			expect.objectContaining( {
				client: 'acme',
				withPlugins: [ 'fluent-smtp' ],
				acceptOlder: true,
				resume: true,
			} )
		);
	} );
} );
