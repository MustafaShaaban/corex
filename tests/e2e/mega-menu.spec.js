/**
 * Corex E2E: the mega menu item, as a visitor and an editor meet it (spec 137 slice 3; US-NAV-02 but
 * its phone criterion, US-NAV-04; FR-009 to FR-019; SC-002, SC-007; DECISIONS #691).
 *
 * A CoreX header holding a menu of six items, three of them mega items, each with its own panel, a
 * template part of the `mega-menu` area made from one of the panel patterns. Read in the browser:
 * one navigation and one list of six; each panel opened by the mouse, the keyboard and a tap; focus
 * order and its return; the bar's height before and after a panel opens; a panel's edges against
 * the window at 782 and 1280px, left to right and right to left; reduced motion; the panel without
 * the script. In the editor: the Navigation block accepts the item, and a new panel is made from a
 * panel pattern as the patterns' descriptions say. And SC-007: a header saved from
 * `corex/header-saas` at v0.48.1 (`fixtures/header-saas-v0.48.1.html`) still opens its panel and
 * its search.
 *
 * Everything is made through the REST API under a mark of this run and removed after it.
 *
 * ENVIRONMENT-GATED: needs the site at COREX_BASE_URL with corex-ui on, the CoreX theme active, the
 * `corex-e2e-arabic.php` fixture in its mu-plugins and `npx playwright install`.
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

async function adminSession( browser ) {
	const context = await browser.newContext( { storageState: STORAGE_STATE } );
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
	made.pages.push( headerPage.id, savedPage.id );
	headerPath = new URL( headerPage.link ).pathname;
	savedSaasPath = new URL( savedPage.link ).pathname;
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

test.describe( 'in the editor', () => {
	test.use( { storageState: STORAGE_STATE } );

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

		// The panel patterns are valid blocks.
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
		made.parts.push( part.id );

		expect( part.area ).toBe( 'mega-menu' );
		expect( part.content.raw ).toContain( 'corex-megamenu--simple' );
	} );
} );
