/**
 * An installation, driven from the browser a request at a time (spec 107, plan D8; FR-025).
 *
 * The site does a piece and answers where the installation stands. The browser asks for the next
 * piece, paced, until the files are in place, and then asks the release just installed to finish.
 * The client is the one thing replaced: it answers what each test scripts, in order.
 */
import { runInstallation } from '../installRun.js';
import {
	ReleaseRefusedError,
	ReleaseUnreachableError,
} from '../releaseClient.js';

const standing = ( step, cursor = 0, of = 0 ) => ( { step, cursor, of } );

/**
 * A client whose installation routes answer from a script.
 *
 * @param {Object} script        What each route answers, in order.
 * @param {Array}  script.step   Answers of `step`; an Error is thrown.
 * @param {Array}  script.finish Answers of `finish`.
 * @return {Object} `{ client, asked }`.
 */
function scripted( { step = [], finish = [] } ) {
	const asked = [];
	const next = ( name, answers ) => async () => {
		asked.push( name );
		const answer = answers.shift();
		if ( answer === undefined ) {
			throw new Error(
				`the test scripted no more answers for ${ name }`
			);
		}
		if ( answer instanceof Error ) {
			throw answer;
		}
		return answer;
	};

	return {
		client: {
			step: next( 'step', step ),
			finish: next( 'finish', finish ),
		},
		asked,
	};
}

const noWaiting = { wait: async () => {} };

it( 'asks for the next piece until the files are in place, then for the release to finish', async () => {
	const { client, asked } = scripted( {
		step: [
			standing( 'unpack', 1200, 7340 ),
			standing( 'verify', 0, 20 ),
			standing( 'copy_tables' ),
			standing( 'swap' ),
			standing( 'finish' ),
		],
		finish: [ { done: true, version: '0.45.0', changes: [] } ],
	} );
	const stoodAt = [];

	const outcome = await runInstallation( client, {
		...noWaiting,
		onStand: ( stand ) => stoodAt.push( stand.step ),
	} );

	expect( asked ).toEqual( [
		'step',
		'step',
		'step',
		'step',
		'step',
		'finish',
	] );
	expect( stoodAt ).toEqual( [
		'unpack',
		'verify',
		'copy_tables',
		'swap',
		'finish',
	] );
	expect( outcome ).toEqual( { version: '0.45.0', changes: [] } );
} );

it( 'goes on asking the release to finish while a network’s sites are brought to it', async () => {
	const { client, asked } = scripted( {
		step: [ standing( 'finish' ) ],
		finish: [
			{ done: false, step: 'finish', cursor: 20, of: 0 },
			{ done: false, step: 'finish', cursor: 40, of: 0 },
			{ done: true, version: '0.45.0', changes: [ { component: 'x' } ] },
		],
	} );

	const outcome = await runInstallation( client, noWaiting );

	expect( asked.filter( ( name ) => name === 'finish' ) ).toHaveLength( 3 );
	expect( outcome.changes ).toEqual( [ { component: 'x' } ] );
} );

it( 'starts at the database step for an installation whose files are already in place', async () => {
	const { client, asked } = scripted( {
		finish: [ { done: true, version: '0.45.0', changes: [] } ],
	} );

	await runInstallation( client, { ...noWaiting, from: 'finish' } );

	expect( asked ).toEqual( [ 'finish' ] );
} );

it( 'paces itself: it waits before each request after the first', async () => {
	const { client } = scripted( {
		step: [ standing( 'verify' ), standing( 'finish' ) ],
		finish: [ { done: true, version: '0.45.0', changes: [] } ],
	} );
	const waited = [];

	await runInstallation( client, {
		wait: async ( ms ) => waited.push( ms ),
	} );

	expect( waited ).toHaveLength( 2 );
	expect( Math.min( ...waited ) ).toBeGreaterThan( 0 );
} );

it( 'waits and asks again when the site says another request of the installation is at work', async () => {
	const { client, asked } = scripted( {
		step: [
			new ReleaseRefusedError( 'installation_busy', 'Wait a moment.' ),
			standing( 'finish' ),
		],
		finish: [ { done: true, version: '0.45.0', changes: [] } ],
	} );

	await expect( runInstallation( client, noWaiting ) ).resolves.toEqual( {
		version: '0.45.0',
		changes: [],
	} );
	expect( asked ).toEqual( [ 'step', 'step', 'finish' ] );
} );

it( 'stops on a refusal, with the site’s words and where the installation stood', async () => {
	const { client } = scripted( {
		step: [
			standing( 'verify' ),
			new ReleaseRefusedError(
				'differs_from_description',
				'What was unpacked is not what the package says.'
			),
		],
	} );

	const failure = await runInstallation( client, noWaiting ).catch(
		( caught ) => caught
	);

	expect( failure ).toBeInstanceOf( ReleaseRefusedError );
	expect( failure.message ).toBe(
		'What was unpacked is not what the package says.'
	);
	expect( failure.filesInPlace ).toBe( false );
} );

it.each( [
	[
		'the swap did not answer',
		{
			step: [
				standing( 'swap' ),
				new ReleaseUnreachableError( 'No answer.' ),
			],
		},
	],
	[
		'the new release did not answer',
		{
			step: [ standing( 'finish' ) ],
			finish: [ new ReleaseUnreachableError( 'No answer.' ) ],
		},
	],
	[
		'a database change failed',
		{
			step: [ standing( 'finish' ) ],
			finish: [
				new ReleaseRefusedError(
					'database_change_failed',
					'It failed.'
				),
			],
		},
	],
] )( 'says the files may be in place when %s', async ( _when, script ) => {
	const { client } = scripted( script );

	const failure = await runInstallation( client, noWaiting ).catch(
		( caught ) => caught
	);

	// From the swap on, the way out is the way back, and the screen has to offer it.
	expect( failure.filesInPlace ).toBe( true );
} );
