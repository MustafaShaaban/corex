/**
 * Coming soon mode, end to end (spec 101: SC-002, SC-003, SC-004, SC-007).
 *
 * The unit and integration suites prove what the mode decides. What they cannot see is what is
 * actually sent: the redirect itself, the cookie a preview link sets, the page a browser draws.
 * Each of those ends the PHP request, so the only place to assert them is from outside it.
 *
 * This file runs in a Playwright project of its own, after every other spec and alone (see
 * playwright.config.js). Turning the mode on changes what every signed-out and subscriber request
 * receives, and other specs drive both; switched on in the middle of the shared run it would fail
 * them at random. It puts the mode back when it is done, whatever happened in between.
 *
 * The tests run in order and share state on purpose: they are one walk through a site being put
 * into Coming soon, reviewed, and taken out again.
 */
const { test, expect, request: apiRequest } = require( '@playwright/test' );
const { contrast, pinAppearance, signInAs } = require( './helpers' );
const { STORAGE_STATE } = require( './global-setup' );

const SCREEN =
	'/wp-admin/admin.php?page=corex-operations-security&tab=environment';

/** One fixture user per spec file that signs in: login protection counts attempts per address. */
const EDITOR = process.env.COREX_SOON_EDITOR_USER || 'corex-soon-editor';
const EDITOR_PASS =
	process.env.COREX_SOON_EDITOR_PASS || 'CorexE2E!soon-editor1';
const SUBSCRIBER =
	process.env.COREX_SOON_SUBSCRIBER_USER || 'corex-soon-subscriber';
const SUBSCRIBER_PASS =
	process.env.COREX_SOON_SUBSCRIBER_PASS || 'CorexE2E!soon-subscriber1';

const NO_SESSION = { cookies: [], origins: [] };

test.describe.configure( { mode: 'serial' } );

/** The mode the site was in before this file touched it, put back in afterAll. */
let previousMode = '';
/** Addresses of the real site: a published post and a published page. */
let postUrl = '';
let pageUrl = '';

/**
 * The mode the site is in, read from the form an operator would read it from.
 *
 * @param {import('@playwright/test').Page} page An administrator's page.
 * @return {Promise<string>} The current mode.
 */
async function currentMode( page ) {
	await page.goto( SCREEN );

	return (
		( await page
			.locator( '[data-corex-mode-form]' )
			.getAttribute( 'data-current-mode' ) ) || ''
	);
}

/**
 * Change the mode by posting the screen's own form, with the confirmation the mode needs.
 *
 * The form itself is driven by hand in the first test; this is for the changes the walk needs in
 * between, where what is under test is what the mode does and not how it is selected.
 *
 * @param {import('@playwright/test').Page} page An administrator's page.
 * @param {string}                          mode The mode to apply.
 */
async function applyMode( page, mode ) {
	await page.goto( SCREEN );
	const nonce = await page
		.locator( '[data-corex-mode-form] input[name="corex_ops_mode_nonce"]' )
		.inputValue();

	const response = await page.request.post( '/wp-admin/admin-post.php', {
		form: {
			action: 'corex_ops_mode',
			corex_ops_mode_nonce: nonce,
			corex_mode: mode,
			corex_confirm: '1',
			corex_confirm_phrase: mode === 'production' ? 'PRODUCTION' : '',
		},
	} );

	expect(
		response.url(),
		`applying ${ mode } should be saved or already in force`
	).toMatch( /corex_status=(saved|unchanged)/ );
}

/**
 * A signed-out client that does not follow redirects, so a 302 can be looked at.
 *
 * @param {string} baseURL Where the site is served.
 * @return {Promise<import('@playwright/test').APIRequestContext>} The client.
 */
function visitor( baseURL ) {
	return apiRequest.newContext( {
		baseURL,
		storageState: NO_SESSION,
		maxRedirects: 0,
	} );
}

/**
 * Whether a response is "go to the home URL, temporarily, and here is nothing else".
 *
 * @param {import('@playwright/test').APIResponse} response The response.
 * @param {string}                                 baseURL  Where the site is served.
 */
async function expectRedirectHome( response, baseURL ) {
	expect( response.status(), response.url() ).toBe( 302 );
	expect(
		new URL( response.headers().location, baseURL ).pathname,
		response.url()
	).toBe( new URL( '/', baseURL ).pathname );
	// SC-002: zero bytes of the page that was asked for.
	expect( ( await response.body() ).length, response.url() ).toBe( 0 );
	// A cached redirect would outlive the mode.
	expect( response.headers()[ 'cache-control' ] || '' ).toContain(
		'no-cache'
	);
}

