/**
 * Spec 115, US-SAFE-15: the CoreX theme's front page can be read and its buttons used.
 *
 * Measured signed out on the front page, as a visitor meets it: the hero heading against the hero's
 * own background, each hero and call-to-action button against the section behind it, and that a
 * keyboard reaches each button, which goes to a real page and shows where focus is.
 *
 * Spec 134 (SC-005, DECISIONS #640): the hero is the dark band and the call to action the brass band,
 * in the default style and in the Dark variation (drawn as `theme-colours.js` says); the hero's
 * button is brass with a graphite label in both. `tests/Unit/Patterns/HeroContrastTest.php` works
 * the same out from the stylesheet and the palettes.
 *
 * The buttons go to the brand's primary action link or, without one, the site's contact page. The
 * spec makes a contact page when the site has none, and removes only what it made.
 */
const { test, expect } = require( '@playwright/test' );
const { STORAGE_STATE } = require( './global-setup' );
const { contrast } = require( './helpers' );
const { MODES, showDarkVariation } = require( './theme-colours' );

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

/**
 * The colours the front page draws for its hero and its call to action.
 *
 * @param {import('@playwright/test').Page} page The front page.
 * @return {Promise<Object>} Each section's ground and text, its heading and its button.
 */
function bands( page ) {
	return page.evaluate( () => {
		const read = ( section ) => {
			const heading = section.querySelector( 'h1, h2' );
			const button = section.querySelector( '.wp-block-button__link' );
			const style = ( element ) => window.getComputedStyle( element );
			return {
				ground: style( section ).backgroundColor,
				text: style( section ).color,
				heading: style( heading ).color,
				button: style( button ).backgroundColor,
				label: style( button ).color,
			};
		};
		const hero = document.querySelector(
			'main section.is-style-corex-band-dark'
		);
		const cta = document.querySelector(
			'main section.is-style-corex-band-brass'
		);
		return {
			hero: hero && read( hero ),
			cta: cta && read( cta ),
			heroHoldsTheHeading: !! hero?.querySelector( 'h1' ),
		};
	} );
}

for ( const mode of Object.keys( MODES ) ) {
	test( `the hero is a dark band with a brass button and a graphite label, and the call to action a brass band, in the ${ mode } style (SC-005)`, async ( {
		page,
	} ) => {
		const { preset, custom } = MODES[ mode ];
		await page.goto( '/' );
		if ( mode === 'dark' ) {
			await showDarkVariation( page );
		}
		const { hero, cta, heroHoldsTheHeading } = await bands( page );

		expect( heroHoldsTheHeading ).toBe( true );
		expect( hero ).toEqual( {
			ground: custom.band,
			text: custom[ 'band-ink' ],
			heading: custom[ 'band-ink' ],
			button: custom[ 'band-accent' ],
			label: custom.band,
		} );
		// Dark in both styles: white text on it reads at least 12:1, the band's graphite on its brass 4.5:1.
		expect(
			contrast( hero.ground, 'rgb(255, 255, 255)' )
		).toBeGreaterThanOrEqual( 12 );
		expect( contrast( hero.label, hero.button ) ).toBeGreaterThanOrEqual(
			4.5
		);
		expect( contrast( hero.button, hero.ground ) ).toBeGreaterThanOrEqual(
			3
		);

		expect( cta ).toEqual( {
			ground: preset.accent,
			text: custom[ 'on-accent' ],
			heading: custom[ 'on-accent' ],
			button: custom[ 'on-accent' ],
			label: custom[ 'band-ink' ],
		} );
		expect( contrast( cta.heading, cta.ground ) ).toBeGreaterThanOrEqual(
			4.5
		);
		expect( contrast( cta.button, cta.ground ) ).toBeGreaterThanOrEqual(
			3
		);
		expect( contrast( cta.label, cta.button ) ).toBeGreaterThanOrEqual(
			4.5
		);
	} );
}
