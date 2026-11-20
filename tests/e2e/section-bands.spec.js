/**
 * Spec 134, slice 2 (FR-011 to FR-015, DECISIONS #640): the theme's base elements and corex-ui's
 * section styles, as the browser draws them.
 *
 * - A content page: the brass button with its graphite label, the 10px corner and a 44px target, its
 *   hover, its focus ring and a disabled button; a link in running text and on hover; selected text.
 * - A hand-made dark band holding a heading, a paragraph with a link, a button and a card: each is
 *   drawn in the band's colours by its ordinary rules (US-BRD-01, scenario 9), in English and in
 *   Arabic, right to left.
 * - A brass band and a quiet band: their ground, a heading with no colour of its own takes the brass
 *   band's text colour (FR-014) and the theme's heading colour on the quiet band; on brass a card, a
 *   field and a plain element button (the search block's) read, in Dark too (DECISIONS #642).
 * - A disabled button inside a band, and a disabled secondary button, keep the theme's disabled look.
 * - The kit's button: every style in one row is 48px tall, padding 0 and 24px, its label centred; a
 *   bare `a.wp-element-button` stays on one line at 375px. The kit's secondary and ghost.
 * - A band placed directly inside the other band keeps its own readable colours, both ways.
 * - The hero and call-to-action patterns parse as valid blocks in the editor.
 *
 * Both modes: the default style and the Dark variation, drawn as `theme-colours.js` says. Arabic is
 * asked for per request with the cookie the `corex-e2e-arabic.php` fixture reads.
 *
 * Every expected colour is read from `theme/theme.json` and `theme/styles/dark.json`, so these
 * checks follow the tokens. ENVIRONMENT-GATED: needs the site at COREX_BASE_URL.
 */
const { test, expect } = require( '@playwright/test' );

const { STORAGE_STATE } = require( './global-setup' );
const { contrast } = require( './helpers' );
const { MODES, settle, showDarkVariation } = require( './theme-colours' );

const VISITOR = { cookies: [], origins: [] };
const DESKTOP = { width: 1280, height: 900 };

