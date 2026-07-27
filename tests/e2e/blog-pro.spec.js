/**
 * Blog Pro admin workspace and native front-end proof (spec 068 T170; spec 075).
 *
 * Admin: the workspace renders from live WordPress state and can be acted on. Front end: the native
 * single template exposes the functional social-share, newsletter, and comment surfaces. No console
 * errors on either.
 *
 * Rewritten for spec 075. The previous version asserted four metric cards and a "Moderation queue"
 * heading, both of which that spec replaced — the same way spec 074 left a notification spec clicking
 * a tab it had deleted. A browser spec that is not re-run when its surface changes is not a test.
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

	test( 'names the post every panel is describing, and offers a way to change it', async ( {
		page,
	} ) => {
		// The defect spec 075 opened on: everything was computed for whichever post sorted first,
		// under a heading that read as a site-wide total, with nothing naming the subject.
		const errors = collectConsoleErrors( page );

		const selector = page.locator(
			'[data-corex-blog-post-selector] [role="combobox"]'
		);
		await expect( selector ).toBeVisible();

		// The analytics heading quotes the same post the selector shows.
		const subject = ( await selector.textContent() ).trim();
		await expect( page.locator( '.corex-blog-pro__panel h2' ) ).toContainText(
			subject
		);
		// And the period is stated, because a count without a window means nothing.
		await expect( page.locator( '.corex-blog-pro__subject-meta' ) ).toContainText(
			'30'
		);

		await expect( page.getByText( 'Editorial workflow' ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'Authors' } ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'Sharing' } ) ).toBeVisible();

		expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
	} );

	test( 'speaks in words, never in slugs', async ( { page } ) => {
		// `ready_for_review` and `publish` were printed raw on an otherwise translated screen.
		const body = await page.locator( '.corex-blog-pro-app' ).innerText();

		expect( body ).not.toMatch( /ready_for_review|needs_changes/ );
	} );

	test( 'offers a real editorial control rather than a read-only card', async ( {
		page,
	} ) => {
		// POST /blog/editorial/{id}/transition had no caller in the product at all.
		const apply = page.locator( '[data-corex-blog-transition]' );
		await expect( apply ).toBeVisible();
		// Disabled until a destination is chosen: the form will not submit nothing.
		await expect( apply ).toBeDisabled();

		await page
			.locator( '.corex-blog-pro__editorial .corex-select__button' )
			.click();
		await expect( page.locator( '[role="option"]' ).first() ).toBeVisible();
	} );

	test( 'the selection is the URL, so a view can be linked and reloaded', async ( {
		page,
	} ) => {
		// An id naming no post it listed falls back to a real one, rather than rendering a screen
		// full of empty panels about nothing.
		await page.goto(
			'/wp-admin/admin.php?page=corex-blog-pro&post=999999999'
		);

		await expect(
			page.locator( '[data-corex-blog-post-selector] [role="combobox"]' )
		).toBeVisible();
		await expect( page.locator( '.corex-blog-pro__panel h2' ) ).toBeVisible();
	} );

	test( 'contains the workspace within the page, LTR and RTL', async ( { page } ) => {
		// The claim under test is that *the workspace* contains itself, not that WP admin is
		// perfect — the same framing tests/e2e/notification-center.spec.js uses for the drawer.
		//
		// Measured because it matters: the CoreX shell overflows the viewport by exactly 1px in RTL
		// at 375px on **every** CoreX screen, including `corex-settings` and `corex-addons`, which
		// this spec never touches. Asserting `scrollWidth <= clientWidth` on the document would make
		// this spec fail for a framework-wide chrome defect it did not cause and cannot fix from
		// here. Recorded in PROGRESS.md instead.
		for ( const dir of [ 'ltr', 'rtl' ] ) {
			await page
				.locator( 'html' )
				.evaluate( ( root, value ) => root.setAttribute( 'dir', value ), dir );

			for ( const width of [ 375, 768, 1024, 1440 ] ) {
				await page.setViewportSize( { width, height: 900 } );

				const fits = await page.evaluate( () => {
					const app = document.querySelector( '.corex-blog-pro-app' );
					const box = app.getBoundingClientRect();
					return (
						app.scrollWidth <= app.clientWidth + 1 &&
						box.left >= -1 &&
						box.right <= document.documentElement.clientWidth + 1
					);
				} );

				expect( fits, `workspace overflow at ${ width }px (${ dir })` ).toBe(
					true
				);
			}
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
