#!/usr/bin/env node
/**
 * Does a release candidate merge into a client repository, and do the client's suites pass on the
 * result? (spec 116, US-NFR-08; Definition of Done item 13)
 *
 *   node scripts/client-merge-check.mjs --client <path or URL> --candidate <ref>
 *        [--framework <path or URL>] [--suite "<command>"]... [--report <file>] [--keep]
 *
 * 1. Clones the client repository into a new temporary folder (`git clone --no-local`, so the
 *    clone shares no file with it). The repository given is only read.
 * 2. Fetches the candidate from the framework repository (the checkout this script is in, unless
 *    `--framework` says otherwise) and merges it.
 * 3. Lists every conflict, and marks the ones in a file a `sites/<client>/corex-baseline.json`
 *    records as a local edit (`exceptions`): the change a client made on purpose and the release
 *    also changed.
 * 4. On a clean merge, moves each baseline to the candidate, as a client does once it has taken a
 *    release, and runs each `--suite` from the clone's root, `node scripts/verify-framework.mjs`
 *    first.
 * 5. Writes a Markdown report for the release record (stdout, or `--report <file>`), and exits
 *    non-zero on a conflict or a failing suite. Removes the clone unless `--keep`.
 *
 * Before a tag, the person cutting the release runs it against each running client at its
 * recorded baseline, with that client's own suites (CONTRIBUTING.md, "Before a tag"). CI runs it
 * on every pull request against a client site it generates (the `client-merge` job).
 */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const VERIFY = 'node scripts/verify-framework.mjs';
const IDENTITY = [
	'-c',
	'user.name=CoreX client merge check',
	'-c',
	'user.email=client-merge-check@example.invalid',
];

/**
 * Runs a program, or with `args` null a command line through the system's shell.
 *
 * @param {string}        command The program, or the whole command line.
 * @param {string[]|null} args    Its arguments; null for a command line.
 * @param {string}        cwd     Where to run it.
 * @return {{status: number, output: string}} Its exit status and everything it printed.
 */
function run( command, args, cwd ) {
	const options = {
		cwd,
		shell: args === null,
		encoding: 'utf8',
		maxBuffer: 64 * 1024 * 1024,
		windowsHide: true,
	};
	const result =
		args === null
			? spawnSync( command, options )
			: spawnSync( command, args, options );
	return {
		status: result.status ?? 1,
		output: `${ result.stdout ?? '' }${ result.stderr ?? '' }`,
	};
}

function git( cwd, ...args ) {
	const result = run( 'git', [ ...IDENTITY, ...args ], cwd );

	if ( result.status !== 0 ) {
		throw new Error(
			`git ${ args.join( ' ' ) } failed in ${ cwd }:\n${ result.output }`
		);
	}

	return result.output.trim();
}

/**
 * Points a client's baseline at a framework commit, keeping its local edits: what a client does
 * once it has taken a release. Also `--record-baseline <file> <commit>`, for a client repository
 * made to be checked.
 *
 * @param {string} file    The `corex-baseline.json` to change.
 * @param {string} release The release name to record.
 * @param {string} commit  The full commit hash to record.
 */
export function recordBaseline( file, release, commit ) {
	const record = JSON.parse( fs.readFileSync( file, 'utf8' ) );
	fs.writeFileSync(
		file,
		JSON.stringify( { ...record, release, commit }, null, '\t' ) + '\n'
	);
}

/**
 * Every `sites/<client>/corex-baseline.json` in a checkout, read.
 * @param {string} root A checkout.
 */
function baselines( root ) {
	const sites = path.join( root, 'sites' );

	if ( ! fs.existsSync( sites ) ) {
		return [];
	}

	return fs
		.readdirSync( sites )
		.map( ( site ) => path.join( 'sites', site, 'corex-baseline.json' ) )
		.filter( ( file ) => fs.existsSync( path.join( root, file ) ) )
		.map( ( file ) => ( {
			file,
			record: JSON.parse(
				fs.readFileSync( path.join( root, file ), 'utf8' )
			),
		} ) );
}

/**
 * The check itself, with no output of its own.
 *
 * @param {Object}   options
 * @param {string}   options.client      The client repository: a path or a URL git can clone.
 * @param {string}   options.candidate   The release candidate: a ref in the framework repository.
 * @param {string}   [options.framework] The framework repository (the current folder by default).
 * @param {string[]} [options.suites]    Commands to run from the merged clone's root.
 * @param {string}   [options.workdir]   Where to make the clone (a new temporary folder by default).
 * @return {Object} What happened: baseline, candidate, conflicts, suites, passed, clone.
 */
