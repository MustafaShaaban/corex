/**
 * Captures every theme template at 1280 and 375px (spec 121, US-BRD-03, TC-BRD-03-05).
 *
 *   node specs/121-tokens-and-type/evidence/capture-templates.js <base-url> <out-dir> [lang]
 *
 * The addresses come from tests/e2e/quality/templates.json, on a site prepared by
 * tests/e2e/quality/seed-quality-site.php. Run it once on the base branch and once on this one,
 * into `before/` and `after/`, and compare. Signed out: what a visitor sees.
 */
const path = require( 'node:path' );
const fs = require( 'node:fs' );
const { chromium } = require( '@playwright/test' );

const [ base, out ] = process.argv.slice( 2 );
const WIDTHS = [ 1280, 375 ];

async function main() {
	if ( ! base || ! out ) {
		throw new Error( 'Usage: capture-templates.js <base-url> <out-dir>' );
	}

	const list = require( path.resolve(
		__dirname,
		'../../../tests/e2e/quality/templates.json'
	) ).templates;
	fs.mkdirSync( out, { recursive: true } );

	const browser = await chromium.launch();

	for ( const width of WIDTHS ) {
		const page = await browser.newPage( {
			viewport: { width, height: 900 },
			reducedMotion: 'reduce',
		} );

		for ( const [ name, { path: address } ] of Object.entries( list ) ) {
			await page.goto( base + address, { waitUntil: 'networkidle' } );
			await page.evaluate( () => document.fonts.ready );
			await page.screenshot( {
				path: path.join( out, `${ name }-${ width }.png` ),
				fullPage: true,
			} );
		}

		await page.close();
	}

	await browser.close();
}

main().catch( ( error ) => {
	process.stderr.write( `${ error.message }\n` );
	process.exit( 1 );
} );