const BAND_PAGE = [
	'<!-- wp:paragraph {"className":"cx134-running"} --><p class="cx134-running">Running text with <a class="cx134-page-link" href="/">a link in it</a>.</p><!-- /wp:paragraph -->',
	'<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"cx134-page-button"} --><div class="wp-block-button cx134-page-button"><a class="wp-block-button__link wp-element-button" href="/">A page button</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
	'<!-- wp:html --><p><button type="button" class="wp-element-button cx134-disabled" disabled>Not yet</button></p><!-- /wp:html -->',
	'<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-corex-secondary"} --><div class="wp-block-button is-style-corex-secondary"><a class="wp-block-button__link wp-element-button cx134-secondary-disabled" aria-disabled="true" href="/">A secondary, not yet</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
	'<!-- wp:buttons {"className":"cx134-row"} --><div class="wp-block-buttons cx134-row"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/">Default</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-corex-secondary"} --><div class="wp-block-button is-style-corex-secondary"><a class="wp-block-button__link wp-element-button cx134-secondary" href="/">Secondary</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-corex-ghost"} --><div class="wp-block-button is-style-corex-ghost"><a class="wp-block-button__link wp-element-button cx134-ghost" href="/">Ghost</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
	'<!-- wp:html --><p class="cx134-bare-row"><a class="wp-element-button cx134-bare" href="/">A bare link button</a></p><!-- /wp:html -->',
	'<!-- wp:group {"tagName":"section","className":"is-style-corex-band-dark","layout":{"type":"constrained"}} --><section class="wp-block-group is-style-corex-band-dark">',
	'<!-- wp:heading --><h2 class="wp-block-heading cx134-band-heading">On the dark band</h2><!-- /wp:heading -->',
	'<!-- wp:paragraph --><p class="cx134-band-text">Text with <a class="cx134-band-link" href="/">a link on the band</a>.</p><!-- /wp:paragraph -->',
	'<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button cx134-band-button" href="/">A band button</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
	'<!-- wp:group {"className":"is-style-corex-card"} --><div class="wp-block-group is-style-corex-card cx134-card"><!-- wp:paragraph --><p class="cx134-card-text">A card with <a class="cx134-card-link" href="/">its own link</a>.</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
	'<!-- wp:html --><p><button type="button" class="wp-element-button cx134-band-disabled" disabled>Not yet on the band</button></p><!-- /wp:html -->',
	'</section><!-- /wp:group -->',
	'<!-- wp:group {"tagName":"section","className":"is-style-corex-band-brass","layout":{"type":"constrained"}} --><section class="wp-block-group is-style-corex-band-brass">',
	'<!-- wp:heading --><h2 class="wp-block-heading cx134-brass-heading">On the brass band</h2><!-- /wp:heading -->',
	'<!-- wp:paragraph --><p>Text with <a class="cx134-brass-link" href="/">a link on brass</a>.</p><!-- /wp:paragraph -->',
	'<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button cx134-brass-button" href="/">A brass band button</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
	'<!-- wp:group {"className":"is-style-corex-card"} --><div class="wp-block-group is-style-corex-card cx134-brass-card"><!-- wp:paragraph --><p class="cx134-brass-card-text">A card on brass.</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
	'<!-- wp:search {"label":"Search","buttonText":"Search"} /-->',
	'<!-- wp:corex/newsletter-signup /-->',
	'<!-- wp:html --><p><button type="button" class="wp-element-button cx134-brass-disabled" disabled>Not yet on brass</button></p><!-- /wp:html -->',
	'<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-corex-secondary"} --><div class="wp-block-button is-style-corex-secondary"><a class="wp-block-button__link wp-element-button cx134-brass-secondary" href="/">A secondary on brass</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
	'</section><!-- /wp:group -->',
	'<!-- wp:group {"tagName":"section","className":"is-style-corex-band-dark cx134-outer-dark","layout":{"type":"constrained"}} --><section class="wp-block-group is-style-corex-band-dark cx134-outer-dark">',
	'<!-- wp:group {"tagName":"div","className":"is-style-corex-band-brass cx134-brass-in-dark","layout":{"type":"constrained"}} --><div class="wp-block-group is-style-corex-band-brass cx134-brass-in-dark">',
	'<!-- wp:heading --><h2 class="wp-block-heading cx134-brass-in-dark-heading">Brass inside dark</h2><!-- /wp:heading -->',
	'<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button cx134-brass-in-dark-button" href="/">A button</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
	'</div><!-- /wp:group -->',
	'</section><!-- /wp:group -->',
	'<!-- wp:group {"tagName":"section","className":"is-style-corex-band-brass cx134-outer-brass","layout":{"type":"constrained"}} --><section class="wp-block-group is-style-corex-band-brass cx134-outer-brass">',
	'<!-- wp:group {"tagName":"div","className":"is-style-corex-band-dark cx134-dark-in-brass","layout":{"type":"constrained"}} --><div class="wp-block-group is-style-corex-band-dark cx134-dark-in-brass">',
	'<!-- wp:heading --><h2 class="wp-block-heading cx134-dark-in-brass-heading">Dark inside brass</h2><!-- /wp:heading -->',
	'<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button cx134-dark-in-brass-button" href="/">A button</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
	'</div><!-- /wp:group -->',
	'</section><!-- /wp:group -->',
	'<!-- wp:group {"tagName":"section","className":"is-style-corex-band-quiet","layout":{"type":"constrained"}} --><section class="wp-block-group is-style-corex-band-quiet">',
	'<!-- wp:heading --><h2 class="wp-block-heading cx134-quiet-heading">On the quiet band</h2><!-- /wp:heading -->',
	'</section><!-- /wp:group -->',
].join( '' );

let bandPage = null;

async function adminSession( browser ) {
	const context = await browser.newContext( { storageState: STORAGE_STATE } );
	const page = await context.newPage();
	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate( () => window.wpApiSettings.nonce );

	return { context, page, nonce };
}

