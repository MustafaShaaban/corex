/**
 * `npm run verify:framework` — are this repository's framework files the ones it recorded? (spec 102)
 *
 * A client repository carries a copy of the framework and takes each release by merging it. That
 * only stays conflict-free while the client leaves framework-owned files alone, and until this
 * script that was a belief checked by a hand-typed `git diff` over a list of directories — a list
 * that did not include the repository root.
 *
 * This compares every path the client does **not** own against the commit named in
 * `sites/<client>/corex-baseline.json`, and exits non-zero on anything that differs without a
 * recorded exception. It needs git and Node, and nothing installed.
 *
 *   node scripts/verify-framework.mjs                  compare, one line per finding
 *   node scripts/verify-framework.mjs --json           the same result as one JSON object
 *   node scripts/verify-framework.mjs --record v1.2.3  write that release into every record
 */
import { spawnSync } from 'node:child_process';
import { existsSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import {
	BaselineError,
	evaluateDrift,
	parseBaseline,
	sharedCommit,
} from './framework-baseline.mjs';
import { loadOwnership, resolveRole } from './repository-ownership.mjs';

const SITES_DIRECTORY = 'sites';
const BASELINE_FILE = 'corex-baseline.json';
const RECORD_HINT =
	'record one with `npm run verify:framework -- --record <release tag>`';
const NOTHING_FOUND = { drift: [], excepted: [], stale: [], warnings: [] };

const git = ( root, args ) => {
	const run = spawnSync( 'git', args, {
		cwd: root,
		encoding: 'utf8',
		maxBuffer: 64 * 1024 * 1024,
		windowsHide: true,
	} );

	return { succeeded: run.status === 0, stdout: run.stdout ?? '' };
};

const gitPaths = ( root, args ) =>
	git( root, args ).stdout.split( '\0' ).filter( Boolean );

const commitOf = ( root, ref ) => {
	const resolved = git( root, [
		'rev-parse',
		'--quiet',
		'--verify',
		'--end-of-options',
		`${ ref }^{commit}`,
	] );

	return resolved.succeeded ? resolved.stdout.trim() : null;
};

/**
 * @param {string} root Absolute path of the repository.
 * @return {string[]} Every `sites/<client>/corex-baseline.json` present, repository-relative.
 */
const baselineFiles = ( root ) => {
	const sites = path.join( root, SITES_DIRECTORY );

	if ( ! existsSync( sites ) ) {
		return [];
	}

	return readdirSync( sites, { withFileTypes: true } )
		.filter( ( entry ) => entry.isDirectory() )
		.map(
			( entry ) =>
				`${ SITES_DIRECTORY }/${ entry.name }/${ BASELINE_FILE }`
		)
		.filter( ( file ) => existsSync( path.join( root, file ) ) );
};

const decoded = ( root, file ) => {
	try {
		return JSON.parse( readFileSync( path.join( root, file ), 'utf8' ) );
	} catch ( error ) {
		if ( error instanceof SyntaxError ) {
			throw new BaselineError( `${ file }: not valid JSON` );
		}

		throw error;
	}
};

const readRecord = ( root, file ) => {
	const raw = decoded( root, file );

	try {
		return { file, ...parseBaseline( raw ) };
	} catch ( error ) {
		if ( error instanceof BaselineError ) {
			throw new BaselineError( `${ file }: ${ error.message }` );
		}

		throw error;
	}
};

const foundBaselineFiles = ( root ) => {
	const files = baselineFiles( root );

	if ( files.length === 0 ) {
		throw new BaselineError(
			`no baseline recorded — expected ${ SITES_DIRECTORY }/<client>/${ BASELINE_FILE }; ${ RECORD_HINT }`
		);
	}

	return files;
};

/**
 * The records, checked far enough that their commit can be compared against.
 *
 * @param {string} root Absolute path of the repository.
 * @return {Object[]} One record per client site, all naming the same existing commit.
 * @throws {BaselineError} When there is none, one is invalid, they disagree, or the commit is absent.
 */
const usableRecords = ( root ) => {
	const records = foundBaselineFiles( root ).map( ( file ) =>
		readRecord( root, file )
	);
	const commit = sharedCommit( records );

	if ( commitOf( root, commit ) === null ) {
		throw new BaselineError(
			`baseline commit ${ commit } is not in this repository — fetch the framework ` +
				'remote, and check out full history rather than a shallow clone'
		);
	}

	return records;
};

/**
 * @param {string} root   Absolute path of the repository.
 * @param {string} commit The baseline commit.
 * @return {string[]} Tracked paths that differ from it, and untracked paths git is not told to ignore.
 */
const changedSince = ( root, commit ) => [
	...gitPaths( root, [
		'diff',
		'--name-only',
		'--no-renames',
		'-z',
		commit,
		'--',
	] ),
	...gitPaths( root, [ 'ls-files', '--others', '--exclude-standard', '-z' ] ),
];

const releaseWarnings = ( root, { release, commit } ) => {
	const tagged = commitOf( root, `refs/tags/${ release }` );

	if ( tagged === null || tagged === commit ) {
		return [];
	}

	return [
		`the tag ${ release } points at ${ tagged }, not at the recorded commit — ` +
			'the comparison uses the recorded commit',
	];
};

const compare = ( root, ownership ) => {
	const records = usableRecords( root );
	const [ { release, commit } ] = records;
	const found = evaluateDrift( {
		ownership,
		exceptions: records.flatMap( ( record ) => record.exceptions ),
		changed: changedSince( root, commit ),
	} );
	const clean = found.drift.length === 0 && found.stale.length === 0;

	return {
		status: clean ? 'pass' : 'fail',
		baseline: { release, commit },
		...found,
		warnings: releaseWarnings( root, { release, commit } ),
		failures: [],
	};
};

const releaseCommit = ( root, release ) => {
	if ( ! release ) {
		throw new BaselineError( '--record needs a release tag to record' );
	}

	const commit = commitOf( root, release );

	if ( commit === null ) {
		throw new BaselineError(
			`${ release } does not name a commit in this repository — fetch the framework remote first`
		);
	}

	return commit;
};

const record = ( root, release ) => {
	const commit = releaseCommit( root, release );
	const recorded = new Date().toISOString().slice( 0, 10 );

	// Every record is decoded before any is written, so one unreadable file changes none of them.
	const rewritten = foundBaselineFiles( root ).map( ( file ) => ( {
		file,
		contents: {
			release,
			commit,
			recorded,
			exceptions: decoded( root, file ).exceptions ?? [],
		},
	} ) );

	for ( const { file, contents } of rewritten ) {
		writeFileSync(
			path.join( root, file ),
			JSON.stringify( contents, null, 2 ) + '\n'
		);
	}

	return {
		status: 'pass',
		baseline: { release, commit },
		recorded: rewritten.map( ( { file } ) => file ),
	};
};

const failed = ( message ) => ( {
	status: 'fail',
	baseline: null,
	...NOTHING_FOUND,
	failures: [ message ],
} );

const findingLines = ( result ) => [
	...result.failures.map( ( failure ) => `FAIL\t${ failure }` ),
	...result.warnings.map( ( warning ) => `WARN\t${ warning }` ),
	...result.drift.map( ( file ) => `DRIFT\t${ file }` ),
	...result.stale.map(
		( { path: file } ) =>
			`STALE\t${ file }\tno longer differs — delete the exception`
	),
	...result.excepted.map(
		( { path: file, reason, upstream } ) =>
			`EXCEPTION\t${ file }\t${ reason }\t${ upstream }`
	),
];

const summaryLine = ( result ) =>
	[
		'framework',
		result.status.toUpperCase(),
		`${ result.drift.length } drifted`,
		`${ result.excepted.length } accepted exceptions`,
		result.baseline
			? `baseline ${ result.baseline.release } ${ result.baseline.commit }`
			: 'no usable baseline',
	].join( '\t' );

const report = ( result ) => {
	if ( result.role === 'framework' ) {
		return [
			'framework\tPASS\tthe framework repository — nothing to compare',
		];
	}

	if ( result.recorded ) {
		const { release, commit } = result.baseline;

		return result.recorded.map(
			( file ) => `RECORDED\t${ file }\t${ release }\t${ commit }`
		);
	}

	return [ ...findingLines( result ), summaryLine( result ) ];
};

const verify = ( root, args ) => {
	const ownership = loadOwnership( root );
	const role = resolveRole( {
		ownership,
		env: process.env,
		originUrl: git( root, [ 'remote', 'get-url', 'origin' ] ).stdout,
	} );

	if ( role === 'framework' ) {
		return { status: 'pass', role };
	}

	const recordAt = args.indexOf( '--record' );

	try {
		return recordAt === -1
			? compare( root, ownership )
			: record( root, args[ recordAt + 1 ] );
	} catch ( error ) {
		if ( error instanceof BaselineError ) {
			return failed( error.message );
		}

		throw error;
	}
};

const args = process.argv.slice( 2 );
const toplevel = git( process.cwd(), [ 'rev-parse', '--show-toplevel' ] );
const result = toplevel.succeeded
	? verify( toplevel.stdout.trim(), args )
	: failed( 'not inside a git repository' );

process.stdout.write(
	args.includes( '--json' )
		? JSON.stringify( result, null, 2 ) + '\n'
		: report( result ).join( '\n' ) + '\n'
);
process.exitCode = result.status === 'pass' ? 0 : 1;
