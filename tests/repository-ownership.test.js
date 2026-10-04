/**
 * Which paths are a client's, and which repository this is.
 *
 * Spec 102. The framework prescribes `sites/<client>/` and, until this spec, every tool that walks
 * the tree kept its own idea of what that meant — or none. This suite holds the one definition the
 * rest now read: a path is client-owned if it matches a pattern in
 * `.github/repository-ownership.json`, and framework-owned otherwise.
 */
const { execFileSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const {
	parseOwnership,
	loadOwnership,
	matchesPattern,
	isClientOwned,
	repositoryFromRemoteUrl,
	resolveRole,
} = require( '../scripts/repository-ownership.mjs' );

const repositoryRoot = path.resolve( __dirname, '..' );

const ownership = parseOwnership( {
	frameworkRepository: 'Acme/framework',
	clientOwned: [ 'sites/**', '.github/workflows/site-*.yml' ],
	localOnly: [ '.claude/worktrees/**' ],
} );

describe( 'pattern matching', () => {
	it( 'lets ** cross directories', () => {
		expect(
			matchesPattern( 'sites/**', 'sites/acme/acme-theme/style.css' )
		).toBe( true );
	} );

	it( 'keeps * inside one path segment', () => {
		expect(
			matchesPattern(
				'.github/workflows/site-*.yml',
				'.github/workflows/nested/site-acme.yml'
			)
		).toBe( false );
	} );

	it( 'anchors a pattern at the repository root', () => {
		expect( matchesPattern( 'sites/**', 'plugins/sites/thing.php' ) ).toBe(
			false
		);
	} );

	it( 'treats every other character literally', () => {
		expect( matchesPattern( 'a.b/*', 'axb/file' ) ).toBe( false );
	} );
} );

describe( 'client ownership', () => {
	it.each( [
		'sites/acme/acme-site/acme-site.php',
		'sites/acme/corex-baseline.json',
		'.github/workflows/site-acme.yml',
	] )( 'gives %s to the client', ( file ) => {
		expect( isClientOwned( ownership, file ) ).toBe( true );
	} );

	it.each( [
		'README.md',
		'.github/workflows/ci.yml',
		'plugins/corex-core/corex-core.php',
		// A file merely *named* like the directory is not inside it.
		'sites',
		'websites/acme/index.php',
	] )( 'leaves %s with the framework', ( file ) => {
		expect( isClientOwned( ownership, file ) ).toBe( false );
	} );
} );

describe( 'reading a remote URL', () => {
	it.each( [
		[ 'git@github.com:Acme/framework.git', 'Acme/framework' ],
		[ 'https://github.com/Acme/framework.git', 'Acme/framework' ],
		[ 'https://github.com/Acme/framework', 'Acme/framework' ],
		[ 'ssh://git@github.com/Acme/framework.git', 'Acme/framework' ],
	] )( 'reads %s', ( url, expected ) => {
		expect( repositoryFromRemoteUrl( url ) ).toBe( expected );
	} );

	it( 'answers null for something that is not a repository URL', () => {
		expect( repositoryFromRemoteUrl( '' ) ).toBeNull();
	} );
} );

describe( 'which repository this is', () => {
	it( 'is the framework when CI says so', () => {
		expect(
			resolveRole( {
				ownership,
				env: { GITHUB_REPOSITORY: 'Acme/framework' },
			} )
		).toBe( 'framework' );
	} );

	it( 'compares repository names without regard to case', () => {
		expect(
			resolveRole( {
				ownership,
				env: { GITHUB_REPOSITORY: 'acme/Framework' },
			} )
		).toBe( 'framework' );
	} );

	it( 'is a client when CI names any other repository', () => {
		expect(
			resolveRole( {
				ownership,
				env: { GITHUB_REPOSITORY: 'Client/website' },
			} )
		).toBe( 'client' );
	} );

	it( 'falls back to the origin remote outside CI', () => {
		expect(
			resolveRole( {
				ownership,
				env: {},
				originUrl: 'git@github.com:Client/website.git',
			} )
		).toBe( 'client' );
	} );

	it( 'recognises the framework by its origin remote', () => {
		expect(
			resolveRole( {
				ownership,
				env: {},
				originUrl: 'https://github.com/Acme/framework.git',
			} )
		).toBe( 'framework' );
	} );

	it( 'lets an explicit role override everything else', () => {
		expect(
			resolveRole( {
				ownership,
				env: {
					COREX_REPOSITORY_ROLE: 'client',
					GITHUB_REPOSITORY: 'Acme/framework',
				},
				originUrl: 'git@github.com:Acme/framework.git',
			} )
		).toBe( 'client' );
	} );

	it( 'refuses an explicit role it does not know', () => {
		expect( () =>
			resolveRole( {
				ownership,
				env: { COREX_REPOSITORY_ROLE: 'customer' },
			} )
		).toThrow( /COREX_REPOSITORY_ROLE.*framework.*client/ );
	} );

	it( 'assumes the framework when nothing says otherwise', () => {
		// The strict default: a repository that cannot say what it is gets the rule that
		// forbids `sites/`, not the one that allows it.
		expect( resolveRole( { ownership, env: {} } ) ).toBe( 'framework' );
	} );
} );

describe( 'the map itself', () => {
	it.each( [
		[ 'a missing framework repository', { clientOwned: [ 'sites/**' ] } ],
		[
			'a framework repository that is not owner/name',
			{ frameworkRepository: 'framework', clientOwned: [ 'sites/**' ] },
		],
		[
			'no client-owned pattern at all',
			{ frameworkRepository: 'Acme/framework', clientOwned: [] },
		],
		[
			'a pattern that is not a string',
			{ frameworkRepository: 'Acme/framework', clientOwned: [ 7 ] },
		],
	] )( 'is rejected with %s', ( _description, raw ) => {
		expect( () => parseOwnership( raw ) ).toThrow(
			/repository-ownership\.json/
		);
	} );

	it( 'is committed, and gives sites/ to the client', () => {
		const committed = loadOwnership( repositoryRoot );

		expect( committed.frameworkRepository ).toBe( 'MustafaShaaban/corex' );
		expect(
			isClientOwned( committed, 'sites/acme/acme-theme/theme.json' )
		).toBe( true );
		expect(
			isClientOwned( committed, '.github/workflows/site-acme.yml' )
		).toBe( true );
	} );
} );

describe( 'the tools that walk the tree', () => {
	const committed = loadOwnership( repositoryRoot );
	const everyPattern = [ ...committed.clientOwned, ...committed.localOnly ];
	const directoryPatterns = ( patterns ) =>
		patterns.filter( ( pattern ) => pattern.endsWith( '/**' ) );
	const linesOf = ( file ) =>
		fs
			.readFileSync( path.join( repositoryRoot, file ), 'utf8' )
			.split( /\r?\n/ );

	it( 'keeps ESLint out of every client-owned and local-only path', () => {
		// Loaded by Node itself, in a child process, exactly as ESLint loads it. Jest cannot
		// `require` this file: the WordPress preset it re-exports pulls in an ES module.
		const ignores = JSON.parse(
			execFileSync(
				process.execPath,
				[
					'-e',
					'process.stdout.write( JSON.stringify( require( "./eslint.config.js" )[ 0 ].ignores ) )',
				],
				{ cwd: repositoryRoot, encoding: 'utf8' }
			)
		);

		expect( ignores ).toEqual( expect.arrayContaining( everyPattern ) );
	} );

	describe( 'Jest', () => {
		/**
		 * The ignore patterns as Jest resolved them, not as `jest.config.js` wrote them.
		 *
		 * The difference is the whole test. An earlier version substituted `<rootDir>` itself and
		 * passed while Jest went on running a failing test under `sites/`: on Windows, Jest
		 * rewrites path separators inside each pattern and leaves a backslash that precedes a
		 * dot alone, so a checkout whose own path holds a dot-directory — every agent session
		 * worktree under `.claude/worktrees/` — turned each `<rootDir>` pattern into one that
		 * matched nothing. Asking Jest is the only way to see what Jest will do.
		 */
		let resolved = [];

		beforeAll( () => {
			const shown = execFileSync(
				process.execPath,
				[
					require.resolve( '@wordpress/scripts/bin/wp-scripts.js' ),
					'test-unit-js',
					'--showConfig',
				],
				{
					cwd: repositoryRoot,
					encoding: 'utf8',
					maxBuffer: 32 * 1024 * 1024,
				}
			);

			resolved = JSON.parse( shown ).configs[ 0 ].testPathIgnorePatterns;
		}, 120000 );

		it.each( directoryPatterns( everyPattern ) )(
			'is kept out of %s',
			( pattern ) => {
				const testFile = path.join(
					repositoryRoot,
					...pattern
						.replace( '**', 'acme/thing.test.js' )
						.split( '/' )
				);

				expect(
					resolved.filter( ( ignored ) =>
						new RegExp( ignored ).test( testFile )
					)
				).not.toEqual( [] );
			}
		);

		it( 'still runs the framework’s own tests', () => {
			const ownTest = path.join(
				repositoryRoot,
				'tests',
				'repo-hygiene.test.js'
			);

			expect(
				resolved.filter( ( ignored ) =>
					new RegExp( ignored ).test( ownTest )
				)
			).toEqual( [] );
		} );
	} );

	// Static files cannot read the map, so they are held to it here instead. Only client-owned
	// directories are asserted: neither tool's `**` glob enters a dot-directory, which is what
	// every local-only path is.
	it.each( [ '.stylelintignore', '.prettierignore' ] )(
		'lists every client-owned directory in %s',
		( file ) => {
			expect( linesOf( file ) ).toEqual(
				expect.arrayContaining(
					directoryPatterns( committed.clientOwned )
				)
			);
		}
	);
} );
