/**
 * Corex E2E: tokens and type as the browser draws them (spec 121, R2.1).
 *
 * - US-BRD-03: the front page prints the theme's spacing, font sizes and shadows only, headings
 *   follow the type scale, a button has the 6px CoreX radius and a motion-token transition.
 * - US-BRD-04: a page in Arabic draws its text in IBM Plex Sans Arabic at 1.8 and 1.3, and only a
 *   page with Arabic text loads the Arabic files; Latin words stay in the Latin face.
 * - US-BRD-09: paragraphs in the system font, buttons, labels and headings in Space Grotesk, on the
 *   site, a CoreX admin screen and the login; an email body in the system font.
 * - US-BRD-02: Light and System on a light computer draw the same admin, and wp-admin loads no
 *   theme stylesheet.
 * - US-NFR-02: in an Arabic inbox an address and a version read in their own order (TC-NFR-02-03),
 *   and the From and To dates, at 1.8 and in the interface face, still fit on a phone and at 200%.
 *
 * Arabic is asked for per request with the cookie the `corex-e2e-arabic.php` fixture reads, so the
 * site's own language is never changed. The Arabic page is made through the REST API and removed.
 *
 * ENVIRONMENT-GATED: needs the site at COREX_BASE_URL + `npx playwright install`.
 */
const { test, expect } = require( '@playwright/test' );

const { STORAGE_STATE } = require( './global-setup' );
const { wpEval } = require( './helpers' );

const DESKTOP = { width: 1280, height: 900 };
const VISITOR = { cookies: [], origins: [] };
const SYSTEM_FONT = /^system-ui/;
const SPACE_GROTESK = /^"?Space Grotesk"?/;
const CORE_X_SCREEN = '/wp-admin/admin.php?page=corex-settings';
const ARABIC = [
	'<!-- wp:heading --><h2 class="wp-block-heading">مرحبًا بكم في الموقع</h2><!-- /wp:heading -->',
	'<!-- wp:paragraph --><p class="cx-mixed">يعمل هذا الموقع على CoreX 0.46.1 ويمكنك مراسلتنا على user@example.test في أي وقت.</p><!-- /wp:paragraph -->',
].join( '' );
// Every heading level, so h3 to h6 are read as well as the front page's h1 and h2 (FR-009).
const HEADINGS = [ 1, 2, 3, 4, 5, 6 ]
	.map(
		( level ) =>
			`<!-- wp:heading {"level":${ level }} --><h${ level } class="wp-block-heading">Heading level ${ level }</h${ level }><!-- /wp:heading -->`
	)
	.join( '' );

let arabicPage = null;
let headingsPage = null;

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
	const headings = await page.request.post( '/wp-json/wp/v2/pages', {
		headers: { 'X-WP-Nonce': nonce },
		data: {
			title: 'cx121-headings',
			status: 'publish',
			content: HEADINGS,
		},
	} );
	headingsPage = await headings.json();
	await context.close();
} );