test.beforeAll( async ( { browser } ) => {
	const { context, page, nonce } = await adminSession( browser );
	const response = await page.request.post( '/wp-json/wp/v2/pages', {
		headers: { 'X-WP-Nonce': nonce },
		data: { title: 'cx134-bands', status: 'publish', content: BAND_PAGE },
	} );
	bandPage = await response.json();
	await context.close();
} );

test.afterAll( async ( { browser } ) => {
	if ( ! bandPage?.id ) {
		return;
	}
	const { context, page, nonce } = await adminSession( browser );
	await page.request.delete(
		`/wp-json/wp/v2/pages/${ bandPage.id }?force=true`,
		{ headers: { 'X-WP-Nonce': nonce } }
	);
	await context.close();
} );

/**
 * Open the band page in a mode and a language, and wait until it is drawn.
 *
 * @param {import('@playwright/test').Page}           page    The page.
 * @param {import('@playwright/test').BrowserContext} context Its context.
 * @param {string}                                    baseURL The site.
 * @param {string}                                    mode    'default' or 'dark'.
 * @param {string}                                    locale  'en' or 'ar'.
 */
async function open( page, context, baseURL, mode, locale ) {
	if ( locale === 'ar' ) {
		await context.addCookies( [
			{ name: 'corex_e2e_locale', value: 'ar', url: baseURL },
		] );
	}
	await page.setViewportSize( DESKTOP );
	await page.goto( bandPage.link );
	if ( mode === 'dark' ) {
		await showDarkVariation( page );
	}
	await settle( page );
}

/**
 * What one element computes.
 *
 * @param {import('@playwright/test').Page} page     The page.
 * @param {string}                          selector The element.
 * @param {string}                          pseudo   A pseudo-element, or null.
 * @return {Promise<Object>} Its colours, edges, ring, corner and height.
 */
function computed( page, selector, pseudo = null ) {
	return page
		.locator( selector )
		.first()
		.evaluate( ( element, which ) => {
			const style = window.getComputedStyle( element, which );
			return {
				color: style.color,
				background: style.backgroundColor,
				border: style.borderTopColor,
				decoration: style.textDecorationLine,
				outlineColor: style.outlineColor,
				outlineStyle: style.outlineStyle,
				outlineWidth: style.outlineWidth,
				outlineOffset: style.outlineOffset,
				radius: style.borderTopLeftRadius,
				cursor: style.cursor,
				height: element.getBoundingClientRect().height,
			};
		}, pseudo );
}

/**
 * Moves keyboard focus to an element the way a person does, so `:focus-visible` matches.
 *
 * @param {import('@playwright/test').Page} page     The page.
 * @param {string}                          selector The element to reach.
 */
async function focusByKeyboard( page, selector ) {
	await page
		.locator( 'body' )
		.evaluate( ( body ) => body.ownerDocument.activeElement?.blur() );
	for ( let press = 0; press < 40; press++ ) {
		await page.keyboard.press( 'Tab' );
		if (
			await page
				.locator( selector )
				.first()
				.evaluate( ( el ) => el === el.ownerDocument.activeElement )
		) {
			await settle( page );
			return;
		}
	}
	throw new Error( `Tab never reached ${ selector }` );
}

