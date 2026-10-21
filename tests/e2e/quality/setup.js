/**
 * Before the quality checks: start a fresh findings file, then sign in as the browser suite does.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const { OUTPUT } = require( './findings' );
const signIn = require( '../global-setup' );

module.exports = async () => {
	fs.rmSync( path.join( OUTPUT, 'findings.jsonl' ), { force: true } );
	await signIn();
};
