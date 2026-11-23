/**
 * Corex E2E: a CoreX header changes form at one width, its navigation breakpoint (spec 137,
 * slice 1; US-NAV-11; FR-001 to FR-005, FR-007 (c); SC-001; DECISIONS #692).
 *
 * WordPress folds a Navigation block below 600px; the theme used to switch its mega and search
 * panels at 768px; its token said 782px. Now the token decides for all of them. At one pixel
 * either side of it this reads the header as a visitor sees it and measures rather than looks:
 * below the token the menu button shows and the menu does not, a mega panel and the search panel
 * sit in place; from the token up the menu is inline, the button is gone, and both panels are
 * dropdowns. Nothing in the header is in the other form at either width.
 *
 * Twice: under the CoreX theme, whose token is 782px, and under a child of it whose own
 * `theme.json` sets 1024px and which copies no file of the CoreX theme
 * (`tests/e2e/fixtures/themes/corex-e2e-nav-child`, drawn for a request by the
 * `corex-e2e-child-theme.php` fixture).
 *
 * The header is read in the default header part (the front page) and in the SaaS header pattern,
 * the one that holds a mega panel and the search, on a page provisioned through the REST API. The
 * collapse rules are also read where they are drawn, on the page and in the editor's canvas, at
 * the token's width.
 *
 * ENVIRONMENT-GATED: needs the site at COREX_BASE_URL with the `corex-e2e-*.php` fixtures in its
 * mu-plugins, and `npx playwright install`.
 */
const { test, expect } = require( '@playwright/test' );

const { STORAGE_STATE } = require( './global-setup' );
const { wpEval } = require( './helpers' );

/** The child of the CoreX theme the fixture draws a request under. */
const CHILD = 'corex-e2e-nav-child';

/** Each theme the header is read under, with its token's width in pixels. */
const THEMES = [
	{ name: 'the CoreX theme', child: null, breakpoint: 782 },
	{
		name: `a child whose token is 1024px (${ CHILD })`,
		child: CHILD,
		breakpoint: 1024,
	},
];

const HEIGHT = 900;

/** In the title of the page made here, so it is known for this run's. */
const MARK = `cxnav${ ( Date.now() % 36 ** 4 ).toString( 36 ) }`;

/** Where each header is read, the SaaS page's filled in by `beforeAll`. */
const HEADERS = [
	{
		name: 'the default header part',
		path: '/',
		selector: '.corex-header--simple',
	},
	{
		name: 'the SaaS header pattern',
		path: null,
		selector: '.corex-header--saas',
		panels: true,
	},
];

let madePage = null;

/**
 * Open a page on the admin's session and read the REST nonce it carries.
 *
 * @param {import('@playwright/test').Browser} browser The browser.
 * @return {Promise<{context: import('@playwright/test').BrowserContext, page: import('@playwright/test').Page, nonce: string}>} The session.
 */
async function adminSession( browser ) {
	const context = await browser.newContext( { storageState: STORAGE_STATE } );
	const page = await context.newPage();
	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate( () => window.wpApiSettings.nonce );

	return { context, page, nonce };
}

/**
 * How the header is drawn right now: which of the two controls of its menu shows, where the menu
 * sits, and where its mega and search panels sit once opened.
 *
 * @param {import('@playwright/test').Page} page     The page.
 * @param {string}                          selector The header.
 * @return {Promise<Object>} What was read.
 */
function readHeader( page, selector ) {
	return page
		.locator( selector )
		.first()
		.evaluate( ( header ) => {
			const shown = ( element ) =>
				!! element &&
				window.getComputedStyle( element ).display !== 'none' &&
				element.getBoundingClientRect().width > 0;
			const position = ( element ) =>
				element ? window.getComputedStyle( element ).position : null;

			const menu = header.querySelector(
				'.wp-block-navigation__responsive-container'
			);
			const mega = header.querySelector( 'details.corex-mega' );
			if ( mega ) {
				mega.open = true;
			}
			const search = header.querySelector( '[data-corex-search-panel]' );
			if ( search ) {
				search.removeAttribute( 'hidden' );
			}

			return {
				width: window.innerWidth,
				button: shown(
					header.querySelector(
						'.wp-block-navigation__responsive-container-open'
					)
				),
				menu: shown( menu ),
				menuPosition: position( menu ),
				links: [
					...header.querySelectorAll(
						'.wp-block-navigation__responsive-container .wp-block-navigation-item__content'
					),
				].filter( shown ).length,
				mega: position( mega?.querySelector( '.corex-mega__panel' ) ),
				search: position( search ),
			};
		} );
}

