/**
 * The autoloader of a shared-host `dist` package (DECISIONS #265).
 *
 * In the package `wp-content/` is the project root. A plugin at `wp-content/plugins/<name>/` looks
 * for `vendor/autoload.php` two directories up, exactly as it does in the repository, and finds
 * `wp-content/vendor/`. The CLI package sits at `wp-content/packages/cli/`, where its own
 * "three directories up" is the same root. What does not carry over is Composer's map: it names
 * `addons/<name>/src/`, and the package holds add-ons under `plugins/`. So the map is translated
 * through the copy plan and Composer generates the autoloader inside the package, from the
 * repository's lock file and without its dev packages. The checkout's own `vendor/` is not read.
 */

import {
	existsSync,
	readFileSync,
	writeFileSync,
	copyFileSync,
	mkdirSync,
	rmSync,
} from 'node:fs';
import { join, relative, resolve, sep } from 'node:path';
import { spawnSync } from 'node:child_process';

/** The package's project root, relative to the package. */
export const PACKAGE_ROOT = 'wp-content';

/** Where every packaged plugin finds the autoloader, relative to the package. */
export const AUTOLOADER = 'wp-content/vendor/autoload.php';

const COMPOSER_INSTALL = [
	'install',
	'--no-dev',
	'--optimize-autoloader',
	'--no-interaction',
	'--no-progress',
	'--no-scripts',
	'--no-plugins',
];

const posix = ( path ) => path.split( sep ).join( '/' );

/**
 * Where the copy plan puts a source path, or null when the plan does not package it.
 *
 * @param {Array<{from:string,to:string}>} copies The copy plan.
 * @param {string}                         source An absolute path in the repository.
 * @return {string|null} The absolute path in the package.
 */
function packagedPath( copies, source ) {
	const op = copies.find(
		( copy ) =>
			source === copy.from || source.startsWith( copy.from + sep )
	);
	return op ? join( op.to, relative( op.from, source ) ) : null;
}

/**
 * Translate the repository's Composer autoload map into the packaged layout. Pure: reads only.
 *
 * @param {string}                                     repoRoot The repository root.
 * @param {string}                                     distDir  Where the package is written.
 * @param {Array<{from:string,to:string,kind:string}>} copies   The copy plan.
 * @return {{composer:Object, lock:string, manifest:{file:string, psr4:Object<string,string>}, warnings:string[]}} What Composer is given, and what the package promises to load.
 */
export function planAutoload( repoRoot, distDir, copies ) {
	const lock = join( repoRoot, 'composer.lock' );
	if ( ! existsSync( join( repoRoot, 'composer.json' ) ) || ! existsSync( lock ) ) {
		throw new Error(
			'composer.json and composer.lock are needed at the repository root: the package autoloader is generated from them.'
		);
	}
	const composer = JSON.parse(
		readFileSync( join( repoRoot, 'composer.json' ), 'utf8' )
	);
	const { 'psr-4': namespaces = {}, ...otherKinds } = composer.autoload ?? {};
	if ( Object.keys( otherKinds ).length > 0 ) {
		throw new Error(
			`composer.json autoload has ${ Object.keys( otherKinds ).join(
				', '
			) }: only psr-4 is translated into the package.`
		);
	}

	const packageRoot = join( distDir, PACKAGE_ROOT );
	const sources = [];
	const fromPackageRoot = {};
	const fromPackage = {};
	const warnings = [];
	for ( const [ prefix, path ] of Object.entries( namespaces ) ) {
		if ( typeof path !== 'string' ) {
			throw new Error(
				`composer.json maps ${ prefix } to more than one directory: the package takes one directory per namespace.`
			);
		}
		const source = resolve( repoRoot, path );
		const packaged = packagedPath( copies, source );
		if ( packaged === null ) {
			warnings.push(
				`${ prefix } is mapped to ${ path }, which is not in the package — left out of its autoloader.`
			);
			continue;
		}
		sources.push( source );
		fromPackageRoot[ prefix ] = posix( relative( packageRoot, packaged ) ) + '/';
		fromPackage[ prefix ] = posix( relative( distDir, packaged ) ) + '/';
	}

	// A framework tree with classes and no namespace would be copied and could never be loaded.
	for ( const op of copies ) {
		const src = join( op.from, 'src' );
		const mapped = sources.some( ( source ) =>
			source.startsWith( op.from + sep )
		);
		if ( [ 'plugin', 'cli' ].includes( op.kind ) && existsSync( src ) && ! mapped ) {
			throw new Error(
				`${ posix(
					relative( repoRoot, src )
				) } holds classes that no namespace in composer.json is mapped to: the package could not load them.`
			);
		}
	}

	const {
		'autoload-dev': ignoredDevAutoload,
		scripts: ignoredScripts,
		...kept
	} = composer;

	return {
		composer: { ...kept, autoload: { 'psr-4': fromPackageRoot } },
		lock,
		manifest: { file: AUTOLOADER, psr4: fromPackage },
		warnings,
	};
}

