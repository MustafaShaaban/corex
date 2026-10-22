/**
 * The front-end budget (spec 116, US-NFR-07; Definition of Done item 10).
 *
 * Every published page and every theme template's address, as a visitor gets it, at 375 and
 * 1280px:
 *
 * - cumulative layout shift, from the first frame to the end of a scroll to the bottom, at most
 *   0.05;
 * - every image has a width and a height, or an aspect ratio;
 * - an image below the first screen loads lazily, and the first screen's main image does not;
 * - the bytes of CoreX's scripts and styles on the page, files and inline, within 10% of its
 *   budget in `byte-budget.json` (raising a budget is a recorded decision);
 * - a CoreX block's script or style loads only on a page that shows that block (principle VI).
 *
 * Every run writes the bytes it measured to test-results/quality/bytes.jsonl;
 * `node scripts/quality-known-failures.mjs --budget <dir>` makes them the budget.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( '@playwright/test' );

const { installQualityHelpers } = require( './in-page' );
const { assertNoNewFindings, OUTPUT, RECORDING } = require( './findings' );
const { site, PHONE, DESKTOP } = require( './site' );
const BUDGET = require( './byte-budget.json' ).pages;

const MAX_SHIFT = 0.05;
const OVER_BUDGET = 1.1;

const ADDRESSES = [
	...new Map(
		[
			...site.templates
				.filter( ( entry ) => entry.path && entry.status === 200 )
				.map( ( entry ) => entry.path ),
			...site.pages.map( ( entry ) => entry.path ),
		].map( ( address ) => [ address, address ] )
	).values(),
].sort();

/** In the page before its scripts: every layout shift not caused by input, added up. */
function watchShifts() {
	window.__corexShift = 0;
	window.__corexShifts = [];

	new PerformanceObserver( ( list ) => {
		for ( const entry of list.getEntries() ) {
			if ( ! entry.hadRecentInput ) {
				window.__corexShift += entry.value;
				window.__corexShifts.push(
					( entry.sources || [] )
						.map(
							( source ) =>
								source.node &&
								window.__corexQuality.signature( source.node )
						)
						.filter( Boolean )
						.join( ', ' )
				);
			}
		}
	} ).observe( { type: 'layout-shift', buffered: true } );
}

/** Images as the first screen shows them, before anything scrolls. */
function images() {
	const fold = window.innerHeight;
	const all = [ ...document.images ].filter(
		( image ) =>
			image.getBoundingClientRect().width > 0 || image.loading === 'lazy'
	);
	const inView = all.filter(
		( image ) => image.getBoundingClientRect().top < fold
	);
	const main = inView.sort(
		( a, b ) => b.width * b.height - a.width * a.height
	)[ 0 ];

	return all.map( ( image ) => {
		const style = window.getComputedStyle( image );
		return {
			signature: window.__corexQuality.signature( image ),
			file: ( image.currentSrc || image.src )
				.split( '?' )[ 0 ]
				.split( '/' )
				.pop(),
			sized:
				( image.hasAttribute( 'width' ) &&
					image.hasAttribute( 'height' ) ) ||
				( style.aspectRatio !== 'auto' && style.aspectRatio !== '' ),
			below: image.getBoundingClientRect().top >= fold,
			lazy: image.loading === 'lazy',
			main: image === main,
		};
	} );
}

