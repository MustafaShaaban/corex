/* eslint-disable no-console -- this is a CLI evidence tool; the console IS its output. */
/**
 * Capture the admin error surfaces as a real low-privilege user (spec 083).
 *
 * A standalone script rather than a test, following the harness spec 076 established in
 * `capture-datetime-evidence.mjs`: tests assert, captures record. Run it with the output directory
 * as the first argument:
 *
 *   node tests/e2e/capture-error-evidence.mjs specs/083-admin-error-surface/evidence/after
 */
import { chromium } from '@playwright/test';
import { mkdirSync } from 'fs';
import path from 'path';

const BASE = process.env.COREX_BASE_URL || 'http://corex.local';
const USER = process.env.COREX_REQUESTER_USER || 'corex-requester';
const PASS = process.env.COREX_REQUESTER_PASS || 'CorexE2E!requester1';

const OUT_DIR = process.argv[ 2 ];
if ( ! OUT_DIR ) {
	console.error(
		'usage: node tests/e2e/capture-error-evidence.mjs <output-dir>'
	);
	process.exit( 1 );
}

const SURFACES = [
	[ 'denied-corex-screen', '/wp-admin/admin.php?page=corex-forms' ],
	[ 'denied-corex-careers', '/wp-admin/edit.php?post_type=corex_job' ],
	[ 'denied-core-plugins', '/wp-admin/plugins.php' ],
	[ 'denied-core-users', '/wp-admin/users.php' ],
	[ 'not-found-corex', '/wp-admin/admin.php?page=corex-nonexistent' ],
];

mkdirSync( OUT_DIR, { recursive: true } );

const browser = await chromium.launch();
const context = await browser.newContext( {
	baseURL: BASE,
	viewport: { width: 1280, height: 800 },
} );
const page = await context.newPage();

await page.goto( '/wp-login.php' );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await Promise.all( [
	page.waitForNavigation().catch( () => {} ),
	page.click( '#wp-submit' ),
] );

if ( page.url().includes( 'wp-login.php' ) ) {
	console.error(
		`could not sign in as ${ USER }; captured nothing rather than capturing the login page`
	);
	await browser.close();
	process.exit( 1 );
}

for ( const [ name, url ] of SURFACES ) {
	const response = await page.goto( url );
	const file = path.join( OUT_DIR, `${ name }.png` );
	await page.screenshot( { path: file, fullPage: true } );
	console.log( `${ response.status() }  ${ name }  <- ${ url }` );
}

await browser.close();
