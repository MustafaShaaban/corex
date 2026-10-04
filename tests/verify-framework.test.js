/**
 * `npm run verify:framework`, run for real against a repository built for the purpose (spec 102).
 *
 * Each case makes a small git repository in a temporary directory — a "framework" commit, tagged,
 * then a client site committed on top with a baseline record naming that commit — and runs the
 * actual script in it. Nothing is mocked: the question the script answers is what git says
 * changed, and a stand-in for git would only be asserting its own answer.
 */
const { execFileSync, spawnSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );

const frameworkRoot = path.resolve( __dirname, '..' );
const script = path.join( frameworkRoot, 'scripts', 'verify-framework.mjs' );
const ownershipMap = fs.readFileSync(
	path.join( frameworkRoot, '.github', 'repository-ownership.json' ),
	'utf8'
);
const BASELINE = 'sites/acme/corex-baseline.json';

const repositories = [];

const git = ( root, ...args ) =>
	execFileSync( 'git', args, { cwd: root, encoding: 'utf8' } ).trim();

const write = ( root, file, contents ) => {
	const target = path.join( root, ...file.split( '/' ) );

	fs.mkdirSync( path.dirname( target ), { recursive: true } );
	fs.writeFileSync( target, contents );
};

const commitAll = ( root, message ) => {
	git( root, 'add', '-A' );
	git( root, 'commit', '-q', '-m', message );

	return git( root, 'rev-parse', 'HEAD' );
};

const writeBaseline = ( root, record, file = BASELINE ) =>
	write( root, file, JSON.stringify( record, null, 2 ) + '\n' );

/**
 * A client repository: a tagged framework commit, then a client site recording it.
 *
 * @return {{root:string, frameworkCommit:string}} Where it is, and the commit it records.
 */
const clientRepository = () => {
	const root = fs.mkdtempSync( path.join( os.tmpdir(), 'corex-verify-' ) );
	repositories.push( root );

	git( root, 'init', '-q', '-b', 'main' );
	git( root, 'config', 'user.email', 'test@example.com' );
	git( root, 'config', 'user.name', 'Test' );
	git( root, 'config', 'commit.gpgsign', 'false' );
	git( root, 'config', 'core.autocrlf', 'false' );

	write( root, '.github/repository-ownership.json', ownershipMap );
	write( root, 'README.md', 'The framework.\n' );
	write( root, 'plugins/core/core.php', '<?php // framework\n' );
	const frameworkCommit = commitAll( root, 'framework' );
	git( root, 'tag', 'v1.0.0' );

	write( root, 'sites/acme/acme-site/acme-site.php', '<?php // client\n' );
	write( root, '.github/workflows/site-acme.yml', 'name: Acme\n' );
	writeBaseline( root, {
		release: 'v1.0.0',
		commit: frameworkCommit,
		exceptions: [],
	} );
	commitAll( root, 'client site' );

	return { root, frameworkCommit };
};

const verify = ( root, args = [], env = {} ) =>
	spawnSync( process.execPath, [ script, ...args ], {
		cwd: root,
		encoding: 'utf8',
		env: {
			...process.env,
			GITHUB_REPOSITORY: '',
			COREX_REPOSITORY_ROLE: 'client',
			...env,
		},
	} );

const readmeException = {
	path: 'README.md',
	reason: 'Local note awaiting an upstream fix.',
	upstream: 'https://github.com/Acme/framework/issues/7',
};

afterAll( () => {
	for ( const root of repositories ) {
		fs.rmSync( root, { recursive: true, force: true, maxRetries: 3 } );
	}
} );

jest.setTimeout( 60000 );

describe( 'an untouched framework', () => {
	it( 'passes', () => {
		const { root } = clientRepository();
		const outcome = verify( root );

		expect( outcome.stdout ).toContain( 'framework\tPASS' );
		expect( outcome.status ).toBe( 0 );
	} );

	it( 'passes however much the client’s own files change', () => {
		const { root } = clientRepository();
		write( root, 'sites/acme/acme-theme/style.css', 'body{}\n' );
		write( root, '.github/workflows/site-acme.yml', 'name: Acme site\n' );

		expect( verify( root ).status ).toBe( 0 );
	} );
} );

