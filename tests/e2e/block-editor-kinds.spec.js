/**
 * Spec 136, slice 4 (User Story 2 scenarios 2 to 4, FR-018 to FR-020, SC-008): the blocks drawn in
 * place and the records blocks, as the block editor draws them.
 *
 * A draft holds copyright, newsletter signup and social share (in place), posts, breadcrumbs and
 * jobs (records), and a form with no form chosen, each saved as a self-closing dynamic block, as a
 * page saved before this slice holds them. In the editor:
 * - every block loads without a validation warning;
 * - the three in-place blocks are drawn with no request to the server's block renderer, and the
 *   newsletter's heading, button label and consent text are typed where they stand, while its
 *   form never submits;
 * - the records blocks are previewed inside their wrapper, inert, and their loading and empty
 *   states are WordPress's Placeholder with their words (the renderer's answer is held back or
 *   emptied for that, by the test);
 * - the form asks which form on the canvas, and a choice there sets the block's attribute and
 *   draws the form.
 *
 * The spec makes the draft and removes it. ENVIRONMENT-GATED: needs the site at COREX_BASE_URL.
 */
const { test, expect } = require( '@playwright/test' );

const { STORAGE_STATE } = require( './global-setup' );

const block = ( name, attributes = {} ) =>
	`<!-- wp:${ name } ${ JSON.stringify( attributes ) } /-->`;

const IN_PLACE = [ 'copyright', 'newsletter-signup', 'social-share' ];

const CONTENT = [
	block( 'corex/copyright' ),
	block( 'corex/newsletter-signup', { heading: 'Letters' } ),
	block( 'corex/social-share' ),
	block( 'corex/posts', { count: 1 } ),
	block( 'corex/breadcrumbs' ),
	block( 'corex/jobs', { count: 1 } ),
	block( 'corex/form', { formSlug: '' } ),
].join( '' );

const made = { draft: null };

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
	made.draft = await (
		await page.request.post( route( 'wp/v2/posts' ), {
			headers: { 'X-WP-Nonce': nonce },
			data: {
				title: 'cx136 kinds in the editor',
				status: 'draft',
				content: CONTENT,
			},
		} )
	).json();
	await context.close();
} );

test.afterAll( async ( { browser } ) => {
	const { context, page, nonce, route } = await adminSession( browser );
	if ( made.draft?.id ) {
		await page.request.delete(
			route( `wp/v2/posts/${ made.draft.id }`, 'force=true' ),
			{ headers: { 'X-WP-Nonce': nonce } }
		);
	}
	await context.close();
} );

/**
 * @param {string} url A request's address.
 * @return {string|null} The block the server's block renderer was asked for, if it was.
 */
function rendererBlock( url ) {
	const match = decodeURIComponent( url ).match(
		/block-renderer\/(corex\/[a-z-]+)/
	);

	return match ? match[ 1 ] : null;
}

/**
 * Opens the draft in the editor, after any routes the test set, and waits for its blocks.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @return {Promise<import('@playwright/test').FrameLocator>} The editor's canvas.
 */
async function openDraft( page ) {
	await page.goto( `/wp-admin/post.php?post=${ made.draft.id }&action=edit` );
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	await expect( canvas.locator( '.wp-block-corex-copyright' ) ).toBeVisible( {
		timeout: 45_000,
	} );
	// The editor's welcome guide, on a fresh profile, sits over the canvas.
	await page.evaluate( () =>
		window.wp.data
			.dispatch( 'core/preferences' )
			.set( 'core/edit-post', 'welcomeGuide', false )
	);
	await expect( page.locator( '.edit-post-welcome-guide' ) ).toHaveCount( 0 );

	return canvas;
}

/**
 * @param {import('@playwright/test').Page} page The editor.
 * @param {string}                          name A block name.
 * @return {Promise<Object>} The attributes of the first block of that name.
 */
function attributesOf( page, name ) {
	return page.evaluate(
		( blockName ) =>
			window.wp.data
				.select( 'core/block-editor' )
				.getBlocks()
				.find( ( one ) => one.name === blockName ).attributes,
		name
	);
}

test( 'FR-018: the in-place blocks are drawn without the server and load without a warning', async ( {
	page,
} ) => {
	const asked = [];
	page.on( 'request', ( request ) => {
		const name = rendererBlock( request.url() );
		if ( name ) {
			asked.push( name );
		}
	} );
	const canvas = await openDraft( page );

	await expect( canvas.locator( 'p.corex-copyright' ) ).toContainText(
		String( new Date().getUTCFullYear() )
	);
	await expect(
		canvas.locator( '.corex-newsletter-signup__heading' )
	).toHaveText( 'Letters' );
	await expect(
		canvas.locator( '.corex-social-share .corex-social-share__link' )
	).toHaveCount( 5 );
	// The records blocks still ask the server: their previews arrive.
	await expect(
		canvas.locator(
			'.wp-block-corex-posts .corex-posts, .wp-block-corex-posts .corex-posts__empty'
		)
	).toBeVisible( { timeout: 30_000 } );

	await expect( canvas.locator( '.block-editor-warning' ) ).toHaveCount( 0 );
	expect(
		asked.filter( ( name ) =>
			IN_PLACE.some( ( slug ) => name === `corex/${ slug }` )
		)
	).toEqual( [] );
	expect( asked ).toContain( 'corex/posts' );
} );

