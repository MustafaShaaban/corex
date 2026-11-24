/**
 * Corex E2E: the mega menu item, as a visitor and an editor meet it (spec 137 slice 3; US-NAV-02 but
 * its phone criterion, US-NAV-04; FR-009 to FR-019; SC-002, SC-007; DECISIONS #691, #705).
 *
 * A CoreX header holding a menu of six items, three of them mega items, each with its own panel, a
 * template part of the `mega-menu` area made from one of the panel patterns. Read in the browser:
 * one navigation and one list of six; each panel opened by the mouse, the keyboard and a tap; focus
 * order and its return; the bar's height before and after a panel opens; a panel's edges against
 * the window at 782 and 1280px, left to right and right to left; reduced motion; the panel without
 * the script. In the editor: the Navigation block accepts the item, and a new panel is made from a
 * panel pattern as the patterns' descriptions say. And SC-007: a header saved from
 * `corex/header-saas` at v0.48.1 (`fixtures/header-saas-v0.48.1.html`) still opens its panel and
 * its search. A child theme ships a panel as a file (`fixtures/themes/corex-e2e-panel-child`, drawn
 * for a request by `corex-e2e-child-theme.php`), and with the CoreX UI add-on off for a request
 * (`corex-e2e-addon-off.php`) nothing of the item is drawn, loaded or offered in the editor.
 *
 * Everything is made through the REST API under a mark of this run and removed after it.
 *
 * ENVIRONMENT-GATED: needs the site at COREX_BASE_URL with corex-ui on, the CoreX theme active, the
 * `corex-e2e-arabic.php`, `corex-e2e-child-theme.php` and `corex-e2e-addon-off.php` fixtures in its
 * mu-plugins and `npx playwright install`.
 */
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { test, expect } = require( '@playwright/test' );

const { STORAGE_STATE } = require( './global-setup' );

/** In the slug of everything made here, so it is known for this run's. */
const MARK = `cxmega${ ( Date.now() % 36 ** 4 ).toString( 36 ) }`;

/** The three mega items, each with the panel pattern its panel is made from. */
const MEGA = [
	{ label: 'Services', pattern: 'corex/megamenu-services' },
	{ label: 'Products', pattern: 'corex/megamenu-product' },
	{ label: 'Resources', pattern: 'corex/megamenu-docs' },
];
const LINKS = [ 'Work', 'About', 'Blog' ];

const HEADER = '.e2e-mega-header';
const OPEN_DELAY = 150;

const made = { parts: [], navigation: null, pages: [] };
let headerPath = null;
let savedSaasPath = null;
let childPanelPath = null;

/** The child of the CoreX theme that ships a panel as a file, and that panel's slug. */
const PANEL_CHILD = 'corex-e2e-panel-child';
const CHILD_PANEL = 'e2e-child-panel';

async function adminSession( browser ) {
	// With script, whatever the test that runs first asks of its own page.
	const context = await browser.newContext( {
		storageState: STORAGE_STATE,
		javaScriptEnabled: true,
	} );
	const page = await context.newPage();
	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate( () => window.wpApiSettings.nonce );

	return { context, page, nonce };
}

async function rest( session, method, route, data ) {
	const response = await session.page.request.fetch( `/wp-json${ route }`, {
		method,
		headers: { 'X-WP-Nonce': session.nonce },
		data,
	} );
	expect(
		response.ok(),
		`${ method } ${ route }: HTTP ${ response.status() }`
	).toBe( true );

	return response.json();
}

const slugOf = ( label ) => `${ MARK }-${ label.toLowerCase() }`;

/**
 * Three links for a panel. A panel pattern links a page only where the site has it, so on a site
 * without those pages its entries are text; these give the keyboard checks links to move among.
 *
 * @param {string} label The item's label.
 * @return {string} A paragraph block holding the links.
 */
function panelLinks( label ) {
	const links = [ 'one', 'two', 'three' ]
		.map(
			( word ) =>
				`<a href="/${ label.toLowerCase() }-${ word }/">${ label } ${ word }</a>`
		)
		.join( ' ' );

	return `<!-- wp:paragraph --><p>${ links }</p><!-- /wp:paragraph -->`;
}

/**
 * A CoreX header holding the menu, as the theme's header part draws one. The Navigation block holds
 * its items itself: a published menu would become WordPress's fallback menu for every header
 * without one, on the pages other specs read while this one runs.
 *
 * @param {string} items The menu's items, as block markup.
 * @return {string} The page's content.
 */