describe( 'a changed framework file', () => {
	it( 'fails on one changed character, and names the file', () => {
		const { root } = clientRepository();
		write( root, 'README.md', 'The framework!\n' );
		const outcome = verify( root );

		expect( outcome.stdout ).toContain( 'DRIFT\tREADME.md' );
		expect( outcome.status ).toBe( 1 );
	} );

	it( 'fails the same way once the change is committed', () => {
		const { root } = clientRepository();
		write( root, 'plugins/core/core.php', '<?php // patched\n' );
		commitAll( root, 'patch the framework' );
		const outcome = verify( root );

		expect( outcome.stdout ).toContain( 'DRIFT\tplugins/core/core.php' );
		expect( outcome.status ).toBe( 1 );
	} );

	it( 'fails on a file added outside the client’s paths', () => {
		const { root } = clientRepository();
		write( root, 'handoff-notes.txt', 'left at the root\n' );
		const outcome = verify( root );

		expect( outcome.stdout ).toContain( 'DRIFT\thandoff-notes.txt' );
		expect( outcome.status ).toBe( 1 );
	} );

	it( 'fails on a deleted framework file', () => {
		const { root } = clientRepository();
		fs.rmSync( path.join( root, 'plugins', 'core', 'core.php' ) );
		const outcome = verify( root );

		expect( outcome.stdout ).toContain( 'DRIFT\tplugins/core/core.php' );
		expect( outcome.status ).toBe( 1 );
	} );
} );

describe( 'a recorded exception', () => {
	it( 'is reported with its reason, and passes', () => {
		const { root, frameworkCommit } = clientRepository();
		write( root, 'README.md', 'The framework, patched locally.\n' );
		writeBaseline( root, {
			release: 'v1.0.0',
			commit: frameworkCommit,
			exceptions: [ readmeException ],
		} );
		const outcome = verify( root );

		expect( outcome.stdout ).toContain(
			`EXCEPTION\tREADME.md\t${ readmeException.reason }`
		);
		expect( outcome.status ).toBe( 0 );
	} );

	it( 'fails as stale once the file no longer differs', () => {
		const { root, frameworkCommit } = clientRepository();
		writeBaseline( root, {
			release: 'v1.0.0',
			commit: frameworkCommit,
			exceptions: [ readmeException ],
		} );
		const outcome = verify( root );

		expect( outcome.stdout ).toContain( 'STALE\tREADME.md' );
		expect( outcome.status ).toBe( 1 );
	} );
} );

describe( 'a baseline that cannot be compared against', () => {
	it( 'fails when no record exists', () => {
		const { root } = clientRepository();
		fs.rmSync( path.join( root, ...BASELINE.split( '/' ) ) );
		const outcome = verify( root );

		expect( outcome.stdout ).toMatch( /FAIL\t.*no baseline/i );
		expect( outcome.status ).toBe( 1 );
	} );

	it( 'fails when the record is not valid, and names the file', () => {
		const { root } = clientRepository();
		writeBaseline( root, {
			release: 'v1.0.0',
			commit: '',
			exceptions: [],
		} );
		const outcome = verify( root );

		expect( outcome.stdout ).toMatch(
			/FAIL\tsites\/acme\/corex-baseline\.json.*"commit" is empty/
		);
		expect( outcome.status ).toBe( 1 );
	} );

	it( 'fails when the commit is not in this repository', () => {
		const { root } = clientRepository();
		writeBaseline( root, {
			release: 'v1.0.0',
			commit: 'c'.repeat( 40 ),
			exceptions: [],
		} );
		const outcome = verify( root );

		expect( outcome.stdout ).toMatch( /FAIL\t.*not in this repository/ );
		expect( outcome.status ).toBe( 1 );
	} );

	it( 'fails when two client sites record different commits', () => {
		const { root } = clientRepository();
		write( root, 'sites/acme/acme-site/more.php', '<?php // client\n' );
		const later = commitAll( root, 'client work' );
		writeBaseline(
			root,
			{ release: 'v1.0.0', commit: later, exceptions: [] },
			'sites/beta/corex-baseline.json'
		);
		const outcome = verify( root );

		expect( outcome.stdout ).toMatch( /FAIL\t.*record different commits/ );
		expect( outcome.status ).toBe( 1 );
	} );
} );

