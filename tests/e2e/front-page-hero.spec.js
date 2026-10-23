/**
 * Spec 115, US-SAFE-15: the CoreX theme's front page can be read and its buttons used.
 *
 * Measured signed out on the front page, as a visitor meets it: the hero heading against the hero's
 * own background, each hero and call-to-action button against the section behind it, and that a
 * keyboard reaches each button, which goes to a real page and shows where focus is. The dark palette
 * is measured from the palettes themselves by `tests/Unit/Patterns/HeroContrastTest.php`, since it is
 * a style variation a site chooses and not a mode of the page.
 *
 * The buttons go to the brand's primary action link or, without one, the site's contact page. The
 * spec makes a contact page when the site has none, and removes only what it made.
 */
const { test, expect } = require( '@playwright/test' );
const { STORAGE_STATE } = require( './global-setup' );

const NO_SESSION = { cookies: [], origins: [] };

let madeContact = 0;

test.beforeAll( async ( { browser } ) => {
	const context = await browser.newContext( { storageState: STORAGE_STATE } );
	const page = await context.newPage();
	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate( () => window.wpApiSettings.nonce );
	const found = await (
		await page.request.get(
			'/wp-json/wp/v2/pages?slug=contact&status=publish',
			{
				headers: { 'X-WP-Nonce': nonce },
			}
		)
	).json();
	if ( found.length === 0 ) {
		const made = await page.request.post( '/wp-json/wp/v2/pages', {
			headers: { 'X-WP-Nonce': nonce },
			data: { title: 'Contact', slug: 'contact', status: 'publish' },
		} );
		madeContact = ( await made.json() ).id;
	}
	await context.close();
} );

test.afterAll( async ( { browser } ) => {
	if ( ! madeContact ) {
		return;
	}
	const context = await browser.newContext( { storageState: STORAGE_STATE } );
	const page = await context.newPage();
	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate( () => window.wpApiSettings.nonce );
	await page.request.delete(
		`/wp-json/wp/v2/pages/${ madeContact }?force=true`,
		{
			headers: { 'X-WP-Nonce': nonce },
		}
	);
	await context.close();
} );

test.use( { storageState: NO_SESSION } );

/**
 * What the browser drew for an element, as WCAG relative luminance: its text colour, the first
 * background that is not transparent from the element outwards, and its section's own background.
 *
 * @param {import('@playwright/test').Locator} locator The element.
 * @return {Promise<{text: number, background: number, sectionOwn: number}>} Luminances.
 */
function measure( locator ) {
	return locator.evaluate( ( element ) => {
		const channel = ( value ) => {
			const c = value / 255;
			return c <= 0.03928 ? c / 12.92 : ( ( c + 0.055 ) / 1.055 ) ** 2.4;
		};
		const luminance = ( css ) => {
			const [ r, g, b ] = css.match( /\d+(\.\d+)?/g ).map( Number );
			return (
				0.2126 * channel( r ) +
				0.7152 * channel( g ) +
				0.0722 * channel( b )
			);
		};
		const opaque = ( css ) =>
			! /rgba\([^)]*,\s*0\)|transparent/.test( css );
		const backgroundOf = ( start ) => {
			for ( let node = start; node; node = node.parentElement ) {
				const background =
					window.getComputedStyle( node ).backgroundColor;
				if ( opaque( background ) ) {
					return background;
				}
			}
			return 'rgb(255, 255, 255)';
		};
		return {
			text: luminance( window.getComputedStyle( element ).color ),
			background: luminance( backgroundOf( element ) ),
			sectionOwn: luminance(
				backgroundOf( element.closest( 'section' ) )
			),
		};
	} );
}

const ratio = ( a, b ) =>
	( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 );

test( 'the hero heading can be read against the hero (at least 4.5:1)', async ( {
	page,
} ) => {
	await page.goto( '/' );
	const heading = page.locator( 'main section h1' ).first();
	await expect( heading ).toBeVisible();

	const { text, background } = await measure( heading );

	expect( ratio( text, background ) ).toBeGreaterThanOrEqual( 4.5 );
} );

test( 'each hero and call-to-action button stands apart from its section, and goes to a real page', async ( {
	page,
} ) => {
	await page.goto( '/' );
	const buttons = page.locator( 'main .corex-action-link a' );
	await expect( buttons ).toHaveCount( 2 );

	for ( const button of await buttons.all() ) {
		expect(
			await button.evaluate( ( el ) => el.closest( 'section' ) !== null )
		).toBe( true );
		const { background, sectionOwn } = await measure( button );
		expect( ratio( background, sectionOwn ) ).toBeGreaterThanOrEqual( 3 );

		const href = await button.getAttribute( 'href' );
		expect( href ).toMatch( /^https?:\/\// );
		expect( ( await page.request.get( href ) ).status() ).toBe( 200 );
	}
} );

test( 'a keyboard reaches each button, which shows where focus is', async ( {
	page,
} ) => {
	await page.goto( '/' );
	const buttons = await page.locator( 'main .corex-action-link a' ).all();
	expect( buttons ).toHaveLength( 2 );

	for ( const button of buttons ) {
		const label = await button.textContent();
		let reached = false;
		for ( let press = 0; press < 60 && ! reached; press++ ) {
			await page.keyboard.press( 'Tab' );
			reached = await button.evaluate(
				( el ) => el === el.ownerDocument.activeElement
			);
		}
		expect( reached, `Tab reaches "${ label }"` ).toBe( true );

		const ring = await button.evaluate( ( el ) => {
			const style = window.getComputedStyle( el );
			return {
				style: style.outlineStyle,
				width: parseFloat( style.outlineWidth ),
			};
		} );
		expect( ring.style ).not.toBe( 'none' );
		expect( ring.width ).toBeGreaterThan( 0 );
	}
} );

test( 'shows a visitor no sample figures or quote on the front page', async ( {
	page,
} ) => {
	await page.goto( '/' );

	await expect( page.locator( 'body' ) ).not.toContainText(
		'Client satisfaction'
	);
	await expect( page.locator( 'body' ) ).not.toContainText(
		'Sample: replace before publishing'
	);
	expect( await page.content() ).not.toContain( 'href="#"' );
} );
