/**
 * After the quality checks: say which known failures no finding matched any more (spec 116).
 *
 * Printed and written to `test-results/quality/stale-known-failures.json`, never a failure. A
 * screen can stop showing a defect because of code a pull request did not touch, and a run of
 * part of the suite matches only part of the list. The pull request that fixes a screen removes
 * its entries; this tells it which.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const { covers, OUTPUT } = require( './findings' );
const KNOWN = require( './known-failures.json' );
const leavesNothing = require( '../global-teardown' );

module.exports = async () => {
	const file = path.join( OUTPUT, 'findings.jsonl' );
	const findings = fs.existsSync( file )
		? fs
				.readFileSync( file, 'utf8' )
				.split( '\n' )
				.filter( Boolean )
				.map( ( line ) => JSON.parse( line ) )
		: [];

	const stale = KNOWN.entries.filter(
		( entry ) => ! findings.some( ( finding ) => covers( entry, finding ) )
	);

	fs.mkdirSync( OUTPUT, { recursive: true } );
	fs.writeFileSync(
		path.join( OUTPUT, 'stale-known-failures.json' ),
		JSON.stringify( stale, null, '\t' ) + '\n'
	);

	process.stdout.write(
		`\nQuality checks: ${ findings.length } finding(s) recorded, ${ stale.length } known failure(s) not seen in this run` +
			( stale.length > 0
				? ' (if the whole suite ran, remove them: test-results/quality/stale-known-failures.json).\n'
				: '.\n' )
	);

	// Last, so the report above is printed even when the site was left with something.
	await leavesNothing();
};
