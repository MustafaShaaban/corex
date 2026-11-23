/**
 * Spec 137 slice 2: the language switcher in the browser (User Story 5 = US-NAV-05; FR-031 to FR-034,
 * FR-032a; SC-004, SC-010; `design/handoffs/header-system.md`, steps 2 and 2a).
 *
 * - One language (the source CoreX ships): no control, no wrapper, and neither the block's script nor
 *   its style on the page.
 * - English (current) and Arabic, from a source the `corex-e2e-languages.php` fixture binds for a
 *   request with the cookie `corex_e2e_languages`: each form, each name leading to the address the
 *   source gave; the toggle is one link "العربية" named by its text (SC-010); the menu by mouse and by
 *   keyboard (Enter, the arrow keys, Escape back to the button, a click outside); every target 44px.
 * - Three languages: the toggle prints the list (SC-010).
 * - Arabic, right to left (the `corex_e2e_locale=ar` cookie of `corex-e2e-arabic.php`): the page
 *   carries `dir="rtl"` and draws its text in the Arabic font token (FR-034), the toggle reads
 *   "English" in the heading face, and the open menu stays inside the window.
 * - At 375px, in both directions, nothing scrolls sideways. Without script the menu is the list.
 *
 * The header and the phone menu hold the switcher from slices 4 and 5; here it sits in a page.
 * ENVIRONMENT-GATED: needs the site at COREX_BASE_URL with both fixtures in its mu-plugins.
 */
const { test, expect } = require( '@playwright/test' );

const { STORAGE_STATE } = require( './global-setup' );

const VISITOR = { cookies: [], origins: [] };
const DESKTOP = { width: 1280, height: 900 };
const PHONE = { width: 375, height: 812 };
const TARGET = 44;
const SMALL_BUTTON = 36;
const ZOOMED_PHONE = { width: 188, height: 406 };

const PAGE = [
	'<!-- wp:paragraph --><p class="cx137-text">A page that holds the language switcher in each form.</p><!-- /wp:paragraph -->',
	'<!-- wp:group {"className":"cx137-menu","layout":{"type":"flex","justifyContent":"right"}} --><div class="wp-block-group cx137-menu is-content-justification-right is-layout-flex"><!-- wp:corex/language-switcher /--></div><!-- /wp:group -->',
	'<!-- wp:group {"className":"cx137-list"} --><div class="wp-block-group cx137-list"><!-- wp:corex/language-switcher {"form":"list"} /--></div><!-- /wp:group -->',
	'<!-- wp:group {"className":"cx137-toggle"} --><div class="wp-block-group cx137-toggle"><!-- wp:corex/language-switcher {"form":"toggle"} /--></div><!-- /wp:group -->',
	'<!-- wp:group {"className":"cx137-start"} --><div class="wp-block-group cx137-start"><!-- wp:corex/language-switcher /--></div><!-- /wp:group -->',
	'<!-- wp:group {"className":"cx137-narrow"} --><div class="wp-block-group cx137-narrow"><!-- wp:corex/language-switcher {"form":"list"} /--></div><!-- /wp:group -->',
	'<!-- wp:paragraph --><p><a class="cx137-after" href="/">A link after the switchers</a></p><!-- /wp:paragraph -->',
].join( '' );

let probe = null;

async function adminSession( browser ) {
	// Script on, whatever the describe block asks of its own pages.
	const context = await browser.newContext( {
		storageState: STORAGE_STATE,
		javaScriptEnabled: true,
	} );
	const page = await context.newPage();
	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate( () => window.wpApiSettings.nonce );

	return { context, page, nonce };
}

test.beforeAll( async ( { browser } ) => {
	const { context, page, nonce } = await adminSession( browser );
	const response = await page.request.post( '/wp-json/wp/v2/pages', {
		headers: { 'X-WP-Nonce': nonce },
		data: { title: 'cx137-languages', status: 'publish', content: PAGE },
	} );
	probe = await response.json();
	await context.close();
} );

test.afterAll( async ( { browser } ) => {
	if ( ! probe?.id ) {
		return;
	}
	const { context, page, nonce } = await adminSession( browser );
	await page.request.delete(
		`/wp-json/wp/v2/pages/${ probe.id }?force=true`,
		{
			headers: { 'X-WP-Nonce': nonce },
		}
	);
	await context.close();
} );