test.describe( "the theme's base elements on a content page (FR-011, FR-012)", () => {
	test.use( { storageState: VISITOR } );

	for ( const mode of Object.keys( MODES ) ) {
		test( `the button is brass with a graphite label, a 10px corner and a 44px target, in the ${ mode } style`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			const { preset, custom } = MODES[ mode ];
			await open( page, context, baseURL, mode, 'en' );
			const selector = '.cx134-page-button .wp-block-button__link';

			const rest = await computed( page, selector );
			expect( rest ).toMatchObject( {
				background: preset.accent,
				color: custom[ 'on-accent' ],
				radius: '10px',
			} );
			expect( rest.height ).toBeGreaterThanOrEqual( 44 );
			expect(
				contrast( rest.color, rest.background )
			).toBeGreaterThanOrEqual( 4.5 );

			await page.locator( selector ).hover();
			await settle( page );
			const hover = await computed( page, selector );
			expect( hover ).toMatchObject( {
				background: custom[ 'accent-hover' ],
				color: custom[ 'on-accent' ],
			} );

			await page.mouse.move( 0, 0 );
			await focusByKeyboard( page, selector );
			expect( await computed( page, selector ) ).toMatchObject( {
				outlineColor: preset.accent,
				outlineStyle: 'solid',
				outlineWidth: '2px',
				outlineOffset: '2px',
			} );
		} );

		test( `a disabled button is a quiet surface that cannot be pressed, in the ${ mode } style`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			const { preset } = MODES[ mode ];
			await open( page, context, baseURL, mode, 'en' );

			expect( await computed( page, '.cx134-disabled' ) ).toMatchObject( {
				background: preset[ 'surface-strong' ],
				color: preset[ 'ink-soft' ],
				cursor: 'not-allowed',
			} );
		} );

		test( `a link in running text is the darker brass, underlined, and primary on hover; selected text takes the selection colours, in the ${ mode } style`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			const { preset } = MODES[ mode ];
			await open( page, context, baseURL, mode, 'en' );

			expect( await computed( page, '.cx134-page-link' ) ).toMatchObject(
				{
					color: preset[ 'accent-dark' ],
					decoration: 'underline',
				}
			);
			await page.locator( '.cx134-page-link' ).hover();
			await settle( page );
			expect( ( await computed( page, '.cx134-page-link' ) ).color ).toBe(
				preset.primary
			);

			expect(
				await computed( page, '.cx134-running', '::selection' )
			).toMatchObject( {
				background: preset.selection,
				color: preset[ 'selection-text' ],
			} );
		} );
	}
} );

test.describe( "the kit's button, secondary and ghost (FR-011)", () => {
	test.use( { storageState: VISITOR } );

	/**
	 * The box of an element as the browser lays it out.
	 *
	 * @param {import('@playwright/test').Page} page     The page.
	 * @param {string}                          selector The element.
	 * @return {Promise<Object>} Its display, height, paddings and line count.
	 */
	const box = ( page, selector ) =>
		page
			.locator( selector )
			.first()
			.evaluate( ( element ) => {
				const style = window.getComputedStyle( element );
				return {
					display: style.display,
					height: element.getBoundingClientRect().height,
					padding: `${ style.paddingBlockStart } ${ style.paddingInlineStart }`,
					lines: element.getClientRects().length,
				};
			} );

	test( 'every button style in one row is 48px tall, padding 0 and 24px, its label centred', async ( {
		page,
		context,
		baseURL,
	} ) => {
		await open( page, context, baseURL, 'default', 'en' );

		const row = await page
			.locator( '.cx134-row .wp-block-button__link' )
			.evaluateAll( ( links ) =>
				links.map( ( link ) => {
					const style = window.getComputedStyle( link );
					return {
						display: style.display,
						align: style.alignItems,
						height: link.getBoundingClientRect().height,
						padding: `${ style.paddingBlockStart } ${ style.paddingInlineStart }`,
					};
				} )
			);

		expect( row ).toHaveLength( 3 );
		for ( const button of row ) {
			expect( button ).toEqual( {
				display: 'inline-flex',
				align: 'center',
				height: 48,
				padding: '0px 24px',
			} );
		}
	} );

	test( 'a bare link button is an inline flex box on one line at 375px', async ( {
		page,
		context,
		baseURL,
	} ) => {
		await open( page, context, baseURL, 'default', 'en' );
		await page.setViewportSize( { width: 375, height: 800 } );

		expect( await box( page, '.cx134-bare' ) ).toEqual( {
			display: 'inline-flex',
			height: 48,
			padding: '0px 24px',
			lines: 1,
		} );
	} );

	for ( const mode of Object.keys( MODES ) ) {
		test( `the secondary is the raised surface with a border edge and the ghost ink text, in the ${ mode } style`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			const { preset } = MODES[ mode ];
			await open( page, context, baseURL, mode, 'en' );

			expect( await computed( page, '.cx134-secondary' ) ).toMatchObject(
				{
					background: preset[ 'surface-raised' ],
					color: preset.ink,
					border: preset.border,
				}
			);
			expect( await computed( page, '.cx134-ghost' ) ).toMatchObject( {
				background: 'rgba(0, 0, 0, 0)',
				color: preset.ink,
			} );

			await page.locator( '.cx134-secondary' ).hover();
			await settle( page );
			expect( await computed( page, '.cx134-secondary' ) ).toMatchObject(
				{
					background: preset[ 'surface-alt' ],
					border: preset.ink,
				}
			);
			await page.locator( '.cx134-ghost' ).hover();
			await settle( page );
			expect( await computed( page, '.cx134-ghost' ) ).toMatchObject( {
				background: preset[ 'surface-alt' ],
				color: preset.ink,
			} );
		} );
	}

	test( 'a disabled link button takes no click', async ( {
		page,
		context,
		baseURL,
	} ) => {
		await open( page, context, baseURL, 'default', 'en' );

		expect(
			await page
				.locator( '.cx134-secondary-disabled' )
				.evaluate(
					( el ) => window.getComputedStyle( el ).pointerEvents
				)
		).toBe( 'none' );
	} );
} );