/**
 * The media conditions of the collapse stylesheet in a document, served as a file or inline.
 *
 * @param {import('@playwright/test').Page|import('@playwright/test').Frame} target The document.
 * @return {Promise<string[]>} Each condition as the browser reads it.
 */
function collapseConditions( target ) {
	return target.evaluate( () =>
		[ ...document.styleSheets ]
			.filter( ( sheet ) =>
				( sheet.ownerNode?.id ?? '' ).startsWith(
					'corex-navigation-collapse'
				)
			)
			.flatMap( ( sheet ) => [ ...sheet.cssRules ] )
			.filter( ( rule ) => rule.conditionText )
			.map( ( rule ) => rule.conditionText )
	);
}

/**
 * The two conditions the collapse rules hold at a breakpoint: folded between WordPress's 600px and
 * the token, the desktop form from the token up.
 *
 * @param {number} breakpoint The token's width in pixels.
 * @return {string[]} The conditions.
 */
const expectedConditions = ( breakpoint ) => [
	`(600px <= width < ${ breakpoint }px)`,
	`(width >= ${ breakpoint }px)`,
];

test.use( { storageState: { cookies: [], origins: [] } } );

test.beforeAll( async ( { browser } ) => {
	const session = await adminSession( browser );
	const response = await session.page.request.post( '/wp-json/wp/v2/pages', {
		headers: { 'X-WP-Nonce': session.nonce },
		data: {
			title: `${ MARK } SaaS header`,
			content: '<!-- wp:pattern {"slug":"corex/header-saas"} /-->',
			status: 'publish',
		},
	} );
	expect(
		response.ok(),
		`publishing the SaaS header page: HTTP ${ response.status() }`
	).toBe( true );
	madePage = await response.json();
	HEADERS[ 1 ].path = new URL( madePage.link ).pathname;
	await session.context.close();
} );

/**
 * PHP that removes what WordPress stores for the fixture child, and says how much of it is left.
 *
 * The editor opened under the child makes WordPress write the child's Global Styles post
 * (`wp-global-styles-corex-e2e-nav-child`) and the `wp_theme` term that names the child. Any post
 * that term holds goes with it: the child's Global Styles, and a template or part saved for it.
 */
const REMOVE_CHILD_RECORDS = `
$theme = ${ JSON.stringify( CHILD ) };
$held = static fn () => get_posts( array(
	'post_type'   => array( 'wp_global_styles', 'wp_template', 'wp_template_part' ),
	'post_status' => array_keys( get_post_stati() ),
	'numberposts' => -1,
	'fields'      => 'ids',
	'tax_query'   => array( array( 'taxonomy' => 'wp_theme', 'field' => 'slug', 'terms' => $theme ) ),
) );
foreach ( $held() as $id ) {
	wp_delete_post( (int) $id, true );
}
$term = get_term_by( 'slug', $theme, 'wp_theme' );
if ( $term ) {
	wp_delete_term( $term->term_id, 'wp_theme' );
}
echo count( $held() ) + ( get_term_by( 'slug', $theme, 'wp_theme' ) ? 1 : 0 );
`;

