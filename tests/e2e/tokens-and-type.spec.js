/**
 * Corex E2E: tokens and type as the browser draws them (spec 121, R2.1).
 *
 * - US-BRD-03: the front page prints the theme's spacing, font sizes and shadows only, headings
 *   follow the type scale, a button has the 6px CoreX radius and a motion-token transition.
 * - US-BRD-04: a page in Arabic draws its text in IBM Plex Sans Arabic at 1.8 and 1.3, and only a
 *   page with Arabic text loads the Arabic files; Latin words stay in the Latin face.
 * - US-BRD-09: paragraphs in the system font, buttons, labels and headings in Space Grotesk, on the
 *   site, a CoreX admin screen and the login.
 * - US-BRD-02: Light and System on a light computer draw the same admin, and wp-admin loads no
 *   theme stylesheet.
 *
 * Arabic is asked for per request with the cookie the `corex-e2e-arabic.php` fixture reads, so the
 * site's own language is never changed. The Arabic page is made through the REST API and removed.
 *
 * ENVIRONMENT-GATED: needs the site at COREX_BASE_URL + `npx playwright install`.
 */
const { test, expect } = require( '@playwright/test' );

const { STORAGE_STATE } = require( './global-setup' );

const DESKTOP = { width: 1280, height: 900 };
const VISITOR = { cookies: [], origins: [] };
const SYSTEM_FONT = /^system-ui/;
const SPACE_GROTESK = /^"?Space Grotesk"?/;
const CORE_X_SCREEN = '/wp-admin/admin.php?page=corex-settings';
const ARABIC = [
	'<!-- wp:heading --><h2 class="wp-block-heading">مرحبًا بكم في الموقع</h2><!-- /wp:heading -->',
	'<!-- wp:paragraph --><p class="cx-mixed">يعمل هذا الموقع على CoreX 0.46.1 ويمكنك مراسلتنا على user@example.test في أي وقت.</p><!-- /wp:paragraph -->',
].join( '' );

let arabicPage = null;

async function adminSession( browser ) {
	const context = await browser.newContext( { storageState: STORAGE_STATE } );
	const page = await context.newPage();
	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate( () => window.wpApiSettings.nonce );

	return { context, page, nonce };
}

/**
 * What one element computes for the properties a test reads.
 *
 * @param {import('@playwright/test').Page} page     The page.
 * @param {string}                          selector The element.
 * @return {Promise<Object>} Its font, size, tracking, line height, radius and transition.
 */
function computed( page, selector ) {
	return page
		.locator( selector )
		.first()
		.evaluate( ( element ) => {
			const style = window.getComputedStyle( element );

			return {
				family: style.fontFamily,
				size: parseFloat( style.fontSize ),
				weight: style.fontWeight,
				tracking: style.letterSpacing,
				lineHeight: parseFloat( style.lineHeight ),
				radius: style.borderRadius,
				transition: style.transitionDuration,
			};
		} );
}

/**
 * Every font file the page asked for while it loaded.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @return {string[]} The file names, filled in as the page loads.
 */
function fontRequests( page ) {
	const files = [];
	page.on( 'request', ( request ) => {
		if ( request.url().endsWith( '.woff2' ) ) {
			files.push( request.url().split( '/' ).pop() );
		}
	} );

	return files;
}

test.beforeAll( async ( { browser } ) => {
	const { context, page, nonce } = await adminSession( browser );
	const response = await page.request.post( '/wp-json/wp/v2/pages', {
		headers: { 'X-WP-Nonce': nonce },
		data: {
			title: 'cx121-arabic',
			status: 'publish',
			content: ARABIC,
		},
	} );
	arabicPage = await response.json();
	await context.close();
} );

test.afterAll( async ( { browser } ) => {
	if ( ! arabicPage?.id ) {
		return;
	}
	const { context, page, nonce } = await adminSession( browser );
	await page.request
		.delete( `/wp-json/wp/v2/pages/${ arabicPage.id }?force=true`, {
			headers: { 'X-WP-Nonce': nonce },
		} )
		.catch( () => {} );
	await context.close();
} );