function headerMarkup( items ) {
	return [
		'<!-- wp:group {"tagName":"header","align":"full","className":"corex-header corex-header--simple e2e-mega-header","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->',
		'<header class="wp-block-group alignfull corex-header corex-header--simple e2e-mega-header" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">',
		'<!-- wp:group {"className":"corex-header__inner","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap","verticalAlignment":"center"}} --><div class="wp-block-group corex-header__inner">',
		'<!-- wp:site-title {"level":0} /-->',
		'<!-- wp:group {"className":"corex-header__actions","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} --><div class="wp-block-group corex-header__actions">',
		`<!-- wp:navigation {"overlayMenu":"mobile","className":"corex-header__nav","layout":{"type":"flex"}} -->${ items }<!-- /wp:navigation -->`,
		'</div><!-- /wp:group --></div><!-- /wp:group --></header><!-- /wp:group -->',
		'<!-- wp:paragraph --><p class="e2e-mega-after">Content under the header.</p><!-- /wp:paragraph -->',
	].join( '' );
}

test.use( { storageState: { cookies: [], origins: [] } } );

test.beforeAll( async ( { browser } ) => {
	const session = await adminSession( browser );
	const patterns = await rest(
		session,
		'GET',
		'/wp/v2/block-patterns/patterns'
	);
	const contentOf = ( name ) =>
		patterns.find( ( pattern ) => pattern.name === name )?.content;

	for ( const { label, pattern } of MEGA ) {
		expect( contentOf( pattern ), `the pattern ${ pattern }` ).toBeTruthy();
		const part = await rest( session, 'POST', '/wp/v2/template-parts', {
			slug: slugOf( label ),
			title: `${ MARK } ${ label }`,
			area: 'mega-menu',
			content: contentOf( pattern ) + panelLinks( label ),
		} );
		made.parts.push( part.id );
	}

	const items = [
		...MEGA.map(
			( { label } ) =>
				`<!-- wp:corex/mega-menu ${ JSON.stringify( { label, panel: slugOf( label ) } ) } /-->`
		),
		...LINKS.map(
			( label ) =>
				`<!-- wp:navigation-link ${ JSON.stringify( { label, url: `/${ label.toLowerCase() }/`, kind: 'custom' } ) } /-->`
		),
	].join( '' );
	// The same items as a menu for the editor, kept a draft: WordPress falls back to published menus only.
	made.navigation = (
		await rest( session, 'POST', '/wp/v2/navigation', {
			title: `${ MARK } menu`,
			status: 'draft',
			content: items,
		} )
	).id;

	const headerPage = await rest( session, 'POST', '/wp/v2/pages', {
		title: `${ MARK } mega header`,
		status: 'publish',
		content: headerMarkup( items ),
	} );
	const savedPage = await rest( session, 'POST', '/wp/v2/pages', {
		title: `${ MARK } saved SaaS header`,
		status: 'publish',
		content: fs.readFileSync(
			path.join( __dirname, 'fixtures', 'header-saas-v0.48.1.html' ),
			'utf8'
		),
	} );
	const childPage = await rest( session, 'POST', '/wp/v2/pages', {
		title: `${ MARK } child panel`,
		status: 'publish',
		content: headerMarkup(
			`<!-- wp:corex/mega-menu ${ JSON.stringify( {
				label: 'Child',
				panel: CHILD_PANEL,
			} ) } /-->` +
				'<!-- wp:navigation-link {"label":"Work","url":"/work/","kind":"custom"} /-->'
		),
	} );
	made.pages.push( headerPage.id, savedPage.id, childPage.id );
	headerPath = new URL( headerPage.link ).pathname;
	savedSaasPath = new URL( savedPage.link ).pathname;
	childPanelPath = new URL( childPage.link ).pathname;
	await session.context.close();
} );

test.afterAll( async ( { browser } ) => {
	const session = await adminSession( browser );
	for ( const id of made.pages ) {
		await rest( session, 'DELETE', `/wp/v2/pages/${ id }?force=true` );
	}
	if ( made.navigation ) {
		await rest(
			session,
			'DELETE',
			`/wp/v2/navigation/${ made.navigation }?force=true`
		);
	}
	for ( const id of made.parts ) {
		await rest(
			session,
			'DELETE',
			`/wp/v2/template-parts/${ id }?force=true`
		);
	}
	await session.context.close();
} );

/**
 * The header's triggers and panels, in the menu's order.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @return {Array<Object>} Each item's label, trigger and panel.
 */
function megaItems( page ) {
	const header = page.locator( HEADER );

	return MEGA.map( ( { label } ) => {
		const trigger = header.getByRole( 'button', {
			name: label,
			exact: true,
		} );

		return {
			label,
			trigger,
			panel: header.getByRole( 'group', { name: label, exact: true } ),
		};
	} );
}

async function openHeader( page, width = 1280 ) {
	await page.setViewportSize( { width, height: 900 } );
	await page.goto( headerPath );
	await expect(
		page.locator( `${ HEADER } [data-corex-mega-menu="ready"]` )
	).toHaveCount( MEGA.length );
}

async function expectOnlyOpen( items, open ) {
	for ( const item of items ) {
		const isOpen = item === open;
		await expect( item.trigger ).toHaveAttribute(
			'aria-expanded',
			String( isOpen )
		);
		if ( isOpen ) {
			await expect( item.panel ).toBeVisible();
		} else {
			await expect( item.panel ).toBeHidden();
		}
	}
}