/** CoreX's scripts and styles on the page, and the blocks they belong to. */
function corexAssets() {
	const isCorex = ( url ) =>
		/\/wp-content\/(plugins\/corex-[a-z-]+|themes\/corex)\//.test( url );
	const files = performance
		.getEntriesByType( 'resource' )
		.filter(
			( entry ) =>
				isCorex( entry.name ) && /\.(js|css)(\?|$)/.test( entry.name )
		)
		.map( ( entry ) => ( {
			name: entry.name.split( '?' )[ 0 ],
			bytes: entry.decodedBodySize,
		} ) );
	const inline = [
		...document.querySelectorAll(
			'style[id^="corex"], script[id^="corex"]'
		),
	].map( ( element ) => ( {
		name: `#${ element.id }`,
		bytes: element.textContent.length,
	} ) );

	const blockOf = ( name ) =>
		( name.match( /\/build\/blocks\/([a-z0-9-]+)\// ) ||
			name.match(
				/^#corex-([a-z0-9-]+?)-(?:view-)?(?:style|script)(?:-inline)?-(?:css|js)$/
			) ||
			[] )[ 1 ];
	const blockShown = ( block ) => {
		const name = block.replace( /^corex-/, '' );
		return [ ...document.querySelectorAll( '[class]' ) ].some(
			( element ) =>
				[ ...element.classList ].some(
					( token ) =>
						token === `wp-block-corex-${ name }` ||
						token === `corex-${ name }` ||
						token.startsWith( `corex-${ name }__` ) ||
						token.startsWith( `corex-${ name }--` )
				)
		);
	};

	const assets = [ ...files, ...inline ];

	return {
		bytes: assets.reduce( ( sum, asset ) => sum + asset.bytes, 0 ),
		assets,
		strays: assets
			.map( ( asset ) => ( { ...asset, block: blockOf( asset.name ) } ) )
			.filter( ( asset ) => asset.block && ! blockShown( asset.block ) ),
	};
}

/**
 * Every page's measured bytes, one JSON line per test, for `quality-known-failures.mjs --budget`.
 *
 * @param {string} address The page's address.
 * @param {number} bytes   CoreX's script and style bytes on it.
 */
function noteBytes( address, bytes ) {
	fs.mkdirSync( OUTPUT, { recursive: true } );
	fs.appendFileSync(
		path.join( OUTPUT, 'bytes.jsonl' ),
		JSON.stringify( { page: address, bytes } ) + '\n'
	);
}

test.describe( 'front-end pages, as a visitor sees them', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	for ( const address of ADDRESSES ) {
		for ( const viewport of [ PHONE, DESKTOP ] ) {
			test( `${ address } at ${ viewport.width }px stays still, sized, lazy below the fold and within its budget`, async ( {
				page,
			} ) => {
				await page.addInitScript( installQualityHelpers );
				await page.addInitScript( watchShifts );
				await page.setViewportSize( viewport );

				const response = await page.goto( address, {
					waitUntil: 'load',
				} );
				expect(
					response?.status(),
					`${ address } could not be measured`
				).toBe( 200 );
				await page
					.waitForLoadState( 'networkidle', { timeout: 10_000 } )
					.catch( () => {} );

				const pictured = await page.evaluate( images );

				await page.evaluate( async () => {
					for (
						let y = 0;
						y < document.documentElement.scrollHeight;
						y += 400
					) {
						window.scrollTo( 0, y );
						await new Promise( ( resolve ) =>
							setTimeout( resolve, 60 )
						);
					}
				} );
				await page.waitForTimeout( 500 );

				const shift = await page.evaluate( () => ( {
					value: window.__corexShift,
					sources: window.__corexShifts,
				} ) );
				const assets = await page.evaluate( corexAssets );
				const cell = `${ viewport.width }-light-ltr`;
				const finding = ( rule, signature, detail ) => ( {
					screen: `page:${ address }`,
					cell,
					check: 'performance',
					rule,
					signature,
					detail,
				} );
				const findings = [];

				if ( shift.value > MAX_SHIFT ) {
					findings.push(
						finding(
							'layout-shift-over-0.05',
							'document',
							`cumulative layout shift ${ shift.value.toFixed( 3 ) } (${ [ ...new Set( shift.sources ) ].join( '; ' ) })`
						)
					);
				}

				for ( const image of pictured ) {
					if ( ! image.sized ) {
						findings.push(
							finding(
								'image-without-dimensions',
								`${ image.signature } ${ image.file }`,
								`${ image.file } has no width and height, and no aspect ratio`
							)
						);
					}
					if ( image.below && ! image.lazy ) {
						findings.push(
							finding(
								'image-below-fold-not-lazy',
								`${ image.signature } ${ image.file }`,
								`${ image.file } is below the first screen and loads at once`
							)
						);
					}
					if ( image.main && image.lazy ) {
						findings.push(
							finding(
								'first-image-lazy',
								`${ image.signature } ${ image.file }`,
								`${ image.file } is the first screen's main image and loads lazily`
							)
						);
					}
				}

				for ( const stray of assets.strays ) {
					findings.push(
						finding(
							'block-asset-without-block',
							stray.name,
							`${ stray.name } (block ${ stray.block }) loads on a page that does not show it`
						)
					);
				}

				noteBytes( address, assets.bytes );
				const budget = BUDGET[ address ];

				// Soft, so that a page over its budget still reports its other findings in the same run.
				if ( ! RECORDING && budget === undefined ) {
					expect
						.soft(
							budget,
							`${ address } has no byte budget in tests/e2e/quality/byte-budget.json (it loads ${ assets.bytes } bytes of CoreX assets)`
						)
						.toBeDefined();
				} else if ( ! RECORDING ) {
					expect
						.soft(
							assets.bytes,
							`${ address } loads ${ assets.bytes } bytes of CoreX scripts and styles, over its budget of ${ budget } by more than 10%: ${ assets.assets.map( ( asset ) => `${ asset.name } ${ asset.bytes }` ).join( ', ' ) }`
						)
						.toBeLessThanOrEqual(
							Math.round( budget * OVER_BUDGET )
						);
				}

				assertNoNewFindings( expect, findings );
			} );
		}
	}
} );
