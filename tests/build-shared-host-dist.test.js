/**
 * Shared-host dist builder (spec 061, FR-061-06; DECISIONS #265).
 *
 * The package is built from a small repository on disk and then asked the question a host asks:
 * does it load? `scripts/shared-host-dist-probe.php` answers that in a PHP process of its own,
 * including the packaged core plugin as WordPress would. The fixture's core plugin file is the
 * real `plugins/corex-core/corex-core.php`, so the places that file looks for an autoloader are
 * the ones under test.
 *
 * Needs `php` and `composer` on the PATH, as the builder does. The fixture requires no Composer
 * package, so nothing is downloaded.
 */

const {
	mkdtempSync,
	mkdirSync,
	writeFileSync,
	readFileSync,
	readdirSync,
	existsSync,
	rmSync,
} = require( 'node:fs' );
const { join } = require( 'node:path' );
const { tmpdir } = require( 'node:os' );
const { spawnSync } = require( 'node:child_process' );

const mod = require( '../scripts/build-shared-host-dist.mjs' );

const REPO = join( __dirname, '..' );
const PROBE = join( REPO, 'scripts', 'shared-host-dist-probe.php' );
const NAMESPACES = {
	'Corex\\': 'wp-content/plugins/corex-core/src/',
	'Corex\\Ui\\': 'wp-content/plugins/corex-ui/src/',
	'Corex\\Cli\\': 'wp-content/packages/cli/src/',
};

// Each build runs Composer once, and a cold Composer start takes seconds.
jest.setTimeout( 120000 );

const roots = [];
afterEach( () => {
	roots
		.splice( 0 )
		.forEach( ( root ) => rmSync( root, { recursive: true, force: true } ) );
} );

const phpClass = ( namespace, name, body = '' ) =>
	`<?php\nnamespace ${ namespace };\n\nfinal class ${ name }\n{\n${ body }}\n`;