test.afterAll( async ( { browser } ) => {
	const made = [ arabicPage?.id, headingsPage?.id ].filter( Boolean );
	if ( made.length === 0 ) {
		return;
	}
	const { context, page, nonce } = await adminSession( browser );
	for ( const id of made ) {
		await page.request
			.delete( `/wp-json/wp/v2/pages/${ id }?force=true`, {
				headers: { 'X-WP-Nonce': nonce },
			} )
			.catch( () => {} );
	}
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

	test( 'sets h3 to h6 on the type scale too: size, line height, weight 600 and negative tracking', async ( {
		page,
	} ) => {
		await page.goto( headingsPage.link );

		// At 1280px every fluid size has reached the design's value (TC-BRD-03-02).
		const scale = {
			h3: { size: 36, line: 1.2, tracking: -0.02 },
			h4: { size: 28, line: 1.3, tracking: -0.015 },
			h5: { size: 22, line: 1.4, tracking: -0.01 },
			h6: { size: 16, line: 1.5, tracking: -0.01 },
		};
		for ( const [ tag, want ] of Object.entries( scale ) ) {
			const heading = await computed( page, `main ${ tag }` );

			expect( heading.size, tag ).toBeCloseTo( want.size, 0 );
			expect( heading.weight, tag ).toBe( '600' );
			expect( heading.lineHeight / heading.size, tag ).toBeCloseTo(
				want.line,
				2
			);
			expect( parseFloat( heading.tracking ), tag ).toBeCloseTo(
				want.tracking * want.size,
				1
			);
			expect( heading.family, tag ).toMatch( SPACE_GROTESK );
		}
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
		// The light shadow is drawn in graphite (spec 121; spec 134 moved the theme off navy).
		expect( tokens.light[ 0 ] ).toContain( '20 21 26' );
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
		// The shell's own text, which takes the base line height (DECISIONS #517), reads at 1.8
		// too (FR-015, TC-BRD-04-05).
		const shell = await computed( page, '.corex-admin' );

		expect( paragraph.family ).toMatch( /CoreX Arabic/ );
		expect( paragraph.lineHeight / paragraph.size ).toBeCloseTo( 1.8, 1 );
		expect( shell.lineHeight / shell.size ).toBeCloseTo( 1.8, 1 );
	} );

	test( 'keeps an email body in the system font stack (TC-BRD-09-02)', async ( {
		page,
	} ) => {
		// The preview is the email as it is sent (spec 120), drawn by the server.
		await page.goto(
			'/wp-admin/admin.php?page=corex-email-studio&tab=templates'
		);
		await expect(
			page.locator( '.corex-email-app > .corex-loadable' )
		).toHaveAttribute( 'data-corex-state', 'ready' );
		const templates = page.locator( '.corex-email-app__list button' );
		test.skip(
			( await templates.count() ) === 0,
			'This site has no Email Studio template: CoreX installs them on a new site.'
		);
		await templates.first().click();
		await page
			.getByRole( 'button', { name: 'Preview', exact: true } )
			.click();

		const body = page
			.frameLocator( 'iframe[title="The email as it is sent"]' )
			.locator( 'td.cx-surface.cx-text:not(.cx-band)' )
			.first();
		await expect( body ).toBeVisible();
		const family = await body.evaluate(
			( element ) =>
				element.ownerDocument.defaultView.getComputedStyle( element )
					.fontFamily
		);

		expect( family ).toMatch( SYSTEM_FONT );
		expect( family ).not.toMatch( /Space Grotesk|Arabic/ );
	} );
} );

