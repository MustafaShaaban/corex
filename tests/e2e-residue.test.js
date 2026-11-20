/**
 * The browser suite's clean-up acts only on the site its run started on (tests/e2e/residue.js).
 * On 2026-11-20 a run refused before it started handed its teardown the previous run's mark, and
 * the teardown counted another install's submissions against it. WP-CLI is the boundary, stubbed.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { startRun, forgetRun, finishRun } = require( './e2e/residue' );

const MARK_FILE = path.join( __dirname, 'e2e', '.auth', 'residue-mark.json' );
const SITE = 'http://127.0.0.1:8916';
const OTHER = 'http://127.0.0.1:8917';

let saved = null;

beforeEach( () => {
	saved = fs.existsSync( MARK_FILE ) ? fs.readFileSync( MARK_FILE ) : null;
} );

afterEach( () => {
	if ( saved === null ) {
		fs.rmSync( MARK_FILE, { force: true } );
	} else {
		fs.writeFileSync( MARK_FILE, saved );
	}
} );

/**
 * A WP-CLI that serves `home` and answers the run's queries.
 *
 * @param {string} home The address WP-CLI's install answers for.
 * @return {{calls: string[], wpEval: (php: string) => string}} The stub and what it was asked.
 */
function wpCliServing( home ) {
	const calls = [];

	return {
		calls,
		wpEval: ( php ) => {
			calls.push( php );
			if ( php.includes( 'home_url()' ) ) {
				return home;
			}
			if ( php.includes( 'MAX(ID)' ) ) {
				return '100';
			}
			if ( php.includes( 'post_title' ) ) {
				return '101 a submission with no marker';
			}

			return '0';
		},
	};
}

test( 'a run cleans and counts on the site it started on', () => {
	startRun( wpCliServing( SITE ).wpEval, SITE );
	const site = wpCliServing( SITE );

	expect( finishRun( site.wpEval, SITE ) ).toEqual( [
		'101 a submission with no marker',
	] );
	expect( site.calls.some( ( php ) => php.includes( 'post_title' ) ) ).toBe(
		true
	);
} );

test.each( [
	[ 'a mark from another site', OTHER, OTHER ],
	[ 'WP-CLI serving another install', SITE, OTHER ],
] )( '%s removes and counts nothing', ( _case, under, served ) => {
	startRun( wpCliServing( SITE ).wpEval, SITE );
	const other = wpCliServing( served );

	expect( finishRun( other.wpEval, under ) ).toEqual( [] );
	expect(
		other.calls.some(
			( php ) => php.includes( 'post_title' ) || php.includes( 'DELETE' )
		)
	).toBe( false );
} );

test( 'a run with no site named removes and counts nothing', () => {
	startRun( wpCliServing( SITE ).wpEval, SITE );
	const site = wpCliServing( SITE );

	expect( finishRun( site.wpEval, undefined ) ).toEqual( [] );
	expect( site.calls ).toEqual( [] );
} );

test( 'a run refused before it starts leaves its teardown no mark to act on', () => {
	startRun( wpCliServing( SITE ).wpEval, SITE );
	forgetRun();
	const site = wpCliServing( SITE );

	expect( finishRun( site.wpEval, SITE ) ).toEqual( [] );
	expect( site.calls ).toEqual( [] );
} );
