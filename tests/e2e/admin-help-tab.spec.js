/**
 * No contextual Help tab on any CoreX admin screen (spec 097).
 *
 * The unit tests prove the mechanism — that `remove_help_tabs()` is called, at the right hook, on
 * the right screens. Only a browser can answer the thing that was actually reported: that nothing
 * of the Help surface reaches the page, that removing it left no hole where it used to be, and that
 * wp-admin at large still has its own.
 *
 * Everything here asserts **absence**, which is a shape worth stating plainly: there is no click to
 * force and no panel to wait for, because a passing run is one where the markup was never emitted.
 * A test that opened the panel and asserted it was empty would pass on the defect.
 */

const { test, expect } = require( '@playwright/test' );
const { COREX_ROUTES } = require( './helpers' );

/**
 * Everything WordPress emits *because a screen has help*. All of it must be gone.
 *
 * `#contextual-help-wrap` is deliberately not in this list, and the distinction is the whole
 * mechanism rather than a concession. `WP_Screen::render_screen_meta()` emits that one empty
 * `<div class="hidden">` on **every** admin screen in WordPress, help or no help — it is the
 * container the panel would be rendered into. What is conditional on `get_help_tabs()` is
 * `#screen-meta-links`, `#contextual-help-link-wrap` and the tabs and panels themselves, and those
 * are what `remove_help_tabs()` takes away.
 *
 * So the wrap is asserted separately below, on the terms that actually matter: no tabs inside it,
 * nothing that can open it, and zero height. Asserting it absent instead would be asserting that
 * CoreX had rewritten core's own markup, which it has not and should not.
 */
const HELP_SELECTORS = [
	'#screen-meta-links',
	'#contextual-help-link-wrap',
	'#contextual-help-link',
	'[id^="tab-link-"]',
	'[id^="tab-panel-"]',
	// The `<li>` entries, not the `<ul class="contextual-help-tabs">` around them — that list is
	// emitted unconditionally alongside the wrap, for the same reason and with the same
	// consequences (none). An entry in it is a tab; the empty list is not.
	'.contextual-help-tabs li',
];

/**
 * A CoreX route, addressed the way the rail addresses it.
 *
 * @param {string} slug The CoreX page slug.
 * @return {string} The admin path for that route.
 */
const routeUrl = ( slug ) => `/wp-admin/admin.php?page=${ slug }`;

/**
 * Assert that no usable part of WordPress's contextual help exists on the current page.
 *
 * @param {import('@playwright/test').Page} page  The page to check.
 * @param {string}                          where A label naming the case, for the failure message.
 */
async function expectNoHelpSurface( page, where ) {
	for ( const selector of HELP_SELECTORS ) {
		await expect(
			page.locator( selector ),
			`${ selector } present on ${ where }`
		).toHaveCount( 0 );
	}

	// And the container core always emits holds nothing and takes no space — so there is no
	// invisible panel and no band above the shell, which is the reported defect stated as geometry.
	const inert = await page.evaluate( () => {
		const wrap = document.querySelector( '#contextual-help-wrap' );
		const meta = document.querySelector( '#screen-meta' );
		const box = ( el ) =>
			el ? Math.round( el.getBoundingClientRect().height ) : 0;

		return {
			wrapHeight: box( wrap ),
			metaHeight: box( meta ),
			// `#contextual-help-back` is core's own empty div inside the wrap; anything beyond it
			// would be content somebody put in a panel that is supposed to be gone.
			wrapContent: wrap
				? wrap.textContent.replace( /\s+/g, '' ).length
				: 0,
		};
	} );

	expect( inert.wrapHeight, `help wrap occupies space on ${ where }` ).toBe(
		0
	);
	expect( inert.metaHeight, `screen-meta occupies space on ${ where }` ).toBe(
		0
	);
	expect( inert.wrapContent, `help wrap has content on ${ where }` ).toBe(
		0
	);
}