test.describe( 'the inbox in Arabic', () => {
	test.use( { viewport: DESKTOP } );

	const ARABIC_COOKIE = ( baseURL ) => [
		{ name: 'corex_e2e_locale', value: 'ar', url: baseURL },
	];

	test( 'reads an address and a version in their own order inside Arabic (TC-NFR-02-03)', async ( {
		page,
		context,
		baseURL,
	} ) => {
		const id = Number(
			wpEval(
				"$id = wp_insert_post( [ 'post_type' => 'corex_submission', 'post_status' => 'private', 'post_title' => 'cx121 order' ] );" +
					"update_post_meta( $id, 'corex_form_slug', 'contact' );" +
					"update_post_meta( $id, 'corex_field_name', 'Order Check' );" +
					"update_post_meta( $id, 'corex_field_email', 'user@example.test' );" +
					"update_post_meta( $id, 'corex_field_message', 'A question about CoreX 0.46.1, please.' );" +
					'echo (int) $id;'
			)
		);
		test.skip( ! id, 'WP-CLI is not available to store the submission.' );

		try {
			await context.addCookies( ARABIC_COOKIE( baseURL ) );
			await page.goto( '/wp-admin/admin.php?page=corex-submissions' );
			await page.getByText( 'user@example.test' ).first().click();
			await expect(
				page.locator( '.corex-field-value', {
					hasText: 'CoreX 0.46.1',
				} )
			).toBeVisible();

			// Where each part of the text is drawn: an address keeps "user" before "test", and the
			// sentence keeps "CoreX" before its version and its full stop at its own end.
			const reads = await page.evaluate( () => {
				const found = [];
				const walker = document.createTreeWalker(
					document.querySelector( '.corex-admin' ),
					window.NodeFilter.SHOW_TEXT
				);
				const leftOf = ( node, from, length ) => {
					const range = document.createRange();
					range.setStart( node, from );
					range.setEnd( node, from + length );
					return range.getBoundingClientRect().left;
				};

				for (
					let node = walker.nextNode();
					node;
					node = walker.nextNode()
				) {
					const text = node.nodeValue;
					if ( ! node.parentElement.getClientRects().length ) {
						continue;
					}
					if ( text.includes( 'user@example.test' ) ) {
						const at = text.indexOf( 'user@example.test' );
						found.push( {
							what: 'address',
							inOrder:
								leftOf( node, at, 4 ) <
								leftOf( node, at + 13, 4 ),
						} );
					}
					if ( text.includes( 'CoreX 0.46.1, please.' ) ) {
						const at = text.indexOf( 'CoreX 0.46.1, please.' );
						const name = leftOf( node, at, 5 );
						const version = leftOf( node, at + 6, 6 );
						const please = leftOf( node, at + 14, 6 );
						const stop = leftOf( node, at + 20, 1 );
						found.push( {
							what: 'version',
							inOrder:
								name < version &&
								version < please &&
								please < stop,
						} );
					}
				}

				return found;
			} );

			expect(
				reads.filter( ( read ) => read.what === 'address' ).length
			).toBeGreaterThan( 0 );
			expect(
				reads.filter( ( read ) => read.what === 'version' ).length
			).toBeGreaterThan( 0 );
			expect( reads.filter( ( read ) => ! read.inOrder ) ).toEqual( [] );
		} finally {
			wpEval( `wp_delete_post( ${ id }, true );` );
		}
	} );

	for ( const width of [ 375, 1280 ] ) {
		test( `keeps From and To apart and inside the panel at ${ width }px, at 100% and 200% text`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			await context.addCookies( ARABIC_COOKIE( baseURL ) );
			await page.setViewportSize( { width, height: 900 } );
			await page.goto( '/wp-admin/admin.php?page=corex-submissions' );
			await expect( page.locator( '.corex-inbox__dates' ) ).toBeVisible();

			for ( const size of [ '100%', '200%' ] ) {
				const fit = await page.evaluate( ( fontSize ) => {
					const root = document.documentElement;
					root.style.fontSize = fontSize;
					const range = document.querySelector(
						'.corex-inbox__dates'
					);
					const panel = range
						.closest( '.corex-inbox__filter-grid' )
						.getBoundingClientRect();
					const [ from, to ] = [
						...range.querySelectorAll( ':scope > label' ),
					].map( ( label ) => {
						const style = window.getComputedStyle( label );
						return {
							label: label.getBoundingClientRect(),
							input: label
								.querySelector( 'input' )
								.getBoundingClientRect(),
							ratio:
								parseFloat( style.lineHeight ) /
								parseFloat( style.fontSize ),
							direction: style.direction,
						};
					} );
					const across =
						Math.min( from.label.right, to.label.right ) -
						Math.max( from.label.left, to.label.left );
					const down =
						Math.min( from.label.bottom, to.label.bottom ) -
						Math.max( from.label.top, to.label.top );
					const inside = ( field ) =>
						field.input.left >= panel.left - 1 &&
						field.input.right <= panel.right + 1;
					const answer = {
						direction: from.direction,
						ratio: from.ratio,
						overlap: across > 2 && down > 2,
						inside: inside( from ) && inside( to ),
						scrolls: root.scrollWidth > root.clientWidth,
					};
					root.style.fontSize = '';
					return answer;
				}, size );

				expect( fit, size ).toMatchObject( {
					direction: 'rtl',
					overlap: false,
					inside: true,
					scrolls: false,
				} );
				expect( fit.ratio, size ).toBeCloseTo( 1.8, 1 );
			}
		} );
	}
} );