test( 'FR-018: the newsletter is typed where it stands and never submits', async ( {
	page,
} ) => {
	const subscribed = [];
	page.on( 'request', ( request ) => {
		if (
			decodeURIComponent( request.url() ).includes(
				'newsletter/subscribe'
			)
		) {
			subscribed.push( request.url() );
		}
	} );
	const canvas = await openDraft( page );
	const signup = canvas.locator( '.wp-block-corex-newsletter-signup' );

	const heading = signup.locator( '.corex-newsletter-signup__heading' );
	await heading.click();
	await page.keyboard.press( 'End' );
	await page.keyboard.type( ' from us' );

	const button = signup.locator( '.corex-newsletter-signup__submit' );
	await button.click();
	await page.keyboard.type( 'Join us' );
	await page.keyboard.press( 'Enter' );

	const consent = signup.locator(
		'.corex-newsletter-signup__consent [role="textbox"]'
	);
	await consent.click();
	await page.keyboard.type( 'Write to me & my team' );

	expect(
		await attributesOf( page, 'corex/newsletter-signup' )
	).toMatchObject( {
		heading: 'Letters from us',
		buttonLabel: 'Join us',
		consentText: 'Write to me & my team',
	} );
	await expect(
		signup.locator(
			'.corex-newsletter-signup__consent input[type="checkbox"]'
		)
	).not.toBeChecked();

	// A submit of the form, as the browser sends it, is stopped in the editor.
	await signup.locator( 'form' ).evaluate( ( form ) => form.requestSubmit() );
	await expect( page ).toHaveURL( /post\.php\?post=\d+&action=edit/ );
	await expect( signup.locator( 'form' ) ).toBeVisible();
	expect( subscribed ).toEqual( [] );
} );

test( 'FR-019: a records preview is inert inside its wrapper, and its loading state is named', async ( {
	page,
} ) => {
	// Hold the posts preview back, so its loading state stays on the canvas.
	let release;
	const held = new Promise( ( resolve ) => {
		release = resolve;
	} );
	await page.route(
		( url ) => rendererBlock( url.href ) === 'corex/posts',
		async ( route ) => {
			await held;
			await route.continue();
		}
	);
	const canvas = await openDraft( page );
	const posts = canvas.locator( '.wp-block-corex-posts' );

	await expect( posts.locator( '.components-placeholder' ) ).toContainText(
		'Loading posts…'
	);
	await expect( posts.locator( '.components-spinner' ) ).toBeVisible();
	release();

	const preview = posts
		.locator( '.corex-posts, .corex-posts__empty' )
		.first();
	await expect( preview ).toBeVisible( { timeout: 30_000 } );
	// WordPress's Disabled holds the preview: nothing in it can be clicked or focused.
	expect(
		await preview.evaluate( ( element ) =>
			Boolean( element.closest( '[inert]' ) )
		)
	).toBe( true );

	const breadcrumbs = canvas.locator( '.wp-block-corex-breadcrumbs' );
	await expect( breadcrumbs.locator( 'nav.corex-breadcrumbs' ) ).toBeVisible(
		{ timeout: 30_000 }
	);
	expect(
		await breadcrumbs
			.locator( 'nav.corex-breadcrumbs' )
			.evaluate( ( element ) => Boolean( element.closest( '[inert]' ) ) )
	).toBe( true );
} );

test( 'FR-019: when the server draws nothing, a records block says so', async ( {
	page,
} ) => {
	await page.route(
		( url ) => rendererBlock( url.href ) === 'corex/jobs',
		( route ) =>
			route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( { rendered: '' } ),
			} )
	);
	const canvas = await openDraft( page );

	await expect(
		canvas.locator( '.wp-block-corex-jobs .components-placeholder' )
	).toContainText( 'No open positions to show.', { timeout: 30_000 } );
} );

test( 'FR-020: the form asks which form on the canvas, and a choice draws it', async ( {
	page,
} ) => {
	const canvas = await openDraft( page );
	const form = canvas.locator( '.wp-block-corex-form' );
	const chooser = form.locator( '.components-placeholder' );

	await expect( chooser ).toContainText(
		'Choose the form this block shows.'
	);
	// Inserted from the inserter, the block starts with no form.
	expect(
		await page.evaluate(
			() =>
				window.wp.data
					.select( 'core/blocks' )
					.getDefaultBlockVariation( 'corex/form', 'inserter' )
					?.attributes
		)
	).toEqual( { formSlug: '' } );

	await chooser.getByLabel( 'Source' ).selectOption( 'registered' );
	const registered = chooser.getByLabel( 'Form', { exact: true } );
	await expect( registered.locator( 'option[value="contact"]' ) ).toHaveCount(
		1,
		{ timeout: 30_000 }
	);
	await registered.selectOption( 'contact' );

	expect( await attributesOf( page, 'corex/form' ) ).toMatchObject( {
		source: 'registered',
		formSlug: 'contact',
	} );
	await expect( form.locator( 'form.corex-form' ) ).toBeVisible( {
		timeout: 30_000,
	} );
	await expect( form.locator( '.components-placeholder' ) ).toHaveCount( 0 );
	expect(
		await form
			.locator( 'form.corex-form' )
			.evaluate( ( element ) => Boolean( element.closest( '[inert]' ) ) )
	).toBe( true );
} );
