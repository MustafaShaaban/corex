/**
 * Shared-host dist builder (spec 061, FR-061-06; DECISIONS #267).
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
const { describeFolder } = require( '../scripts/release-content-hash.mjs' );

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
		.forEach( ( root ) =>
			rmSync( root, { recursive: true, force: true } )
		);
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
	// what a package describes itself with in a repository, and a web server would hand to anyone
	write( 'plugins/corex-core/README.md' );
	write( 'plugins/corex-core/composer.json', '{}' );
	write( 'addons/corex-ui/package.json', '{}' );
	write( 'theme/README.md' );
	write( 'packages/cli/README.md' );
	// client source (target layout)
	write( 'sites/acme/acme-site/acme-site.php' );
	write( 'sites/acme/acme-site/README.md' );
	write( 'sites/acme/acme-theme/style.css' );
	write( 'sites/acme/acme-theme/package.json', '{}' );
	// minimal wp core
	write( 'wp/wp-load.php' );
	write( 'wp/wp-includes/version.php', "<?php\n$wp_version = '7.1.3';\n" );
	write( 'wp/wp-includes/sodium_compat/composer.json', '{}' ); // WordPress's own, and WordPress's to ship
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
/**
 * Describe a package again, as a build that had produced it in its present state would have.
 *
 * The tests below spoil a built package one way and ask what verification says. Since a package
 * measures its own folders (spec 107), every such change is also "not what the description says";
 * measuring again leaves the one fault each test is about.
 *
 * @param {string} distDir The package.
 */
function describedAgain( distDir ) {
	const file = join( distDir, 'corex-release.json' );
	const manifest = JSON.parse( readFileSync( file, 'utf8' ) );

	for ( const path of manifest.release_paths ) {
		manifest.contents[ path ] = describeFolder( join( distDir, path ) );
	}
	writeFileSync( file, JSON.stringify( manifest, null, 2 ) + '\n' );
}

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
	// What Composer was given to work from is not left beside the vendor/ it made.
	expect( packaged ).not.toContain( 'wp-content/composer.json' );
	expect( packaged ).not.toContain( 'wp-content/composer.lock' );
	expect( listed( join( root, 'vendor' ) ).sort() ).toEqual( [
		'autoload.php',
		'phpunit',
		'phpunit/phpunit',
		'phpunit/phpunit/phpunit',
	] );
	expect(
		readFileSync( join( root, 'vendor', 'autoload.php' ), 'utf8' )
	).toBe( '<?php // the checkout autoloader' );
} );

// Reported by CI on 2026-10-08: mPDF ships its own `.github`, Composer installed it as shipped,
// and the package was refused for holding a forbidden path.
it( 'removes from the packaged vendor/ what a package may not hold, and keeps the rest', () => {
	const { distDir } = buildFixture();
	const shipped = join( distDir, 'wp-content', 'vendor', 'acme', 'pdf' );
	const write = ( rel ) => {
		mkdirSync( join( shipped, rel, '..' ), { recursive: true } );
		writeFileSync( join( shipped, rel ), 'x' );
	};
	write( '.github/workflows/tests.yml' );
	write( 'tests/WriterTest.php' );
	write( 'src/Writer.php' );
	expect( mod.verifyDist( distDir ).ok ).toBe( false );

	const removed = mod.prunePackagedVendor( distDir );
	describedAgain( distDir );

	expect( removed.sort() ).toEqual( [
		'wp-content/vendor/acme/pdf/.github',
		'wp-content/vendor/acme/pdf/tests',
	] );
	expect( existsSync( join( shipped, 'src', 'Writer.php' ) ) ).toBe( true );
	expect( mod.verifyDist( distDir ) ).toEqual( { ok: true, errors: [] } );
} );

