/**
 * Corex E2E — the theme keeps what a visitor reads off the edges of the window.
 *
 * On a phone the theme's content column is wider than the window, so the only space between a line
 * of text and the glass is the one the theme gives: its root padding, set in `theme.json` and
 * given by WordPress to every constrained block. Until it was set, the title, the text, the header
 * and the footer of every template began at pixel 0.
 *
 * So this reads every template the theme ships, and one page holding every pattern the site
 * registers, at a phone's width and at the narrowest window a page has to work in (320, WCAG 2.2,
 * 1.4.10), left to right and right to left, and measures rather than looks: how far the nearest
 * line of text is from each edge, and whether the page scrolls sideways. A gutter is also the
 * easiest way to make a page scroll sideways, by taking width from something that had only just
 * enough: the form's grid and the docs header's search field were two.
 *
 * It also holds what the gutter does to a block an editor sets to full width or to wide in a
 * page's content. On a phone the first runs from one edge of the window to the other with its own
 * text inset, and the second keeps the gutter. On a desktop the first is the window's width and
 * the second the theme's wide size, and the text column is where it was before there was a gutter.
 *
 * The pages are provisioned through the REST API (admin session) and read as a visitor.
 *
 * ENVIRONMENT-GATED: needs the site at COREX_BASE_URL + `npx playwright install`.
 */
const { test, expect } = require( '@playwright/test' );

const { STORAGE_STATE } = require( './global-setup' );

/**
 * The theme's root padding in pixels: spacing preset 40 as a page has it, which is 1rem.
 *
 * theme.json writes that preset as 1.5rem. WordPress keeps its own sizes for the slugs 20 to 80
 * until a theme sets `defaultSpacingSizes` to false, and this theme does not, so the number here
 * is the one measured and not the one written (DECISIONS #344).
 */
const GUTTER = 16;

/** `settings.layout` in theme.json: the content column and a wide block. */
const CONTENT_SIZE = 768;
const WIDE_SIZE = 1200;

const PHONES = [
	{ width: 375, height: 812 },
	{ width: 320, height: 640 },
];

const DESKTOP = { width: 1280, height: 720 };

const DIRECTIONS = [ 'ltr', 'rtl' ];

/**
 * In every title made here, so one search finds these posts and nothing else.
 *
 * Short on purpose. A search's title is a heading that repeats the words searched for, and a word
 * longer than the window does not break there: that is a defect of its own (PROGRESS.md, "Open,
 * and not hidden"), and not the one this file is about.
 */
const MARK = `cxg${ ( Date.now() % 36 ** 4 ).toString( 36 ) }`;

/** Words no post holds. */
const FINDS_NOTHING = 'qzxjvk';

/** Long enough to fill a line of the narrowest window from one side to the other. */
const SENTENCE =
	'A paragraph with enough words in it to run from one side of a narrow window to the other.';

/**
 * What a page made here holds: a paragraph, a group set to full width and one set to wide. Only a
 * template that prints the page's content shows them.
 */
const BODY = [
	`<!-- wp:paragraph --><p>${ SENTENCE }</p><!-- /wp:paragraph -->`,
	'<!-- wp:group {"align":"full","className":"corex-gutter-full","layout":{"type":"constrained"}} -->',
	`<div class="wp-block-group alignfull corex-gutter-full"><!-- wp:paragraph --><p>${ SENTENCE }</p><!-- /wp:paragraph --></div>`,
	'<!-- /wp:group -->',
	'<!-- wp:group {"align":"wide","className":"corex-gutter-wide","layout":{"type":"constrained"}} -->',
	`<div class="wp-block-group alignwide corex-gutter-wide"><!-- wp:paragraph --><p>${ SENTENCE }</p><!-- /wp:paragraph --></div>`,
	'<!-- /wp:group -->',
].join( '' );

/** The page templates of `customTemplates` in theme.json, and the default one (no name). */
const PAGE_TEMPLATES = [
	'',
	'page-landing',
	'page-contact',
	'page-form',
	'page-about',
	'page-services',
	'page-account',
];

/** The page that holds every pattern, and the pattern whose search field had only just enough. */
const PATTERNS = 'every pattern';
const DOCS_HEADER = 'corex/header-docs';

/** Where each page is read, filled in by `beforeAll`. */
const paths = {
	'front-page': '/',
	search: `/?s=${ MARK }`,
	// With nothing found the search template prints the contact pattern, form and all.
	'search, nothing found': `/?s=${ FINDS_NOTHING }`,
	404: `/${ MARK }-no-such-page/`,
	'archive-corex_project': '/projects/',
};

