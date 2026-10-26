/**
 * Reads what the front page renders (spec 121): spacing, font-size and shadow presets, heading
 * sizes, a button's radius and font, and which font files loaded.
 *
 *   node specs/121-tokens-and-type/evidence/probe-front.js <url>
 */
const { chromium } = require( '@playwright/test' );

( async () => {
	const browser = await chromium.launch();
	const page = await browser.newPage( { viewport: { width: 1280, height: 900 } } );
	const fonts = [];
	page.on( 'response', ( response ) => {
		if ( response.url().endsWith( '.woff2' ) ) {
			fonts.push( response.url().split( '/' ).pop() );
		}
	} );
	await page.goto( process.argv[ 2 ], { waitUntil: 'networkidle' } );
	const result = await page.evaluate( () => {
		const root = getComputedStyle( document.documentElement );
		const presets = {};
		for ( const sheet of document.styleSheets ) {
			let rules;
			try {
				rules = sheet.cssRules;
			} catch {
				continue;
			}
			for ( const rule of rules ) {
				if ( rule.selectorText === ':root' || rule.selectorText === 'body' ) {
					for ( const name of rule.style ) {
						if ( /^--wp--preset--(spacing|font-size|shadow)--/.test( name ) ) {
							presets[ name ] = root.getPropertyValue( name ).trim();
						}
					}
				}
			}
		}
		const read = ( selector ) => {
			const element = document.querySelector( selector );
			if ( ! element ) {
				return null;
			}
			const style = getComputedStyle( element );
			return {
				size: style.fontSize,
				weight: style.fontWeight,
				spacing: style.letterSpacing,
				lineHeight: style.lineHeight,
				family: style.fontFamily,
				radius: style.borderRadius,
				transition: style.transitionDuration,
			};
		};
		return {
			presets,
			h1: read( 'h1' ),
			h2: read( 'h2' ),
			h3: read( 'h3' ),
			p: read( 'p' ),
			button: read( '.wp-element-button, .wp-block-button__link' ),
			label: read( 'label' ),
			nav: read( '.wp-block-navigation a' ),
		};
	} );
	console.log( JSON.stringify( { ...result, fonts }, null, 1 ) );
	await browser.close();
} )();
