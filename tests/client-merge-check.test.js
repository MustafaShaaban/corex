/**
 * The client merge check (spec 116, US-NFR-08), on throwaway repositories made here: a framework
 * with one release and a candidate after it, and a client that took the release and added a site.
 */
const { execFileSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );

const {
	checkClientMerge,
	report,
} = require( '../scripts/client-merge-check.mjs' );

const IDENTITY = [
	'-c',
	'user.name=Test',
	'-c',
	'user.email=test@example.invalid',
	'-c',
	'init.defaultBranch=main',
];
const git = ( cwd, ...args ) =>
	execFileSync( 'git', [ ...IDENTITY, ...args ], {
		cwd,
		encoding: 'utf8',
	} ).trim();
const write = ( root, file, text ) => {
	fs.mkdirSync( path.dirname( path.join( root, file ) ), {
		recursive: true,
	} );
	fs.writeFileSync( path.join( root, file ), text );
};
const commit = ( root, message ) => {
	git( root, 'add', '-A' );
	git( root, 'commit', '--quiet', '-m', message );
	return git( root, 'rev-parse', 'HEAD' );
};

let scratch;
let framework;
let client;

/**
 * The framework has `shared.txt` at its release; the client records it as a local edit when it
 * changes it, and the candidate changes `shared.txt` or `other.txt` as a test asks.
 * @param {Object}  root0                   What the repositories hold.
 * @param {boolean} root0.clientEditsShared Whether the client changed shared.txt and recorded it.
 * @param {string}  root0.candidateEdits    The file the candidate changes.
 */
function repositories( { clientEditsShared, candidateEdits } ) {
	framework = path.join( scratch, 'framework' );
	client = path.join( scratch, 'client-source' );
	fs.mkdirSync( framework );
	git( framework, 'init', '--quiet' );
	write( framework, 'shared.txt', 'release\n' );
	write( framework, 'other.txt', 'release\n' );
	const release = commit( framework, 'release' );

	git( scratch, ...[ 'clone', '--quiet', framework, client ] );
	write(
		client,
		'sites/acme/corex-baseline.json',
		JSON.stringify( {
			release: 'v1.0.0',
			commit: release,
			exceptions: clientEditsShared
				? [
						{
							path: 'shared.txt',
							reason: 'the client changed the greeting',
							upstream: '',
						},
					]
				: [],
		} )
	);
	if ( clientEditsShared ) {
		write( client, 'shared.txt', 'the client wrote this\n' );
	}
	commit( client, 'the client site' );

	write( framework, candidateEdits, 'candidate\n' );
	commit( framework, 'candidate' );
	git( framework, 'branch', 'candidate' );
}

const verifyStandIn = ( root ) =>
	write( root, 'scripts/verify-framework.mjs', 'process.exit( 0 );\n' );

beforeEach( () => {
	scratch = fs.mkdtempSync( path.join( os.tmpdir(), 'corex-merge-test-' ) );
} );

afterEach( () => {
	fs.rmSync( scratch, { recursive: true, force: true } );
} );

describe( 'the client merge check', () => {
	it( 'merges a candidate, moves the baseline to it, and runs the suites', () => {
		repositories( {
			clientEditsShared: false,
			candidateEdits: 'other.txt',
		} );
		verifyStandIn( framework );
		commit( framework, 'a verify stand-in' );
		git( framework, 'branch', '-f', 'candidate' );

		const result = checkClientMerge( {
			client,
			candidate: 'candidate',
			framework,
			suites: [ 'node -e "process.exit( 0 )"' ],
			workdir: fs.mkdtempSync( path.join( scratch, 'work-' ) ),
		} );

		expect( result.merged ).toBe( true );
		expect( result.conflicts ).toEqual( [] );
		expect(
			result.suites.map( ( suite ) => [ suite.command, suite.status ] )
		).toEqual( [
			[ 'node scripts/verify-framework.mjs', 0 ],
			[ 'node -e "process.exit( 0 )"', 0 ],
		] );
		expect( result.passed ).toBe( true );
		expect(
			JSON.parse(
				fs.readFileSync(
					path.join( result.clone, 'sites/acme/corex-baseline.json' ),
					'utf8'
				)
			).commit
		).toBe( result.candidateCommit );
		expect( report( result ) ).toContain(
			'the candidate merges and the suites pass'
		);
	} );

	it( 'names a conflict in a recorded local edit, and runs no suite', () => {
		repositories( {
			clientEditsShared: true,
			candidateEdits: 'shared.txt',
		} );

		const result = checkClientMerge( {
			client,
			candidate: 'candidate',
			framework,
			workdir: fs.mkdtempSync( path.join( scratch, 'work-' ) ),
		} );

		expect( result.merged ).toBe( false );
		expect( result.conflicts ).toEqual( [
			{
				file: 'shared.txt',
				localEdit: expect.objectContaining( {
					reason: 'the client changed the greeting',
					record: path.join( 'sites', 'acme', 'corex-baseline.json' ),
				} ),
			},
		] );
		expect( result.suites ).toEqual( [] );
		expect( result.passed ).toBe( false );
		expect( report( result ) ).toMatch(
			/a recorded local edit.*the client changed the greeting/
		);
	} );

	it( 'fails on a client suite that fails, and shows its output', () => {
		repositories( {
			clientEditsShared: false,
			candidateEdits: 'other.txt',
		} );
		verifyStandIn( framework );
		commit( framework, 'a verify stand-in' );
		git( framework, 'branch', '-f', 'candidate' );

		const result = checkClientMerge( {
			client,
			candidate: 'candidate',
			framework,
			suites: [
				'node -e "console.log( \'the client test broke\' ); process.exit( 3 )"',
			],
			workdir: fs.mkdtempSync( path.join( scratch, 'work-' ) ),
		} );

		expect( result.passed ).toBe( false );
		expect( result.suites[ 1 ].status ).toBe( 3 );
		expect( report( result ) ).toContain( 'the client test broke' );
	} );

	it( 'leaves the client repository it was given as it was', () => {
		repositories( {
			clientEditsShared: true,
			candidateEdits: 'shared.txt',
		} );
		const head = git( client, 'rev-parse', 'HEAD' );

		checkClientMerge( {
			client,
			candidate: 'candidate',
			framework,
			workdir: fs.mkdtempSync( path.join( scratch, 'work-' ) ),
		} );

		expect( git( client, 'rev-parse', 'HEAD' ) ).toBe( head );
		expect( git( client, 'status', '--porcelain' ) ).toBe( '' );
	} );
} );
