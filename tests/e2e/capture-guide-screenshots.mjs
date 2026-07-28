/* eslint-disable no-console -- this is a CLI capture tool; the console IS its output. */
/**
 * Capture the screenshots the guides reference (spec 084, FR-015).
 *
 * The contract that makes this worth having: **it fails loudly.** A guide names a shot by capture
 * id, this script knows how to take each id, and any id a guide asks for that this script cannot
 * produce is a non-zero exit with the id named. Documentation whose pictures quietly stop matching
 * the product is worse than documentation with no pictures, because a reader trusts a screenshot.
 *
 * Built on the harness spec 076 established and spec 083 reused: a standalone node script
 * driving chromium, with the shared admin storage state.
 *
 *   node tests/e2e/capture-guide-screenshots.mjs
 */
import { chromium } from '@playwright/test';
import { existsSync, mkdirSync } from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const REPO = path.resolve( HERE, '../..' );
const OUT_DIR = path.join( REPO, 'addons/corex-guides/assets/screenshots' );
const STORAGE_STATE = path.join( HERE, '.auth', 'admin.json' );

const BASE = process.env.COREX_BASE_URL || 'http://corex.local';

/**
 * Every shot a guide can reference, by capture id.
 *
 * `probe` is what must be on the page before the shutter opens. It is the loud-failure mechanism:
 * a screen that renamed the control, or an add-on that stopped registering it, produces a missing
 * probe rather than a screenshot of the wrong thing.
 */
const SHOTS = [
	{
		id: 'submissions-inbox',
		url: '/wp-admin/admin.php?page=corex-submissions',
		probe: '.corex-admin__content',
	},
	{
		id: 'email-delivery',
		url: '/wp-admin/admin.php?page=corex-email-studio&tab=delivery',
		probe: '.corex-admin__content',
	},
];

if ( ! existsSync( STORAGE_STATE ) ) {
	console.error(
		`No admin session at ${ STORAGE_STATE }. Run \`npm run test:e2e\` once to create it.`
	);
	process.exit( 1 );
}

mkdirSync( OUT_DIR, { recursive: true } );

const browser = await chromium.launch();
const context = await browser.newContext( {
	baseURL: BASE,
	storageState: STORAGE_STATE,
	viewport: { width: 1280, height: 800 },
	// Fixed, so a shot taken on a retina laptop and one taken in CI are the same file.
	deviceScaleFactor: 1,
} );
const page = await context.newPage();

const failures = [];

for ( const shot of SHOTS ) {
	const response = await page.goto( shot.url ).catch( () => null );

	if ( ! response || response.status() >= 400 ) {
		failures.push(
			`${ shot.id }: ${ shot.url } answered ${
				response ? response.status() : 'nothing'
			}`
		);
		continue;
	}

	const found = await page
		.locator( shot.probe )
		.first()
		.waitFor( { state: 'visible', timeout: 10000 } )
		.then( () => true )
		.catch( () => false );

	if ( ! found ) {
		failures.push(
			`${ shot.id }: ${ shot.url } loaded, but "${ shot.probe }" was not there — the screen has changed`
		);
		continue;
	}

	const file = path.join( OUT_DIR, `${ shot.id }.png` );
	await page.screenshot( { path: file } );
	console.log( `captured  ${ shot.id }  <- ${ shot.url }` );
}

await browser.close();

if ( failures.length > 0 ) {
	console.error( `\n${ failures.length } shot(s) could not be captured:` );
	failures.forEach( ( failure ) => console.error( `  ${ failure }` ) );
	console.error(
		'\nEvery id above is referenced by a guide. Fix the screen or the guide — do not commit stale images.'
	);
	process.exit( 1 );
}

console.log( `\n${ SHOTS.length } screenshot(s) written to ${ OUT_DIR }` );