test.describe( 'a hand-made dark band holding a button, a link and a card (FR-013, US-BRD-01 scenario 9)', () => {
	test.use( { storageState: VISITOR } );

	for ( const mode of Object.keys( MODES ) ) {
		for ( const locale of [ 'en', 'ar' ] ) {
			test( `each takes the band's colours, in the ${ mode } style, ${
				locale === 'ar' ? 'Arabic, right to left' : 'English'
			}`, async ( { page, context, baseURL } ) => {
				const { custom } = MODES[ mode ];
				await open( page, context, baseURL, mode, locale );

				expect(
					await page.evaluate(
						() => document.documentElement.dir || 'ltr'
					)
				).toBe( locale === 'ar' ? 'rtl' : 'ltr' );

				const band = await computed(
					page,
					'.is-style-corex-band-dark'
				);
				const heading = await computed( page, '.cx134-band-heading' );
				const text = await computed( page, '.cx134-band-text' );
				const link = await computed( page, '.cx134-band-link' );
				const button = await computed( page, '.cx134-band-button' );
				const card = await computed( page, '.cx134-card' );
				const cardText = await computed( page, '.cx134-card-text' );
				const cardLink = await computed( page, '.cx134-card-link' );

				expect( band ).toMatchObject( {
					background: custom.band,
					color: custom[ 'band-ink' ],
				} );
				expect( heading.color ).toBe( custom[ 'band-ink' ] );
				expect( text.color ).toBe( custom[ 'band-ink' ] );
				expect( link ).toMatchObject( {
					color: custom[ 'band-link' ],
					decoration: 'underline',
				} );
				expect( button ).toMatchObject( {
					background: custom[ 'band-accent' ],
					color: custom.band,
				} );
				expect( card ).toMatchObject( {
					background: custom[ 'band-raised' ],
					border: custom[ 'band-line' ],
				} );
				expect( cardText.color ).toBe( custom[ 'band-ink' ] );
				expect( cardLink.color ).toBe( custom[ 'band-link' ] );

				// Measured against what is behind each: none is the colour of its ground.
				expect(
					contrast( heading.color, band.background )
				).toBeGreaterThanOrEqual( 4.5 );
				expect(
					contrast( link.color, band.background )
				).toBeGreaterThanOrEqual( 4.5 );
				expect(
					contrast( button.background, band.background )
				).toBeGreaterThanOrEqual( 3 );
				expect(
					contrast( button.color, button.background )
				).toBeGreaterThanOrEqual( 4.5 );
				expect(
					contrast( cardLink.color, card.background )
				).toBeGreaterThanOrEqual( 4.5 );

				await page.locator( '.cx134-band-button' ).hover();
				await settle( page );
				expect(
					( await computed( page, '.cx134-band-button' ) ).background
				).toBe( custom[ 'band-accent-hover' ] );

				await page.mouse.move( 0, 0 );
				await focusByKeyboard( page, '.cx134-band-link' );
				const ring = await computed( page, '.cx134-band-link' );
				expect( ring ).toMatchObject( {
					outlineColor: custom[ 'band-accent' ],
					outlineWidth: '2px',
					outlineOffset: '2px',
				} );
				expect(
					contrast( ring.outlineColor, band.background )
				).toBeGreaterThanOrEqual( 3 );
			} );
		}
	}
} );

