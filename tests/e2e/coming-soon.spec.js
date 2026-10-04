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
const { signInAs } = require( './helpers' );
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
 * The WCAG contrast ratio between two computed colours.
 *
 * @param {string} first  A computed `rgb()` or `rgba()` colour.
 * @param {string} second Another.
 * @return {number} The ratio, from 1 to 21.
 */
function contrast( first, second ) {
	const luminance = ( colour ) => {
		const [ red, green, blue ] = colour
			.match( /[\d.]+/g )
			.slice( 0, 3 )
			.map( ( channel ) => {
				const value = Number( channel ) / 255;

				return value <= 0.03928
					? value / 12.92
					: Math.pow( ( value + 0.055 ) / 1.055, 2.4 );
			} );

		return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
	};
	const [ lighter, darker ] = [
		luminance( first ),
		luminance( second ),
	].sort( ( a, b ) => b - a );

	return ( lighter + 0.05 ) / ( darker + 0.05 );
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

		return (
			await page.locator( '.corex-standalone__detail p' ).innerText()
		).trim();
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
	const link = (
		await page.locator( '.corex-standalone__detail p' ).innerText()
	).trim();
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