function makeRepo() {
	const root = mkdtempSync( join( tmpdir(), 'corex-dist-' ) );
	roots.push( root );
	const write = ( rel, body = 'x' ) => {
		const abs = join( root, rel );
		mkdirSync( join( abs, '..' ), { recursive: true } );
		writeFileSync( abs, body );
	};
	// framework: the real loader, and one class per namespace the package has to load
	write(
		'plugins/corex-core/corex-core.php',
		readFileSync(
			join( REPO, 'plugins', 'corex-core', 'corex-core.php' ),
			'utf8'
		).replace(
			/(COREX_CORE_VERSION',\s*')[^']+/,
			( match, head ) => head + '9.9.9'
		)
	);
	write(
		'plugins/corex-core/src/Boot.php',
		phpClass(
			'Corex',
			'Boot',
			"    public static function init(): void\n    {\n        add_action('plugins_loaded', [self::class, 'init']);\n    }\n"
		)
	);
	write( 'addons/corex-ui/corex-ui.php' );
	write(
		'addons/corex-ui/src/UiServiceProvider.php',
		phpClass( 'Corex\\Ui', 'UiServiceProvider' )
	);
	write(
		'packages/cli/src/CliServiceProvider.php',
		phpClass( 'Corex\\Cli', 'CliServiceProvider' )
	);
	write( 'packages/cli/stubs/model.stub' );
	write( 'packages/build-tools/index.js' ); // a build tool, not part of a running site
	write( 'theme/style.css' );
	write(
		'composer.json',
		JSON.stringify( {
			name: 'corex/fixture',
			autoload: {
				'psr-4': {
					'Corex\\': 'plugins/corex-core/src/',
					'Corex\\Ui\\': 'addons/corex-ui/src/',
					'Corex\\Cli\\': 'packages/cli/src/',
				},
			},
			'autoload-dev': { 'psr-4': { 'Corex\\Tests\\': 'tests/' } },
		} )
	);
	write(
		'composer.lock',
		JSON.stringify( {
			'content-hash': 'fixture',
			packages: [],
			'packages-dev': [],
			aliases: [],
			'minimum-stability': 'stable',
			'stability-flags': [],
			'prefer-stable': false,
			'prefer-lowest': false,
			platform: [],
			'platform-dev': [],
		} )
	);
	// the checkout's own vendor/, installed with its dev packages as a working checkout has it
	write( 'vendor/autoload.php', '<?php // the checkout autoloader' );
	write( 'vendor/phpunit/phpunit/phpunit', 'dev tool' );
	// a dev/runtime path that must be excluded by the copy filter
	write( 'plugins/corex-core/node_modules/dep/index.js' );
	write( 'plugins/corex-core/tests/Thing.test.php' );
	// client source (target layout)
	write( 'sites/acme/acme-site/acme-site.php' );
	write( 'sites/acme/acme-theme/style.css' );
	// minimal wp core
	write( 'wp/wp-load.php' );
	write( 'wp/wp-admin/index.php' );
	write( 'wp/wp-config.php', 'SECRET' ); // must NOT be packaged
	write( 'wp/.htaccess', '# the development install rewrite rules' ); // nor this: the host has its own
	write( 'wp/wp-content/plugins/symlinked/whatever.php' ); // must NOT be packaged (we build from source)
	return root;
}

function buildFixture() {
	const root = makeRepo();
	const distDir = join( root, 'dist' );
	const plan = mod.buildPlan( { repoRoot: root, distDir, client: 'acme' } );
	mod.runBuild( plan, distDir );
	return { root, distDir };
}

/**
 * Ask the package whether it loads, in a PHP process that has nothing of this checkout in it.
 *
 * @param {string} distDir The built package.
 * @return {{errors: string[], loaded: Object<string, string>}} What the probe reported.
 */
function probe( distDir ) {
	const run = spawnSync( 'php', [ PROBE, distDir ], { encoding: 'utf8' } );
	try {
		return JSON.parse( run.stdout );
	} catch {
		throw new Error(
			`the probe printed no report:\n${ run.stdout }\n${ run.stderr }`
		);
	}
}

/**
 * Every path in a directory, relative to it and written with forward slashes.
 *
 * @param {string} dir The directory to list.
 * @return {string[]} The relative paths of its files and directories.
 */
const listed = ( dir ) =>
	readdirSync( dir, { recursive: true } ).map( ( rel ) =>
		rel.replace( /\\/g, '/' )
	);

it( 'plans the framework, the CLI package and the client site, and the namespaces the package must load', () => {
	const root = makeRepo();
	const { copies, manifest } = mod.buildPlan( {
		repoRoot: root,
		distDir: join( root, 'dist' ),
		client: 'acme',
	} );

	expect( [ ...new Set( copies.map( ( c ) => c.kind ) ) ].sort() ).toEqual( [
		'cli',
		'client-plugin',
		'client-theme',
		'core',
		'plugin',
		'theme',
	] );
	expect( manifest.plugins ).toEqual(
		expect.arrayContaining( [ 'corex-core', 'corex-ui', 'acme-site' ] )
	);
	expect( manifest.themes ).toEqual(
		expect.arrayContaining( [ 'corex', 'acme-theme' ] )
	);
	expect( manifest.corex_version ).toBe( '9.9.9' );
	expect( manifest.client ).toBe( 'acme' );
	expect( manifest.autoload ).toEqual( {
		file: 'wp-content/vendor/autoload.php',
		psr4: NAMESPACES,
	} );
} );

it( 'never plans to copy wp-config.php, .htaccess, the symlinked wp/wp-content tree or the checkout vendor/', () => {
	const root = makeRepo();
	const { copies } = mod.buildPlan( {
		repoRoot: root,
		distDir: join( root, 'dist' ),
		client: 'acme',
	} );
	const froms = copies.map( ( c ) =>
		c.from.slice( root.length ).replace( /\\/g, '/' )
	);

	expect( froms ).toContain( '/wp/wp-load.php' );
	expect( froms ).not.toContain( '/wp/wp-config.php' );
	expect( froms ).not.toContain( '/wp/.htaccess' );
	expect( froms.filter( ( f ) => f.startsWith( '/wp/wp-content' ) ) ).toEqual(
		[]
	);
	expect( froms.filter( ( f ) => f.startsWith( '/vendor' ) ) ).toEqual( [] );
} );

it( 'refuses to plan a framework plugin whose classes no namespace is mapped to', () => {
	const root = makeRepo();
	mkdirSync( join( root, 'addons', 'corex-unmapped', 'src' ), {
		recursive: true,
	} );

	expect( () =>
		mod.buildPlan( { repoRoot: root, distDir: join( root, 'dist' ) } )
	).toThrow( /addons\/corex-unmapped\/src.*composer\.json/ );
} );

it( 'builds a package whose core plugin loads the framework, in a PHP process of its own', () => {
	const { distDir } = buildFixture();

	expect( probe( distDir ) ).toEqual( {
		errors: [],
		loaded: {
			'Corex\\': 'Corex\\Boot',
			'Corex\\Ui\\': 'Corex\\Ui\\UiServiceProvider',
			'Corex\\Cli\\': 'Corex\\Cli\\CliServiceProvider',
		},
	} );
	expect( mod.verifyDist( distDir ) ).toEqual( { ok: true, errors: [] } );
} );

it( 'builds the package vendor/ beside the plugins, and leaves the checkout vendor/ as it was', () => {
	const { root, distDir } = buildFixture();
	const packaged = listed( distDir );

	expect( packaged ).toContain( 'wp-content/vendor/autoload.php' );
	expect( packaged.filter( ( rel ) => /phpunit/.test( rel ) ) ).toEqual( [] );
	expect( packaged ).not.toContain( 'vendor' );
	// What Composer was given to work from is not left in the web root.
	expect( packaged.filter( ( rel ) => /composer\.(json|lock)$/.test( rel ) ) )
		.toEqual( [] );
	expect( listed( join( root, 'vendor' ) ).sort() ).toEqual( [
		'autoload.php',
		'phpunit',
		'phpunit/phpunit',
		'phpunit/phpunit/phpunit',
	] );
	expect( readFileSync( join( root, 'vendor', 'autoload.php' ), 'utf8' ) ).toBe(
		'<?php // the checkout autoloader'
	);
} );

it( 'packages the CLI with its stubs, the client site, and none of the dev or runtime files', () => {
	const { distDir } = buildFixture();
	const packaged = listed( distDir );

	expect( packaged ).toEqual(
		expect.arrayContaining( [
			'wp-content/packages/cli/src/CliServiceProvider.php',
			'wp-content/packages/cli/stubs/model.stub',
			'wp-content/plugins/acme-site/acme-site.php',
			'wp-content/themes/acme-theme/style.css',
			'wp-admin/index.php',
			'corex-release.json',
		] )
	);
	expect( packaged ).not.toContain( '.htaccess' );
	expect( packaged ).not.toContain( 'wp-config.php' );
	expect( packaged ).not.toContain( 'wp-content/packages/build-tools' );
	expect( packaged ).not.toContain(
		'wp-content/plugins/corex-core/node_modules'
	);
	expect( packaged ).not.toContain( 'wp-content/plugins/corex-core/tests' );
} );

describe( 'the verifier rejects a package', () => {
	it( 'with a forbidden path in it', () => {
		const { distDir } = buildFixture();
		mkdirSync( join( distDir, 'wp-content/plugins/x/.git' ), {
			recursive: true,
		} );
		writeFileSync(
			join( distDir, 'wp-content/plugins/x/.git/config' ),
			'x'
		);

		const bad = mod.verifyDist( distDir );

		expect( bad.ok ).toBe( false );
		expect( bad.errors.join( ' ' ) ).toMatch( /forbidden path/ );
	} );

	it( 'that would overwrite the host .htaccess', () => {
		const { distDir } = buildFixture();
		writeFileSync( join( distDir, '.htaccess' ), '# rewrite rules' );

		expect( mod.verifyDist( distDir ).errors ).toEqual( [
			expect.stringMatching( /\.htaccess/ ),
		] );
	} );

	it( 'with no autoloader where its plugins look for one', () => {
		const { distDir } = buildFixture();
		rmSync( join( distDir, 'wp-content', 'vendor' ), { recursive: true } );

		const bad = mod.verifyDist( distDir );

		expect( bad.ok ).toBe( false );
		expect( bad.errors.join( '\n' ) ).toMatch(
			/corex-core\.php did not load wp-content\/vendor\/autoload\.php/
		);
	} );

	it( 'that maps a namespace to a directory it does not hold', () => {
		const { distDir } = buildFixture();
		rmSync( join( distDir, 'wp-content', 'plugins', 'corex-ui', 'src' ), {
			recursive: true,
		} );

		expect( mod.verifyDist( distDir ).errors ).toEqual( [
			'no class under Corex\\Ui\\ loads from wp-content/plugins/corex-ui/src/',
		] );
	} );

	it( 'whose vendor/ was installed with dev packages', () => {
		const { distDir } = buildFixture();
		const installed = join(
			distDir,
			'wp-content',
			'vendor',
			'composer',
			'installed.json'
		);
		writeFileSync(
			installed,
			JSON.stringify( {
				...JSON.parse( readFileSync( installed, 'utf8' ) ),
				dev: true,
				'dev-package-names': [ 'phpunit/phpunit' ],
			} )
		);

		expect( mod.verifyDist( distDir ).errors ).toEqual( [
			expect.stringMatching( /dev packages.*phpunit\/phpunit/ ),
		] );
	} );
} );

it( 'fails verification when a client theme ships SCSS/JS source but no compiled output', () => {
	const root = mkdtempSync( join( tmpdir(), 'corex-dist-assets-' ) );
	roots.push( root );
	const dist = join( root, 'dist' );
	const theme = join( dist, 'wp-content', 'themes', 'acme-theme' );
	mkdirSync( join( theme, 'assets', 'src', 'scss' ), { recursive: true } );
	writeFileSync(
		join( theme, 'assets', 'src', 'scss', 'main.scss' ),
		'body{}'
	);

	// SCSS source but no compiled assets/css → flagged
	const errs = mod.verifyClientAssets( dist );
	expect( errs.join( ' ' ) ).toMatch(
		/acme-theme.*SCSS source present but no compiled/
	);

	// add the compiled CSS → passes
	mkdirSync( join( theme, 'assets', 'css' ), { recursive: true } );
	writeFileSync( join( theme, 'assets', 'css', 'main.css' ), 'body{}' );
	expect( mod.verifyClientAssets( dist ) ).toEqual( [] );
} );

it( 'dry-run plans without writing dist/', () => {
	const root = makeRepo();
	const distDir = join( root, 'dist' );
	const plan = mod.buildPlan( { repoRoot: root, distDir, client: 'acme' } );
	mod.runBuild( plan, distDir, { dryRun: true } );
	expect( existsSync( distDir ) ).toBe( false );
} );