test.describe( 'the CoreX theme', () => {
	test.use( { storageState: VISITOR, viewport: DESKTOP } );

	test( 'prints only its own spacing, font sizes and shadows, on the design scale', async ( {
		page,
	} ) => {
		const files = fontRequests( page );
		await page.goto( '/' );

		const presets = await page.evaluate( () => {
			const root = window.getComputedStyle( document.documentElement );
			const read = ( name ) => root.getPropertyValue( name ).trim();

			return {
				spacing: [
					'10',
					'20',
					'25',
					'30',
					'40',
					'50',
					'60',
					'70',
					'80',
				].map( ( slug ) => {
					const probe = document.createElement( 'div' );
					probe.style.width = `var(--wp--preset--spacing--${ slug })`;
					document.body.append( probe );
					const width = probe.getBoundingClientRect().width;
					probe.remove();
					return width;
				} ),
				wordPressDefaults: [
					'--wp--preset--spacing--x-large',
					'--wp--preset--font-size--large',
					'--wp--preset--font-size--x-large',
					'--wp--preset--shadow--natural',
					'--wp--preset--shadow--deep',
				].filter( ( name ) => read( name ) !== '' ),
			};
		} );

		expect( presets.spacing ).toEqual( [
			4, 8, 12, 16, 24, 32, 48, 64, 96,
		] );
		expect( presets.wordPressDefaults ).toEqual( [] );
		// An English page draws no Arabic glyph, so it fetches no Arabic file (TC-BRD-04-03).
		expect( files.filter( ( file ) => file.includes( 'arabic' ) ) ).toEqual(
			[]
		);
	} );

	test( 'sets headings on the type scale, buttons and labels in Space Grotesk, paragraphs in the system font', async ( {
		page,
	} ) => {
		await page.goto( '/' );

		const h1 = await computed( page, 'h1' );
		const h2 = await computed( page, 'main h2' );
		const paragraph = await computed( page, 'main p' );
		const button = await computed(
			page,
			'.wp-block-button__link, .wp-element-button'
		);
		const label = await computed( page, 'label' );

		expect( h1 ).toMatchObject( { size: 64, weight: '600' } );
		expect( parseFloat( h1.tracking ) ).toBeCloseTo( -0.03 * 64, 1 );
		expect( h2.size ).toBe( 48 );
		expect( h1.family ).toMatch( SPACE_GROTESK );
		expect( paragraph.family ).toMatch( SYSTEM_FONT );
		expect( button.family ).toMatch( SPACE_GROTESK );
		expect( label.family ).toMatch( SPACE_GROTESK );
		// The 6px CoreX radius; the hover change takes the fast motion token, 140ms (TC-BRD-03-04).
		expect( button.radius ).toBe( '6px' );
		expect( button.transition.split( ', ' ) ).toContain( '0.14s' );
	} );

	test( 'draws an Arabic page in IBM Plex Sans Arabic at Arabic line heights, Latin words in their own face', async ( {
		page,
		context,
		baseURL,
	} ) => {
		const files = fontRequests( page );
		await context.addCookies( [
			{ name: 'corex_e2e_locale', value: 'ar', url: baseURL },
		] );
		await page.goto( arabicPage.link );
		await page.evaluate( () => document.fonts.ready );

		const paragraph = await computed( page, 'p.cx-mixed' );
		const heading = await computed( page, 'main h2' );
		const ranges = await page.evaluate( () =>
			[ ...document.fonts ]
				.filter( ( face ) =>
					face.family.includes( 'IBM Plex Sans Arabic' )
				)
				.map( ( face ) => face.unicodeRange )
		);

		expect(
			await page.evaluate( () => document.documentElement.lang )
		).toBe( 'ar' );
		expect( paragraph.family ).toMatch( /^"?IBM Plex Sans Arabic"?/ );
		expect( paragraph.lineHeight / paragraph.size ).toBeCloseTo( 1.8, 2 );
		expect( heading.family ).toMatch( /^"?IBM Plex Sans Arabic"?/ );
		expect( heading.lineHeight / heading.size ).toBeCloseTo( 1.3, 2 );
		// The Arabic face covers Arabic only, so "CoreX 0.46.1" and the address fall to the Latin face.
		expect( ranges.length ).toBeGreaterThan( 0 );
		ranges.forEach( ( range ) =>
			expect( range ).toMatch( /^U\+600-6FF/i )
		);
		expect(
			files.some( ( file ) => file.includes( 'ibm-plex-sans-arabic' ) )
		).toBe( true );
	} );
} );