test.describe( 'the brass band and the quiet band (FR-013, FR-014)', () => {
	test.use( { storageState: VISITOR } );

	for ( const mode of Object.keys( MODES ) ) {
		test( `the brass band draws graphite text and a graphite button, and the quiet band its grey ground, in the ${ mode } style`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			const { preset, custom } = MODES[ mode ];
			await open( page, context, baseURL, mode, 'en' );

			const brass = await computed( page, '.is-style-corex-band-brass' );
			const heading = await computed( page, '.cx134-brass-heading' );
			const link = await computed( page, '.cx134-brass-link' );
			const button = await computed( page, '.cx134-brass-button' );

			expect( brass ).toMatchObject( {
				background: preset.accent,
				color: custom[ 'on-accent' ],
			} );
			expect( heading.color ).toBe( custom[ 'on-accent' ] );
			expect( link.color ).toBe( custom[ 'on-accent' ] );
			expect( button ).toMatchObject( {
				background: custom[ 'on-accent' ],
				color: custom[ 'band-ink' ],
			} );
			expect(
				contrast( button.background, brass.background )
			).toBeGreaterThanOrEqual( 3 );
			expect(
				contrast( button.color, button.background )
			).toBeGreaterThanOrEqual( 4.5 );
			expect(
				contrast( heading.color, brass.background )
			).toBeGreaterThanOrEqual( 4.5 );

			const quiet = await computed( page, '.is-style-corex-band-quiet' );
			const quietHeading = await computed( page, '.cx134-quiet-heading' );
			expect( quiet.background ).toBe( preset[ 'surface-alt' ] );
			expect( quietHeading.color ).toBe( preset.primary );
		} );

		test( `a card, a field and a plain element button on the brass band read, in the ${ mode } style`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			const { preset, custom } = MODES[ mode ];
			await open( page, context, baseURL, mode, 'en' );

			const brass = await computed( page, '.is-style-corex-band-brass' );
			const card = await computed( page, '.cx134-brass-card' );
			const cardText = await computed( page, '.cx134-brass-card-text' );
			const search = await computed(
				page,
				'.is-style-corex-band-brass .wp-block-search__button'
			);

			expect( card.background ).toBe( custom[ 'band-ink' ] );
			expect( cardText.color ).toBe( custom[ 'on-accent' ] );
			expect(
				contrast( cardText.color, card.background )
			).toBeGreaterThanOrEqual( 4.5 );
			expect( search ).toMatchObject( {
				background: custom[ 'on-accent' ],
				color: custom[ 'band-ink' ],
			} );
			expect(
				contrast( search.background, brass.background )
			).toBeGreaterThanOrEqual( 3 );
			expect(
				contrast( search.color, search.background )
			).toBeGreaterThanOrEqual( 4.5 );

			const email = page.locator( '.corex-newsletter-signup__email' );
			test.skip(
				( await email.count() ) === 0,
				'The newsletter add-on is not active, so its field is not drawn.'
			);
			const field = await computed(
				page,
				'.corex-newsletter-signup__email'
			);
			expect( field ).toMatchObject( {
				background: preset.accent,
				color: custom[ 'on-accent' ],
				border: custom[ 'on-accent' ],
			} );
			expect(
				contrast( field.color, field.background )
			).toBeGreaterThanOrEqual( 4.5 );
			expect(
				contrast( field.border, field.background )
			).toBeGreaterThanOrEqual( 3 );

			// Its submit is the band's plain button, graphite on brass, not brass on brass.
			const submit = await computed(
				page,
				'.corex-newsletter-signup__submit'
			);
			expect(
				contrast( submit.background, brass.background )
			).toBeGreaterThanOrEqual( 3 );
			expect(
				contrast( submit.color, submit.background )
			).toBeGreaterThanOrEqual( 4.5 );
		} );

		test( `the secondary on brass is graphite text and edge with nothing behind, in the ${ mode } style`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			const { custom } = MODES[ mode ];
			await open( page, context, baseURL, mode, 'en' );

			expect(
				await computed( page, '.cx134-brass-secondary' )
			).toMatchObject( {
				background: 'rgba(0, 0, 0, 0)',
				color: custom[ 'on-accent' ],
				border: custom[ 'on-accent' ],
			} );
		} );

		test( `a band placed directly inside the other keeps its own readable colours, both ways, in the ${ mode } style`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			await open( page, context, baseURL, mode, 'en' );

			for ( const inner of [ 'brass-in-dark', 'dark-in-brass' ] ) {
				const band = await computed( page, `.cx134-${ inner }` );
				const heading = await computed(
					page,
					`.cx134-${ inner }-heading`
				);
				const button = await computed(
					page,
					`.cx134-${ inner }-button`
				);

				expect(
					contrast( heading.color, band.background ),
					`${ inner }: heading`
				).toBeGreaterThanOrEqual( 4.5 );
				expect(
					contrast( button.background, band.background ),
					`${ inner }: button`
				).toBeGreaterThanOrEqual( 3 );
				expect(
					contrast( button.color, button.background ),
					`${ inner }: label`
				).toBeGreaterThanOrEqual( 4.5 );
			}
		} );

		test( `a disabled button keeps the theme's disabled look inside a band and as a secondary, in the ${ mode } style`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			const { preset, custom } = MODES[ mode ];
			await open( page, context, baseURL, mode, 'en' );

			expect(
				await computed( page, '.cx134-band-disabled' )
			).toMatchObject( {
				background: custom[ 'band-line' ],
				color: custom[ 'band-ink-soft' ],
				cursor: 'not-allowed',
			} );
			expect(
				await computed( page, '.cx134-brass-disabled' )
			).toMatchObject( {
				background: custom[ 'accent-hover' ],
				color: custom[ 'on-accent' ],
				cursor: 'not-allowed',
			} );
			expect(
				await computed( page, '.cx134-secondary-disabled' )
			).toMatchObject( {
				background: preset[ 'surface-strong' ],
				color: preset[ 'ink-soft' ],
				cursor: 'not-allowed',
			} );
		} );
	}
} );

