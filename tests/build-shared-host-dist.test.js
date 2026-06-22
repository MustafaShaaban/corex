/**
 * Shared-host dist builder (spec 061, FR-061-06). Verifies the plan composes framework + client source
 * correctly, the manifest shape, that forbidden runtime/dev paths are excluded from the built tree, and that
 * the verifier accepts a good tree and rejects a bad one. Uses a tiny on-disk fixture (fast).
 */

const { mkdtempSync, mkdirSync, writeFileSync, existsSync, rmSync } = require( 'node:fs' );
const { join } = require( 'node:path' );
const { tmpdir } = require( 'node:os' );

const mod = require( '../scripts/build-shared-host-dist.mjs' );

function makeRepo() {
	const root = mkdtempSync( join( tmpdir(), 'corex-dist-' ) );
	const write = ( rel, body = 'x' ) => {
		const abs = join( root, rel );
		mkdirSync( join( abs, '..' ), { recursive: true } );
		writeFileSync( abs, body );
	};
	// framework
	write( 'plugins/corex-core/corex-core.php', "define('COREX_CORE_VERSION', '9.9.9');" );
	write( 'addons/corex-ui/corex-ui.php' );
	write( 'theme/style.css' );
	write( 'vendor/autoload.php' );
	// a dev/runtime path that must be excluded by the copy filter
	write( 'plugins/corex-core/node_modules/dep/index.js' );
	write( 'plugins/corex-core/tests/Thing.test.php' );
	// client source (target layout)
	write( 'sites/acme/acme-site/acme-site.php' );
	write( 'sites/acme/acme-theme/style.css' );
	// minimal wp core
	write( 'wp/wp-load.php' );
	mkdirSync( join( root, 'wp', 'wp-admin' ), { recursive: true } );
	writeFileSync( join( root, 'wp', 'wp-admin', 'index.php' ), 'x' );
	write( 'wp/wp-config.php', 'SECRET' ); // must NOT be packaged
	write( 'wp/wp-content/plugins/symlinked/whatever.php' ); // must NOT be packaged (we build from source)
	return root;
}

it( 'plans framework plugins, theme, vendor, and the client site, with a typed manifest', () => {
	const root = makeRepo();
	const distDir = join( root, 'dist' );
	const { copies, manifest } = mod.buildPlan( { repoRoot: root, distDir, client: 'acme' } );

	const kinds = copies.map( ( c ) => c.kind );
	expect( kinds ).toEqual( expect.arrayContaining( [ 'core', 'plugin', 'theme', 'client-plugin', 'client-theme', 'vendor' ] ) );
	expect( manifest.plugins ).toEqual( expect.arrayContaining( [ 'corex-core', 'corex-ui', 'acme-site' ] ) );
	expect( manifest.themes ).toEqual( expect.arrayContaining( [ 'corex', 'acme-theme' ] ) );
	expect( manifest.corex_version ).toBe( '9.9.9' );
	expect( manifest.client ).toBe( 'acme' );
	rmSync( root, { recursive: true, force: true } );
} );

it( 'never plans to copy wp-config.php or the symlinked wp/wp-content tree', () => {
	const root = makeRepo();
	const { copies } = mod.buildPlan( { repoRoot: root, distDir: join( root, 'dist' ), client: 'acme' } );
	const froms = copies.map( ( c ) => c.from.replace( /\\/g, '/' ) );
	expect( froms.some( ( f ) => f.endsWith( 'wp-config.php' ) ) ).toBe( false );
	expect( froms.some( ( f ) => f.includes( 'wp/wp-content' ) ) ).toBe( false );
	rmSync( root, { recursive: true, force: true } );
} );

it( 'builds a tree that excludes node_modules/tests and passes verification', () => {
	const root = makeRepo();
	const distDir = join( root, 'dist' );
	const plan = mod.buildPlan( { repoRoot: root, distDir, client: 'acme' } );
	mod.runBuild( plan, distDir, { dryRun: false } );

	// forbidden dev/runtime paths excluded
	expect( existsSync( join( distDir, 'wp-content/plugins/corex-core/node_modules' ) ) ).toBe( false );
	expect( existsSync( join( distDir, 'wp-content/plugins/corex-core/tests' ) ) ).toBe( false );
	// real source present
	expect( existsSync( join( distDir, 'wp-content/plugins/acme-site/acme-site.php' ) ) ).toBe( true );
	expect( existsSync( join( distDir, 'wp-content/themes/acme-theme/style.css' ) ) ).toBe( true );
	expect( existsSync( join( distDir, 'wp-admin/index.php' ) ) ).toBe( true );
	expect( existsSync( join( distDir, 'corex-release.json' ) ) ).toBe( true );

	expect( mod.verifyDist( distDir ).ok ).toBe( true );
	rmSync( root, { recursive: true, force: true } );
} );

it( 'verifier rejects a tree with a forbidden path or a missing manifest', () => {
	const root = makeRepo();
	const distDir = join( root, 'dist' );
	const plan = mod.buildPlan( { repoRoot: root, distDir, client: 'acme' } );
	mod.runBuild( plan, distDir, { dryRun: false } );

	// inject a forbidden path
	mkdirSync( join( distDir, 'wp-content/plugins/x/.git' ), { recursive: true } );
	writeFileSync( join( distDir, 'wp-content/plugins/x/.git/config' ), 'x' );
	const bad = mod.verifyDist( distDir );
	expect( bad.ok ).toBe( false );
	expect( bad.errors.join( ' ' ) ).toMatch( /forbidden path/ );
	rmSync( root, { recursive: true, force: true } );
} );

it( 'dry-run plans without writing dist/', () => {
	const root = makeRepo();
	const distDir = join( root, 'dist' );
	const plan = mod.buildPlan( { repoRoot: root, distDir, client: 'acme' } );
	mod.runBuild( plan, distDir, { dryRun: true } );
	expect( existsSync( distDir ) ).toBe( false );
	rmSync( root, { recursive: true, force: true } );
} );
