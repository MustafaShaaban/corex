/**
 * Before the quality checks: start fresh findings and bytes files, then sign in as the browser suite
 * does.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const { OUTPUT } = require( './findings' );
const signIn = require( '../global-setup' );

module.exports = async () => {
	for ( const file of [ 'findings.jsonl', 'bytes.jsonl' ] ) {
		fs.rmSync( path.join( OUTPUT, file ), { force: true } );
	}
	await signIn();
};
