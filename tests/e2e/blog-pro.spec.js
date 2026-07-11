/**
 * Blog Pro admin workspace and native front-end proof (spec 068: T170).
 *
 * Admin: the real analytics/editorial/comments/authors/sharing workspace renders from
 * live WordPress state. Front end: the native single template exposes the functional
 * social-share, newsletter, and comment surfaces. No console errors on either.
 */

const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

test.describe( 'Blog Pro admin workspace', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=corex-blog-pro' );
		await expect(
			page.getByRole( 'heading', { name: 'CoreX Blog Pro' } )
		).toBeVisible();
	} );

	test( 'renders analytics, editorial, comments, authors, and sharing from real state', async ( {
		page,
	} ) => {
		const errors = collectConsoleErrors( page );

		// First-party analytics metric cards (views/reads/share clicks/avg read).
		await expect( page.locator( '.corex-blog-pro__metric' ) ).toHaveCount( 4 );
		// The functional workflow panels are present (not planned/sample copy).
		await expect( page.getByText( 'Editorial workflow' ) ).toBeVisible();
		await expect( page.getByText( 'Moderation queue' ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'Authors' } ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'Sharing' } ) ).toBeVisible();

		// Metric cards carry a real surface background (regression: an undefined token
		// left them transparent).
		const background = await page
			.locator( '.corex-blog-pro__metric' )
			.first()
			.evaluate( ( node ) => getComputedStyle( node ).backgroundColor );
		expect( background ).not.toBe( 'rgba(0, 0, 0, 0)' );

		expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
	} );

	test( 'contains the workspace without horizontal overflow across viewports', async ( {
		page,
	} ) => {
		for ( const width of [ 375, 768, 1024, 1440 ] ) {
			await page.setViewportSize( { width, height: 900 } );
			const fits = await page.evaluate(
				() =>
					document.documentElement.scrollWidth <=
					document.documentElement.clientWidth
			);
			expect( fits, `horizontal overflow at ${ width }px` ).toBe( true );
		}
	} );
} );

test.describe( 'Native blog front end', () => {
	test( 'single post exposes share, newsletter, and comment surfaces', async ( {
		page,
	} ) => {
		const errors = collectConsoleErrors( page );
		await page.goto( '/hello-world/' );

		await expect( page.getByRole( 'heading', { name: 'Hello world!', level: 1 } ) ).toBeVisible();
		// Functional CoreX blocks resolve (require the active email/newsletter addons).
		await expect( page.getByRole( 'button', { name: 'Copy link' } ) ).toBeVisible();
		await expect( page.getByRole( 'button', { name: 'Subscribe' } ) ).toBeVisible();
		// Native comment thread + reply form.
		await expect( page.locator( 'form.comment-form, #commentform' ) ).toBeVisible();

		expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
	} );
} );