/**
 * Have Composer install the production packages and generate the autoloader inside the package.
 *
 * @param {{composer:Object, lock:string}} autoload The plan from `planAutoload`.
 * @param {string}                         distDir  Where the package is written.
 */
export function installPackagedVendor( autoload, distDir ) {
	const packageRoot = join( distDir, PACKAGE_ROOT );
	const manifest = join( packageRoot, 'composer.json' );
	const lock = join( packageRoot, 'composer.lock' );
	// Either of these, set in a developer's shell, would send the install somewhere else.
	const { COMPOSER, COMPOSER_VENDOR_DIR, ...env } = process.env;

	mkdirSync( packageRoot, { recursive: true } );
	writeFileSync( manifest, JSON.stringify( autoload.composer, null, 4 ) );
	copyFileSync( autoload.lock, lock );
	try {
		const run = spawnSync( 'composer', COMPOSER_INSTALL, {
			cwd: packageRoot,
			env,
			encoding: 'utf8',
			shell: process.platform === 'win32',
		} );
		if ( run.status !== 0 ) {
			throw new Error(
				`composer install failed for the package (is composer on the PATH?):\n${
					run.error?.message ?? run.stderr
				}`
			);
		}
	} finally {
		// What Composer worked from is not part of a web root.
		rmSync( manifest, { force: true } );
		rmSync( lock, { force: true } );
	}
}

/**
 * The dev packages Composer recorded for the packaged vendor/, as an error when there are any.
 *
 * @param {string} distDir The built package.
 * @return {string[]} One message, or none.
 */
function verifyNoDevPackages( distDir ) {
	const installed = join(
		distDir,
		PACKAGE_ROOT,
		'vendor',
		'composer',
		'installed.json'
	);
	if ( ! existsSync( installed ) ) {
		return [];
	}
	const record = JSON.parse( readFileSync( installed, 'utf8' ) );
	const names = record[ 'dev-package-names' ] ?? [];
	if ( record.dev === false && names.length === 0 ) {
		return [];
	}
	return [
		`${ PACKAGE_ROOT }/vendor was installed with dev packages (${
			names.join( ', ' ) || 'none named'
		}): they do not belong in a web root.`,
	];
}

/**
 * Prove the package loads: no dev packages, and the probe's verdict from a PHP process of its own.
 *
 * @param {string} distDir     The built package.
 * @param {string} probeScript The path of `shared-host-dist-probe.php`.
 * @return {string[]} One message per reason the package would not load on a host.
 */
export function verifyPackagedAutoload( distDir, probeScript ) {
	const run = spawnSync( 'php', [ probeScript, distDir ], {
		encoding: 'utf8',
	} );
	let report;
	try {
		report = JSON.parse( run.stdout );
	} catch {
		return [
			`the package could not be asked whether it loads (is php on the PATH?):\n${
				run.error?.message ?? ( run.stderr || run.stdout )
			}`,
		];
	}
	return [ ...verifyNoDevPackages( distDir ), ...report.errors ];
}