/**
 * What the bar looks like right now: where it sits, what it overflows, and its colours.
 *
 * @param {import('@playwright/test').Page} page A page showing the bar.
 * @return {Promise<Object>} The measurements.
 */
function measureBar( page ) {
	return page.evaluate( () => {
		const bar = document.querySelector( '.corex-coming-soon-bar' );
		const box = bar.getBoundingClientRect();
		const style = window.getComputedStyle( bar );
		const link = bar.querySelector( 'a' );

		return {
			insideViewport:
				box.left >= 0 && box.right <= window.innerWidth + 0.5,
			overflowsItself: bar.scrollWidth > bar.clientWidth,
			background: style.backgroundColor,
			text: style.color,
			link: link ? window.getComputedStyle( link ).color : '',
			linkHeight: link ? link.getBoundingClientRect().height : 0,
			edgeLeft: parseFloat( style.borderLeftWidth ),
			edgeRight: parseFloat( style.borderRightWidth ),
		};
	} );
}

/** Every combination SC-007 names. */
const MATRIX = [ 'ltr', 'rtl' ].flatMap( ( direction ) =>
	[ 'light', 'dark' ].flatMap( ( scheme ) =>
		[
			{ name: 'mobile', width: 375, height: 800 },
			{ name: 'desktop', width: 1280, height: 800 },
		].map( ( viewport ) => ( { direction, scheme, viewport } ) )
	)
);

/**
 * Put a page into one cell of the matrix.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {Object}                          cell Direction, colour scheme and viewport.
 * @param {string}                          url  The address to load.
 */
async function enter( page, cell, url ) {
	await page.setViewportSize( cell.viewport );
	await page.emulateMedia( { colorScheme: cell.scheme } );
	await page.goto( url );
	await page.evaluate( ( direction ) => {
		document.documentElement.setAttribute( 'dir', direction );
		document.body.setAttribute( 'dir', direction );
	}, cell.direction );
}

const describe = ( cell ) =>
	`${ cell.direction }, ${ cell.scheme }, ${ cell.viewport.name }`;

test.beforeAll( async ( { browser }, testInfo ) => {
	const context = await browser.newContext( {
		baseURL: testInfo.project.use.baseURL,
		storageState: STORAGE_STATE,
	} );
	const page = await context.newPage();

	previousMode = await currentMode( page );
	// A site already in Coming soon is taken out first, so the first test can put it in.
	if ( previousMode === 'coming-soon' ) {
		await applyMode( page, 'development' );
	}

	const nonce = await page.evaluate( () =>
		window.wpApiSettings ? window.wpApiSettings.nonce : ''
	);
	const first = async ( type ) => {
		const response = await page.request.get(
			`/wp-json/wp/v2/${ type }?per_page=1&status=publish&_fields=link`,
			nonce ? { headers: { 'X-WP-Nonce': nonce } } : {}
		);
		const [ item ] = response.ok() ? await response.json() : [];

		return item
			? new URL( item.link ).pathname + new URL( item.link ).search
			: '';
	};

	postUrl = await first( 'posts' );
	pageUrl = await first( 'pages' );

	await context.close();
} );

test.afterAll( async ( { browser }, testInfo ) => {
	// Whatever happened above, the site goes back to the mode it was found in.
	const context = await browser.newContext( {
		baseURL: testInfo.project.use.baseURL,
		storageState: STORAGE_STATE,
	} );
	const page = await context.newPage();

	if ( previousMode && ( await currentMode( page ) ) !== previousMode ) {
		await applyMode( page, previousMode );
	}

	await context.close();
} );

test( 'the site has a published post and a published page to hide', () => {
	// Everything below asks for these addresses. Without them the redirect assertions would be
	// about addresses that do not exist, which are redirected for a different reason.
	expect( postUrl, 'a published post' ).not.toBe( '' );
	expect( pageUrl, 'a published page' ).not.toBe( '' );
} );