test.describe( 'CoreX admin carries no contextual Help tab', () => {
	test( 'no route emits a help link, wrapper or panel', async ( {
		page,
	} ) => {
		for ( const [ slug ] of COREX_ROUTES ) {
			const response = await page.goto( routeUrl( slug ) );
			expect( response.status(), `${ slug } responded` ).toBe( 200 );

			await expect( page.locator( '.corex-admin__shell' ) ).toBeVisible();
			await expectNoHelpSurface( page, slug );
		}
	} );

	test( 'there is no help toggle to open, by keyboard or by script', async ( {
		page,
	} ) => {
		await page.goto( routeUrl( 'corex-settings' ) );

		// The panel is opened by a `<button id="contextual-help-link">`. Asserting the button is
		// absent is the whole claim: a CSS-hidden button would still be in the DOM, still focusable
		// by script, and still able to expand the panel — which FR-002 exists to rule out.
		await expect( page.locator( '#contextual-help-link' ) ).toHaveCount(
			0
		);

		// Forcing core's own toggle by script still opens nothing, because there is nothing to
		// open. This is the case a CSS fix would fail: hide the button and the panel behind it is
		// still one `.show()` away.
		const afterForcing = await page.evaluate( () => {
			const wrap = document.querySelector( '#contextual-help-wrap' );
			if ( ! wrap ) {
				return { height: 0, tabs: 0 };
			}
			wrap.classList.remove( 'hidden' );
			return {
				height: Math.round( wrap.getBoundingClientRect().height ),
				tabs: wrap.querySelectorAll( '[id^="tab-panel-"]' ).length,
			};
		} );

		expect( afterForcing.tabs, 'panels behind the hidden wrap' ).toBe( 0 );
		expect(
			afterForcing.height,
			'forcing the wrap visible revealed a panel'
		).toBe( 0 );
	} );

	test( 'the shell starts where the page starts, with no blank band above it', async ( {
		page,
	} ) => {
		for ( const [ slug ] of COREX_ROUTES ) {
			await page.goto( routeUrl( slug ) );
			await expect( page.locator( '.corex-admin__shell' ) ).toBeVisible();

			const gap = await page.evaluate( () => {
				const shell = document.querySelector( '.corex-admin__shell' );
				const bar = document.querySelector( '#wpadminbar' );
				const barBottom = bar ? bar.getBoundingClientRect().bottom : 0;
				const meta = document.querySelector( '#screen-meta' );

				return {
					// How far the product starts below the admin bar. The Help panel used to open
					// here and push everything down; with it gone there is nothing between them.
					belowBar: Math.round(
						shell.getBoundingClientRect().top - barBottom
					),
					// A wrapper left behind with no content still occupies its own height. Zero or
					// absent are both fine; anything else is the hole this spec exists to prevent.
					metaHeight: meta
						? Math.round( meta.getBoundingClientRect().height )
						: 0,
				};
			} );

			expect(
				gap.belowBar,
				`${ slug } shell offset below admin bar`
			).toBeLessThanOrEqual( 1 );
			expect( gap.metaHeight, `${ slug } screen-meta height` ).toBe( 0 );
		}
	} );
} );

test.describe( 'the removal holds in every appearance a reader can be in', () => {
	// Light and dark, both directions, four widths, and 200% zoom. The matrix is not decoration:
	// a CSS-based "fix" would pass some of these and fail others, and a wrapper that only shows in
	// RTL is exactly the kind of thing this repository has shipped before.
	for ( const dir of [ 'ltr', 'rtl' ] ) {
		for ( const scheme of [ 'light', 'dark' ] ) {
			test( `no help surface in ${ scheme } ${ dir } at any width`, async ( {
				page,
			} ) => {
				await page.emulateMedia( { colorScheme: scheme } );

				for ( const width of [ 375, 768, 1024, 1440 ] ) {
					await page.setViewportSize( { width, height: 900 } );
					await page.goto( routeUrl( 'corex-forms' ) );
					await page
						.locator( 'html' )
						.evaluate(
							( root, value ) =>
								root.setAttribute( 'dir', value ),
							dir
						);

					await expectNoHelpSurface(
						page,
						`${ scheme } ${ dir } ${ width }px`
					);
				}
			} );
		}
	}

	test( 'no help surface at 200% zoom', async ( { browser, baseURL } ) => {
		// A real zoom, not a smaller viewport: `deviceScaleFactor` alone changes pixel density and
		// not layout. Halving the CSS viewport while doubling the scale factor is what a reader at
		// 200% actually gets, and it is the case where a leftover wrapper would be most visible.
		const context = await browser.newContext( {
			baseURL,
			viewport: { width: 640, height: 450 },
			deviceScaleFactor: 2,
		} );
		const page = await context.newPage();

		await page.goto( routeUrl( 'corex-settings' ) );
		await expect( page.locator( '.corex-admin__shell' ) ).toBeVisible();
		await expectNoHelpSurface( page, '200% zoom' );

		await context.close();
	} );
} );