test.afterAll( async ( { browser } ) => {
	if ( madePage ) {
		const session = await adminSession( browser );
		await session.page.request.delete(
			`/wp-json/wp/v2/pages/${ madePage.id }?force=true`,
			{ headers: { 'X-WP-Nonce': session.nonce } }
		);
		await session.context.close();
	}

	// Through WP-CLI, on the install COREX_WP_PATH names: no route removes a Global Styles post.
	const left = wpEval( REMOVE_CHILD_RECORDS );
	if ( left === null ) {
		process.stderr.write(
			`
What WordPress stored for ${ CHILD } was not removed: WP-CLI did not answer.
`
		);
		return;
	}
	expect(
		left,
		`the records WordPress stored for ${ CHILD } left behind`
	).toBe( '0' );
} );

for ( const theme of THEMES ) {
	test.describe( `under ${ theme.name }`, () => {
		test.beforeEach( async ( { context, baseURL } ) => {
			if ( theme.child ) {
				await context.addCookies( [
					{
						name: 'corex_e2e_child',
						value: theme.child,
						url: baseURL,
					},
				] );
			}
		} );

		for ( const header of HEADERS ) {
			test( `${ header.name } is in its phone form at ${
				theme.breakpoint - 1
			}px and its desktop form at ${
				theme.breakpoint
			}px (SC-001)`, async ( { page } ) => {
				const forms = [
					{ width: theme.breakpoint - 1, desktop: false },
					{ width: theme.breakpoint, desktop: true },
				];

				for ( const { width, desktop } of forms ) {
					await page.setViewportSize( { width, height: HEIGHT } );
					const response = await page.goto( header.path );
					expect(
						response.headers()[ 'x-corex-e2e-child' ] ?? null,
						'the theme the page is drawn under'
					).toBe( theme.child );
					await expect( page.locator( header.selector ) ).toHaveCount(
						1
					);

					const read = await readHeader( page, header.selector );
					const form = desktop ? 'desktop' : 'phone';
					const where = `${ header.name } at ${ read.width }px, in its ${ form } form`;

					expect( read.width, 'the window is as wide as asked' ).toBe(
						width
					);
					expect(
						read.button,
						`${ where }: the menu button shows`
					).toBe( ! desktop );
					expect( read.menu, `${ where }: the menu shows` ).toBe(
						desktop
					);

					if ( desktop ) {
						// WordPress's overlay is `fixed`. In the bar the menu is `relative`, or
						// `static` where the menu holds a mega menu item, whose panel hangs from
						// the header instead (DECISIONS #705).
						expect(
							read.menuPosition,
							`${ where }: the menu is in the bar, not an overlay`
						).not.toBe( 'fixed' );
						expect(
							read.links,
							`${ where }: the menu's links show`
						).toBeGreaterThan( 0 );
					}

					if ( header.panels ) {
						const panel = desktop ? 'absolute' : 'static';
						expect(
							read.mega,
							`${ where }: an open mega panel is ${
								desktop ? 'a dropdown' : 'in place'
							}`
						).toBe( panel );
						expect(
							read.search,
							`${ where }: the search panel is ${
								desktop ? 'a dropdown' : 'in place'
							}`
						).toBe( panel );
					}
				}
			} );
		}

		test( "the collapse rules on the page name the token's width (FR-004)", async ( {
			page,
		} ) => {
			await page.goto( '/' );

			expect( await collapseConditions( page ) ).toEqual(
				expectedConditions( theme.breakpoint )
			);
		} );

		test( "the collapse rules in the editor's canvas name the token's width (FR-004)", async ( {
			browser,
			baseURL,
		} ) => {
			const context = await browser.newContext( {
				storageState: STORAGE_STATE,
			} );
			if ( theme.child ) {
				await context.addCookies( [
					{
						name: 'corex_e2e_child',
						value: theme.child,
						url: baseURL,
					},
				] );
			}
			const page = await context.newPage();
			await page.goto( '/wp-admin/post-new.php?post_type=page' );
			await page
				.frameLocator( 'iframe[name="editor-canvas"]' )
				.locator( 'body' )
				.waitFor();

			await expect
				.poll( () =>
					collapseConditions(
						page.frame( { name: 'editor-canvas' } )
					)
				)
				.toEqual( expectedConditions( theme.breakpoint ) );
			await context.close();
		} );
	} );
}
