/**
 * Spec 134, slice 3 (US-BRD-05, FR-020, FR-022, SC-006, SC-007): the logo set as the browser draws it.
 *
 * - The login, in each CoreX admin appearance (Dark, Light, System) on a dark and on a light
 *   computer: the lockup it draws suits the ground behind it, and a pixel sampled inside each of the
 *   four corner modules differs from that ground by at least 3:1 (SC-006), as does "Core". "Core" and
 *   the corners are the neutral colour, the centre and the "x" brass (FR-018). The centre, the "x"
 *   and the divider are measured and attached to the result, not held to 3:1: the light brass reads
 *   about 2.95 to 3.1:1 as the glow behind it moves, and the approved artboard draws the divider
 *   softer (a logo carries no WCAG contrast requirement).
 * - The login's ambient glow and grid stop under reduced motion, and the glow is centred in both
 *   directions (constitution, principle VIII).
 * - A logo set in CoreX Settings, Branding, replaces the lockup in every appearance, and keeps the
 *   name WordPress gives the link; the CoreX lockup is named "CoreX" (TC-BRD-05-06).
 * - The front page under the CoreX theme links the theme's favicon when the site has no site icon,
 *   and only the site's icon when it has one; under another theme CoreX links no favicon (SC-007).
 *
 * Each choice is made for one request by the `corex-e2e-logo-set.php` fixture, so the site's saved
 * appearance, logo, icon and theme are never changed. ENVIRONMENT-GATED: needs the site at
 * COREX_BASE_URL with the fixture in its must-use plugins.
 */
const { test, expect } = require( '@playwright/test' );

const { contrast, LOGIN_PATHS } = require( './helpers' );

const VISITOR = { cookies: [], origins: [] };

/** The ground behind the logo, for each lockup, as the approved artboard draws it (FR-019). */
const LOCKUPS = {
	dark: {
		file: 'corex-lockup-dark.svg',
		neutral: [ 244, 245, 247 ],
		brass: [ 201, 162, 94 ],
	},
	light: {
		file: 'corex-lockup-light.svg',
		neutral: [ 20, 21, 26 ],
		brass: [ 173, 134, 67 ],
	},
};

/** A site path the fixture answers as a site logo or a site icon: a file WordPress ships. */
const SITE_IMAGE = '/wp-includes/images/w-logo-blue.png';

const rgb = ( [ red, green, blue ] ) => `rgb(${ red }, ${ green }, ${ blue })`;

/**
 * Give the next requests a fixture choice.
 *
 * @param {import('@playwright/test').BrowserContext} context The context.
 * @param {string}                                    baseURL The site.
 * @param {Object<string,string>}                     choices Cookie names, without the prefix, to values.
 */
async function choose( context, baseURL, choices ) {
	await context.addCookies(
		Object.entries( choices ).map( ( [ name, value ] ) => ( {
			name: `corex_e2e_${ name }`,
			value,
			url: baseURL,
		} ) )
	);
}

/**
 * Open the login form on whichever address serves it.
 *
 * @param {import('@playwright/test').Page} page The page.
 */
async function openLogin( page ) {
	for ( const loginPath of LOGIN_PATHS ) {
		await page.goto( loginPath ).catch( () => {} );
		if ( await page.locator( '#user_login' ).isVisible() ) {
			return;
		}
	}
	throw new Error( 'No address served the login form.' );
}

/**
 * Read the login's logo: the file it draws, where each part of that file falls in the logo's box,
 * and the box's own size.
 *
 * @param {import('@playwright/test').Page} page The login.
 * @return {Promise<Object>} The logo's file, box and parts, in CSS pixels from the box's corner.
 */
