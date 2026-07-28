/* eslint-disable no-console -- this is a CLI acceptance tool; the console IS its output. */
/**
 * The denied surface's acceptance matrix (spec 079, T060).
 *
 * RTL, 375px, 200% zoom, light and dark. **It measures rather than just photographs**: the failure
 * this repository keeps meeting is a one-pixel horizontal overflow that no screenshot review has
 * ever caught, and that is still open on `corex-access`. Every cell asserts
 * `scrollWidth <= clientWidth` and the run exits non-zero if any cell overflows.
 *
 * Screenshots are captured for the four corners of the matrix — enough to review the design, not so
 * many that nobody looks at them.
 *
 *   node tests/e2e/capture-denied-acceptance.mjs specs/079-admin-errors-access-request/evidence/after
 */
import { chromium } from '@playwright/test';
import { mkdirSync } from 'fs';
import path from 'path';

const BASE = process.env.COREX_BASE_URL || 'http://corex.local';
const USER = process.env.COREX_REQUESTER_USER || 'corex-requester';
const PASS = process.env.COREX_REQUESTER_PASS || 'CorexE2E!requester1';
const DENIED = '/wp-admin/admin.php?page=corex-forms';

const OUT_DIR = process.argv[ 2 ];
if ( ! OUT_DIR ) {
	console.error(
		'usage: node tests/e2e/capture-denied-acceptance.mjs <output-dir>'
	);
	process.exit( 1 );
}

/** Widths that matter: the narrowest phone we support, and a desktop. */
const WIDTHS = [
	{ name: '375', width: 375, height: 720 },
	{ name: '1280', width: 1280, height: 800 },
];

/** 200% zoom is expressed as deviceScaleFactor-independent text scaling via CSS zoom. */
const ZOOMS = [ 1, 2 ];
const DIRECTIONS = [ 'ltr', 'rtl' ];
const APPEARANCES = [ 'light', 'dark' ];

/** The corners worth photographing. Everything is measured; these are the ones a human reviews. */
const PHOTOGRAPH = new Set( [
	'375-rtl-dark-2x',
	'375-ltr-light-1x',
	'1280-rtl-light-1x',
	'1280-ltr-dark-2x',
] );

mkdirSync( OUT_DIR, { recursive: true } );

const browser = await chromium.launch();
const failures = [];
let measured = 0;

for ( const size of WIDTHS ) {
	const context = await browser.newContext( {
		baseURL: BASE,
		viewport: { width: size.width, height: size.height },
	} );
	const page = await context.newPage();

	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', USER );
	await page.fill( '#user_pass', PASS );
	await Promise.all( [
		page.waitForNavigation().catch( () => {} ),
		page.click( '#wp-submit' ),
	] );

	for ( const direction of DIRECTIONS ) {
		for ( const appearance of APPEARANCES ) {
			for ( const zoom of ZOOMS ) {
				const cell = `${ size.name }-${ direction }-${ appearance }-${ zoom }x`;

				await page.goto( DENIED );

				await page.evaluate(
					( [ dir, mode, scale ] ) => {
						document.documentElement.setAttribute( 'dir', dir );
						document.body.classList.remove(
							'corex-appearance-light',
							'corex-appearance-dark'
						);
						document.body.classList.add(
							`corex-appearance-${ mode }`
						);
						// Text zoom rather than a device pixel ratio: 200% zoom is what a person
						// with low vision actually does, and it is what breaks a layout.
						document.documentElement.style.fontSize = `${
							100 * scale
						}%`;
					},
					[ direction, appearance, zoom ]
				);

				await page.waitForTimeout( 120 );

				const overflow = await page.evaluate( () => {
					const el = document.documentElement;
					return {
						scrollWidth: el.scrollWidth,
						clientWidth: el.clientWidth,
					};
				} );

				measured++;

				if ( overflow.scrollWidth > overflow.clientWidth ) {
					failures.push(
						`${ cell }: scrollWidth ${
							overflow.scrollWidth
						} > clientWidth ${ overflow.clientWidth } (+${
							overflow.scrollWidth - overflow.clientWidth
						}px)`
					);
				}

				if ( PHOTOGRAPH.has( cell ) ) {
					await page.screenshot( {
						path: path.join( OUT_DIR, `denied-${ cell }.png` ),
						fullPage: true,
					} );
					console.log( `captured  denied-${ cell }.png` );
				}
			}
		}
	}

	await context.close();
}

await browser.close();

console.log( `\nmeasured ${ measured } cells` );

if ( failures.length > 0 ) {
	console.error( `\n${ failures.length } cell(s) scroll sideways:` );
	failures.forEach( ( f ) => console.error( `  ${ f }` ) );
	process.exit( 1 );
}

console.log( 'no cell scrolls sideways' );