describe( 'the framework’s own repository', () => {
	it( 'has nothing to compare, and passes', () => {
		const { root } = clientRepository();
		write( root, 'README.md', 'Edited, as the framework is free to be.\n' );
		const outcome = verify( root, [], {
			COREX_REPOSITORY_ROLE: 'framework',
		} );

		expect( outcome.stdout ).toMatch( /nothing to compare/ );
		expect( outcome.status ).toBe( 0 );
	} );
} );

describe( 'recording a new baseline', () => {
	const nextRelease = ( root ) => {
		write( root, 'README.md', 'The framework, second release.\n' );
		const commit = commitAll( root, 'framework v1.1.0' );
		git( root, 'tag', 'v1.1.0' );

		return commit;
	};

	it( 'writes the release and its commit, after which the check passes', () => {
		const { root } = clientRepository();
		const released = nextRelease( root );

		expect( verify( root ).status ).toBe( 1 );
		expect( verify( root, [ '--record', 'v1.1.0' ] ).status ).toBe( 0 );

		const recorded = JSON.parse(
			fs.readFileSync(
				path.join( root, ...BASELINE.split( '/' ) ),
				'utf8'
			)
		);
		expect( recorded ).toMatchObject( {
			release: 'v1.1.0',
			commit: released,
			exceptions: [],
		} );
		expect( verify( root ).status ).toBe( 0 );
	} );

	it( 'refuses to record without being told which release', () => {
		const { root } = clientRepository();
		const outcome = verify( root, [ '--record' ] );

		expect( outcome.stdout ).toMatch( /FAIL	--record needs a release/ );
		expect( outcome.status ).toBe( 1 );
	} );

	it( 'refuses a release that does not exist, and changes nothing', () => {
		const { root } = clientRepository();
		const before = fs.readFileSync(
			path.join( root, ...BASELINE.split( '/' ) ),
			'utf8'
		);
		const outcome = verify( root, [ '--record', 'v9.9.9' ] );

		expect( outcome.stdout ).toMatch( /FAIL\t.*v9\.9\.9/ );
		expect( outcome.status ).toBe( 1 );
		expect(
			fs.readFileSync(
				path.join( root, ...BASELINE.split( '/' ) ),
				'utf8'
			)
		).toBe( before );
	} );
} );

describe( 'a release name that points somewhere else', () => {
	it( 'is warned about, and does not fail the check', () => {
		const { root } = clientRepository();
		write( root, 'README.md', 'The framework, one commit past its tag.\n' );
		const pastTheTag = commitAll( root, 'framework, untagged' );
		writeBaseline( root, {
			release: 'v1.0.0',
			commit: pastTheTag,
			exceptions: [],
		} );
		const outcome = verify( root );

		expect( outcome.stdout ).toMatch( /WARN\t.*v1\.0\.0/ );
		expect( outcome.status ).toBe( 0 );
	} );
} );

describe( '--json', () => {
	it( 'reports the same result as one object', () => {
		const { root, frameworkCommit } = clientRepository();
		write( root, 'README.md', 'The framework!\n' );
		const outcome = verify( root, [ '--json' ] );

		expect( JSON.parse( outcome.stdout ) ).toMatchObject( {
			status: 'fail',
			baseline: { release: 'v1.0.0', commit: frameworkCommit },
			drift: [ 'README.md' ],
		} );
		expect( outcome.status ).toBe( 1 );
	} );
} );