function readLogo( page ) {
	return page.evaluate( async () => {
		const link = document.querySelector( '#login h1 a' );
		const image = window.getComputedStyle( link ).backgroundImage;
		const url = ( image.match( /url\("?(.*?)"?\)$/ ) || [] )[ 1 ] || '';
		const box = link.getBoundingClientRect();
		const result = {
			url,
			name: link.textContent.trim(),
			width: box.width,
			height: box.height,
			parts: null,
		};

		if ( ! url.endsWith( '.svg' ) ) {
			return result;
		}

		// Draw the file at its own size, to read where each shape lies in its units.
		const holder = document.createElement( 'div' );
		holder.style.cssText =
			'position:absolute;inset-block-start:0;inset-inline-start:0;visibility:hidden';
		holder.innerHTML = await ( await fetch( url ) ).text();
		document.body.append( holder );
		const svg = holder.querySelector( 'svg' );
		const [ , , unitsWide, unitsHigh ] = svg
			.getAttribute( 'viewBox' )
			.split( /\s+/ )
			.map( Number );
		svg.setAttribute( 'width', unitsWide );
		svg.setAttribute( 'height', unitsHigh );
		const origin = svg.getBoundingClientRect();
		const place = ( element ) => {
			const rect = element.getBoundingClientRect();

			return {
				x: rect.left - origin.left,
				y: rect.top - origin.top,
				width: rect.width,
				height: rect.height,
			};
		};
		const rects = [ ...svg.querySelectorAll( 'rect' ) ];
		const modules = rects.filter(
			( rect ) => rect.getAttribute( 'width' ) === '12'
		);
		const centre = modules.find(
			( rect ) => rect.getAttribute( 'x' ) === '18'
		);
		const [ core, x ] = [ ...svg.querySelectorAll( 'path' ) ].map( place );
		const divider = rects.find(
			( rect ) => rect.getAttribute( 'width' ) === '1.5'
		);

		// `background-size: contain`, centred: the file is as tall as the box.
		const scale = box.height / unitsHigh;
		const left = ( box.width - unitsWide * scale ) / 2;
		const toBox = ( shape ) => ( {
			x: left + shape.x * scale,
			y: shape.y * scale,
			width: shape.width * scale,
			height: shape.height * scale,
		} );
		result.parts = {
			corners: modules
				.filter( ( rect ) => rect !== centre )
				.map( ( rect ) => toBox( place( rect ) ) ),
			centre: toBox( place( centre ) ),
			divider: divider ? toBox( place( divider ) ) : null,
			core: toBox( core ),
			x: toBox( x ),
			drawn: { x: left, width: unitsWide * scale },
		};
		holder.remove();

		return result;
	} );
}

/**
 * The pixels of the logo's box as the screen shows them.
 *
 * @param {import('@playwright/test').Page} page The login.
 * @return {Promise<{width: number, height: number, ratio: number, data: number[]}>} RGBA pixels.
 */
async function logoPixels( page ) {
	const png = await page.locator( '#login h1 a' ).screenshot();

	return page.evaluate( async ( encoded ) => {
		const picture = new window.Image();
		picture.src = `data:image/png;base64,${ encoded }`;
		await picture.decode();
		const canvas = document.createElement( 'canvas' );
		canvas.width = picture.naturalWidth;
		canvas.height = picture.naturalHeight;
		const context = canvas.getContext( '2d' );
		context.drawImage( picture, 0, 0 );
		const box = document
			.querySelector( '#login h1 a' )
			.getBoundingClientRect();

		return {
			width: canvas.width,
			height: canvas.height,
			ratio: canvas.width / box.width,
			data: [
				...context.getImageData( 0, 0, canvas.width, canvas.height )
					.data,
			],
		};
	}, png.toString( 'base64' ) );
}

/**
 * Sample the logo: the ground beside the drawing, and inside each part the pixel that stands out
 * most from it (inside a module or a letter's stroke that is the part's own colour).
 *
 * @param {Object} logo   What `readLogo` answered.
 * @param {Object} pixels What `logoPixels` answered.
 * @return {Object} Each part's colour, as `[r, g, b]`, and the ground's.
 */
