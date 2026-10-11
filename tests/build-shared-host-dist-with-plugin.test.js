/**
 * Shared-host dist builder: a plugin that is not CoreX's or the site's own travels in a package
 * when it is named to the builder (spec 107, FR-028; plan D17).
 *
 * A client site sends its mail through a third-party plugin. On a host where WordPress may not
 * change its own files, the release is the only way that plugin changes, so it has to be a
 * folder of the release like the others: listed, measured, and held to that measure on the
 * site. A plugin copied into `dist/` after the builder has measured it is none of those.
 *
 * The plan is what is under test here, and one copy. No Composer run is needed for either.
 */

const {
	mkdtempSync,
	mkdirSync,
	writeFileSync,
	existsSync,
	rmSync,
	cpSync,
} = require( 'node:fs' );
const { join } = require( 'node:path' );
const { tmpdir } = require( 'node:os' );

const mod = require( '../scripts/build-shared-host-dist.mjs' );

const roots = [];
afterEach( () => {
	roots
		.splice( 0 )
		.forEach( ( root ) =>
			rmSync( root, { recursive: true, force: true } )
		);
} );

/**
 * A repository with the least a plan needs, and a third-party plugin beside it as an install has one.
 *
 * @return {{root: string, mailer: string, write: (rel: string, body?: string) => void}} The repository, the plugin's folder, and a file writer.
 */
function makeRepo() {
	const root = mkdtempSync( join( tmpdir(), 'corex-with-plugin-' ) );
	roots.push( root );
	const write = ( rel, body = 'x' ) => {
		const abs = join( root, rel );
		mkdirSync( join( abs, '..' ), { recursive: true } );
		writeFileSync( abs, body );
	};
	write(
		'plugins/corex-core/corex-core.php',
		"<?php\n/**\n * Plugin Name: Corex Core\n * Requires PHP: 8.3\n * Requires at least: 7.0\n */\ndefine('COREX_CORE_VERSION', '9.9.9');\n"
	);
	write( 'composer.json', '{"autoload":{"psr-4":{}}}' );
	write( 'composer.lock', '{}' );
	write(
		'installed/acme-mailer/acme-mailer.php',
		'<?php\n/**\n * Plugin Name: Acme Mailer\n * Version: 2.3.1\n */\n'
	);
	write( 'installed/acme-mailer/includes/send.php', '<?php // sends' );
	write( 'installed/acme-mailer/composer.json', '{"name":"acme/mailer"}' );
	write( 'installed/acme-mailer/tests/SendTest.php', '<?php // its own' );
	write( 'installed/acme-mailer/.github/workflows/ci.yml', 'on: push' );

	return { root, mailer: join( root, 'installed', 'acme-mailer' ), write };
}

const planWith = ( root, withPlugins ) =>
	mod.buildPlan( {
		repoRoot: root,
		distDir: join( root, 'dist' ),
		withPlugins,
	} );

it( 'plans a named plugin as a folder of the release, beside CoreX’s own', () => {
	const { root, mailer } = makeRepo();
	const { copies, manifest } = planWith( root, [ mailer ] );

	expect(
		copies.filter( ( op ) => op.kind === 'third-party-plugin' )
	).toEqual( [
		{
			from: mailer,
			to: join( root, 'dist', 'wp-content', 'plugins', 'acme-mailer' ),
			kind: 'third-party-plugin',
		},
	] );
	expect( manifest.plugins ).toEqual( [ 'acme-mailer', 'corex-core' ] );
	expect( manifest.third_party_plugins ).toEqual( [ 'acme-mailer' ] );
	// A release path is what a site unpacks, checks against `contents`, and replaces whole.
	expect( manifest.release_paths ).toContain(
		'wp-content/plugins/acme-mailer'
	);
} );

it( 'takes a folder given relative to the repository', () => {
	const { root } = makeRepo();

	expect(
		planWith( root, [ 'installed/acme-mailer' ] ).manifest.release_paths
	).toContain( 'wp-content/plugins/acme-mailer' );
} );

it( 'says no third-party plugin is in a package that was given none', () => {
	const { root } = makeRepo();

	expect( planWith( root, [] ).manifest.third_party_plugins ).toEqual( [] );
} );

it( 'copies the plugin as its author shipped it, without what a site refuses in a package', () => {
	const { root, mailer } = makeRepo();
	const packaged = join(
		root,
		'dist',
		'wp-content',
		'plugins',
		'acme-mailer'
	);

	// The copy alone, with the builder's own rule for this kind of tree.
	cpSync( mailer, packaged, {
		recursive: true,
		filter: mod.copyFilter( 'third-party-plugin' ),
	} );

	expect( existsSync( join( packaged, 'acme-mailer.php' ) ) ).toBe( true );
	expect( existsSync( join( packaged, 'includes', 'send.php' ) ) ).toBe(
		true
	);
	// Its own to ship: a plugin may read the file that describes it.
	expect( existsSync( join( packaged, 'composer.json' ) ) ).toBe( true );
	// A site refuses a package that holds either, whoever wrote them.
	expect( existsSync( join( packaged, 'tests' ) ) ).toBe( false );
	expect( existsSync( join( packaged, '.github' ) ) ).toBe( false );
} );

it.each( [
	[
		'is not there',
		( { root } ) => join( root, 'installed', 'no-such-plugin' ),
		/no-such-plugin.*is not a folder/,
	],
	[
		'is not a plugin',
		( { root, write } ) => {
			write( 'installed/just-files/notes.php', '<?php // no header' );
			return join( root, 'installed', 'just-files' );
		},
		/just-files.*Plugin Name/,
	],
	[
		'has a name the package already holds',
		( { root, write } ) => {
			write(
				'installed/corex-core/corex-core.php',
				'<?php\n/**\n * Plugin Name: Somebody else’s\n */\n'
			);
			return join( root, 'installed', 'corex-core' );
		},
		/already holds a plugin named corex-core/,
	],
	[
		'has a name a site would not take for a folder of a release',
		( { root, write } ) => {
			write(
				'installed/acme mailer/acme-mailer.php',
				'<?php\n/**\n * Plugin Name: Acme Mailer\n */\n'
			);
			return join( root, 'installed', 'acme mailer' );
		},
		/acme mailer.*name/,
	],
] )( 'refuses a folder that %s', ( _, folder, says ) => {
	const repo = makeRepo();

	expect( () => planWith( repo.root, [ folder( repo ) ] ) ).toThrow( says );
} );