test.describe( 'what the removal must not touch', () => {
	test( 'native WordPress screens keep their own Help tab and open it', async ( {
		page,
	} ) => {
		// The removal is scoped to CoreX screens by `CorexScreens::supports()`. If that scoping ever
		// broke, CoreX would be stripping help from the whole of wp-admin — a far worse defect than
		// the one it was written to fix, and one nothing else here would notice.
		for ( const path of [
			'/wp-admin/edit.php',
			'/wp-admin/options-general.php',
			'/wp-admin/plugins.php',
		] ) {
			await page.goto( path );

			const link = page.locator( '#contextual-help-link' );
			await expect( link, `${ path } help link` ).toBeVisible();

			await link.click();
			await expect(
				page.locator( '#contextual-help-wrap' ),
				`${ path } help panel`
			).toBeVisible();
		}
	} );

	test( 'the Guides screen still does everything it did', async ( {
		page,
	} ) => {
		await page.goto( routeUrl( 'corex-guides' ) );

		// Guides render, grouped, with topics that open to real steps.
		await expect(
			page.locator( '.corex-guides__guide' ).first()
		).toBeVisible();
		expect(
			await page.locator( '[data-corex-guide-section]' ).count()
		).toBeGreaterThan( 1 );

		const topic = page
			.locator( '#guide-corex-publishing .corex-guides__topic' )
			.first();
		await topic.locator( 'summary' ).click();
		await expect(
			topic.locator( '.corex-guides__step-instruction' ).first()
		).toBeVisible();

		// Search still narrows.
		await page.fill( '#corex-guides-search', 'category' );
		await expect( page.locator( '#guide-corex-publishing' ) ).toBeVisible();
		await expect( page.locator( '#guide-corex-submissions' ) ).toBeHidden();
		await page.fill( '#corex-guides-search', '' );

		// Screenshots still resolve — a broken image is a guide teaching a screen that is not there.
		//
		// Asserted by fetching the file rather than by reading `naturalWidth`. They are
		// `loading="lazy"` and most sit inside a collapsed `<details>`, so the browser has
		// legitimately not fetched them: `naturalWidth` is 0 for an image that is perfectly fine and
		// merely unrequested, which fails on a working screen and would have to be loosened into
		// something that no longer checks anything. The file being served is the actual claim.
		const shots = page.locator( '.corex-guides__shot' );
		expect( await shots.count() ).toBeGreaterThan( 0 );

		const src = await shots.first().getAttribute( 'src' );
		const asset = await page.request.get( src );
		expect( asset.status(), `guide screenshot ${ src }` ).toBe( 200 );

		// The support request panel is still on the screen people reach when stuck.
		await expect( page.locator( '#corex-guides-support' ) ).toBeVisible();
		await expect(
			page.locator( '.corex-guides__support-form' )
		).toBeVisible();
	} );

	test( 'a guide still links to the screen it describes', async ( {
		page,
	} ) => {
		// This is what `onScreen()` feeds now that the help tab is gone — the better half of spec
		// 084's US4, and the reason the address a guide declares was kept rather than deleted.
		await page.goto( routeUrl( 'corex-guides' ) );

		const link = page.locator( '.corex-guides__guide-screen a' ).first();
		await expect( link ).toBeVisible();

		const href = await link.getAttribute( 'href' );
		expect( href, 'guide screen link points into wp-admin' ).toMatch(
			/\/wp-admin\//
		);

		await link.click();
		// It lands on a real screen, not a 404 or an access refusal.
		await expect( page.locator( '#wpbody-content' ) ).toBeVisible();
		expect( page.url() ).toContain( '/wp-admin/' );
	} );

	test( 'a guide registered by a client plugin still appears', async ( {
		page,
	} ) => {
		// Registered through `corex_guides`, the seam a plugin with no container access uses, by
		// `tests/e2e/fixtures/corex-e2e-client-guide.php` — dropped into `mu-plugins` by the CI
		// workflow and by `scripts/setup-wordpress.ps1`. FR-008 is about somebody else's plugin, so
		// proving it with a Corex guide would prove nothing.
		//
		// Deliberately NOT skipped when the fixture is missing. A browser check that quietly opts
		// out when its fixture is absent reports green for the one condition it exists to catch —
		// which is how spec 092's link check let 85 broken links publish. A missing fixture is a
		// broken environment and should say so.
		await page.goto( routeUrl( 'corex-guides' ) );

		const clientGuide = page.locator( '#guide-e2e-client-guide' );
		await expect(
			clientGuide,
			'client-guide fixture missing — copy tests/e2e/fixtures/corex-e2e-client-guide.php into wp/wp-content/mu-plugins/'
		).toBeVisible();
		await expect( clientGuide ).toContainText( 'Client plugin guide' );

		// Its own capability gating still applies, and the screen it declares still links out.
		await expect(
			clientGuide.locator( '.corex-guides__guide-screen a' )
		).toHaveAttribute( 'href', /upload\.php/ );

		await expectNoHelpSurface( page, 'guides with a client guide' );
	} );
} );