function sample( logo, pixels ) {
	const at = ( x, y ) => {
		const offset = ( y * pixels.width + x ) * 4;

		return pixels.data.slice( offset, offset + 3 );
	};
	// The ground: halfway between the box's start and the drawing, at mid height.
	const groundX = Math.round( ( logo.parts.drawn.x / 2 ) * pixels.ratio );
	const ground = at(
		groundX,
		Math.round( ( logo.height / 2 ) * pixels.ratio )
	);
	const standOut = ( part ) => {
		let best = null;
		let bestRatio = 0;
		const x0 = Math.ceil( part.x * pixels.ratio );
		const y0 = Math.ceil( part.y * pixels.ratio );
		const x1 = Math.floor( ( part.x + part.width ) * pixels.ratio );
		const y1 = Math.floor( ( part.y + part.height ) * pixels.ratio );

		for ( let y = y0; y < y1; y++ ) {
			for ( let x = x0; x < x1; x++ ) {
				const pixel = at( x, y );
				const ratio = contrast( rgb( pixel ), rgb( ground ) );
				if ( ratio > bestRatio ) {
					best = pixel;
					bestRatio = ratio;
				}
			}
		}

		return best;
	};
	const middle = ( part ) =>
		at(
			Math.round( ( part.x + part.width / 2 ) * pixels.ratio ),
			Math.round( ( part.y + part.height / 2 ) * pixels.ratio )
		);

	return {
		ground,
		corners: logo.parts.corners.map( middle ),
		centre: middle( logo.parts.centre ),
		divider: logo.parts.divider ? standOut( logo.parts.divider ) : null,
		core: standOut( logo.parts.core ),
		// The right half of the "x": its left edge can reach under the "e" (the word is tracked
		// tight), and the "e" is the neutral.
		x: standOut( {
			...logo.parts.x,
			x: logo.parts.x.x + logo.parts.x.width / 2,
			width: logo.parts.x.width / 2,
		} ),
	};
}

/**
 * Whether two colours are the same within a rounding step or two.
 *
 * @param {number[]} a One colour, `[r, g, b]`.
 * @param {number[]} b Another.
 * @return {boolean} Whether they match.
 */
const near = ( a, b ) =>
	a.every( ( channel, index ) => Math.abs( channel - b[ index ] ) <= 3 );

/**
 * Whether a colour is a dark ground rather than a light one: it stands out more from white.
 *
 * @param {number[]} colour A colour, `[r, g, b]`.
 * @return {boolean} Whether it is dark.
 */
const isDark = ( colour ) =>
	contrast( rgb( colour ), 'rgb(0, 0, 0)' ) <
	contrast( rgb( colour ), 'rgb(255, 255, 255)' );

const APPEARANCES = [ 'dark', 'light', 'system' ];
const COMPUTERS = [ 'dark', 'light' ];

test.describe( 'the login draws the whole mark on its card (FR-020, SC-006)', () => {
	test.use( {
		storageState: VISITOR,
		deviceScaleFactor: 2,
		reducedMotion: 'reduce',
	} );

	for ( const appearance of APPEARANCES ) {
		for ( const computer of COMPUTERS ) {
			test( `${ appearance } appearance on a ${ computer } computer`, async ( {
				page,
				context,
				baseURL,
			} ) => {
				await choose( context, baseURL, {
					appearance,
					logo_url: 'none',
				} );
				await page.emulateMedia( {
					colorScheme: computer,
					reducedMotion: 'reduce',
				} );
				await openLogin( page );

				const logo = await readLogo( page );
				const pixels = await logoPixels( page );
				const parts = sample( logo, pixels );
				const ground = isDark( parts.ground ) ? 'dark' : 'light';
				const lockup = LOCKUPS[ ground ];
				const ratios = {
					corners: parts.corners.map( ( colour ) =>
						contrast( rgb( colour ), rgb( parts.ground ) )
					),
					centre: contrast(
						rgb( parts.centre ),
						rgb( parts.ground )
					),
					divider: parts.divider
						? contrast( rgb( parts.divider ), rgb( parts.ground ) )
						: null,
					core: contrast( rgb( parts.core ), rgb( parts.ground ) ),
					x: contrast( rgb( parts.x ), rgb( parts.ground ) ),
				};

				const measured = JSON.stringify( {
					appearance,
					computer,
					ground,
					file: logo.url.split( '/' ).pop(),
					parts,
					ratios,
				} );
				test.info().annotations.push( {
					type: 'measured',
					description: measured,
				} );
				await test.info().attach( 'measured', {
					body: measured,
					contentType: 'application/json',
				} );

				// SC-006 as written: each of the four corner modules stands out from the ground by
				// 3:1, and so does "Core". The centre, the "x" and the divider are recorded above.
				for ( const ratio of ratios.corners ) {
					expect( ratio ).toBeGreaterThanOrEqual( 3 );
				}
				expect( ratios.core ).toBeGreaterThanOrEqual( 3 );

				// FR-018: "Core" and the corners are the neutral colour, the "x" and the centre brass.
				for ( const colour of parts.corners ) {
					expect( near( colour, lockup.neutral ) ).toBe( true );
				}
				expect( near( parts.core, lockup.neutral ) ).toBe( true );
				expect( near( parts.centre, lockup.brass ) ).toBe( true );
				expect( near( parts.x, lockup.brass ) ).toBe( true );
				expect( parts.divider ).not.toBeNull();

				// The lockup that suits the ground it is drawn on: Dark and Light by their own
				// choice, System as its card is drawn.
				if ( appearance !== 'system' ) {
					expect( ground ).toBe( appearance );
				}
				expect( logo.url ).toContain(
					`/assets/brand/${ lockup.file }`
				);
				expect( logo.name ).toBe( 'CoreX' );
			} );
		}
	}

	test( 'a logo set in Branding replaces the lockup in every appearance (TC-BRD-05-06)', async ( {
		page,
		context,
		baseURL,
	} ) => {
		for ( const appearance of APPEARANCES ) {
			for ( const computer of COMPUTERS ) {
				await choose( context, baseURL, {
					appearance,
					logo_url: SITE_IMAGE,
				} );
				await page.emulateMedia( {
					colorScheme: computer,
					reducedMotion: 'reduce',
				} );
				await openLogin( page );
				const logo = await readLogo( page );

				expect( logo.url ).toBe( new URL( SITE_IMAGE, baseURL ).href );
				// The site's own logo keeps the name WordPress gives the link.
				expect( logo.name ).not.toBe( 'CoreX' );
			}
		}
	} );
} );