test( 'SC-002, FR-011: three mega items among six are one navigation with one list of six items', async ( {
	page,
} ) => {
	await openHeader( page );
	const header = page.locator( HEADER );

	await expect( header.getByRole( 'navigation' ) ).toHaveCount( 1 );
	await expect(
		header
			.getByRole( 'list' )
			.filter( { has: page.locator( '.corex-mega-menu' ) } )
	).toHaveCount( 1 );
	await expect(
		header.locator( '.wp-block-navigation__container > li' )
	).toHaveCount( 6 );

	const controls = await header
		.locator( '.corex-mega-menu > button' )
		.evaluateAll( ( buttons ) =>
			buttons.map( ( button ) => button.getAttribute( 'aria-controls' ) )
		);
	expect( controls ).toHaveLength( 3 );
	expect( new Set( controls ).size ).toBe( 3 );
	for ( const item of megaItems( page ) ) {
		await expect( item.trigger ).toHaveAttribute(
			'aria-expanded',
			'false'
		);
		await expect( item.panel ).toBeHidden();
	}
} );

test( 'SC-002, US-NAV-04 criterion 1: the mouse resting on each item opens its own panel, and leaving closes it', async ( {
	page,
} ) => {
	await openHeader( page );
	const items = megaItems( page );

	for ( const item of items ) {
		await item.trigger.hover();
		await expectOnlyOpen( items, item );
	}

	await page.mouse.move( 5, 880 );
	await expectOnlyOpen( items, null );
} );

test( 'US-NAV-04 criterion 1: a mouse that passes over an item opens nothing', async ( {
	page,
} ) => {
	await openHeader( page );
	const [ services ] = megaItems( page );
	const box = await services.trigger.boundingBox();

	await page.mouse.move( box.x + box.width / 2, box.y + box.height / 2 );
	await page.mouse.move( 5, 880 );
	await page.waitForTimeout( OPEN_DELAY * 3 );

	await expect( services.trigger ).toHaveAttribute(
		'aria-expanded',
		'false'
	);
} );

test( 'SC-002, US-NAV-04 criteria 3 and 5: each panel opens by the keyboard; arrows move in it; Escape returns focus', async ( {
	page,
} ) => {
	await openHeader( page );
	const items = megaItems( page );

	for ( const item of items ) {
		await item.trigger.focus();
		await page.keyboard.press( 'Enter' );
		await expectOnlyOpen( items, item );
		await expect( item.trigger ).toHaveAttribute(
			'aria-controls',
			await item.panel.getAttribute( 'id' )
		);

		await page.keyboard.press( 'ArrowDown' );
		const first = item.panel.locator( 'a[href], button' ).first();
		await expect( first ).toBeFocused();
		await page.keyboard.press( 'End' );
		await expect(
			item.panel.locator( 'a[href], button' ).last()
		).toBeFocused();
		await page.keyboard.press( 'Home' );
		await expect( first ).toBeFocused();

		await page.keyboard.press( 'Escape' );
		await expectOnlyOpen( items, null );
		await expect( item.trigger ).toBeFocused();
	}
} );

test( 'US-NAV-04 criterion 2: the focus order runs from a trigger into its panel, and leaving the panel closes it', async ( {
	page,
} ) => {
	await openHeader( page );
	const [ services, products ] = megaItems( page );
	const links = services.panel.locator( 'a[href], button' );

	await services.trigger.focus();
	await page.keyboard.press( 'Enter' );
	await page.keyboard.press( 'Tab' );
	await expect( links.first() ).toBeFocused();

	const count = await links.count();
	for ( let index = 1; index < count; index++ ) {
		await page.keyboard.press( 'Tab' );
	}
	await expect( links.last() ).toBeFocused();

	await page.keyboard.press( 'Tab' );
	await expect( products.trigger ).toBeFocused();
	await expect( services.trigger ).toHaveAttribute(
		'aria-expanded',
		'false'
	);
	await expect( services.panel ).toBeHidden();
} );

test( "US-NAV-04 criterion 3: Right and Left move among the menu's items, mirrored right to left", async ( {
	page,
	context,
	baseURL,
} ) => {
	await openHeader( page );
	const [ services, products ] = megaItems( page );

	await services.trigger.focus();
	await page.keyboard.press( 'ArrowRight' );
	await expect( products.trigger ).toBeFocused();

	await context.addCookies( [
		{ name: 'corex_e2e_locale', value: 'ar', url: baseURL },
	] );
	await openHeader( page );
	await services.trigger.focus();
	await page.keyboard.press( 'ArrowLeft' );
	await expect( products.trigger ).toBeFocused();
} );

test.describe( 'on a touch screen', () => {
	test.use( { hasTouch: true } );

	test( 'SC-002, US-NAV-04 criterion 6: a tap opens each panel and a second tap closes it', async ( {
		page,
	} ) => {
		await openHeader( page );
		const items = megaItems( page );

		for ( const item of items ) {
			await item.trigger.tap();
			await expectOnlyOpen( items, item );
		}

		const last = items[ items.length - 1 ];
		await last.trigger.tap();
		await expectOnlyOpen( items, null );
	} );
} );