export function checkClientMerge( {
	client,
	candidate,
	framework = process.cwd(),
	suites = [],
	workdir,
} ) {
	const folder =
		workdir ||
		fs.mkdtempSync( path.join( os.tmpdir(), 'corex-client-merge-' ) );
	const clone = path.join( folder, 'client' );

	git( folder, 'clone', '--quiet', '--no-local', client, clone );

	const before = baselines( clone );
	git( clone, 'fetch', '--quiet', framework, candidate );
	const candidateCommit = git( clone, 'rev-parse', 'FETCH_HEAD' );
	const merge = run(
		'git',
		[
			...IDENTITY,
			'merge',
			'--no-edit',
			'--no-ff',
			'--quiet',
			'FETCH_HEAD',
		],
		clone
	);

	const localEdits = new Map(
		before.flatMap( ( { file, record } ) =>
			( record.exceptions || [] ).map( ( exception ) => [
				exception.path,
				{ ...exception, record: file },
			] )
		)
	);
	const conflicts =
		merge.status === 0
			? []
			: git( clone, 'diff', '--name-only', '--diff-filter=U' )
					.split( '\n' )
					.filter( Boolean )
					.map( ( file ) => ( {
						file,
						localEdit: localEdits.get( file ) || null,
					} ) );

	const result = {
		client,
		candidate,
		candidateCommit,
		baselines: before.map( ( { file, record } ) => ( {
			file,
			release: record.release,
			commit: record.commit,
		} ) ),
		merged: merge.status === 0,
		mergeOutput: merge.status === 0 ? '' : merge.output.trim(),
		conflicts,
		suites: [],
		clone,
	};

	if ( result.merged ) {
		for ( const { file } of baselines( clone ) ) {
			recordBaseline(
				path.join( clone, file ),
				`candidate ${ candidate }`,
				candidateCommit
			);
		}

		for ( const command of [
			VERIFY,
			...suites.filter( ( suite ) => suite !== VERIFY ),
		] ) {
			const started = Date.now();
			// A suite is a command line, as a client's CI writes it, so the system's shell runs it.
			const outcome = run( command, null, clone );
			result.suites.push( {
				command,
				status: outcome.status,
				seconds: Math.round( ( Date.now() - started ) / 1000 ),
				output: outcome.output,
			} );
		}
	}

	result.passed =
		result.merged && result.suites.every( ( suite ) => suite.status === 0 );

	return result;
}

/**
 * The result as the release record carries it.
 * @param {Object} result What checkClientMerge() answered.
 */
export function report( result ) {
	const lines = [
		`## Client merge check: ${ result.client }`,
		'',
		`- Candidate: \`${ result.candidate }\` (${ result.candidateCommit.slice( 0, 12 ) })`,
		...result.baselines.map(
			( baseline ) =>
				`- Baseline: ${ baseline.file }, release ${ baseline.release } (${ String( baseline.commit ).slice( 0, 12 ) })`
		),
		`- Merge: ${ result.merged ? 'clean' : `**${ result.conflicts.length } conflict(s)**` }`,
	];

	for ( const conflict of result.conflicts ) {
		lines.push(
			conflict.localEdit
				? `  - \`${ conflict.file }\`: **a recorded local edit** (${ conflict.localEdit.record }: ${ conflict.localEdit.reason }). The client's session agrees how it is resolved before the tag.`
				: `  - \`${ conflict.file }\``
		);
	}

	for ( const suite of result.suites ) {
		lines.push(
			`- Suite \`${ suite.command }\`: ${ suite.status === 0 ? 'passed' : `**failed** (exit ${ suite.status })` }, ${ suite.seconds }s`
		);

		if ( suite.status !== 0 ) {
			lines.push(
				'',
				'```text',
				suite.output.trim().split( '\n' ).slice( -30 ).join( '\n' ),
				'```',
				''
			);
		}
	}

	lines.push(
		'',
		`**Result: ${ result.passed ? 'the candidate merges and the suites pass' : 'the tag waits' }.**`,
		''
	);

	return lines.join( '\n' );
}

function argumentsOf( argv ) {
	const options = { suites: [] };

	for ( let index = 0; index < argv.length; index++ ) {
		const flag = argv[ index ];

		if ( flag === '--keep' ) {
			options.keep = true;
		} else if ( flag === '--suite' ) {
			options.suites.push( argv[ ++index ] );
		} else if (
			[ '--client', '--candidate', '--framework', '--report' ].includes(
				flag
			)
		) {
			options[ flag.slice( 2 ) ] = argv[ ++index ];
		} else {
			throw new Error( `Unknown argument: ${ flag }` );
		}
	}

	if ( ! options.client || ! options.candidate ) {
		throw new Error(
			'Usage: node scripts/client-merge-check.mjs --client <path or URL> --candidate <ref> [--framework <repo>] [--suite "<command>"]... [--report <file>] [--keep]'
		);
	}

	return options;
}

// Run as a command, not when a test imports it: `import.meta` does not survive Jest's transform
// (the same detection as sync-project-status.mjs).
if ( path.basename( process.argv[ 1 ] ?? '' ) === 'client-merge-check.mjs' ) {
	try {
		if ( process.argv[ 2 ] === '--record-baseline' ) {
			recordBaseline( process.argv[ 3 ], 'base', process.argv[ 4 ] );
			process.exit( 0 );
		}

		const options = argumentsOf( process.argv.slice( 2 ) );
		const result = checkClientMerge( {
			...options,
			framework:
				options.framework ||
				path.resolve( path.dirname( process.argv[ 1 ] ), '..' ),
		} );
		const text = report( result );

		if ( options.report ) {
			fs.writeFileSync( options.report, text );
		}

		process.stdout.write( text );

		if ( ! options.keep ) {
			fs.rmSync( path.dirname( result.clone ), {
				recursive: true,
				force: true,
			} );
		}

		process.exit( result.passed ? 0 : 1 );
	} catch ( error ) {
		process.stderr.write( `${ error.message }\n` );
		process.exit( 2 );
	}
}
