/**
 * Whether a client repository's framework files still match the release it recorded (spec 102).
 *
 * The pure half of `npm run verify:framework`: what a baseline record must contain, and how a list
 * of changed paths becomes drift, recorded exceptions and stale exceptions. The half that talks to
 * git is `tests/verify-framework.test.js`.
 */
const {
	BaselineError,
	parseBaseline,
	sharedCommit,
	evaluateDrift,
} = require( '../scripts/framework-baseline.mjs' );

const COMMIT = 'a'.repeat( 40 );
const OTHER_COMMIT = 'b'.repeat( 40 );

const ownership = {
	frameworkRepository: 'Acme/framework',
	clientOwned: [ 'sites/**', '.github/workflows/site-*.yml' ],
	localOnly: [],
};

const record = ( overrides = {} ) => ( {
	release: 'v1.0.0',
	commit: COMMIT,
	recorded: '2026-10-04',
	exceptions: [],
	...overrides,
} );

const exception = ( overrides = {} ) => ( {
	path: 'plugins/core/Mail.php',
	reason: 'Sender address dropped on the way to the driver.',
	upstream: 'https://github.com/Acme/framework/issues/7',
	...overrides,
} );

describe( 'a baseline record', () => {
	it( 'is read back as the release, the commit and the exceptions', () => {
		expect(
			parseBaseline( record( { exceptions: [ exception() ] } ) )
		).toEqual( {
			release: 'v1.0.0',
			commit: COMMIT,
			exceptions: [ exception() ],
		} );
	} );

	it.each( [
		[ 'no release', record( { release: '' } ), /"release"/ ],
		[
			'an empty commit',
			record( { commit: '' } ),
			/"commit" is empty.*--record/,
		],
		[
			'a commit that is not a full hash',
			record( { commit: 'abc123' } ),
			/"commit" must be a full 40-character commit hash/,
		],
		[
			'exceptions that are not a list',
			record( { exceptions: 'none' } ),
			/"exceptions" must be a list/,
		],
		[
			'an exception with no path',
			record( { exceptions: [ exception( { path: '' } ) ] } ),
			/exception 1.*"path"/,
		],
		[
			'an exception with no reason',
			record( { exceptions: [ exception( { reason: '' } ) ] } ),
			/exception 1.*"reason"/,
		],
		[
			'an exception with no upstream reference',
			record( { exceptions: [ exception( { upstream: undefined } ) ] } ),
			/exception 1.*"upstream"/,
		],
	] )( 'is refused with %s', ( _description, raw, message ) => {
		expect( () => parseBaseline( raw ) ).toThrow( BaselineError );
		expect( () => parseBaseline( raw ) ).toThrow( message );
	} );
} );

describe( 'several records in one repository', () => {
	it( 'share the commit they all name', () => {
		expect(
			sharedCommit( [
				{ file: 'sites/acme/corex-baseline.json', commit: COMMIT },
				{ file: 'sites/beta/corex-baseline.json', commit: COMMIT },
			] )
		).toBe( COMMIT );
	} );

	it( 'are refused when they name different commits', () => {
		expect( () =>
			sharedCommit( [
				{ file: 'sites/acme/corex-baseline.json', commit: COMMIT },
				{
					file: 'sites/beta/corex-baseline.json',
					commit: OTHER_COMMIT,
				},
			] )
		).toThrow(
			/sites\/acme\/corex-baseline\.json.*sites\/beta\/corex-baseline\.json/
		);
	} );
} );

describe( 'drift', () => {
	it( 'is every changed path the client does not own', () => {
		expect(
			evaluateDrift( {
				ownership,
				exceptions: [],
				changed: [
					'sites/acme/acme-theme/style.css',
					'.github/workflows/site-acme.yml',
					'plugins/core/Mail.php',
					'README.md',
				],
			} )
		).toEqual( {
			drift: [ 'README.md', 'plugins/core/Mail.php' ],
			excepted: [],
			stale: [],
		} );
	} );

	it( 'is nothing when only the client’s own files changed', () => {
		expect(
			evaluateDrift( {
				ownership,
				exceptions: [],
				changed: [ 'sites/acme/acme-site/acme-site.php' ],
			} )
		).toEqual( { drift: [], excepted: [], stale: [] } );
	} );

	it( 'reports a recorded exception as an exception, not as drift', () => {
		expect(
			evaluateDrift( {
				ownership,
				exceptions: [ exception() ],
				changed: [ 'plugins/core/Mail.php', 'README.md' ],
			} )
		).toEqual( {
			drift: [ 'README.md' ],
			excepted: [ exception() ],
			stale: [],
		} );
	} );

	it( 'calls an exception stale once its file no longer differs', () => {
		// The procedure says to delete a local patch once upstream has it. A list nobody is made
		// to prune becomes the list of things nobody remembers the reason for.
		expect(
			evaluateDrift( {
				ownership,
				exceptions: [ exception() ],
				changed: [],
			} )
		).toEqual( { drift: [], excepted: [], stale: [ exception() ] } );
	} );
} );
