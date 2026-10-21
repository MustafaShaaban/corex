#!/usr/bin/env node
/**
 * The quality checks' known failures (spec 116).
 *
 *   node scripts/quality-known-failures.mjs                  # print the list for the release notes
 *   node scripts/quality-known-failures.mjs --record <dir>   # add a run's new findings to the list
 *   node scripts/quality-known-failures.mjs --budget <dir>   # take a run's bytes as the budget
 *
 * `<dir>` holds a run's `findings.jsonl` and `byte-budget.json`: `test-results/quality` after a
 * local run, or the `quality-findings` artifact of a CI run. Record from a run of the whole suite
 * recorded with `COREX_QUALITY_RECORD=1`, on CI's site: that is the reference.
 *
 * Recording keeps every entry already in the list as it is, with its story and note, and adds
 * one entry per new finding, dated today, with the story chosen by `storyFor()`. A defect seen on
 * five screens or more is in the shared shell, and is recorded once for every screen (`*`).
 */
import fs from 'node:fs';
import path from 'node:path';

// The repository root, from where this script is: it is run from anywhere.
const root = path.resolve( path.dirname( process.argv[ 1 ] ?? '.' ), '..' );
const LIST = path.join( root, 'tests/e2e/quality/known-failures.json' );
const BUDGET = path.join( root, 'tests/e2e/quality/byte-budget.json' );
const SHARED = 5;

/** The story that owns fixing a finding: the screen's own story where one exists, else the check's. */
const WORDING_STORIES = {
	'corex-access': 'US-ACC-05',
	'corex-addons': 'US-ADD-08',
	'corex-forms': 'US-FLW-14',
	'corex-settings-config': 'US-SET-08',
	'corex-settings': 'US-OVR-07',
};

export function storyFor( finding ) {
	const screen = finding.screen.split( '&' )[ 0 ];
	const front = /^(template|page):/.test( finding.screen );

	if ( finding.rule === 'color-contrast' ) {
		return front ? 'US-BRD-01' : 'US-BRD-02';
	}
	if ( finding.rule === 'rail-not-folded' ) {
		return 'US-SHL-04';
	}
	if ( finding.check === 'words' ) {
		return WORDING_STORIES[ screen ] || 'US-NFR-06';
	}
	if ( finding.rule === 'failed-save-loses-input' ) {
		return 'US-SET-01';
	}
	if ( finding.check === 'forms' ) {
		return 'US-VIS-05';
	}
	if ( front && /navigation/.test( finding.signature ) ) {
		return 'US-NAV-14';
	}
	if ( /^image-|first-image/.test( finding.rule ) ) {
		return 'US-BLK-14';
	}

	return (
		{
			layout: 'US-NFR-01',
			accessibility: 'US-NFR-03',
			keyboard: 'US-NFR-03',
			motion: 'US-NFR-07',
			phone: 'US-NFR-04',
			states: 'US-NFR-05',
			performance: 'US-NFR-07',
		}[ finding.check ] || 'US-NFR-03'
	);
}

const readList = () => JSON.parse( fs.readFileSync( LIST, 'utf8' ) );

const readFindings = ( dir ) =>
	fs
		.readFileSync( path.join( dir, 'findings.jsonl' ), 'utf8' )
		.split( '\n' )
		.filter( Boolean )
		.map( ( line ) => JSON.parse( line ) );

const covers = ( entry, finding ) =>
	entry.check === finding.check &&
	entry.rule === finding.rule &&
	entry.signature === finding.signature &&
	( entry.screen === '*' || entry.screen === finding.screen );

/**
 * New entries for the findings no entry covers.
 * @param {Object[]} findings A run's findings.
 * @param {Object[]} existing The entries already in the list.
 * @param {string}   today    Today's date, YYYY-MM-DD.
 */
export function entriesFor( findings, existing, today ) {
	const uncovered = findings.filter(
		( finding ) => ! existing.some( ( entry ) => covers( entry, finding ) )
	);
	const groups = new Map();

	for ( const finding of uncovered ) {
		const key = `${ finding.check }|${ finding.rule }|${ finding.signature }`;
		const group = groups.get( key ) || { finding, screens: new Set() };
		group.screens.add( finding.screen );
		groups.set( key, group );
	}

	return [ ...groups.values() ].flatMap( ( { finding, screens } ) =>
		( screens.size >= SHARED ? [ '*' ] : [ ...screens ].sort() ).map(
			( screen ) => ( {
				check: finding.check,
				rule: finding.rule,
				screen,
				signature: finding.signature,
				since: today,
				story: storyFor( {
					...finding,
					screen: screen === '*' ? finding.screen : screen,
				} ),
				note:
					screen === '*'
						? `On ${ screens.size } screens, e.g. ${ finding.detail }`
						: finding.detail,
			} )
		)
	);
}

function record( dir ) {
	const list = readList();
	const added = entriesFor(
		readFindings( dir ),
		list.entries,
		new Date().toISOString().slice( 0, 10 )
	);
	list.entries = [ ...list.entries, ...added ].sort(
		( a, b ) =>
			a.story.localeCompare( b.story ) ||
			a.check.localeCompare( b.check ) ||
			a.screen.localeCompare( b.screen ) ||
			a.signature.localeCompare( b.signature )
	);
	fs.writeFileSync( LIST, JSON.stringify( list, null, '\t' ) + '\n' );
	process.stdout.write(
		`Added ${ added.length } known failure(s); the list holds ${ list.entries.length }.\n`
	);
}

function budget( dir ) {
	const file = JSON.parse( fs.readFileSync( BUDGET, 'utf8' ) );
	file.pages = JSON.parse(
		fs.readFileSync( path.join( dir, 'byte-budget.json' ), 'utf8' )
	);
	fs.writeFileSync( BUDGET, JSON.stringify( file, null, '\t' ) + '\n' );
	process.stdout.write(
		`Byte budget set for ${ Object.keys( file.pages ).length } page(s).\n`
	);
}

/** The list as the release notes print it: per story, how many, of what. */
function print() {
	const { entries } = readList();
	const byStory = new Map();

	for ( const entry of entries ) {
		byStory.set( entry.story, [
			...( byStory.get( entry.story ) || [] ),
			entry,
		] );
	}

	process.stdout.write(
		`### Known quality failures\n\n${ entries.length } known failure(s), each owned by the story that fixes it:\n\n`
	);
	process.stdout.write(
		'| Story | Count | Checks | Since |\n|---|---:|---|---|\n'
	);

	for ( const [ story, owned ] of [ ...byStory ].sort() ) {
		const checks = [
			...new Set(
				owned.map( ( entry ) => `${ entry.check }/${ entry.rule }` )
			),
		].join( ', ' );
		const since = owned.map( ( entry ) => entry.since ).sort()[ 0 ];
		process.stdout.write(
			`| ${ story } | ${ owned.length } | ${ checks } | ${ since } |\n`
		);
	}
}

// Run as a command, not when a test imports it (as sync-project-status.mjs does).
if (
	path.basename( process.argv[ 1 ] ?? '' ) === 'quality-known-failures.mjs'
) {
	const [ , , flag, dir = path.join( root, 'test-results/quality' ) ] =
		process.argv;

	if ( flag === '--record' ) {
		record( dir );
	} else if ( flag === '--budget' ) {
		budget( dir );
	} else {
		print();
	}
}