test( 'the hero and call-to-action patterns are valid blocks in the editor (FR-015)', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/post-new.php?post_type=page' );
	await page.waitForFunction(
		() =>
			window.wp?.blocks?.getBlockType?.( 'core/group' ) &&
			window.wp?.blocks?.getBlockType?.( 'core/button' ),
		null,
		{ timeout: 45_000 }
	);
	const nonce = await page.evaluate( () => window.wpApiSettings?.nonce );
	const patterns = await (
		await page.request.get( '/wp-json/wp/v2/block-patterns/patterns', {
			headers: { 'X-WP-Nonce': nonce },
		} )
	).json();
	const content = Object.fromEntries(
		patterns
			.filter( ( { name } ) =>
				[ 'corex/hero', 'corex/cta' ].includes( name )
			)
			.map( ( { name, content: markup } ) => [ name, markup ] )
	);
	expect( Object.keys( content ).sort() ).toEqual( [
		'corex/cta',
		'corex/hero',
	] );

	const invalid = await page.evaluate( ( markups ) => {
		const found = [];
		const walk = ( name, blocks ) =>
			blocks.forEach( ( block ) => {
				if ( ! block.isValid ) {
					found.push( `${ name }: ${ block.name }` );
				}
				walk( name, block.innerBlocks );
			} );
		Object.entries( markups ).forEach( ( [ name, markup ] ) =>
			walk( name, window.wp.blocks.parse( markup ) )
		);
		return found;
	}, content );

	expect( invalid ).toEqual( [] );
} );