test( 'an operator turns Coming soon on from the screen, with its own acknowledgement', async ( {
	page,
} ) => {
	await page.goto( `${ SCREEN }&mode=coming-soon` );

	const block = page.locator( '[data-mode="coming-soon"]' );
	await expect( block ).toBeVisible();
	await expect( block ).toContainText( '200 status' );
	await expect( block ).toContainText( 'edit posts' );

	// US1.2: without the acknowledgement nothing changes, and the form comes back asking for it.
	await page.click( '[data-corex-mode-apply]' );
	await expect( page ).toHaveURL( /corex_status=confirm/ );
	await expect(
		page.locator( '[data-corex-mode-form]' )
	).not.toHaveAttribute( 'data-current-mode', 'coming-soon' );

	// US1.1: with it, the mode is saved and the history says who did it.
	await page
		.locator( '[data-mode="coming-soon"] input[name="corex_confirm"]' )
		.check();
	await page.click( '[data-corex-mode-apply]' );

	await expect( page ).toHaveURL( /corex_status=saved/ );
	await page.goto( SCREEN );
	await expect( page.locator( '[data-corex-mode-form]' ) ).toHaveAttribute(
		'data-current-mode',
		'coming-soon'
	);
	await expect(
		page.locator( '.corex-opsec__audit-row' ).first()
	).toContainText( 'coming-soon' );
	// No preview link exists until somebody creates one (US4.5).
	await expect( page.locator( '.corex-opsec__preview' ) ).toContainText(
		'No preview link exists'
	);
} );