// The PDF library's fonts are 87MB of a package somebody uploads by hand, 54MB of it six fonts for
// Chinese, Korean and three ancient scripts (measured 2026-10-08: 71MB zipped against 27MB without
// the library). A package keeps the fonts most sites write in, and all of them when asked.
describe( 'the PDF library’s fonts', () => {
	const SHIPPED = [
		'DejaVuSans.ttf',
		'DejaVuSerif-Bold.ttf',
		'XB Riyaz.ttf',
		'XB RiyazBd.ttf',
		'LateefRegOT.ttf',
		'Lateef font OFL.txt',
		'Sun-ExtA.ttf',
		'UnBatang_0613.ttf',
		'FreeSerif.ttf',
		'Aegean.otf',
	];

	function withPdfLibrary( distDir ) {
		const fonts = join(
			distDir,
			'wp-content',
			'vendor',
			'mpdf',
			'mpdf',
			'ttfonts'
		);
		mkdirSync( fonts, { recursive: true } );
		SHIPPED.forEach( ( file ) =>
			writeFileSync( join( fonts, file ), 'x' )
		);
		return fonts;
	}

	it( 'keeps the fonts for Latin and Arabic text, and every licence beside them', () => {
		const { distDir } = buildFixture();
		const fonts = withPdfLibrary( distDir );

		expect( mod.prunePdfFonts( distDir ).sort() ).toEqual( [
			'Aegean.otf',
			'FreeSerif.ttf',
			'Sun-ExtA.ttf',
			'UnBatang_0613.ttf',
		] );
		expect( readdirSync( fonts ).sort() ).toEqual( [
			'DejaVuSans.ttf',
			'DejaVuSerif-Bold.ttf',
			'Lateef font OFL.txt',
			'LateefRegOT.ttf',
			'XB Riyaz.ttf',
			'XB RiyazBd.ttf',
		] );
	} );

	it( 'has nothing to remove from a package without the PDF library', () => {
		const { distDir } = buildFixture();

		expect( mod.prunePdfFonts( distDir ) ).toEqual( [] );
	} );

	it( 'plans the few fonts unless every font is asked for, and says which in the manifest', () => {
		const root = makeRepo();
		const distDir = join( root, 'dist' );
		const plan = ( pdfFonts ) =>
			mod.buildPlan( {
				repoRoot: root,
				distDir,
				client: 'acme',
				pdfFonts,
			} );

		expect( plan( undefined ).manifest.pdf_fonts ).toBe( 'lean' );
		expect( plan( 'all' ).manifest.pdf_fonts ).toBe( 'all' );
		expect( () => plan( 'some' ) ).toThrow( /--pdf-fonts/ );
	} );
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
	// Built from source: what the development install has in its own wp-content is not taken.
	expect( packaged ).not.toContain( 'wp-content/plugins/symlinked' );
	expect( packaged ).not.toContain( 'wp-content/packages/build-tools' );
	expect( packaged ).not.toContain(
		'wp-content/plugins/corex-core/node_modules'
	);
	expect( packaged ).not.toContain( 'wp-content/plugins/corex-core/tests' );
} );

/**
 * Reported from the first site on shared hosting: every plugin's `README.md` and `composer.json`
 * could be fetched from the web. They say what is installed and at which version, to anybody,
 * and nothing on a running site reads them.
 */
it( 'leaves out the files that only describe CoreX or the site to a developer', () => {
	const { distDir } = buildFixture();
	const described = listed( join( distDir, 'wp-content' ) ).filter(
		( path ) =>
			! path.startsWith( 'vendor/' ) &&
			/(^|\/)(README\.md|composer\.json|package\.json)$/.test( path )
	);

	expect( described ).toEqual( [] );
	// What WordPress ships is WordPress's, and is left as it came.
	expect( listed( distDir ) ).toContain(
		'wp-includes/sodium_compat/composer.json'
	);
} );

/**
 * Spec 107 (plan D1, D2). A package is installed from the admin by a site that has only the
 * package to go on, so the package says what it needs, which folders are the release's, and what
 * each holds.
 */