for ( const width of [ 782, 1280 ] ) {
	for ( const locale of [ 'en', 'ar' ] ) {
		const direction = locale === 'ar' ? 'right to left' : 'left to right';

		test( `FR-014, US-NAV-02 criterion 4: at ${ width }px, ${ direction }, every panel opens under the bar, inside the window, as wide as the header's content, and the bar keeps its height`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			if ( locale === 'ar' ) {
				await context.addCookies( [
					{ name: 'corex_e2e_locale', value: 'ar', url: baseURL },
				] );
			}
			await openHeader( page, width );
			expect(
				await page.evaluate(
					() => document.documentElement.dir || 'ltr'
				)
			).toBe( locale === 'ar' ? 'rtl' : 'ltr' );
			const header = page.locator( HEADER );
			const before = await header.boundingBox();

			for ( const item of megaItems( page ) ) {
				await item.trigger.click();
				await expect( item.panel ).toBeVisible();
				const read = await header.evaluate(
					( element, id ) => {
						const panel = document.getElementById( id );
						const card =
							panel.firstElementChild.getBoundingClientRect();
						const inner = element
							.querySelector( '.corex-header__inner' )
							.getBoundingClientRect();

						return {
							window: window.innerWidth,
							scroll: document.documentElement.scrollWidth,
							header: element.getBoundingClientRect().toJSON(),
							panelTop: panel.getBoundingClientRect().top,
							card: card.toJSON(),
							inner: inner.toJSON(),
							// Beside the card, at its middle: the page, not the panel's padding.
							beside: panel.contains(
								document.elementFromPoint(
									Math.max( 1, card.left - 8 ),
									card.top + card.height / 2
								)
							),
							bullets: [
								...panel.querySelectorAll( 'li' ),
							].filter(
								( li ) =>
									window.getComputedStyle( li )
										.listStyleType !== 'none'
							).length,
						};
					},
					await item.panel.getAttribute( 'id' )
				);
				const where = `${ item.label } at ${ width }px, ${ direction }`;

				expect(
					read.card.left,
					`${ where }: the panel's start edge is in the window`
				).toBeGreaterThanOrEqual( 0 );
				expect(
					read.card.right,
					`${ where }: the panel's end edge is in the window`
				).toBeLessThanOrEqual( read.window );
				expect(
					read.scroll,
					`${ where }: nothing scrolls sideways`
				).toBeLessThanOrEqual( read.window );
				expect(
					Math.abs( read.card.left - read.inner.left ),
					`${ where }: starts where the header's content starts`
				).toBeLessThanOrEqual( 1 );
				expect(
					Math.abs( read.card.right - read.inner.right ),
					`${ where }: ends where the header's content ends`
				).toBeLessThanOrEqual( 1 );
				expect(
					read.panelTop,
					`${ where }: opens under the bar, not over its padding`
				).toBeGreaterThanOrEqual( read.header.bottom - 0.5 );
				expect( read.bullets, `${ where }: no list bullets` ).toBe( 0 );
				expect(
					read.beside,
					`${ where }: beside the card the pointer reaches the page, not the panel`
				).toBe( false );
				expect(
					read.header.height,
					`${ where }: the bar keeps its height`
				).toBe( before.height );
			}
		} );
	}
}

test( "US-NAV-04 criterion 4: the trigger is drawn in the menu's own face, and its caret turns when open", async ( {
	page,
	context,
	baseURL,
} ) => {
	for ( const locale of [ 'en', 'ar' ] ) {
		if ( locale === 'ar' ) {
			await context.addCookies( [
				{ name: 'corex_e2e_locale', value: 'ar', url: baseURL },
			] );
		}
		await openHeader( page );
		const [ services ] = megaItems( page );
		const faces = await page.locator( HEADER ).evaluate( ( header ) => {
			const face = ( element ) =>
				window.getComputedStyle( element ).fontFamily;

			return {
				trigger: face(
					header.querySelector( '.corex-mega-menu__trigger' )
				),
				link: face(
					header.querySelector( '.wp-block-navigation-link a' )
				),
			};
		} );
		expect(
			faces.trigger,
			`${ locale }: the trigger and the links share a face`
		).toBe( faces.link );
		expect( faces.trigger ).toContain(
			locale === 'ar' ? 'IBM Plex Sans Arabic' : 'Space Grotesk'
		);

		const caret = services.trigger.locator( '.corex-mega-menu__caret' );
		await expect( caret ).toHaveCSS( 'transform', 'none' );
		await services.trigger.click();
		await expect( caret ).not.toHaveCSS( 'transform', 'none' );
	}
} );

