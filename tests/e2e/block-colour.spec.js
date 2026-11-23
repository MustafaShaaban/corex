/**
 * Spec 136, slice 2a (User Story 1 scenario 6, FR-008, SC-008): a background and a text colour
 * chosen on a section block, as the browser draws them.
 *
 * A page holds a hero and a posts block, each with `backgroundColor` and `textColor` set to CoreX
 * presets. Signed out, in the default style and in the Dark variation (drawn as
 * `theme-colours.js` says), in English and in Arabic, right to left: each block's root is the chosen
 * background, and its title, its text and a link in it are the chosen text colour, though the
 * sheet gives them colours of their own and the theme colours headings and links. The hero's
 * button keeps its own colours.
 *
 * In the editor, the posts block is previewed by its server render inside the editor's wrapper,
 * which alone carries the colour classes (FR-006a): the preview's title link takes the chosen
 * colour there too.
 *
 * Every expected colour is read from `theme/theme.json` and `theme/styles/dark.json`. The spec makes
 * the post the posts block lists, and removes what it made. ENVIRONMENT-GATED: needs the site at
 * COREX_BASE_URL.
 */
const { test, expect } = require( '@playwright/test' );

const { STORAGE_STATE } = require( './global-setup' );
const { MODES, settle, showDarkVariation } = require( './theme-colours' );

const VISITOR = { cookies: [], origins: [] };
const DESKTOP = { width: 1280, height: 900 };

/** The colours chosen on both blocks: CoreX presets, read in each mode. */
const BACKGROUND = 'ink';
const TEXT = 'surface';
const CHOSEN = { backgroundColor: BACKGROUND, textColor: TEXT };

const HERO = {
	title: 'Coloured hero',
	subtitle:
		'A subtitle with <a class="cx136-hero-link" href="/">a link in it</a>.',
	ctaText: 'Start',
	ctaUrl: '/',
	...CHOSEN,
};

const block = ( name, attributes ) =>
	`<!-- wp:${ name } ${ JSON.stringify( attributes ) } /-->`;

const CONTENT = [
	block( 'corex/hero', HERO ),
	block( 'corex/posts', { count: 1, ...CHOSEN } ),
].join( '' );

const made = { post: null, page: null, draft: null };

async function adminSession( browser ) {
	const context = await browser.newContext( { storageState: STORAGE_STATE } );
	const page = await context.newPage();
	await page.goto( '/wp-admin/' );
	const { nonce, root } = await page.evaluate( () => window.wpApiSettings );
	// WordPress's own address for a route: `/wp-json/` or, under plain permalinks, `?rest_route=`.
	const route = ( path, query = '' ) =>
		`${ root }${ path }${ query && ( root.includes( '?' ) ? '&' : '?' ) }${ query }`;

	return { context, page, nonce, route };
}

test.beforeAll( async ( { browser } ) => {
	const { context, page, nonce, route } = await adminSession( browser );
	const create = async ( type, data ) =>
		(
			await page.request.post( route( `wp/v2/${ type }` ), {
				headers: { 'X-WP-Nonce': nonce },
				data,
			} )
		).json();
	// The newest post, so the posts block lists it first.
	made.post = await create( 'posts', {
		title: 'cx136 coloured post',
		status: 'publish',
	} );
	made.page = await create( 'pages', {
		title: 'cx136-colour',
		status: 'publish',
		content: CONTENT,
	} );
	made.draft = await create( 'posts', {
		title: 'cx136 colour in the editor',
		status: 'draft',
		content: block( 'corex/posts', { count: 1, ...CHOSEN } ),
	} );
	await context.close();
} );

test.afterAll( async ( { browser } ) => {
	const { context, page, nonce, route } = await adminSession( browser );
	for ( const [ type, item ] of [
		[ 'pages', made.page ],
		[ 'posts', made.post ],
		[ 'posts', made.draft ],
	] ) {
		if ( item?.id ) {
			await page.request.delete(
				route( `wp/v2/${ type }/${ item.id }`, 'force=true' ),
				{ headers: { 'X-WP-Nonce': nonce } }
			);
		}
	}
	await context.close();
} );

/**
 * The colours one element computes.
 *
 * @param {import('@playwright/test').Locator} locator The element.
 * @return {Promise<{color: string, background: string}>} Its text and background colours.
 */
function colours( locator ) {
	return locator.first().evaluate( ( element ) => {
		const style = window.getComputedStyle( element );

		return { color: style.color, background: style.backgroundColor };
	} );
}

test.describe( 'a chosen text colour reaches the headings, text and links of the page (FR-008)', () => {
	test.use( { storageState: VISITOR } );

	for ( const mode of Object.keys( MODES ) ) {
		for ( const locale of [ 'en', 'ar' ] ) {
			test( `the hero and the posts block in the ${ mode } style, ${ locale }`, async ( {
				page,
				context,
				baseURL,
			} ) => {
				const { preset } = MODES[ mode ];
				if ( locale === 'ar' ) {
					await context.addCookies( [
						{ name: 'corex_e2e_locale', value: 'ar', url: baseURL },
					] );
				}
				await page.setViewportSize( DESKTOP );
				await page.goto( made.page.link );
				if ( mode === 'dark' ) {
					await showDarkVariation( page );
				}
				await settle( page );

				if ( locale === 'ar' ) {
					await expect( page.locator( 'html' ) ).toHaveAttribute(
						'dir',
						'rtl'
					);
				}

				const hero = page.locator( '.wp-block-corex-hero' );
				expect( await colours( hero ) ).toEqual( {
					color: preset[ TEXT ],
					background: preset[ BACKGROUND ],
				} );
				for ( const part of [
					'.corex-hero__title',
					'.corex-hero__subtitle',
					'.cx136-hero-link',
				] ) {
					expect(
						( await colours( hero.locator( part ) ) ).color,
						part
					).toBe( preset[ TEXT ] );
				}
				// The button is drawn on its own fill and keeps its own colours.
				expect(
					await colours( hero.locator( '.corex-hero__cta' ) )
				).toEqual( {
					color: preset.surface,
					background: preset.primary,
				} );

				const posts = page.locator( '.wp-block-corex-posts' );
				expect( await colours( posts ) ).toEqual( {
					color: preset[ TEXT ],
					background: preset[ BACKGROUND ],
				} );
				const title = posts.locator( '.corex-posts__title' ).first();
				await expect( title ).toContainText( 'cx136 coloured post' );
				expect( ( await colours( title ) ).color ).toBe(
					preset[ TEXT ]
				);
				expect( ( await colours( title.locator( 'a' ) ) ).color ).toBe(
					preset[ TEXT ]
				);
				// The card drops its surface, so the text sits on the chosen background.
				expect(
					( await colours( posts.locator( '.corex-posts__item' ) ) )
						.background
				).toBe( 'rgba(0, 0, 0, 0)' );
			} );
		}
	}
} );

test( "in the editor, the posts block's preview takes the colour its wrapper carries (FR-006a, FR-008)", async ( {
	page,
} ) => {
	await page.goto( `/wp-admin/post.php?post=${ made.draft.id }&action=edit` );
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	const link = canvas.locator(
		'.wp-block-corex-posts.has-text-color .corex-posts__title a'
	);
	await expect( link.first() ).toBeVisible( { timeout: 45_000 } );

	expect(
		await canvas
			.locator( '.wp-block-corex-posts.has-text-color .corex-posts' )
			.first()
			.evaluate( ( element ) =>
				element.classList.contains( 'has-text-color' )
			)
	).toBe( false );
	expect( ( await colours( link ) ).color ).toBe(
		MODES.default.preset[ TEXT ]
	);
} );
