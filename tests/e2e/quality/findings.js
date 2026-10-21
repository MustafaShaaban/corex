/**
 * Findings and the known-failures list (spec 116).
 *
 * A **finding** is one thing a check found: the check, the screen (or template), the cell, the
 * rule, a signature of the element and a line for a person. A **known failure** is a finding that
 * was there when the list was written, kept in `known-failures.json` with the date and the story
 * that will fix it. A check fails on a finding that is not known.
 *
 * Signatures (in-page.js) are what make the list hold still. They are built from what an element is, not where
 * it sits: tag, type, role and class names without digits, never a position or an id. So twenty row
 * checkboxes are one finding, and a list that grows by a row does not grow the list. Text-based
 * checks sign with the text itself, digits replaced by `#`.
 *
 * Every finding of a run, known or not, is appended to `test-results/quality/findings.jsonl`,
 * which the CI job uploads. `COREX_QUALITY_RECORD=1` turns failures off for a run whose findings
 * are to become the list (`node scripts/quality-known-failures.mjs --record`).
 */
const fs = require( 'fs' );
const path = require( 'path' );

const KNOWN = require( './known-failures.json' );

const OUTPUT = path.join(
	__dirname,
	'..',
	'..',
	'..',
	'test-results',
	'quality'
);

const RECORDING = process.env.COREX_QUALITY_RECORD === '1';

/**
 * Whether a known-failure entry covers a finding. An entry's `screen` may be `*`.
 *
 * @param {Object} entry   A known-failures entry.
 * @param {Object} finding A finding.
 * @return {boolean} Whether it does.
 */
function covers( entry, finding ) {
	return (
		entry.check === finding.check &&
		entry.rule === finding.rule &&
		entry.signature === finding.signature &&
		( entry.screen === '*' || entry.screen === finding.screen )
	);
}

function isKnown( finding ) {
	return KNOWN.entries.some( ( entry ) => covers( entry, finding ) );
}

function record( findings ) {
	if ( findings.length === 0 ) {
		return;
	}

	fs.mkdirSync( OUTPUT, { recursive: true } );
	fs.appendFileSync(
		path.join( OUTPUT, 'findings.jsonl' ),
		findings.map( ( finding ) => JSON.stringify( finding ) ).join( '\n' ) +
			'\n'
	);
}

/**
 * The line a person reads for one finding: where, which cell, what.
 *
 * @param {Object} finding A finding.
 * @return {string} The line.
 */
function describeFinding( finding ) {
	return `${ finding.screen } [${ finding.cell }] ${ finding.check }/${
		finding.rule
	}: ${ finding.detail } (signature: ${ finding.signature })`;
}

/**
 * Records a test's findings and fails it on any that is not known.
 *
 * @param {import('@playwright/test').Expect} expect   Playwright's expect.
 * @param {Object[]}                          findings What the test found.
 */
function assertNoNewFindings( expect, findings ) {
	record( findings );

	const unknown = findings.filter( ( finding ) => ! isKnown( finding ) );
	// One line per signature and cell: the same defect in twenty rows is one line to read.
	const lines = [ ...new Set( unknown.map( describeFinding ) ) ];

	if ( RECORDING ) {
		return;
	}

	expect(
		lines,
		`${ lines.length } finding(s) not in tests/e2e/quality/known-failures.json. ` +
			'Fix them, or, for a defect another story owns, add a dated entry naming that story.'
	).toEqual( [] );
}

module.exports = {
	assertNoNewFindings,
	covers,
	describeFinding,
	isKnown,
	OUTPUT,
	RECORDING,
};