/**
 * The class WordPress gives the `<body>` of each page read here, by which a page is known to be
 * the one it is read as. Without it a "Page not found" passes for any template: it has text, a
 * gutter and no sideways scroll, and it is what a project's address answered on the first run of
 * this file on a newly provisioned site.
 */
const BODY_CLASS = {
	'front-page': 'home',
	page: 'page-template-default',
	...Object.fromEntries(
		PAGE_TEMPLATES.filter( Boolean ).map( ( template ) => [
			template,
			`page-template-${ template }`,
		] )
	),
	[ PATTERNS ]: 'page-template-default',
	single: 'single-post',
	archive: 'category',
	search: 'search-results',
	'search, nothing found': 'search-no-results',
	404: 'error404',
	'single-corex_project': 'single-corex_project',
	'archive-corex_project': 'post-type-archive-corex_project',
};

/** Every template in theme/templates but `index`, which a site reaches only as its posts page. */
const TEMPLATES = [
	'front-page',
	'page',
	...PAGE_TEMPLATES.filter( Boolean ),
	'single',
	'archive',
	'search',
	'search, nothing found',
	'404',
	'single-corex_project',
	'archive-corex_project',
];

/** The templates that print the content of the page or post they show. */
const PRINTS_CONTENT = [
	'page',
	'page-form',
	'single',
	'single-corex_project',
];

/**
 * The date of the post and the project made here: the last of any list by date, so a spec running
 * beside this one that takes a site's newest post does not take one that is about to be removed.
 */
const LONG_AGO = { date: '2001-01-01T12:00:00' };

/** What `afterAll` removes, as REST collection and id. */
const made = [];

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
 * Publish one post of a type and remember it for `afterAll`.
 *
 * @param {{page: import('@playwright/test').Page, nonce: string}} session    The admin's session.
 * @param {string}                                                 collection The type's REST collection.
 * @param {string}                                                 name       What the title says after the mark.
 * @param {Object}                                                 [fields]   More fields, or other ones.
 * @return {Promise<Object>} The post as REST gives it.
 */
async function publish( { page, nonce }, collection, name, fields = {} ) {
	const response = await page.request.post(
		`/wp-json/wp/v2/${ collection }`,
		{
			headers: { 'X-WP-Nonce': nonce },
			data: {
				title: `${ MARK } ${ name }`,
				content: BODY,
				status: 'publish',
				...fields,
			},
		}
	);
	expect(
		response.ok(),
		`publishing "${ name }" to ${ collection }: HTTP ${ response.status() }`
	).toBe( true );
	const post = await response.json();
	made.push( { collection, id: post.id } );

	return post;
}

/**
 * Every pattern the site registers under `corex/`, one after another, as a page's content.
 *
 * @param {{page: import('@playwright/test').Page, nonce: string}} session The admin's session.
 * @return {Promise<string>} The block comments.
 */
async function everyPattern( { page, nonce } ) {
	const response = await page.request.get(
		'/wp-json/wp/v2/block-patterns/patterns',
		{ headers: { 'X-WP-Nonce': nonce } }
	);
	const names = ( await response.json() )
		.map( ( pattern ) => pattern.name )
		.filter( ( name ) => name.startsWith( 'corex/' ) );
	expect( names, 'the patterns the site registers' ).toContain( DOCS_HEADER );

	return names
		.map( ( name ) => `<!-- wp:pattern {"slug":"${ name }"} /-->` )
		.join( '' );
}

/**
 * The path of an address on this site.
 *
 * @param {string} link A permalink.
 * @return {string} Its path.
 */
const pathOf = ( link ) => new URL( link ).pathname;

/**
 * Go to one of the pages, see that it is that page, and set the direction it is read in.
 *
 * @param {import('@playwright/test').Page} page      The page.
 * @param {string}                          name      A key of `paths`.
 * @param {string}                          direction `ltr` or `rtl`.
 */
async function visit( page, name, direction ) {
	await page.goto( paths[ name ] );
	await expect(
		page.locator( 'body' ),
		`${ paths[ name ] } is read as ${ name }`
	).toHaveClass( new RegExp( `(^|\\s)${ BODY_CLASS[ name ] }(\\s|$)` ) );
	await page
		.locator( 'html' )
		.evaluate(
			( root, dir ) => root.setAttribute( 'dir', dir ),
			direction
		);
}