/**
 * Open the probe page with a set of languages, in a language, at a size.
 *
 * @param {import('@playwright/test').Page} page              The page.
 * @param {string}                          baseURL           The site.
 * @param {Object}                          options
 * @param {?string}                         options.languages `two`, `three`, or null for the site's one.
 * @param {string}                          options.locale    `en` or `ar`.
 * @param {Object}                          options.size      The viewport.
 */
async function open(
	page,
	baseURL,
	{ languages = 'two', locale = 'en', size = DESKTOP } = {}
) {
	const cookies = [];
	if ( languages ) {
		cookies.push( {
			name: 'corex_e2e_languages',
			value: languages,
			url: baseURL,
		} );
	}
	if ( locale === 'ar' ) {
		cookies.push( { name: 'corex_e2e_locale', value: 'ar', url: baseURL } );
	}
	await page.context().addCookies( cookies );
	await page.setViewportSize( size );
	await page.goto( probe.link );
	await page.evaluate( () => document.fonts.ready );
}

/**
 * Whether a point of the page lands on the element: its hit area, which a box drawn by `::after`
 * widens past the element's own box.
 *
 * @param {import('@playwright/test').Locator} locator The element.
 * @return {Promise<{width: number, height: number}>} How far its hit area reaches, across and down,
 *                                                      through its centre, to the nearest pixel.
 */
function hitArea( locator ) {
	return locator.evaluate( ( element ) => {
		const box = element.getBoundingClientRect();
		const x = box.left + box.width / 2;
		const y = box.top + box.height / 2;
		const hits = ( px, py ) => {
			const found = document.elementFromPoint( px, py );
			return found === element || element.contains( found );
		};
		const reach = ( dx, dy ) => {
			let step = 0;
			while (
				step < 60 &&
				hits( x + dx * ( step + 1 ), y + dy * ( step + 1 ) )
			) {
				step++;
			}
			return step;
		};

		return {
			width: reach( -1, 0 ) + reach( 1, 0 ) + 1,
			height: reach( 0, -1 ) + reach( 0, 1 ) + 1,
		};
	} );
}

/**
 * The value of a CSS custom property on the root, as the page computes it.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string}                          name The property.
 * @return {Promise<string>} Its value.
 */
const tokenFamily = ( page, name ) =>
	page.evaluate(
		( property ) =>
			window
				.getComputedStyle( document.documentElement )
				.getPropertyValue( property )
				.trim(),
		name
	);

const familyOf = ( locator ) =>
	locator.evaluate(
		( element ) => window.getComputedStyle( element ).fontFamily
	);

/**
 * The first family of a font stack, without its quotes.
 *
 * @param {string} stack A computed `font-family`.
 * @return {string} Its first family.
 */