test( 'a signed-out visitor gets the coming-soon page at home and a redirect everywhere else', async ( {
	baseURL,
} ) => {
	const client = await visitor( baseURL );

	// The home URL: 200, the page, indexable.
	const home = await client.get( '/' );
	const html = await home.text();
	expect( home.status() ).toBe( 200 );
	expect( html ).toMatch( /<body[^>]*class="[^"]*\bcorex-coming-soon\b/ );
	expect( html ).not.toMatch( /<meta[^>]+name=["']robots["'][^>]*noindex/ );
	expect( html ).not.toContain( 'corex-coming-soon-bar' );

	// The page says the site is not ready. It does not also say which WordPress it runs, where
	// its remote-editing endpoint is, or offer feeds that only redirect back here. Reported from
	// the first site that used the mode in public.
	expect( html ).not.toMatch( /<meta[^>]+name=["']generator["']/ );
	expect( html ).not.toMatch( /rel=["']EditURI["']/ );
	expect( html ).not.toMatch( /type=["']application\/rss\+xml["']/ );
	expect( html ).not.toContain( 'wpemojiSettings' );

	// With a campaign tag it is still the home URL.
	expect( ( await client.get( '/?utm_source=launch' ) ).status() ).toBe(
		200
	);

	// SC-002: every other address is a temporary redirect to home, with none of its content.
	for ( const address of [
		postUrl,
		pageUrl,
		`/corex-no-such-address-${ Date.now() }/`,
		'/?feed=rss2',
		'/?s=launch',
	] ) {
		await expectRedirectHome( await client.get( address ), baseURL );
	}

	// robots.txt is served as it always is.
	const robots = await client.get( '/robots.txt' );
	expect( robots.status() ).toBe( 200 );
	expect( await robots.text() ).toContain( 'User-agent' );

	// The sitemap tells a crawler about the launch page and about nothing else.
	const sitemap = await client.get( '/wp-sitemap.xml' );
	const urls = ( await sitemap.text() ).match( /<loc>/g ) || [];
	expect( sitemap.status() ).toBe( 200 );
	expect( urls ).toHaveLength( 1 );

	// A form on the page posts to the REST API, which the mode never intercepts.
	expect( ( await client.get( '/wp-json/' ) ).status() ).toBe( 200 );

	await client.dispose();
} );

test( 'an administrator reaches the real site everywhere, and is told visitors do not', async ( {
	page,
} ) => {
	for ( const address of [ '/', postUrl, pageUrl ] ) {
		const response = await page.goto( address );
		const bar = page.locator( '.corex-coming-soon-bar' );

		expect( response.status(), address ).toBe( 200 );
		await expect( page.locator( 'body' ), address ).not.toHaveClass(
			/corex-coming-soon/
		);
		await expect( bar, address ).toBeVisible();
		await expect( bar, address ).toContainText( 'Coming soon is on' );
		// SC-003: the way to the screen that changes it, for somebody who can change it.
		await expect(
			bar.getByRole( 'link', { name: 'Open Operations & Security' } ),
			address
		).toBeVisible();
	}

	// The visitor view: what a visitor at the home URL is sent, and nothing a visitor is not.
	await page
		.locator( '.corex-coming-soon-bar' )
		.getByRole( 'link', { name: 'View the coming-soon page' } )
		.click();

	await expect( page.locator( 'body' ) ).toHaveClass( /corex-coming-soon/ );
	await expect( page.locator( '#wpadminbar' ) ).toHaveCount( 0 );
	await expect( page.locator( '.corex-coming-soon-bar' ) ).toHaveCount( 0 );
	await expect( page.locator( 'body' ) ).not.toHaveClass( /logged-in/ );

	// And in the admin, the same message is in the toolbar.
	await page.goto( '/wp-admin/' );
	await expect(
		page.locator( '#wp-admin-bar-corex-coming-soon' )
	).toContainText( 'Coming soon is on' );
} );

test( 'an editor reaches the real site and sees the notice without a link they cannot follow', async ( {
	browser,
	baseURL,
} ) => {
	const page = await signInAs( browser, baseURL, EDITOR, EDITOR_PASS );

	const response = await page.goto( postUrl );
	const bar = page.locator( '.corex-coming-soon-bar' );

	expect( response.status() ).toBe( 200 );
	await expect( page.locator( 'body' ) ).not.toHaveClass(
		/corex-coming-soon/
	);
	await expect( bar ).toContainText( 'Coming soon is on' );
	await expect(
		bar.getByRole( 'link', { name: 'View the coming-soon page' } )
	).toBeVisible();
	await expect( bar.locator( 'a' ) ).toHaveCount( 1 );
	await expect( bar ).not.toContainText( 'Operations' );

	await page.context().close();
} );

test( 'a signed-in subscriber is a visitor', async ( { browser, baseURL } ) => {
	const page = await signInAs(
		browser,
		baseURL,
		SUBSCRIBER,
		SUBSCRIBER_PASS
	);

	// SC-003: reaches none of them. The browser follows the redirect, so the page it ends on is
	// the coming-soon page at the home URL, whichever address was asked for.
	for ( const address of [ postUrl, pageUrl ] ) {
		await page.goto( address );

		expect( new URL( page.url() ).pathname, address ).toBe(
			new URL( '/', baseURL ).pathname
		);
		await expect( page.locator( 'body' ), address ).toHaveClass(
			/corex-coming-soon/
		);
		await expect(
			page.locator( '.corex-coming-soon-bar' ),
			address
		).toHaveCount( 0 );
	}

	await page.context().close();
} );

test( 'a preview link shows a stakeholder the real site until it is regenerated, revoked, or the mode is left', async ( {
	page,
	browser,
	baseURL,
} ) => {
	test.setTimeout( 120_000 );

	/**
	 * Press one of the card's buttons and read the link off the page that answers.
	 *
	 * @param {string} button The button's label.
	 * @return {Promise<string>} The link, as the page shows it.
	 */
	const makeLink = async ( button ) => {
		await page.goto( SCREEN );
		await page.getByRole( 'button', { name: button } ).click();
		await expect(
			page.locator( '.corex-standalone__title' )
		).toContainText( 'Preview link' );

		return page.getByLabel( 'Preview link' ).inputValue();
	};

	const stakeholder = await browser.newContext( {
		baseURL,
		storageState: NO_SESSION,
	} );
	const theirs = await stakeholder.newPage();

	/** Where the stakeholder ends up when they ask for a post, and what they see there. */
	const asksForThePost = async () => {
		await theirs.goto( postUrl );

		return ( await theirs
			.locator( 'body' )
			.evaluate( ( body ) =>
				body.classList.contains( 'corex-coming-soon' )
			) )
			? 'the coming-soon page'
			: 'the real site';
	};

	// Without a link, a stakeholder is a visitor.
	expect( await asksForThePost() ).toBe( 'the coming-soon page' );

	// Created: shown once, whole.
	const link = await makeLink( 'Create preview link' );
	expect( link ).toContain( 'corex_preview=' );
	// And never again: the screen says one exists and does not show it.
	await page.goto( SCREEN );
	await expect( page.locator( '.corex-opsec__preview' ) ).toContainText(
		'A preview link exists'
	);
	expect( await page.content() ).not.toContain(
		new URL( link ).searchParams.get( 'corex_preview' )
	);

	// Opened: the real site, the secret gone from the address bar, and the banner.
	await theirs.goto( link );
	expect( theirs.url() ).not.toContain( 'corex_preview' );
	await expect( theirs.locator( 'body' ) ).not.toHaveClass(
		/corex-coming-soon/
	);
	const banner = theirs.locator( '.corex-coming-soon-bar' );
	await expect( banner ).toContainText( 'Private preview' );
	await expect( banner ).toContainText(
		'The public cannot see this site yet'
	);
	await expect( banner.locator( 'a, button' ) ).toHaveCount( 0 );

	// What the browser was given: not readable by script, not sent cross-site, fourteen days.
	const cookie = ( await stakeholder.cookies() ).find(
		( candidate ) => candidate.name === 'corex_preview'
	);
	expect( cookie.httpOnly ).toBe( true );
	expect( cookie.sameSite ).toBe( 'Lax' );
	expect( cookie.value ).not.toContain(
		new URL( link ).searchParams.get( 'corex_preview' )
	);
	const days = ( cookie.expires - Date.now() / 1000 ) / 86400;
	expect( days ).toBeGreaterThan( 13.9 );
	expect( days ).toBeLessThan( 14.1 );

	// It stays, across the site.
	expect( await asksForThePost() ).toBe( 'the real site' );
	await expect( theirs.locator( '.corex-coming-soon-bar' ) ).toContainText(
		'Private preview'
	);

	// It is no way into the admin (US4.4).
	await theirs.goto( '/wp-admin/' );
	await expect( theirs.locator( '#wpadminbar' ) ).toHaveCount( 0 );
	await expect( theirs.locator( '#wpbody' ) ).toHaveCount( 0 );

	// Regenerated: the old access ends at the next request, and the old link opens nothing.
	const second = await makeLink( 'Regenerate link' );
	expect( second ).not.toBe( link );
	expect( await asksForThePost() ).toBe( 'the coming-soon page' );
	await theirs.goto( link );
	await expect( theirs.locator( 'body' ) ).toHaveClass( /corex-coming-soon/ );

	// The new link works; revoked, it does not.
	await theirs.goto( second );
	expect( await asksForThePost() ).toBe( 'the real site' );
	await page.goto( SCREEN );
	await page.getByRole( 'button', { name: 'Revoke link' } ).click();
	await expect( page ).toHaveURL( /corex_status=preview_revoked/ );
	await expect( page.locator( '.corex-opsec__preview' ) ).toContainText(
		'No preview link exists'
	);
	expect( await asksForThePost() ).toBe( 'the coming-soon page' );

	// A third link; then the site leaves the mode and comes back.
	const third = await makeLink( 'Create preview link' );
	await theirs.goto( third );
	expect( await asksForThePost() ).toBe( 'the real site' );

	await applyMode( page, 'development' );
	await applyMode( page, 'coming-soon' );

	expect( await asksForThePost() ).toBe( 'the coming-soon page' );
	await theirs.goto( third );
	await expect( theirs.locator( 'body' ) ).toHaveClass( /corex-coming-soon/ );
	await page.goto( SCREEN );
	await expect( page.locator( '.corex-opsec__preview' ) ).toContainText(
		'No preview link exists'
	);

	// Each of those is in the history, with the operator and never the link.
	const history = await page.locator( '.corex-opsec__audit' ).innerText();
	expect( history ).toContain( 'Preview link created' );
	expect( history ).toContain( 'Preview link regenerated' );
	expect( history ).toContain( 'Preview link revoked' );
	for ( const made of [ link, second, third ] ) {
		expect( history ).not.toContain(
			new URL( made ).searchParams.get( 'corex_preview' )
		);
	}

	await stakeholder.close();
} );

/**
 * The copy field as it is drawn: where its parts sit, how large they are, and in what colours.
 *
 * @param {import('@playwright/test').Page} page The page that shows a new link.
 * @return {Promise<Object>} The measurements.
 */
function measureCopyField( page ) {
	return page.evaluate( () => {
		const part = ( selector ) => document.querySelector( selector );
		const box = ( element ) => {
			const { left, right, top, bottom, width, height } =
				element.getBoundingClientRect();

			return { left, right, top, bottom, width, height };
		};
		const card = part( '.corex-standalone__card' );
		const row = part( '.corex-standalone__copy-row' );
		const input = part( '[data-corex-copy-value]' );
		const button = part( '[data-corex-copy-button]' );
		const cardStyle = window.getComputedStyle( card );
		const rowStyle = window.getComputedStyle( row );
		const cardBox = box( card );

		return {
			// The card's content box: what is inside its border and its padding.
			inside: {
				left:
					cardBox.left +
					parseFloat( cardStyle.borderLeftWidth ) +
					parseFloat( cardStyle.paddingLeft ),
				right:
					cardBox.right -
					parseFloat( cardStyle.borderRightWidth ) -
					parseFloat( cardStyle.paddingRight ),
			},
			label: box( part( '.corex-standalone__copy-label' ) ),
			input: box( input ),
			button: box( button ),
			status: box( part( '[data-corex-copy-status]' ) ),
			gap: parseFloat( rowStyle.columnGap ),
			rowGap: parseFloat( rowStyle.rowGap ),
			ground: cardStyle.backgroundColor,
			labelInk: window.getComputedStyle(
				part( '.corex-standalone__copy-label' )
			).color,
			statusInk: window.getComputedStyle(
				part( '[data-corex-copy-status]' )
			).color,
			inputInk: window.getComputedStyle( input ).color,
			inputGround: window.getComputedStyle( input ).backgroundColor,
			buttonInk: window.getComputedStyle( button ).color,
			buttonGround: window.getComputedStyle( button ).backgroundColor,
			buttonWordsCut: button.scrollWidth > button.clientWidth,
			pageFits:
				document.documentElement.scrollWidth <=
				document.documentElement.clientWidth,
		};
	} );
}

test( 'the page that shows a new link copies it at a press, and holds in both directions, schemes and widths', async ( {
	page,
} ) => {
	test.setTimeout( 120_000 );

	await page
		.context()
		.grantPermissions( [ 'clipboard-read', 'clipboard-write' ] );
	await page.goto( SCREEN );
	await page.getByRole( 'button', { name: 'Create preview link' } ).click();

	const field = page.getByLabel( 'Preview link' );
	const button = page.getByRole( 'button', { name: 'Copy link' } );
	const status = page.locator( '[data-corex-copy-status]' );
	const link = await field.inputValue();

	expect( link ).toContain( 'corex_preview=' );
	await expect( field ).toHaveAttribute( 'readonly', '' );
	// Said to a screen reader when it changes, without taking the reader from where they are.
	await expect( status ).toHaveAttribute( 'aria-live', 'polite' );
	await expect( status ).toHaveRole( 'status' );
	await expect( status ).toHaveText( '' );

	for ( const cell of MATRIX ) {
		const where = describe( cell );

		await page.setViewportSize( cell.viewport );
		await page.emulateMedia( { colorScheme: cell.scheme } );
		await pinAppearance( page, cell.scheme, cell.direction );
		await page.evaluate( () => window.navigator.clipboard.writeText( '' ) );

		await button.click();
		await expect( status, where ).toHaveText( 'Copied.' );
		expect(
			await page.evaluate( () => window.navigator.clipboard.readText() ),
			`the whole link is on the clipboard: ${ where }`
		).toBe( link );
		await expect(
			page.locator( '.corex-standalone__copy-icon--done' ),
			`the tick replaces the two sheets: ${ where }`
		).toBeVisible();
		await expect(
			page.locator( '.corex-standalone__copy-icon--copy' ),
			where
		).toBeHidden();

		const drawn = await measureCopyField( page );
		const rtl = cell.direction === 'rtl';
		// The start and end edge of a box, whichever side each is on.
		const start = ( box ) => ( rtl ? box.right : box.left );
		const end = ( box ) => ( rtl ? box.left : box.right );
		const insideStart = rtl ? drawn.inside.right : drawn.inside.left;
		const insideEnd = rtl ? drawn.inside.left : drawn.inside.right;

		expect( drawn.pageFits, `no sideways scroll: ${ where }` ).toBe( true );
		expect( drawn.buttonWordsCut, `the button is whole: ${ where }` ).toBe(
			false
		);
		// WCAG 2.2 AA, 2.5.8: a 24 by 24 target.
		expect(
			drawn.button.height,
			`target height: ${ where }`
		).toBeGreaterThanOrEqual( 24 );
		expect(
			drawn.button.width,
			`target width: ${ where }`
		).toBeGreaterThanOrEqual( 24 );
		// The label and the field begin on the card's start edge, and the answer under them.
		expect( start( drawn.label ), `label start: ${ where }` ).toBeCloseTo(
			insideStart,
			0
		);
		expect( start( drawn.input ), `field start: ${ where }` ).toBeCloseTo(
			insideStart,
			0
		);
		expect( start( drawn.status ), `answer start: ${ where }` ).toBeCloseTo(
			insideStart,
			0
		);
		expect( end( drawn.button ), `button end: ${ where }` ).toBeCloseTo(
			insideEnd,
			0
		);

		if ( cell.viewport.name === 'desktop' ) {
			// One row: the same top and bottom, the button after the field, the row's gap between.
			expect( drawn.button.top, `row top: ${ where }` ).toBeCloseTo(
				drawn.input.top,
				0
			);
			expect( drawn.button.bottom, `row bottom: ${ where }` ).toBeCloseTo(
				drawn.input.bottom,
				0
			);
			expect(
				Math.abs( start( drawn.button ) - end( drawn.input ) ),
				`gap in the row: ${ where }`
			).toBeCloseTo( drawn.gap, 0 );
			expect(
				drawn.input.width,
				`the field is the wide one: ${ where }`
			).toBeGreaterThan( drawn.button.width * 2 );
		} else {
			// Narrow: the button under the field, at the field's width.
			expect(
				drawn.button.top - drawn.input.bottom,
				`gap under the field: ${ where }`
			).toBeCloseTo( drawn.rowGap, 0 );
			expect( drawn.button.width, `button width: ${ where }` ).toBeCloseTo(
				drawn.input.width,
				0
			);
			expect(
				start( drawn.button ),
				`button start: ${ where }`
			).toBeCloseTo( insideStart, 0 );
		}

		// WCAG 2.2 AA, 1.4.3: 4.5 to 1 for text.
		for ( const [ name, ink, ground ] of [
			[ 'label', drawn.labelInk, drawn.ground ],
			[ 'answer', drawn.statusInk, drawn.ground ],
			[ 'field', drawn.inputInk, drawn.inputGround ],
			[ 'button', drawn.buttonInk, drawn.buttonGround ],
		] ) {
			expect(
				contrast( ink, ground ),
				`${ name } contrast: ${ where }`
			).toBeGreaterThanOrEqual( 4.5 );
		}
	}

	// Reached from the keyboard, the button shows where the focus is (WCAG 2.2 AA, 2.4.7).
	await field.focus();
	await page.keyboard.press( 'Tab' );
	await expect( button ).toBeFocused();
	const ring = await button.evaluate( ( element ) => {
		const style = window.getComputedStyle( element );

		return { style: style.outlineStyle, width: style.outlineWidth };
	} );
	expect( ring.style ).toBe( 'solid' );
	expect( parseFloat( ring.width ) ).toBeGreaterThanOrEqual( 2 );

	// A focused field is selected whole, so a keyboard copies it without the button too.
	await field.focus();
	expect(
		await field.evaluate(
			( input ) =>
				input.selectionStart === 0 &&
				input.selectionEnd === input.value.length
		)
	).toBe( true );

	// The answer is taken back, so a second press is said again.
	await expect( status ).toHaveText( '', { timeout: 10_000 } );
	await expect(
		page.locator( '.corex-standalone__copy-icon--copy' )
	).toBeVisible();
} );

test( 'the link is copied where the browser has no Clipboard API', async ( {
	page,
} ) => {
	await page
		.context()
		.grantPermissions( [ 'clipboard-read', 'clipboard-write' ] );

	// A page not served over HTTPS or from this machine is given no `navigator.clipboard`.
	const plain = await page.context().newPage();
	await plain.addInitScript( () => {
		Object.defineProperty( window.navigator, 'clipboard', {
			value: undefined,
		} );
	} );
	await plain.goto( SCREEN );
	await plain.getByRole( 'button', { name: 'Regenerate link' } ).click();
	expect(
		await plain.evaluate( () => window.navigator.clipboard )
	).toBeUndefined();

	const link = await plain.getByLabel( 'Preview link' ).inputValue();
	await plain.getByRole( 'button', { name: 'Copy link' } ).click();
	await expect( plain.locator( '[data-corex-copy-status]' ) ).toHaveText(
		'Copied.'
	);
	await plain.close();

	// Read by a page that has the API.
	await page.goto( SCREEN );
	expect(
		await page.evaluate( () => window.navigator.clipboard.readText() )
	).toBe( link );
} );

test( 'with scripts off the link is in a field to copy by hand, and there is no button', async ( {
	page,
	browser,
	baseURL,
} ) => {
	const context = await browser.newContext( {
		baseURL,
		storageState: STORAGE_STATE,
		javaScriptEnabled: false,
		permissions: [ 'clipboard-read', 'clipboard-write' ],
	} );
	const scriptless = await context.newPage();

	await scriptless.goto( SCREEN );
	await scriptless.getByRole( 'button', { name: 'Regenerate link' } ).click();

	const field = scriptless.getByLabel( 'Preview link' );
	const link = await field.inputValue();
	expect( link ).toContain( 'corex_preview=' );
	await expect(
		scriptless.locator( '[data-corex-copy-button]' )
	).toBeHidden();

	// By hand: into the field, select all, copy.
	await field.click();
	await scriptless.keyboard.press( 'ControlOrMeta+A' );
	await scriptless.keyboard.press( 'ControlOrMeta+C' );
	await context.close();

	await page
		.context()
		.grantPermissions( [ 'clipboard-read', 'clipboard-write' ] );
	await page.goto( SCREEN );
	expect(
		await page.evaluate( () => window.navigator.clipboard.readText() )
	).toBe( link );

	// The link made for these tests is taken back.
	await page.getByRole( 'button', { name: 'Revoke link' } ).click();
	await expect( page ).toHaveURL( /corex_status=preview_revoked/ );
} );

test( 'the page, the notice and the banner hold in both directions, both schemes and both widths', async ( {
	page,
	browser,
	baseURL,
} ) => {
	test.setTimeout( 180_000 );

	// The coming-soon page, as a visitor.
	const publicContext = await browser.newContext( {
		baseURL,
		storageState: NO_SESSION,
	} );
	const publicPage = await publicContext.newPage();

	for ( const cell of MATRIX ) {
		await enter( publicPage, cell, '/' );

		const fits = await publicPage.evaluate(
			() =>
				document.documentElement.scrollWidth <=
				document.documentElement.clientWidth
		);
		expect(
			fits,
			`the page does not scroll sideways: ${ describe( cell ) }`
		).toBe( true );
		await expect(
			publicPage.locator( 'body.corex-coming-soon' ),
			describe( cell )
		).toBeVisible();
	}

	/**
	 * The bar, measured in one cell.
	 *
	 * @param {import('@playwright/test').Page} barPage  A page that shows the bar.
	 * @param {Object}                          cell     The cell.
	 * @param {boolean}                         hasLinks Whether this bar has links.
	 */
	const expectBarHolds = async ( barPage, cell, hasLinks ) => {
		const where = describe( cell );
		const bar = await measureBar( barPage );

		expect( bar.insideViewport, `inside the viewport: ${ where }` ).toBe(
			true
		);
		expect( bar.overflowsItself, `does not overflow: ${ where }` ).toBe(
			false
		);
		// WCAG 2.2 AA, 1.4.3: 4.5 to 1 for text.
		expect(
			contrast( bar.text, bar.background ),
			`message contrast: ${ where }`
		).toBeGreaterThanOrEqual( 4.5 );
		// The accent sits on the start edge, whichever side that is.
		const [ start, end ] =
			cell.direction === 'rtl'
				? [ bar.edgeRight, bar.edgeLeft ]
				: [ bar.edgeLeft, bar.edgeRight ];
		expect( start, `accent on the start edge: ${ where }` ).toBeGreaterThan(
			0
		);
		expect( end, `none on the end edge: ${ where }` ).toBe( 0 );

		if ( hasLinks ) {
			expect(
				contrast( bar.link, bar.background ),
				`link contrast: ${ where }`
			).toBeGreaterThanOrEqual( 4.5 );
			// WCAG 2.2 AA, 2.5.8: a 24px target.
			expect(
				bar.linkHeight,
				`link target: ${ where }`
			).toBeGreaterThanOrEqual( 24 );
		}
	};

	// The notice, as the administrator, on a page of the real site.
	for ( const cell of MATRIX ) {
		await enter( page, cell, postUrl );
		await expectBarHolds( page, cell, true );
	}

	// The banner, as a stakeholder with a link.
	await page.setViewportSize( { width: 1280, height: 800 } );
	await page.goto( SCREEN );
	await page.getByRole( 'button', { name: 'Create preview link' } ).click();
	const link = await page.getByLabel( 'Preview link' ).inputValue();
	await publicPage.goto( link );

	for ( const cell of MATRIX ) {
		await enter( publicPage, cell, postUrl );
		await expect(
			publicPage.locator( '.corex-coming-soon-bar' )
		).toContainText( 'Private preview' );
		await expectBarHolds( publicPage, cell, false );
	}

	// The link made for this test is taken back.
	await page.goto( SCREEN );
	await page.getByRole( 'button', { name: 'Revoke link' } ).click();
	await expect( page ).toHaveURL( /corex_status=preview_revoked/ );

	await publicContext.close();
} );