test.describe( "the login's ambient glow and grid", () => {
	test.use( { storageState: VISITOR } );

	/**
	 * The login's running animations and transitions, by name (a transition by the property it
	 * moves), with the element or pseudo-element each is on.
	 *
	 * @param {import('@playwright/test').Page} page The login.
	 * @return {Promise<string[]>} One `name on target` per running animation or transition.
	 */
	const running = ( page ) =>
		page.evaluate( () =>
			document
				.getAnimations()
				.filter( ( animation ) => animation.playState === 'running' )
				.map(
					( animation ) =>
						`${
							animation.animationName ||
							animation.transitionProperty
						} on ${
							animation.effect.pseudoElement ||
							animation.effect.target.tagName
						}`
				)
		);

	test( 'run when motion is welcome, which is what makes the next test mean something', async ( {
		page,
	} ) => {
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		await openLogin( page );

		expect( await running( page ) ).toEqual(
			expect.arrayContaining( [
				'corex-login-glow on ::before',
				'corex-login-grid on ::after',
			] )
		);
	} );

	test( 'stop under reduced motion', async ( { page } ) => {
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await openLogin( page );

		// The glow and the grid are cut to a hundredth of a millisecond and play once.
		const ambient = await page.evaluate( () =>
			[ '::before', '::after' ].map( ( pseudo ) => {
				const style = window.getComputedStyle( document.body, pseudo );
				return {
					name: style.animationName,
					milliseconds: parseFloat( style.animationDuration ) * 1000,
					iterations: style.animationIterationCount,
				};
			} )
		);
		expect( ambient.map( ( { name } ) => name ) ).toEqual( [
			'corex-login-glow',
			'corex-login-grid',
		] );
		for ( const { milliseconds, iterations } of ambient ) {
			expect( milliseconds ).toBeLessThanOrEqual( 0.01 );
			expect( iterations ).toBe( '1' );
		}

		// WordPress focuses the first field about 200 ms after load (wp_attempt_focus), and that
		// focus starts the field's border and shadow transitions: wait for the focus itself, then
		// for every animation and transition on the page, each cut to a hundredth of a millisecond,
		// to end. A fixed wait ended before the focus on a fast machine and caught them running.
		await expect( page.locator( '#user_login' ) ).toBeFocused();
		const longest = await page.evaluate( async () => {
			const frame = () =>
				new Promise( ( resolve ) =>
					window.requestAnimationFrame( resolve )
				);
			await frame();
			await frame();
			const animations = document.getAnimations();
			const durations = animations.map(
				( animation ) =>
					animation.effect.getComputedTiming().activeDuration
			);
			if ( durations.every( ( duration ) => duration <= 0.01 ) ) {
				await Promise.all(
					animations.map( ( animation ) =>
						animation.finished.catch( () => {} )
					)
				);
			}
			return Math.max( 0, ...durations );
		} );
		expect( longest ).toBeLessThanOrEqual( 0.01 );

		expect( await running( page ) ).toEqual( [] );
	} );

	for ( const direction of [ 'ltr', 'rtl' ] ) {
		test( `the glow is centred on the page, ${ direction }`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			if ( direction === 'rtl' ) {
				// The Arabic fixture runs the request in Arabic, right to left.
				await context.addCookies( [
					{ name: 'corex_e2e_locale', value: 'ar', url: baseURL },
				] );
			}
			await page.setViewportSize( { width: 1280, height: 900 } );
			await page.emulateMedia( { reducedMotion: 'reduce' } );
			await openLogin( page );

			const glow = await page.evaluate( () => {
				const style = window.getComputedStyle(
					document.body,
					'::before'
				);
				const { e: shift } = new window.DOMMatrix( style.transform );

				return {
					dir: document.documentElement.dir || 'ltr',
					centre:
						parseFloat( style.left ) +
						parseFloat( style.width ) / 2 +
						shift,
					page: document.documentElement.clientWidth / 2,
				};
			} );
			test.info().annotations.push( {
				type: 'measured',
				description: JSON.stringify( { direction, ...glow } ),
			} );

			expect( glow.dir ).toBe( direction );
			expect( Math.abs( glow.centre - glow.page ) ).toBeLessThanOrEqual(
				1
			);
		} );
	}
} );

