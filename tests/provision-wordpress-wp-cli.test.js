/**
 * `.github/actions/provision-wordpress/ensure-wp-cli.sh`, run for real (DECISIONS #354).
 *
 * The script is the step that keeps a CI job alive when setup-php ends green without WP-CLI. On
 * most runs it has nothing to do, so the runs themselves say little about it: these cases put it
 * in each state a runner can be in and read what it leaves behind.
 *
 * It runs in bash with a search path made for the case: a directory holding a stand-in for
 * `curl`, the directory it installs into, and the system's own tools. The stand-in writes down
 * the address it was asked for and answers with a file the case names, so nothing here reaches
 * the network. The "release" is a two-line script that answers `--version` the way WP-CLI does.
 */
const { execFileSync, spawnSync } = require( 'node:child_process' );
const crypto = require( 'node:crypto' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );

const script = path.resolve(
	__dirname,
	'..',
	'.github',
	'actions',
	'provision-wordpress',
	'ensure-wp-cli.sh'
);

/*
 * On Windows `bash` is the launcher for the Linux subsystem, which is not what a contributor's
 * shell scripts run in. Git's own bash is found from where git keeps its programs, and it is
 * the one under `usr/bin`: the one under `bin` puts Git's directories, and the real curl, ahead
 * of the search path it was given.
 */
const gitTools = () =>
	path.resolve(
		execFileSync( 'git', [ '--exec-path' ], { encoding: 'utf8' } ).trim(),
		'../../../usr/bin'
	);
const onWindows = process.platform === 'win32';
const systemTools = onWindows ? [ gitTools() ] : [ '/usr/bin', '/bin' ];
const bash = onWindows ? path.join( systemTools[ 0 ], 'bash.exe' ) : 'bash';

const RELEASE = '2.12.0';
const RELEASE_ADDRESS =
	'https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar';

const answersAs = ( version ) =>
	`#!/usr/bin/env bash\necho 'WP-CLI ${ version }'\n`;

const CURL_STAND_IN = `#!/usr/bin/env bash
out=''
while [ $# -gt 1 ]; do
	[ "$1" = '--output' ] && out="$2"
	shift
done
echo "$1" >> "$CURL_ASKED"
[ "$CURL_ANSWERS" = 'no' ] && exit 22
cp "$CURL_ANSWERS" "$out"
`;

const sha256 = ( contents ) =>
	crypto.createHash( 'sha256' ).update( contents ).digest( 'hex' );

const forBash = ( file ) => file.replace( /\\/g, '/' );

const runners = [];

/**
 * A runner as setup-php left it.
 *
 * @param {Object}      state
 * @param {string|null} state.wp      What is at `wp` before the script runs, or null for nothing.
 * @param {string}      state.release What the release's address answers with.
 * @return {Object} Where things are, and how to read them back.
 */
const runner = ( { wp = null, release = answersAs( RELEASE ) } = {} ) => {
	const root = fs.mkdtempSync( path.join( os.tmpdir(), 'corex-wp-cli-' ) );
	runners.push( root );

	const standIns = path.join( root, 'stand-ins' );
	const installDir = path.join( root, 'bin' );
	const releaseFile = path.join( root, 'release.phar' );
	const asked = path.join( root, 'asked.txt' );

	fs.mkdirSync( standIns );
	fs.mkdirSync( installDir );
	fs.writeFileSync( path.join( standIns, 'curl' ), CURL_STAND_IN, {
		mode: 0o755,
	} );
	fs.writeFileSync( releaseFile, release );

	if ( wp !== null ) {
		fs.writeFileSync( path.join( installDir, 'wp' ), wp, { mode: 0o755 } );
	}

	return {
		standIns,
		installDir,
		releaseFile,
		asked,
		addressesAsked: () =>
			fs.existsSync( asked )
				? fs.readFileSync( asked, 'utf8' ).trim().split( '\n' )
				: [],
		wpInstalled: () =>
			fs.existsSync( path.join( installDir, 'wp' ) )
				? fs.readFileSync( path.join( installDir, 'wp' ), 'utf8' )
				: null,
	};
};