test( 'US-NAV-04 criterion 7: with reduced motion set, the caret turns without a transition', async ( {
	page,
} ) => {
	await page.emulateMedia( { reducedMotion: 'reduce' } );
	await openHeader( page );
	const caret = megaItems( page )[ 0 ].trigger.locator(
		'.corex-mega-menu__caret'
	);
	await expect( caret ).toHaveCSS( 'transition-duration', '0s' );

	await page.emulateMedia( { reducedMotion: 'no-preference' } );
	await expect( caret ).not.toHaveCSS( 'transition-duration', '0s' );
} );

test.describe( 'without the script', () => {
	test.use( { javaScriptEnabled: false } );

	test( 'FR-016: a panel opens while the pointer or the focus is inside its item', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.goto( headerPath );
		const [ services ] = megaItems( page );

		await expect( services.panel ).toBeHidden();
		await services.trigger.hover();
		await expect( services.panel ).toBeVisible();

		await page.mouse.move( 5, 880 );
		await expect( services.panel ).toBeHidden();

		await services.trigger.focus();
		await expect( services.panel ).toBeVisible();
	} );

	test( 'UI review, FR-016: a mouse moves from the trigger down to the card with no script, and the panel stays open', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.goto( headerPath );
		const [ services ] = megaItems( page );
		const trigger = await services.trigger.boundingBox();
		const x = trigger.x + trigger.width / 2;

		await page.mouse.move( x, trigger.y + trigger.height / 2 );
		await expect( services.panel ).toBeVisible();
		const card = await services.panel
			.locator( '.wp-block-template-part' )
			.boundingBox();

		// Straight down, a pixel at a time, across the band between the item and the card.
		for (
			let y = trigger.y + trigger.height / 2;
			y <= card.y + 20;
			y += 2
		) {
			await page.mouse.move( x, y );
		}

		await expect( services.panel ).toBeVisible();
	} );
} );

/**
 * The colour, size and weight a token resolves to on the page, read from a probe element.
 *
 * @param {import('@playwright/test').Page} page   The page.
 * @param {Object}                          tokens Property names by key, each a CSS value.
 * @return {Promise<Object>} What each one computes to, by key.
 */
function resolveTokens( page, tokens ) {
	return page.locator( HEADER ).evaluate( ( header, values ) => {
		const probe = document.createElement( 'span' );
		header.appendChild( probe );
		const read = Object.fromEntries(
			Object.entries( values ).map( ( [ name, [ property, value ] ] ) => {
				probe.style.setProperty( property, value );
				const computed = window
					.getComputedStyle( probe )
					.getPropertyValue( property );
				probe.style.removeProperty( property );
				return [ name, computed ];
			} )
		);
		probe.remove();
		return read;
	}, tokens );
}

const TOKENS = {
	inkSoft: [ 'color', 'var(--wp--preset--color--ink-soft)' ],
	ink: [ 'color', 'var(--wp--preset--color--ink)' ],
	surfaceAlt: [ 'background-color', 'var(--wp--preset--color--surface-alt)' ],
	accentSubtle: [
		'background-color',
		'var(--wp--custom--color--accent-subtle)',
	],
	accentDark: [ 'color', 'var(--wp--preset--color--accent-dark)' ],
	xs: [ 'font-size', 'var(--wp--preset--font-size--xs)' ],
};

test( "UI review: every item of the bar is the kit's bar link, so the row has one rhythm", async ( {
	page,
} ) => {
	await openHeader( page );
	const tokens = await resolveTokens( page, TOKENS );
	const row = await page.locator( HEADER ).evaluate( ( header ) => {
		const list = header.querySelector( '.wp-block-navigation__container' );
		const controls = [ ...list.children ].map( ( item ) =>
			item.querySelector( ':scope > a, :scope > button' )
		);

		return {
			gap: window.getComputedStyle( list ).columnGap,
			items: controls.map( ( control ) => {
				const style = window.getComputedStyle( control );
				return {
					name: control.textContent.trim(),
					height: control.getBoundingClientRect().height,
					start: style.paddingInlineStart,
					end: style.paddingInlineEnd,
					weight: style.fontWeight,
					color: style.color,
					face: style.fontFamily,
				};
			} ),
		};
	} );

	expect( row.gap, 'the gap between items, the kit list gap' ).toBe( '4px' );
	expect( row.items ).toHaveLength( 6 );
	for ( const item of row.items ) {
		expect( item, `${ item.name } at rest` ).toEqual( {
			name: item.name,
			height: 40,
			start: '12px',
			end: '12px',
			weight: '500',
			color: tokens.inkSoft,
			face: row.items[ 0 ].face,
		} );
	}
} );

test( 'UI review: a plain link and a trigger take the same hover, ink on the quiet ground', async ( {
	page,
} ) => {
	await openHeader( page );
	const tokens = await resolveTokens( page, TOKENS );
	const header = page.locator( HEADER );
	const link = header.locator( '.wp-block-navigation-link a' ).first();
	const [ services ] = megaItems( page );

	await link.hover();
	await expect( link ).toHaveCSS( 'color', tokens.ink );
	await expect( link ).toHaveCSS( 'background-color', tokens.surfaceAlt );

	await services.trigger.hover();
	await expect( services.trigger ).toHaveCSS( 'color', tokens.ink );
	await expect( services.trigger ).toHaveCSS(
		'background-color',
		tokens.surfaceAlt
	);
} );