/**
 * How far the nearest line of text is from each edge of the window, and how far the page scrolls
 * sideways.
 *
 * A line is one a visitor can see: text clipped to a pixel for screen readers is left out, and so
 * is anything placed wholly outside the window, which is where a form keeps the field no person is
 * meant to fill.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @return {Promise<{sideways: number, lines: number, left: {px: number, what: string}, right: {px: number, what: string}}>} The measurements.
 */
function textEdges( page ) {
	return page.evaluate( () => {
		const root = document.documentElement;
		const width = root.clientWidth;
		const inWindow = ( rect ) =>
			rect.width > 1 &&
			rect.height > 1 &&
			rect.right > 0 &&
			rect.left < width;
		const lines = [];
		const walker = document.createTreeWalker(
			document.body,
			window.NodeFilter.SHOW_TEXT
		);

		while ( walker.nextNode() ) {
			const node = walker.currentNode;
			const element = node.parentElement;
			if (
				! node.textContent.trim() ||
				element.closest(
					'script, style, noscript, template, .screen-reader-text'
				) ||
				window.getComputedStyle( element ).visibility === 'hidden' ||
				! inWindow( element.getBoundingClientRect() )
			) {
				continue;
			}

			const range = document.createRange();
			range.selectNodeContents( node );
			const rect = range.getBoundingClientRect();
			if ( ! inWindow( rect ) ) {
				continue;
			}

			const what = `<${ element.tagName.toLowerCase() }> "${ node.textContent
				.trim()
				.slice( 0, 40 ) }"`;
			lines.push( {
				what,
				left: Math.round( rect.left * 100 ) / 100,
				right: Math.round( ( width - rect.right ) * 100 ) / 100,
			} );
		}

		const nearest = ( side ) => {
			const line = lines.reduce(
				( found, next ) =>
					next[ side ] < found[ side ] ? next : found,
				lines[ 0 ]
			);

			return { px: line?.[ side ], what: line?.what };
		};

		return {
			sideways: root.scrollWidth - width,
			lines: lines.length,
			left: nearest( 'left' ),
			right: nearest( 'right' ),
		};
	} );
}

/**
 * How far an element's box is from each edge of the window.
 *
 * @param {import('@playwright/test').Locator} locator The element.
 * @return {Promise<{left: number, right: number, width: number}>} The distances, and the window's width.
 */
function boxEdges( locator ) {
	return locator.first().evaluate( ( element ) => {
		const width = document.documentElement.clientWidth;
		const rect = element.getBoundingClientRect();

		return {
			left: Math.round( rect.left * 100 ) / 100,
			right: Math.round( ( width - rect.right ) * 100 ) / 100,
			width,
		};
	} );
}

/**
 * Hold a page to the gutter: its text off both edges, and no scrolling sideways.
 *
 * @param {import('@playwright/test').Page} page The page, already visited.
 */
async function expectTextInsideTheGutter( page ) {
	const found = await textEdges( page );

	expect( found.lines, 'lines of text read' ).toBeGreaterThan( 0 );
	expect(
		found.left.px,
		`${ found.left.what } is ${ found.left.px }px from the left edge`
	).toBeGreaterThanOrEqual( GUTTER );
	expect(
		found.right.px,
		`${ found.right.what } is ${ found.right.px }px from the right edge`
	).toBeGreaterThanOrEqual( GUTTER );
	expect(
		found.sideways,
		`the page is ${ found.sideways }px wider than the window`
	).toBeLessThanOrEqual( 0 );
}

test.use( { storageState: { cookies: [], origins: [] } } );

test.beforeAll( async ( { browser } ) => {
	const session = await adminSession( browser );

	for ( const template of PAGE_TEMPLATES ) {
		const page = await publish(
			session,
			'pages',
			template || 'page',
			template ? { template } : {}
		);
		paths[ template || 'page' ] = pathOf( page.link );
	}

	const patterns = await publish( session, 'pages', 'patterns', {
		content: await everyPattern( session ),
	} );
	paths[ PATTERNS ] = pathOf( patterns.link );

	const post = await publish( session, 'posts', 'post', LONG_AGO );
	paths.single = pathOf( post.link );

	const category = await session.page.request.get(
		`/wp-json/wp/v2/categories/${ post.categories[ 0 ] }`
	);
	paths.archive = pathOf( ( await category.json() ).link );

	const project = await publish(
		session,
		'corex_project',
		'project',
		LONG_AGO
	);
	paths[ 'single-corex_project' ] = pathOf( project.link );

	// A project's address, and the archive's, answer "Page not found" on a site whose permalink
	// rules were last saved before the portfolio add-on was switched on: nothing in the framework
	// saves them again (PROGRESS.md, "Open, and not hidden"). WordPress saves them whenever its
	// Permalinks screen is opened, and what is measured here is the templates, so it is opened.
	await session.page.goto( '/wp-admin/options-permalink.php' );

	await session.context.close();
} );