describe( 'what a package says about itself', () => {
	const RELEASE_PATHS = [
		'wp-content/packages',
		'wp-content/plugins/acme-site',
		'wp-content/plugins/corex-core',
		'wp-content/plugins/corex-ui',
		'wp-content/themes/acme-theme',
		'wp-content/themes/corex',
		'wp-content/vendor',
	];
	const manifestOf = ( distDir ) =>
		JSON.parse(
			readFileSync( join( distDir, 'corex-release.json' ), 'utf8' )
		);

	it( 'names what it needs, the WordPress it was built with, and every folder a release owns', () => {
		const { distDir } = buildFixture();
		const manifest = manifestOf( distDir );

		expect( manifest.schema ).toBe( 2 );
		expect( manifest.requires ).toEqual( { php: '8.3', wordpress: '7.0' } );
		expect( manifest.wordpress_version ).toBe( '7.1.3' );
		expect( manifest.release_paths ).toEqual( RELEASE_PATHS );
	} );

	it( 'measures each of those folders as it finally is, vendor included', () => {
		const { distDir } = buildFixture();
		const manifest = manifestOf( distDir );

		for ( const path of RELEASE_PATHS ) {
			expect( manifest.contents[ path ] ).toEqual(
				describeFolder( join( distDir, path ) )
			);
		}
		expect(
			manifest.contents[ 'wp-content/vendor' ].files
		).toBeGreaterThan( 0 );
	} );

	it( 'is read by the class a site reads it with', () => {
		const { distDir } = buildFixture();
		const run = spawnSync(
			'php',
			[
				join( REPO, 'tests', 'Support', 'read-release-manifest.php' ),
				join( distDir, 'corex-release.json' ),
			],
			{ encoding: 'utf8' }
		);

		expect( JSON.parse( run.stdout ) ).toEqual( {
			version: '9.9.9',
			client: 'acme',
			requiresPhp: '8.3',
			requiresWordPress: '7.0',
			wordPressVersion: '7.1.3',
			releasePaths: RELEASE_PATHS,
		} );
	} );

	it( 'is refused by verification once a file of the release is not what was measured', () => {
		const { distDir } = buildFixture();
		writeFileSync(
			join( distDir, 'wp-content/plugins/corex-ui/corex-ui.php' ),
			'<?php // changed after the package was described'
		);

		expect( mod.verifyDist( distDir ).errors ).toEqual( [
			'wp-content/plugins/corex-ui is not what corex-release.json says it holds',
		] );
	} );

	it( 'becomes one zip, named for what it is, with its description at the root', () => {
		const AdmZip = require( 'adm-zip' );
		const { root, distDir } = buildFixture();

		const zipFile = mod.zipPackage( distDir, root );
		const entries = new AdmZip( zipFile )
			.getEntries()
			.map( ( entry ) => entry.entryName );

		expect( zipFile ).toMatch(
			/corex-release-acme-9\.9\.9-\d{8}-\d{6}\.zip$/
		);
		expect( entries ).toEqual(
			expect.arrayContaining( [
				'corex-release.json',
				'wp-content/plugins/corex-core/corex-core.php',
				'wp-content/vendor/autoload.php',
			] )
		);
		// Nothing wraps it: a zip of a `dist/` folder would put everything one level down.
		expect( entries.some( ( name ) => name.startsWith( 'dist/' ) ) ).toBe(
			false
		);
	} );
} );

it.each( [
	[
		'with a forbidden path in it',
		( distDir ) => {
			mkdirSync( join( distDir, 'wp-content/plugins/x/.git' ), {
				recursive: true,
			} );
		},
		// The verifier prints this path with the separators of the machine it runs on.
		[
			expect.stringMatching(
				/^forbidden path present: wp-content.plugins.x.\.git$/
			),
		],
	],
	[
		'that would overwrite the host .htaccess',
		( distDir ) =>
			writeFileSync( join( distDir, '.htaccess' ), '# rewrite rules' ),
		[ expect.stringMatching( /^\.htaccess is in the package root/ ) ],
	],
	[
		'with no autoloader where its plugins look for one',
		( distDir ) =>
			rmSync( join( distDir, 'wp-content', 'vendor' ), {
				recursive: true,
			} ),
		expect.arrayContaining( [
			expect.stringMatching(
				/^corex-core\.php did not load wp-content\/vendor\/autoload\.php/
			),
		] ),
	],
	[
		'that maps a namespace to a directory it does not hold',
		( distDir ) =>
			rmSync( join( distDir, 'wp-content/plugins/corex-ui/src' ), {
				recursive: true,
			} ),
		[
			'no class under Corex\\Ui\\ loads from wp-content/plugins/corex-ui/src/',
		],
	],
	[
		'whose vendor/ was installed with dev packages',
		( distDir ) => {
			const installed = join(
				distDir,
				'wp-content/vendor/composer/installed.json'
			);
			writeFileSync(
				installed,
				JSON.stringify( {
					...JSON.parse( readFileSync( installed, 'utf8' ) ),
					dev: true,
					'dev-package-names': [ 'phpunit/phpunit' ],
				} )
			);
		},
		[ expect.stringMatching( /dev packages \(phpunit\/phpunit\)/ ) ],
	],
] )( 'the verifier rejects a package %s', ( scenario, spoil, errors ) => {
	const { distDir } = buildFixture();
	spoil( distDir );
	if ( existsSync( join( distDir, 'corex-release.json' ) ) ) {
		describedAgain( distDir );
	}

	expect( mod.verifyDist( distDir ) ).toEqual( { ok: false, errors } );
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