const ensureWpCli = ( on, env = {} ) => {
	const inherited = Object.fromEntries(
		Object.entries( process.env ).filter(
			( [ name ] ) => name.toUpperCase() !== 'PATH'
		)
	);

	return spawnSync( bash, [ script ], {
		encoding: 'utf8',
		env: {
			...inherited,
			PATH: [ on.standIns, on.installDir, ...systemTools ].join(
				path.delimiter
			),
			WP_CLI_FALLBACK_VERSION: RELEASE,
			WP_CLI_FALLBACK_SHA256: sha256( answersAs( RELEASE ) ),
			WP_CLI_INSTALL_DIR: forBash( on.installDir ),
			CURL_ASKED: forBash( on.asked ),
			CURL_ANSWERS: forBash( on.releaseFile ),
			...env,
		},
	} );
};

afterAll( () => {
	for ( const root of runners ) {
		fs.rmSync( root, { recursive: true, force: true, maxRetries: 3 } );
	}
} );

describe( 'ensure-wp-cli.sh, after setup-php', () => {
	test( 'leaves the WP-CLI that setup-php provided, and asks the network for nothing', () => {
		const on = runner( { wp: answersAs( '2.13.0' ) } );

		const run = ensureWpCli( on );

		expect( run.status ).toBe( 0 );
		expect( run.stdout ).toContain( 'WP-CLI 2.13.0 is installed.' );
		expect( on.addressesAsked() ).toEqual( [] );
		expect( on.wpInstalled() ).toBe( answersAs( '2.13.0' ) );
	} );

	// The runner of 2026-10-13: setup-php ended green, and the next step had no `wp` to run
	// (pull requests #392, #393 and #395). The warning is how a person counts these afterwards.
	test( 'installs the named release from its own download when the runner has no wp', () => {
		const on = runner();

		const run = ensureWpCli( on );

		expect( run.status ).toBe( 0 );
		expect( on.addressesAsked() ).toEqual( [ RELEASE_ADDRESS ] );
		expect( on.wpInstalled() ).toBe( answersAs( RELEASE ) );
		expect( run.stdout ).toMatch( /^::warning /m );
	} );

	test.each( [
		[ 'an empty file', '' ],
		[ 'a page of HTML', '<!DOCTYPE html>\n<title>429</title>\n' ],
	] )(
		'installs the release over %s left where wp belongs',
		( _what, leftover ) => {
			const on = runner( { wp: leftover } );

			const run = ensureWpCli( on );

			expect( run.status ).toBe( 0 );
			expect( on.wpInstalled() ).toBe( answersAs( RELEASE ) );
		}
	);

	test( 'installs nothing when the download is not the file the checksum names', () => {
		const on = runner( { release: answersAs( '2.12.0-tampered' ) } );

		const run = ensureWpCli( on );

		expect( run.status ).toBe( 1 );
		expect( run.stdout ).toContain(
			'does not have the SHA-256 action.yml names'
		);
		expect( on.wpInstalled() ).toBeNull();
	} );

	test( 'fails and names the address when the release cannot be downloaded', () => {
		const on = runner();

		const run = ensureWpCli( on, { CURL_ANSWERS: 'no' } );

		expect( run.status ).toBe( 1 );
		expect( run.stdout ).toContain(
			`could not be downloaded from ${ RELEASE_ADDRESS }`
		);
		expect( on.wpInstalled() ).toBeNull();
	} );

	test.each( [
		[
			'a version that is not a full one',
			{ WP_CLI_FALLBACK_VERSION: '2.12' },
			'WP_CLI_FALLBACK_VERSION has to be a full version',
		],
		[
			'a checksum that is not a SHA-256',
			{ WP_CLI_FALLBACK_SHA256: 'ce34ddd838f7351d' },
			'WP_CLI_FALLBACK_SHA256 has to be the 64 characters',
		],
	] )(
		'refuses %s on a runner that needs no fallback',
		( _what, pin, complaint ) => {
			const on = runner( { wp: answersAs( '2.13.0' ) } );

			const run = ensureWpCli( on, pin );

			expect( run.status ).toBe( 1 );
			expect( run.stdout ).toContain( complaint );
		}
	);
} );