test( "UI review: the services panel's badge is the kit's accent badge", async ( {
	page,
} ) => {
	await openHeader( page );
	const tokens = await resolveTokens( page, TOKENS );
	const [ services ] = megaItems( page );
	await services.trigger.click();
	const badge = services.panel.locator( '.corex-badge' );

	await expect( badge ).toHaveText( 'New' );
	await expect( badge ).toHaveCSS( 'background-color', tokens.accentSubtle );
	await expect( badge ).toHaveCSS( 'color', tokens.accentDark );
	await expect( badge ).toHaveCSS( 'font-size', tokens.xs );
	await expect( badge ).toHaveCSS( 'padding-inline-start', '12px' );
	expect( ( await badge.boundingBox() ).height ).toBe( 24 );
	expect(
		parseFloat(
			await badge.evaluate(
				( element ) =>
					window.getComputedStyle( element ).borderTopLeftRadius
			)
		)
	).toBeGreaterThanOrEqual( 12 );
	await expect( services.panel.locator( '.corex-mega__badge' ) ).toHaveCount(
		0
	);
} );

test( "UI review: the product panel's side card is an eyebrow, a title, a line and a link, with no button", async ( {
	page,
} ) => {
	await openHeader( page );
	const [ , products ] = megaItems( page );
	await products.trigger.click();
	const card = products.panel.locator( '.corex-megamenu__feature' );

	await expect( card.locator( '.corex-eyebrow' ) ).toHaveText( 'Guide' );
	await expect( card.locator( '.corex-megamenu__title' ) ).toHaveText(
		'How to brief a web project'
	);
	await expect( card.locator( '.corex-megamenu__text' ) ).toHaveCount( 1 );
	await expect( card.locator( '.wp-block-button' ) ).toHaveCount( 0 );
} );