const firstFamily = ( stack ) =>
	stack
		.split( ',' )[ 0 ]
		.trim()
		.replace( /^["']|["']$/g, '' );

test.describe( 'the language switcher (US-NAV-05)', () => {
	test.use( { storageState: VISITOR } );

	test( "FR-031, FR-033, SC-004: with the site's one language there is no control, and no script or style", async ( {
		page,
		baseURL,
	} ) => {
		await open( page, baseURL, { languages: null } );

		await expect( page.locator( '.cx137-text' ) ).toBeVisible();
		await expect( page.locator( '.corex-language-switcher' ) ).toHaveCount(
			0
		);
		await expect(
			page.locator( '.wp-block-corex-language-switcher' )
		).toHaveCount( 0 );
		expect( await page.content() ).not.toContain(
			'language-switcher/view'
		);
		expect( await page.content() ).not.toContain(
			'.corex-language-switcher'
		);
		// The address WordPress names the block's style by, which the next test finds when it prints.
		expect( await page.content() ).not.toContain(
			'language-switcher/style-index'
		);
	} );

	test( 'SC-010, FR-032a: the toggle is one link, "العربية", in its own lang, dir and face, named by its text', async ( {
		page,
		baseURL,
	} ) => {
		await open( page, baseURL );

		// Where the block prints, its style and its script are on the page.
		expect( await page.content() ).toContain(
			'language-switcher/style-index'
		);
		expect( await page.content() ).toContain( 'language-switcher/view' );

		const toggle = page.locator( '.cx137-toggle .corex-language-switcher' );
		await expect( toggle ).toHaveClass( /corex-language-switcher--toggle/ );
		await expect( toggle.locator( 'a' ) ).toHaveCount( 1 );

		const link = toggle.getByRole( 'link', {
			name: 'العربية',
			exact: true,
		} );
		await expect( link ).toHaveText( 'العربية' );
		await expect( link ).toHaveAttribute( 'lang', 'ar' );
		await expect( link ).toHaveAttribute( 'dir', 'rtl' );
		await expect( link ).toHaveAttribute( 'hreflang', 'ar' );
		await expect( link ).toHaveAttribute(
			'href',
			`${ baseURL }/?corex_e2e_language=ar`
		);

		// Rule 3a: the Arabic name in the Arabic face, on an English page.
		expect( firstFamily( await familyOf( link ) ) ).toBe(
			firstFamily(
				await tokenFamily( page, '--wp--preset--font-family--arabic' )
			)
		);

		const area = await hitArea( link );
		expect( area.width ).toBeGreaterThanOrEqual( TARGET );
		expect( area.height ).toBeGreaterThanOrEqual( TARGET );

		await link.click();
		await expect( page ).toHaveURL( /corex_e2e_language=ar/ );
	} );

	test( 'SC-004, FR-032: the list marks the current language and links the other to the address the source gave', async ( {
		page,
		baseURL,
	} ) => {
		await open( page, baseURL );

		const list = page.locator( '.cx137-list .corex-language-switcher' );
		await expect( list ).toHaveClass( /corex-language-switcher--list/ );

		const current = list.locator( '[aria-current="true"]' );
		await expect( current ).toHaveText( 'English' );
		await expect( current.locator( '[lang="en"][dir="ltr"]' ) ).toHaveText(
			'English'
		);
		expect( await current.evaluate( ( element ) => element.tagName ) ).toBe(
			'SPAN'
		);
		expect(
			await current.evaluate(
				( element ) => window.getComputedStyle( element ).fontWeight
			)
		).toBe( '600' );

		const arabic = list.getByRole( 'link', {
			name: 'العربية',
			exact: true,
		} );
		await expect( arabic ).toHaveAttribute(
			'href',
			`${ baseURL }/?corex_e2e_language=ar`
		);
		const area = await hitArea( arabic );
		expect( area.height ).toBeGreaterThanOrEqual( TARGET );
		expect( area.width ).toBeGreaterThanOrEqual( TARGET );
	} );

	test( 'FR-032: the menu opens by mouse, lists every language, and closes on a click outside', async ( {
		page,
		baseURL,
	} ) => {
		await open( page, baseURL );

		const menu = page.locator( '.cx137-menu .corex-language-switcher' );
		const button = menu.getByRole( 'button', { name: /English/ } );
		const dropdown = menu.locator( '.corex-dropdown__menu' );

		await expect( button ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( dropdown ).toBeHidden();
		// Drawn as the kit's small button, 36px tall; its target reaches 44px past its edges.
		expect( Math.round( ( await button.boundingBox() ).height ) ).toBe(
			SMALL_BUTTON
		);
		const buttonArea = await hitArea( button );
		expect( buttonArea.height ).toBeGreaterThanOrEqual( TARGET );
		expect( buttonArea.width ).toBeGreaterThanOrEqual( TARGET );

		await button.click();
		await expect( button ).toHaveAttribute( 'aria-expanded', 'true' );
		await expect( dropdown ).toBeVisible();
		await expect( dropdown.locator( 'li' ) ).toHaveCount( 2 );
		await expect( dropdown.locator( '[aria-current="true"]' ) ).toHaveText(
			'English'
		);
		const item = dropdown.getByRole( 'link', {
			name: 'العربية',
			exact: true,
		} );
		expect( ( await item.boundingBox() ).height ).toBeGreaterThanOrEqual(
			TARGET
		);

		// Opening it moves nothing else on the page: it is an overlay.
		const after = await page.locator( '.cx137-list' ).boundingBox();
		await page.locator( '.cx137-text' ).click();
		await expect( dropdown ).toBeHidden();
		expect( await page.locator( '.cx137-list' ).boundingBox() ).toEqual(
			after
		);
	} );

	test( 'FR-032: the menu by keyboard: Enter opens, the arrows move, Escape returns to the button, Enter follows', async ( {
		page,
		baseURL,
	} ) => {
		await open( page, baseURL, { languages: 'three' } );

		const menu = page.locator( '.cx137-menu .corex-language-switcher' );
		const button = menu.locator( 'button' );
		const dropdown = menu.locator( '.corex-dropdown__menu' );

		await button.focus();
		await page.keyboard.press( 'Enter' );
		await expect( dropdown ).toBeVisible();

		await page.keyboard.press( 'ArrowDown' );
		await expect(
			dropdown.getByRole( 'link', { name: 'العربية' } )
		).toBeFocused();
		await page.keyboard.press( 'ArrowDown' );
		await expect(
			dropdown.getByRole( 'link', { name: 'Français' } )
		).toBeFocused();
		await page.keyboard.press( 'ArrowUp' );
		await expect(
			dropdown.getByRole( 'link', { name: 'العربية' } )
		).toBeFocused();

		await page.keyboard.press( 'Escape' );
		await expect( dropdown ).toBeHidden();
		await expect( button ).toBeFocused();

		await page.keyboard.press( 'ArrowUp' );
		await expect(
			dropdown.getByRole( 'link', { name: 'Français' } )
		).toBeFocused();
		await page.keyboard.press( 'Enter' );
		await expect( page ).toHaveURL( /corex_e2e_language=fr/ );
	} );

	test( 'FR-032: focus leaving the menu closes it', async ( {
		page,
		baseURL,
	} ) => {
		await open( page, baseURL );

		const menu = page.locator( '.cx137-menu .corex-language-switcher' );
		await menu.locator( 'button' ).focus();
		await page.keyboard.press( 'ArrowDown' );
		await expect( menu.locator( '.corex-dropdown__menu' ) ).toBeVisible();

		await page.locator( '.cx137-after' ).focus();
		await expect( menu.locator( '.corex-dropdown__menu' ) ).toBeHidden();
		await expect( menu.locator( 'button' ) ).toHaveAttribute(
			'aria-expanded',
			'false'
		);
	} );

	test( 'SC-010: with three languages the toggle prints the list', async ( {
		page,
		baseURL,
	} ) => {
		await open( page, baseURL, { languages: 'three' } );

		const toggle = page.locator( '.cx137-toggle .corex-language-switcher' );
		await expect( toggle ).toHaveClass( /corex-language-switcher--list/ );
		await expect( toggle.locator( 'li' ) ).toHaveCount( 3 );
		await expect( toggle.locator( 'a' ) ).toHaveCount( 2 );
	} );

	test( 'FR-034: an Arabic page is right to left in the Arabic font token; the toggle reads "English" in its own face', async ( {
		page,
		baseURL,
	} ) => {
		await open( page, baseURL, { locale: 'ar' } );

		await expect( page.locator( 'html' ) ).toHaveAttribute( 'dir', 'rtl' );
		await expect( page.locator( 'html' ) ).toHaveAttribute( 'lang', /^ar/ );
		const arabicFace = firstFamily(
			await tokenFamily( page, '--wp--preset--font-family--arabic' )
		);
		expect( firstFamily( await familyOf( page.locator( 'body' ) ) ) ).toBe(
			arabicFace
		);
		expect(
			firstFamily( await familyOf( page.locator( '.cx137-text' ) ) )
		).toBe( arabicFace );

		const link = page.locator( '.cx137-toggle a' );
		await expect( link ).toHaveText( 'English' );
		await expect( link ).toHaveAttribute( 'lang', 'en' );
		await expect( link ).toHaveAttribute( 'dir', 'ltr' );
		await expect(
			page
				.locator( '.cx137-toggle' )
				.getByRole( 'link', { name: 'English', exact: true } )
		).toHaveCount( 1 );
		expect( firstFamily( await familyOf( link ) ) ).toBe(
			firstFamily(
				await tokenFamily( page, '--wp--preset--font-family--heading' )
			)
		);

		// The open menu stays inside the window, at the inline end, right to left.
		const menu = page.locator( '.cx137-menu .corex-language-switcher' );
		await menu.locator( 'button' ).click();
		const box = await menu.locator( '.corex-dropdown__menu' ).boundingBox();
		expect( box.x ).toBeGreaterThanOrEqual( 0 );
		expect( box.x + box.width ).toBeLessThanOrEqual( DESKTOP.width );
		await expect( menu.locator( '[aria-current="true"]' ) ).toHaveText(
			'العربية'
		);
	} );

	for ( const locale of [ 'en', 'ar' ] ) {
		test( `nothing scrolls sideways at 375px with the menu open (${ locale })`, async ( {
			page,
			baseURL,
		} ) => {
			await open( page, baseURL, {
				languages: 'three',
				locale,
				size: PHONE,
			} );

			const menu = page.locator( '.cx137-menu .corex-language-switcher' );
			await menu.locator( 'button' ).click();
			await expect(
				menu.locator( '.corex-dropdown__menu' )
			).toBeVisible();

			const sideways = await page.evaluate(
				() =>
					document.documentElement.scrollWidth -
					document.documentElement.clientWidth
			);
			expect( sideways ).toBeLessThanOrEqual( 0 );

			const box = await menu
				.locator( '.corex-dropdown__menu' )
				.boundingBox();
			expect( box.x ).toBeGreaterThanOrEqual( 0 );
			expect( box.x + box.width ).toBeLessThanOrEqual( PHONE.width );
		} );

		test( `a menu at the start of a line opens inside the window at 375px (${ locale })`, async ( {
			page,
			baseURL,
		} ) => {
			await open( page, baseURL, {
				languages: 'three',
				locale,
				size: PHONE,
			} );

			const menu = page.locator(
				'.cx137-start .corex-language-switcher'
			);
			const button = menu.locator( 'button' );
			const dropdown = menu.locator( '.corex-dropdown__menu' );
			await button.click();
			await expect( dropdown ).toBeVisible();
			await expect( dropdown ).toHaveClass( /is-aligned-start/ );

			const box = await dropdown.boundingBox();
			const start = await button.boundingBox();
			expect( box.x ).toBeGreaterThanOrEqual( 0 );
			expect( box.x + box.width ).toBeLessThanOrEqual( PHONE.width );
			// It hangs from the button's inline start: its left edge in English, its right in Arabic.
			if ( locale === 'ar' ) {
				expect(
					Math.abs( box.x + box.width - ( start.x + start.width ) )
				).toBeLessThanOrEqual( 1 );
			} else {
				expect( Math.abs( box.x - start.x ) ).toBeLessThanOrEqual( 1 );
			}

			// A language in it can still be chosen with the mouse.
			await dropdown.getByRole( 'link', { name: 'Français' } ).click();
			await expect( page ).toHaveURL( /corex_e2e_language=fr/ );
		} );
	}

	for ( const locale of [ 'en', 'ar' ] ) {
		test( `the menu is drawn as the kit's small ghost button over its Dropdown (${ locale })`, async ( {
			page,
			baseURL,
		} ) => {
			await open( page, baseURL, { languages: 'three', locale } );

			const menu = page.locator( '.cx137-menu .corex-language-switcher' );
			const button = menu.locator( 'button' );
			await button.click();
			const dropdown = menu.locator( '.corex-dropdown__menu' );
			await expect( dropdown ).toBeVisible();

			const drawn = await menu.evaluate( ( root ) => {
				const css = ( element ) => window.getComputedStyle( element );
				const swatch = document.createElement( 'span' );
				swatch.style.color = 'var(--wp--preset--color--ink)';
				document.body.appendChild( swatch );
				const ink = css( swatch ).color;
				swatch.remove();
				const knob = root.querySelector( 'button' );
				const current = root.querySelector( '[aria-current="true"]' );
				const tick = current.querySelector( '.corex-icon' );
				const other = root.querySelector( '.corex-dropdown__menu a' );

				return {
					ink,
					paddingStart: css( knob ).paddingInlineStart,
					gap: css( knob ).columnGap,
					// Its drawn size, not its turning box: the chevron may still be turning.
					chevron: parseFloat(
						css( knob.querySelector( '.corex-icon' ) ).width
					),
					tick: tick.getBoundingClientRect().width,
					tickX: tick.getBoundingClientRect().left,
					nameX: current
						.querySelector( '[lang]' )
						.getBoundingClientRect().left,
					rowEnd: current.getBoundingClientRect().right,
					rowStart: current.getBoundingClientRect().left,
					otherColour: css( other ).color,
					otherSize: css( other ).fontSize,
					bodySize: css( document.body ).fontSize,
				};
			} );

			expect( drawn.paddingStart ).toBe( '16px' );
			expect( drawn.gap ).toBe( '8px' );
			expect( drawn.chevron ).toBe( 16 );
			expect( drawn.tick ).toBe( 16 );
			// The tick sits at the row's inline end: right of the name in English, left of it in Arabic.
			if ( locale === 'ar' ) {
				expect( drawn.tickX ).toBeLessThan( drawn.nameX );
				expect( drawn.tickX - drawn.rowStart ).toBeLessThan( 24 );
			} else {
				expect( drawn.tickX ).toBeGreaterThan( drawn.nameX );
				expect(
					drawn.rowEnd - ( drawn.tickX + drawn.tick )
				).toBeLessThan( 24 );
			}
			expect( drawn.otherColour ).toBe( drawn.ink );
			expect( drawn.otherSize ).toBe( drawn.bodySize );
		} );

		for ( const place of [ 'cx137-start', 'cx137-menu' ] ) {
			test( `the open menu stays inside a 188px window, a phone at 200% (${ locale }, ${ place })`, async ( {
				page,
				baseURL,
			} ) => {
				await open( page, baseURL, {
					languages: 'three',
					locale,
					size: ZOOMED_PHONE,
				} );

				const menu = page.locator(
					`.${ place } .corex-language-switcher`
				);
				await menu.locator( 'button' ).click();
				const dropdown = menu.locator( '.corex-dropdown__menu' );
				await expect( dropdown ).toBeVisible();

				const box = await dropdown.boundingBox();
				expect( box.x ).toBeGreaterThanOrEqual( 0 );
				expect( box.x + box.width ).toBeLessThanOrEqual(
					ZOOMED_PHONE.width
				);
				await expect(
					dropdown.getByRole( 'link', { name: 'Français' } )
				).toBeVisible();
			} );
		}
	}

	test( 'a list that wraps keeps a 44px target on every row', async ( {
		page,
		baseURL,
	} ) => {
		await open( page, baseURL, { languages: 'three' } );

		const list = page.locator( '.cx137-narrow .corex-language-switcher' );
		// Narrow enough that each language takes a row of its own.
		await list.evaluate( ( element ) => {
			element.parentElement.style.maxInlineSize = '7rem';
		} );
		const links = list.locator( 'a' );
		const [ first, second ] = [
			await links.nth( 0 ).boundingBox(),
			await links.nth( 1 ).boundingBox(),
		];
		expect( second.y ).toBeGreaterThan( first.y + first.height / 2 );

		for ( const index of [ 0, 1 ] ) {
			const area = await hitArea( links.nth( index ) );
			expect( area.height ).toBeGreaterThanOrEqual( TARGET );
		}
	} );

	test( 'a mouse click on a language follows it after the keyboard opened the menu', async ( {
		page,
		baseURL,
	} ) => {
		await open( page, baseURL, { languages: 'three' } );

		const menu = page.locator( '.cx137-menu .corex-language-switcher' );
		await menu.locator( 'button' ).focus();
		await page.keyboard.press( 'ArrowDown' );
		await menu.getByRole( 'link', { name: 'Français' } ).click();

		await expect( page ).toHaveURL( /corex_e2e_language=fr/ );
	} );
} );

test.describe( 'the language switcher without script (FR-032)', () => {
	test.use( { storageState: VISITOR, javaScriptEnabled: false } );

	test( 'the menu form is the list: no button, every language shown, the other one a link', async ( {
		page,
		baseURL,
	} ) => {
		await open( page, baseURL );

		const menu = page.locator( '.cx137-menu .corex-language-switcher' );
		await expect( menu.locator( 'button' ) ).toBeHidden();
		await expect( menu.locator( '[aria-current="true"]' ) ).toBeVisible();
		await expect(
			menu.getByRole( 'link', { name: 'العربية', exact: true } )
		).toBeVisible();
	} );
} );