test.afterAll( async ( { browser } ) => {
	const session = await adminSession( browser );

	for ( const { collection, id } of made ) {
		await session.page.request.delete(
			`/wp-json/wp/v2/${ collection }/${ id }?force=true`,
			{ headers: { 'X-WP-Nonce': session.nonce } }
		);
	}

	await session.context.close();
} );

for ( const phone of PHONES ) {
	test.describe( `in a window ${ phone.width } wide`, () => {
		test.use( { viewport: phone } );

		for ( const direction of DIRECTIONS ) {
			for ( const template of TEMPLATES ) {
				test( `${ template } keeps its text off both edges and does not scroll sideways, ${ direction }`, async ( {
					page,
				} ) => {
					await visit( page, template, direction );

					await expectTextInsideTheGutter( page );
				} );
			}

			test( `a page of every pattern keeps its text off both edges and does not scroll sideways, ${ direction }`, async ( {
				page,
			} ) => {
				await visit( page, PATTERNS, direction );

				await expectTextInsideTheGutter( page );
			} );

			test( `the docs header's search field and its button stay inside the gutter, ${ direction }`, async ( {
				page,
			} ) => {
				await visit( page, PATTERNS, direction );

				const search = await boxEdges(
					page.locator(
						'.corex-header--docs .wp-block-search__inside-wrapper'
					)
				);

				expect( search.left ).toBeGreaterThanOrEqual( GUTTER );
				expect( search.right ).toBeGreaterThanOrEqual( GUTTER );
			} );

			test( `the header and the footer begin and end at the gutter, ${ direction }`, async ( {
				page,
			} ) => {
				await visit( page, 'page', direction );

				expect(
					await boxEdges( page.locator( '.corex-header__inner' ) )
				).toMatchObject( { left: GUTTER, right: GUTTER } );
				expect(
					await boxEdges( page.locator( '.corex-footer__legal' ) )
				).toMatchObject( { left: GUTTER, right: GUTTER } );
			} );

			for ( const template of PRINTS_CONTENT ) {
				test( `a full-width block in the content of ${ template } reaches both edges, its text does not, ${ direction }`, async ( {
					page,
				} ) => {
					await visit( page, template, direction );

					const block = page.locator( '.corex-gutter-full' );

					expect( await boxEdges( block ) ).toMatchObject( {
						left: 0,
						right: 0,
					} );
					expect(
						await boxEdges( block.locator( 'p' ) )
					).toMatchObject( { left: GUTTER, right: GUTTER } );
				} );

				test( `a wide block in the content of ${ template } keeps the gutter, ${ direction }`, async ( {
					page,
				} ) => {
					await visit( page, template, direction );

					expect(
						await boxEdges( page.locator( '.corex-gutter-wide' ) )
					).toMatchObject( { left: GUTTER, right: GUTTER } );
				} );
			}
		}
	} );
}

test.describe( 'on a desktop', () => {
	test.use( { viewport: DESKTOP } );

	for ( const direction of DIRECTIONS ) {
		for ( const template of PRINTS_CONTENT ) {
			test( `${ template } keeps its text column, and a wide and a full-width block in its content are that wide, ${ direction }`, async ( {
				page,
			} ) => {
				await visit( page, template, direction );

				const title = await boxEdges( page.locator( 'main h1' ) );
				const beside = ( size ) => ( title.width - size ) / 2;

				expect( title ).toMatchObject( {
					left: beside( CONTENT_SIZE ),
					right: beside( CONTENT_SIZE ),
				} );
				expect(
					await boxEdges( page.locator( '.corex-gutter-wide' ) )
				).toMatchObject( {
					left: beside( WIDE_SIZE ),
					right: beside( WIDE_SIZE ),
				} );
				expect(
					await boxEdges( page.locator( '.corex-gutter-full' ) )
				).toMatchObject( { left: 0, right: 0 } );
			} );
		}

		test( `what a search that finds nothing shows is in line with its title, ${ direction }`, async ( {
			page,
		} ) => {
			await visit( page, 'search, nothing found', direction );

			const title = await boxEdges( page.locator( 'main h1' ) );
			const form = await boxEdges( page.locator( 'main .corex-form' ) );

			expect( form ).toMatchObject( {
				left: title.left,
				right: title.right,
			} );
		} );
	}
} );