test( 'SC-007: a header saved from corex/header-saas at v0.48.1 still opens its panel and its search', async ( {
	page,
} ) => {
	await page.setViewportSize( { width: 1280, height: 900 } );
	await page.goto( savedSaasPath );
	const header = page.locator( '.corex-header--saas' ).last();

	const summary = header.locator( 'details.corex-mega > summary' );
	await summary.click();
	await expect( header.locator( 'details.corex-mega' ) ).toHaveAttribute(
		'open',
		''
	);
	await expect( header.locator( '.corex-mega__panel' ) ).toBeVisible();

	const toggle = header.locator( '[data-corex-search-toggle]' );
	await expect( toggle ).toBeVisible();
	await toggle.click();
	await expect( toggle ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect(
		header.locator( '#corex-header-search-field' )
	).toBeFocused();
} );

test( 'FR-010, Edge Cases: a child theme ships a panel as a file, declared in its theme.json in the mega-menu area', async ( {
	page,
	context,
	baseURL,
} ) => {
	await page.setViewportSize( { width: 1280, height: 900 } );

	// Under the CoreX theme there is no such part: the item is not drawn.
	await page.goto( childPanelPath );
	await expect( page.locator( `${ HEADER } .corex-mega-menu` ) ).toHaveCount(
		0
	);
	await expect(
		page.locator( `${ HEADER } .wp-block-navigation__container > li` )
	).toHaveCount( 1 );

	await context.addCookies( [
		{ name: 'corex_e2e_child', value: PANEL_CHILD, url: baseURL },
	] );
	const response = await page.goto( childPanelPath );
	expect( response.headers()[ 'x-corex-e2e-child' ] ).toBe( PANEL_CHILD );
	const trigger = page
		.locator( HEADER )
		.getByRole( 'button', { name: 'Child', exact: true } );
	await trigger.click();
	await expect(
		page
			.locator( HEADER )
			.getByRole( 'group', { name: 'Child', exact: true } )
			.getByRole( 'link', {
				name: 'A panel the child theme ships as a file',
			} )
	).toBeVisible();
} );

test( 'FR-010: a child that lists its panel in templateParts repeats the CoreX header and footer entries, or loses their areas', async ( {
	browser,
	baseURL,
} ) => {
	// WordPress merges a child's `templateParts` with its parent's by position: a list holding only
	// the panel would take the place of the header's entry. The fixture repeats both, as the docs say.
	const session = await adminSession( browser );
	await session.context.addCookies( [
		{ name: 'corex_e2e_child', value: PANEL_CHILD, url: baseURL },
	] );
	const areaOf = async ( area ) =>
		(
			await rest( session, 'GET', `/wp/v2/template-parts?area=${ area }` )
		).map( ( part ) => part.id );

	expect( await areaOf( 'header' ) ).toContain( `${ PANEL_CHILD }//header` );
	expect( await areaOf( 'footer' ) ).toContain( `${ PANEL_CHILD }//footer` );
	expect( await areaOf( 'mega-menu' ) ).toContain(
		`${ PANEL_CHILD }//${ CHILD_PANEL }`
	);
	await session.context.close();
} );

test.describe( 'with the CoreX UI add-on off', () => {
	test( 'US-NAV-02 criterion 6: a menu holding mega items shows its other links, and nothing of the item loads', async ( {
		page,
		context,
		baseURL,
	} ) => {
		await context.addCookies( [
			{ name: 'corex_e2e_addon_off', value: 'corex-ui', url: baseURL },
		] );
		await page.setViewportSize( { width: 1280, height: 900 } );
		const response = await page.goto( headerPath );
		expect( response.headers()[ 'x-corex-e2e-addon-off' ] ).toBe(
			'corex-ui'
		);
		const html = await page.content();

		await expect(
			page.locator( `${ HEADER } .wp-block-navigation__container > li` )
		).toHaveCount( LINKS.length );
		await expect( page.locator( '.corex-mega-menu' ) ).toHaveCount( 0 );
		expect( html ).not.toContain( 'mega-menu/view' );
		expect( html ).not.toContain( 'corex-mega-menu-style' );
	} );

	test( 'US-NAV-02 criterion 6: the editor has no mega menu item and the Site Editor no panel area', async ( {
		browser,
		baseURL,
	} ) => {
		const session = await adminSession( browser );
		await session.context.addCookies( [
			{ name: 'corex_e2e_addon_off', value: 'corex-ui', url: baseURL },
		] );
		const themes = await rest(
			session,
			'GET',
			'/wp/v2/themes?status=active&_fields=default_template_part_areas'
		);
		const areas = themes[ 0 ].default_template_part_areas.map(
			( area ) => area.area
		);

		await session.page.goto( '/wp-admin/post-new.php?post_type=page' );
		await session.page.waitForFunction(
			() => window.wp?.blocks?.getBlockType?.( 'core/navigation' ),
			null,
			{ timeout: 45_000 }
		);
		const editor = await session.page.evaluate( () => ( {
			item: !! window.wp.blocks.getBlockType( 'corex/mega-menu' ),
			listed: window.wp.blocks
				.getBlockType( 'core/navigation' )
				.allowedBlocks.includes( 'corex/mega-menu' ),
		} ) );
		await session.context.close();

		expect( areas ).toContain( 'header' );
		expect( areas ).not.toContain( 'mega-menu' );
		expect( editor ).toEqual( { item: false, listed: false } );
	} );
} );

/**
 * The ids of the theme's parts in the `mega-menu` area, saved ones and files alike.
 *
 * @param {import('@playwright/test').APIRequestContext} request A request context on the admin's session.
 * @param {string}                                       nonce   The REST nonce.
 * @return {Promise<Array<string>>} The ids.
 */
async function megaMenuPartIds( request, nonce ) {
	const response = await request.get(
		'/wp-json/wp/v2/template-parts?area=mega-menu&per_page=100',
		{ headers: { 'X-WP-Nonce': nonce } }
	);

	return ( await response.json() ).map( ( part ) => part.id );
}

test.describe( 'in the editor', () => {
	test.use( { storageState: STORAGE_STATE } );

	/** The parts of the area before the test, so what it made is removed even if it fails early. */
	let before = null;

	test.afterEach( async ( { page } ) => {
		if ( ! before ) {
			return;
		}
		const nonce = await page.evaluate( () => window.wpApiSettings?.nonce );
		for ( const id of await megaMenuPartIds( page.request, nonce ) ) {
			if ( ! before.includes( id ) ) {
				await page.request.delete(
					`/wp-json/wp/v2/template-parts/${ id }?force=true`,
					{ headers: { 'X-WP-Nonce': nonce } }
				);
			}
		}
		before = null;
	} );

	test( 'FR-009, FR-013, FR-019: the Navigation block accepts the item, and a panel pattern makes a new panel as its description says', async ( {
		page,
	} ) => {
		page.on( 'dialog', ( dialog ) => dialog.accept() );
		await page.goto( '/wp-admin/post-new.php?post_type=page' );
		await page.waitForFunction(
			() =>
				window.wp?.blocks?.getBlockType?.( 'core/navigation' ) &&
				window.wp?.blocks?.getBlockType?.( 'corex/mega-menu' ),
			null,
			{ timeout: 45_000 }
		);
		const nonce = await page.evaluate( () => window.wpApiSettings?.nonce );
		before = await megaMenuPartIds( page.request, nonce );

		// The panel patterns are valid blocks, kept out of the inserter.
		const patterns = await (
			await page.request.get( '/wp-json/wp/v2/block-patterns/patterns', {
				headers: { 'X-WP-Nonce': nonce },
			} )
		).json();
		const panelPatterns = patterns.filter( ( pattern ) =>
			( pattern.block_types || [] ).includes(
				'core/template-part/mega-menu'
			)
		);
		expect(
			panelPatterns.map( ( pattern ) => pattern.name ).sort()
		).toEqual( [
			'corex/megamenu-docs',
			'corex/megamenu-product',
			'corex/megamenu-services',
			'corex/megamenu-simple',
		] );
		const invalid = await page.evaluate(
			( markups ) =>
				markups
					.flatMap( ( markup ) => window.wp.blocks.parse( markup ) )
					.filter( ( block ) => ! block.isValid )
					.map( ( block ) => block.name ),
			panelPatterns.map( ( pattern ) => pattern.content )
		);
		expect( invalid ).toEqual( [] );
		expect( panelPatterns.map( ( pattern ) => pattern.inserter ) ).toEqual(
			[ false, false, false, false ]
		);

		// The menu made for this run, in a Navigation block, once its items have loaded.
		const navigationClientId = await page.evaluate( ( navigationId ) => {
			const { data, blocks } = window.wp;
			data.dispatch( 'core/preferences' )?.set(
				'core/edit-post',
				'welcomeGuide',
				false
			);
			const navigation = blocks.createBlock( 'core/navigation', {
				ref: navigationId,
			} );
			data.dispatch( 'core/block-editor' ).insertBlocks( navigation );

			return navigation.clientId;
		}, made.navigation );
		const itemId = await page.waitForFunction(
			( parent ) => {
				const { select } = window.wp.data;
				const order =
					select( 'core/block-editor' ).getBlockOrder( parent );

				return (
					order.find(
						( id ) =>
							select( 'core/block-editor' ).getBlockName( id ) ===
							'corex/mega-menu'
					) || null
				);
			},
			navigationClientId,
			{ timeout: 30_000 }
		);
		const clientId = await itemId.jsonValue();

		// The item may go in a Navigation block, beside the menu's links, and nowhere else.
		const accepted = await page.evaluate( ( parent ) => {
			const { data, blocks } = window.wp;
			const editor = data.select( 'core/block-editor' );

			return {
				listed: blocks
					.getBlockType( 'core/navigation' )
					.allowedBlocks.includes( 'corex/mega-menu' ),
				insertable: editor.canInsertBlockType(
					'corex/mega-menu',
					parent
				),
				elsewhere: editor.canInsertBlockType( 'corex/mega-menu' ),
			};
		}, navigationClientId );
		expect( accepted.listed, 'the Navigation block lists the item' ).toBe(
			true
		);
		expect(
			accepted.insertable,
			'the item may go in a Navigation block'
		).toBe( true );
		expect( accepted.elsewhere, 'the item may go nowhere else' ).toBe(
			false
		);

		// A new page may open on WordPress's starter patterns; they are not this test's.
		const starter = page.getByRole( 'dialog', {
			name: 'Choose a pattern',
		} );
		if (
			await starter.waitFor( { timeout: 5_000 } ).then(
				() => true,
				() => false
			)
		) {
			await starter.getByRole( 'button', { name: 'Close' } ).click();
		}

		// Choose the Services item, then make a new panel from the simple panel pattern.
		await page.evaluate( ( id ) => {
			window.wp.data.dispatch( 'core/block-editor' ).selectBlock( id );
			window.wp.data
				.dispatch( 'core/edit-post' )
				?.openGeneralSidebar?.( 'edit-post/block' );
		}, clientId );

		await expect( page.getByLabel( 'Panel', { exact: true } ) ).toHaveValue(
			slugOf( 'Services' )
		);
		await expect(
			page.getByRole( 'button', { name: 'Edit panel' } )
		).toBeVisible();
		// Closed while the item has a panel; the pattern's description says where to find it.
		await page.getByRole( 'button', { name: 'New panel' } ).click();
		// The item still offers them, though the inserter does not.
		const offered = await page
			.getByLabel( 'Start from' )
			.locator( 'option' )
			.evaluateAll( ( options ) =>
				options.map( ( option ) => option.value ).filter( Boolean )
			);
		expect( offered.sort() ).toEqual( [
			'corex/megamenu-docs',
			'corex/megamenu-product',
			'corex/megamenu-services',
			'corex/megamenu-simple',
		] );
		await page
			.getByLabel( 'Start from' )
			.selectOption( 'corex/megamenu-simple' );
		await page.getByRole( 'button', { name: 'Create panel' } ).click();

		const chosen = await page.waitForFunction(
			( id ) => {
				const panel = window.wp.data
					.select( 'core/block-editor' )
					.getBlockAttributes( id ).panel;

				return panel.startsWith( 'megamenu-simple' ) ? panel : null;
			},
			clientId,
			{ timeout: 30_000 }
		);
		const slug = await chosen.jsonValue();
		const parts = await (
			await page.request.get(
				`/wp-json/wp/v2/template-parts?area=mega-menu&per_page=100`,
				{ headers: { 'X-WP-Nonce': nonce } }
			)
		).json();
		const part = parts.find( ( candidate ) => candidate.slug === slug );

		expect( part.area ).toBe( 'mega-menu' );
		expect( part.content.raw ).toContain( 'corex-megamenu--simple' );
	} );
} );