test.describe( 'the CoreX theme links its favicon only when the site has no icon (FR-022, SC-007)', () => {
	test.use( { storageState: VISITOR } );

	/**
	 * The icon links of the front page's head.
	 *
	 * @param {import('@playwright/test').Page} page The front page.
	 * @return {Promise<Array<{rel: string, href: string, sizes: string, type: string}>>} The links.
	 */
	const iconLinks = ( page ) =>
		page.$$eval( 'head link', ( links ) =>
			links
				.filter( ( link ) =>
					/(^|\s)(icon|apple-touch-icon)(\s|$)/.test( link.rel )
				)
				.map( ( link ) => ( {
					rel: link.rel,
					href: link.href,
					sizes: link.getAttribute( 'sizes' ) || '',
					type: link.getAttribute( 'type' ) || '',
				} ) )
		);

	test( 'with no site icon, the theme links its own favicon files (TC-BRD-05-04)', async ( {
		page,
		context,
		baseURL,
		request,
	} ) => {
		await choose( context, baseURL, { site_icon: 'none' } );
		await page.goto( '/' );
		const links = await iconLinks( page );

		expect(
			links.map( ( { rel, sizes, type } ) => [ rel, sizes, type ] )
		).toEqual( [
			[ 'icon', '', 'image/svg+xml' ],
			[ 'icon', '32x32', 'image/png' ],
			[ 'icon', '16x16', 'image/png' ],
			[ 'icon', '512x512', 'image/png' ],
			[ 'apple-touch-icon', '', '' ],
		] );

		for ( const link of links ) {
			expect( link.href ).toMatch(
				/\/themes\/corex\/assets\/favicon\/corex-favicon(-\d+\.png|\.svg)$/
			);
			const answer = await request.get( link.href );
			expect( answer.status() ).toBe( 200 );
			expect( answer.headers()[ 'content-type' ] ).toContain(
				link.href.endsWith( '.svg' ) ? 'image/svg+xml' : 'image/png'
			);
		}
	} );

	test( "with a site icon, only the site's icon is linked (scenario 7)", async ( {
		page,
		context,
		baseURL,
	} ) => {
		await choose( context, baseURL, { site_icon: SITE_IMAGE } );
		await page.goto( '/' );
		const links = await iconLinks( page );

		expect( links.length ).toBeGreaterThan( 0 );
		for ( const link of links ) {
			expect( link.href ).toBe( new URL( SITE_IMAGE, baseURL ).href );
		}
	} );

	test( 'under another theme, CoreX links no favicon (TC-BRD-05-05)', async ( {
		page,
		context,
		baseURL,
	} ) => {
		await choose( context, baseURL, { theme: 'other', site_icon: 'none' } );
		const answer = await page.goto( '/' );
		const other = answer.headers()[ 'x-corex-e2e-theme' ];

		test.skip( ! other, 'The site has no theme besides the CoreX theme.' );

		expect( await iconLinks( page ) ).toEqual( [] );
		expect( await page.content() ).not.toContain( 'corex-favicon' );
	} );
} );
