/**
 * Playwright global teardown (spec 116, US-NFR-09): removes the submissions this run made that
 * carry a test's marker, and fails the run if it made any that are still on the site, naming
 * each. residue.js says what a marker is and why.
 */
const { wpEval } = require( './helpers' );
const { finishRun } = require( './residue' );

module.exports = async () => {
	const left = finishRun( wpEval );

	if ( left.length > 0 ) {
		throw new Error(
			`This run left ${ left.length } submission(s) on the site that carry no test marker. ` +
				'Remove what a test makes, or mark it (a flow slug with "e2e", utm_source "playwright", or a sender at example.com):\n' +
				left.map( ( line ) => `  #${ line }` ).join( '\n' )
		);
	}
};
