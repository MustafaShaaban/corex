/* eslint-disable no-console -- this is a CLI evidence tool; the console IS its output. */
/**
 * Spec 076 evidence capture — what every CoreX admin surface says about time, before and after.
 *
 * Not a test. It screenshots each surface that shows a date, and — more usefully — extracts the
 * text those surfaces actually render, because the defect is a string and a screenshot of a string
 * is harder to diff than the string.
 *
 * The two-timezone comparison is the part a screenshot cannot show on its own: the same record,
 * the same moment, two browser timezones. Six surfaces currently answer differently, which is the
 * baseline for SC-003.
 *
 *   node tests/e2e/capture-datetime-evidence.mjs <outDir>
 *
 * Env: COREX_BASE_URL (default http://corex.local).
 */
import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const BASE_URL = process.env.COREX_BASE_URL || 'http://corex.local';
const STORAGE_STATE = path.join( 'tests', 'e2e', '.auth', 'admin.json' );
const OUT_DIR = process.argv[ 2 ] || 'specs/076-admin-datetime-foundation/evidence/before';

/**
 * Where a date is rendered today, and the selector that holds it.
 *
 * `probe` is deliberately narrow: it targets the element whose text is the date, so the captured
 * evidence is the defect rather than a page of chrome around it.
 */
const SURFACES = [
	{
		// The date is the LAST cell of the row (`<td>{ item.created_at }</td>`), after
		// identity / form / status / delivery / owner. Counting from the end rather than
		// the start, because a column added to the middle would silently re-aim the probe
		// at the wrong cell — which is exactly what the first run of this tool did.
		name: 'submissions-inbox',
		url: '/wp-admin/admin.php?page=corex-submissions',
		probe: '.corex-inbox__table tbody tr td:last-child',
	},
	{
		name: 'forms-flow-list',
		url: '/wp-admin/admin.php?page=corex-forms',
		probe: '.corex-flow-list time, .corex-flow-list__row time',
	},
	{
		name: 'email-studio-logs',
		url: '/wp-admin/admin.php?page=corex-email-studio',
		probe: '.corex-email-app__table-wrap table tbody tr td:nth-child(4)',
	},
	{
		name: 'operations-security-activity',
		url: '/wp-admin/admin.php?page=corex-operations-security',
		probe: '.corex-security__panel li small, .corex-security__lockouts li',
	},
	{
		name: 'notifications',
		url: '/wp-admin/admin.php?page=corex-notifications',
		probe: '.corex-notification-item time, .corex-notifications-screen time',
	},
	{
		name: 'overview',
		url: '/wp-admin/admin.php?page=corex-settings',
		probe: '.corex-overview__activity li',
	},
	{
		name: 'blog-pro',
		url: '/wp-admin/admin.php?page=corex-blog-pro',
		probe: '.corex-blog-pro__comment time, .corex-blog-pro time',
	},
];

/** Anything that looks like a machine timestamp a person should never be shown. */
const MACHINE_SHAPES = [
	/\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/, // ISO 8601 / SQL datetime
	/^\d{10}$/, // bare Unix seconds
];

await mkdir( OUT_DIR, { recursive: true } );

const browser = await chromium.launch();

/**
 * Read every rendered date on every surface, in one browser timezone.
 *
 * @param {string} timezoneId IANA zone to run the browser in.
 * @return {Promise<Object>} Surface name → the date strings it rendered.
 */
async function readAll( timezoneId ) {
	const context = await browser.newContext( {
		storageState: STORAGE_STATE,
		viewport: { width: 1440, height: 900 },
		timezoneId,
	} );
	const page = await context.newPage();
	const seen = {};

	for ( const surface of SURFACES ) {
		await page.goto( BASE_URL + surface.url );
		// The React screens mount after load; `networkidle` waits for the admin's own polling
		// too and made the first run take minutes. A short settle is enough to let a mount
		// finish, and an empty probe result is reported as empty rather than waited out.
		await page.waitForTimeout( 1200 );

		seen[ surface.name ] = await page
			.locator( surface.probe )
			.evaluateAll( ( nodes ) =>
				nodes
					.map( ( node ) => node.textContent.trim() )
					.filter( Boolean )
					.slice( 0, 5 )
			)
			.catch( () => [] );

		if ( timezoneId === 'UTC' ) {
			await page.screenshot( {
				path: path.join( OUT_DIR, `${ surface.name }.png` ),
				fullPage: true,
			} );
		}
	}

	await context.close();
	return seen;
}

const utc = await readAll( 'UTC' );
const tokyo = await readAll( 'Asia/Tokyo' );

const report = { capturedAt: new Date().toISOString(), baseUrl: BASE_URL, surfaces: {} };

for ( const surface of SURFACES ) {
	const inUtc = utc[ surface.name ] || [];
	const inTokyo = tokyo[ surface.name ] || [];

	report.surfaces[ surface.name ] = {
		rendered: inUtc,
		machineShaped: inUtc.filter( ( text ) =>
			MACHINE_SHAPES.some( ( shape ) => shape.test( text ) )
		),
		// The SC-003 baseline: a surface whose text changes with the READER's timezone is telling
		// two operators two different things about one event.
		followsBrowserTimezone:
			inUtc.length > 0 &&
			inUtc.length === inTokyo.length &&
			inUtc.some( ( text, index ) => text !== inTokyo[ index ] ),
		inTokyo,
	};
}

await writeFile(
	path.join( OUT_DIR, 'rendered-dates.json' ),
	JSON.stringify( report, null, '\t' ) + '\n',
	'utf8'
);

console.log( `\nEvidence written to ${ OUT_DIR }\n` );
for ( const [ name, data ] of Object.entries( report.surfaces ) ) {
	const flags = [
		data.machineShaped.length > 0
			? `${ data.machineShaped.length } machine-shaped`
			: '',
		data.followsBrowserTimezone ? 'FOLLOWS BROWSER TIMEZONE' : '',
	]
		.filter( Boolean )
		.join( ', ' );
	console.log(
		`  ${ name.padEnd( 32 ) } ${ data.rendered.length } dates${
			flags ? ` — ${ flags }` : ''
		}`
	);
	data.rendered.slice( 0, 2 ).forEach( ( text ) => console.log( `      "${ text }"` ) );
}