test.describe( 'the login', () => {
	test.use( { storageState: VISITOR, viewport: DESKTOP } );

	test( 'sets labels and the button in Space Grotesk and long text in the system font', async ( {
		page,
	} ) => {
		await page.goto( '/wp-login.php' );

		expect( ( await computed( page, 'body' ) ).family ).toMatch(
			SYSTEM_FONT
		);
		expect( ( await computed( page, '#loginform label' ) ).family ).toMatch(
			/Space Grotesk/
		);
		expect( ( await computed( page, '#wp-submit' ) ).family ).toMatch(
			/Space Grotesk/
		);
	} );
} );

test.describe( 'the CoreX admin', () => {
	test.use( { viewport: DESKTOP } );

	test( 'sets buttons and headings in Space Grotesk and paragraphs in the system font, and loads no theme stylesheet', async ( {
		page,
	} ) => {
		await page.goto( CORE_X_SCREEN );

		const stylesheets = await page.evaluate( () =>
			[ ...document.querySelectorAll( 'link[rel="stylesheet"]' ) ].map(
				( link ) => link.href
			)
		);

		expect(
			( await computed( page, '.corex-admin .corex-admin__description' ) )
				.family
		).toMatch( SYSTEM_FONT );
		expect( ( await computed( page, '.corex-admin h1' ) ).family ).toMatch(
			/Space Grotesk/
		);
		expect(
			(
				await computed(
					page,
					'.corex-admin .button, .corex-admin button'
				)
			).family
		).toMatch( /Space Grotesk/ );
		expect(
			stylesheets.filter( ( href ) => href.includes( '/themes/' ) )
		).toEqual( [] );
	} );

	test( 'draws Light and System on a light computer the same, shadows, overlay and login card included', async ( {
		page,
	} ) => {
		await page.emulateMedia( { colorScheme: 'light' } );
		await page.goto( CORE_X_SCREEN );

		const tokens = await page.evaluate( () => {
			const shell = document.querySelector( '.corex-admin' );
			const names = [
				'--corex-admin-shadow-md',
				'--corex-admin-overlay',
				'--corex-admin-login-card',
				'--corex-admin-login-backdrop',
				'--corex-admin-surface',
			];
			const read = () =>
				names.map( ( name ) =>
					window
						.getComputedStyle( shell )
						.getPropertyValue( name )
						.trim()
				);

			shell.setAttribute( 'data-corex-theme', 'light' );
			const light = read();
			shell.removeAttribute( 'data-corex-theme' );
			const system = read();

			return { light, system };
		} );

		expect( tokens.system ).toEqual( tokens.light );
		expect( tokens.light[ 0 ] ).not.toContain( '11 31 59' );
	} );

	test( 'reads Arabic at Arabic line height', async ( {
		page,
		context,
		baseURL,
	} ) => {
		await context.addCookies( [
			{ name: 'corex_e2e_locale', value: 'ar', url: baseURL },
		] );
		await page.goto( CORE_X_SCREEN );

		const paragraph = await computed(
			page,
			'.corex-admin .corex-admin__description'
		);

		expect( paragraph.family ).toMatch( /CoreX Arabic/ );
		expect( paragraph.lineHeight / paragraph.size ).toBeCloseTo( 1.8, 1 );
	} );
} );
