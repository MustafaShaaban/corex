/**
 * Spec 124, FR-021 (User Story 10): what the site's mode tells a visitor at a flow, measured.
 *
 * - Development and Staging: the test-site notice above the form, in small text with a warning
 *   edge, on the inline-start side in both directions, readable in light and dark.
 * - Forms paused: the paused panel where the form was, spec 122's panel with its info edge and
 *   normal text; an administrator gets the real form.
 * - The Call to action block: every notice in it takes `surface`, so it stays a visible panel on
 *   the block's own `surface-alt`.
 * - Paused after the page was loaded: the paused panel above the form, the refusal said once in
 *   the status line, which spans the form under the button, the answers kept with no field marked,
 *   and focus back on the button.
 * - The Form block, with a flow and with a code-defined form: the same 24px between heading,
 *   notices and form, and a panel exactly as wide as its form.
 * - Production, Coming soon and Maintenance: no notice.
 *
 * The mode is changed by writing its option through WP-CLI, so this spec runs in a project of its
 * own, after the main one (playwright.config.js), and puts back the mode it found. It runs only when
 * WP-CLI reaches the install COREX_BASE_URL serves, and skips otherwise. The four pages are made for
 * the run and removed after it; the flow is kept and reused by the next run, as flow-closed.spec.js
 * keeps its own.
 */
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors, contrast, wpEval } = require( './helpers' );
const { wpCliServes } = require( './residue' );
const { showDarkVariation, settle } = require( './theme-colours' );

test.describe.configure( { mode: 'serial' } );

const FLOW_SLUG = 'corex-e2e-mode-notices';
const PAUSED_EN = 'Forms are paused for a short while. Please try again later.';
const TEXT = {
	en: { testTitle: 'Test site', pausedTitle: 'Paused', paused: PAUSED_EN },
	ar: {
		testTitle: 'موقع تجريبي',
		pausedTitle: 'متوقفة مؤقتًا',
		paused: 'النماذج متوقفة لفترة قصيرة. يُرجى المحاولة لاحقًا.',
	},
};
const WIDTHS = [ 1280, 375 ];

/** What the run made, and the mode it found: written by beforeAll. */
let fixture = null;

const SEED = `
use Corex\\Forms\\Flow\\Flow;
use Corex\\Forms\\Flow\\FlowConfiguration;
use Corex\\Forms\\Flow\\FlowRepository;
use Corex\\Forms\\Flow\\FlowVersion;

$repo = Corex\\Boot::app()->container()->make(FlowRepository::class);
$flow = $repo->findBySlug('${ FLOW_SLUG }');
if ($flow === null) {
    $now = new DateTimeImmutable('now');
    $owner = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 1);
    $flow = $repo->save(new Flow(0, wp_generate_uuid4(), '${ FLOW_SLUG }', 'CoreX E2E mode notices', '', Flow::STATE_DRAFT, $owner, Flow::PLACEMENT_BLOCK, null, 1, 0, false, $owner, $owner, $now, $now));
    $repo->appendVersion(new FlowVersion(0, $flow->id, 1, new FlowConfiguration(
        schema: [
            ['uuid' => 'mn-name', 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'width' => 'half'],
            ['uuid' => 'mn-email', 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'width' => 'half'],
        ],
        validation: ['name' => ['required'], 'email' => ['required', 'email']],
        routing: ['fallback' => ['type' => 'flow_owner']],
        emailRoutes: [],
        success: ['type' => 'inline', 'message' => 'Received.'],
        placementSnapshot: [],
    ), $owner, $now));
    $flow = $repo->save($flow->withState(Flow::STATE_PUBLISHED, 1, $owner, $now));
}
$pages = [];
foreach (['flow' => 'corex/flow', 'cta' => 'corex/cta-flow', 'formflow' => 'corex/form'] as $key => $block) {
    $id = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'Mode notices ' . $key,
        'post_content' => sprintf('<!-- wp:%s {"flowId":%d,"heading":"Talk to us","intro":"Tell us about your project."} /-->', $block, $flow->id),
    ]);
    $pages[$key] = ['id' => $id, 'link' => get_permalink($id)];
}
// A code-defined form in the Form block: CoreX's own contact form.
$id = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Mode notices code form',
    'post_content' => '<!-- wp:corex/form {"source":"registry","formSlug":"contact"} /-->',
]);
$pages['code'] = ['id' => $id, 'link' => get_permalink($id)];
echo wp_json_encode(['flowId' => $flow->id, 'pages' => $pages, 'mode' => get_option('corex_operations_mode', null)]);
`;

/**
 * Put the site in a mode. No page cache stands between the browser and these pages on the test
 * sites, so the option is the whole change.
 *
 * @param {string|null} mode The mode, or null for none stored.
 */
function setMode( mode ) {
	const answer = wpEval(
		mode === null
			? "delete_option('corex_operations_mode'); echo 'set';"
			: `update_option('corex_operations_mode', '${ mode }'); echo 'set';`
	);
	if ( answer === null ) {
		throw new Error(
			`WP-CLI did not answer; the mode was not set to ${ mode }.`
		);
	}
}

/**
 * The test-site notice's sentence on this site, from what the mail gate does with a CoreX email
 * here: it depends on the site's mail settings and on whether CoreX Mail is active (#523, #650).
 *
 * @return {string} The sentence the notice should say.
 */
function testSiteSentence() {
	const decision = lastLine(
		wpEval(
			'echo Corex\\Boot::app()->container()->make(Corex\\Mail\\Capture\\MailGate::class)->decide(true)->value;'
		)
	);
	const start =
		'This is a test copy of the site. What you send here is kept for testing.';
	return (
		{
			deliver: `${ start } This form's emails are delivered.`,
			refuse: `${ start } This form's emails are not delivered.`,
		}[ decision ] ||
		`${ start } This form's emails stay on the site and are not delivered.`
	);
}

/**
 * The last line WP-CLI printed, which is the seed's answer.
 *
 * @param {string} output What WP-CLI printed.
 * @return {string} Its last line.
 */
function lastLine( output ) {
	return ( output || '' ).trim().split( /\r?\n/ ).pop();
}

test.beforeAll( ( {}, testInfo ) => {
	test.skip(
		! wpCliServes( wpEval, testInfo.project.use.baseURL ),
		'needs WP-CLI on the install COREX_BASE_URL serves'
	);
	fixture = JSON.parse( lastLine( wpEval( SEED ) ) );
} );

test.afterAll( () => {
	if ( ! fixture ) {
		return;
	}
	setMode( fixture.mode );
	wpEval( `
foreach ([${ Object.values( fixture.pages )
		.map( ( made ) => made.id )
		.join( ', ' ) }] as $id) { wp_delete_post($id, true); }
` );
} );

/**
 * A signed-out visitor's page, in a language, at a width.
 *
 * @param {import('@playwright/test').Browser} browser The browser.
 * @param {string}                             baseURL The site.
 * @param {string}                             locale  'en' or 'ar'.
 * @param {number}                             width   The viewport width.
 * @return {Promise<{context: Object, page: Object}>} The context to close and its page.
 */
async function visitor( browser, baseURL, locale, width ) {
	const context = await browser.newContext( {
		storageState: { cookies: [], origins: [] },
		viewport: { width, height: 900 },
	} );
	if ( locale === 'ar' ) {
		await context.addCookies( [
			{ name: 'corex_e2e_locale', value: 'ar', url: baseURL },
		] );
	}

	return { context, page: await context.newPage() };
}

/**
 * Open a page in an appearance, drawn.
 *
 * @param {import('@playwright/test').Page} page  The page.
 * @param {string}                          link  Where.
 * @param {string}                          theme 'light' or 'dark'.
 */
async function open( page, link, theme ) {
	await page.goto( link );
	if ( theme === 'dark' ) {
		await showDarkVariation( page );
	}
	await settle( page );
}

/**
 * What a notice computes, with the tokens it should match resolved on the same page.
 *
 * @param {import('@playwright/test').Page} page     The page.
 * @param {string}                          selector The notice.
 * @return {Promise<Object>} Colours, edges, sizes and the boxes around it.
 */
function measure( page, selector ) {
	return page.locator( selector ).evaluate( ( notice ) => {
		const token = ( property, value ) => {
			const probe = document.createElement( 'span' );
			probe.style.setProperty( property, value );
			notice.parentNode.appendChild( probe );
			const answer = window
				.getComputedStyle( probe )
				.getPropertyValue( property );
			probe.remove();
			return answer;
		};
		const style = window.getComputedStyle( notice );
		const box = ( element ) => {
			if ( ! element ) {
				return null;
			}
			const rect = element.getBoundingClientRect();
			return {
				top: rect.top,
				bottom: rect.bottom,
				left: rect.left,
				right: rect.right,
				width: rect.width,
			};
		};
		// A code-defined form stands in no flow section: its panel's parent holds it.
		const section = notice.closest( '.corex-flow' ) || notice.parentElement;
		const sectionStyle = window.getComputedStyle( section );

		return {
			dir: window.getComputedStyle( document.documentElement ).direction,
			background: style.backgroundColor,
			left: {
				width: style.borderLeftWidth,
				color: style.borderLeftColor,
			},
			right: {
				width: style.borderRightWidth,
				color: style.borderRightColor,
			},
			fontSize: style.fontSize,
			title: window.getComputedStyle(
				notice.querySelector( '.corex-flow-notice__title' )
			).color,
			message: window.getComputedStyle(
				notice.querySelector( '.corex-flow-notice__message' )
			).color,
			role: notice.getAttribute( 'role' ),
			live: notice.getAttribute( 'aria-live' ),
			focusable: notice.querySelectorAll( 'a, button, input, [tabindex]' )
				.length,
			tokens: {
				warning: token( 'color', 'var(--wp--preset--color--warning)' ),
				info: token( 'color', 'var(--wp--preset--color--info)' ),
				surface: token( 'color', 'var(--wp--preset--color--surface)' ),
				surfaceAlt: token(
					'color',
					'var(--wp--preset--color--surface-alt)'
				),
				small: token( 'font-size', 'var(--wp--preset--font-size--sm)' ),
			},
			notice: box( notice ),
			heading: box( section.querySelector( '.corex-flow__title' ) ),
			intro: box( section.querySelector( '.corex-flow__intro' ) ),
			form: box( section.querySelector( 'form.corex-form' ) ),
			content: {
				left:
					section.getBoundingClientRect().left +
					parseFloat( sectionStyle.paddingLeft ),
				right:
					section.getBoundingClientRect().right -
					parseFloat( sectionStyle.paddingRight ),
			},
			fits:
				document.documentElement.scrollWidth <=
				document.documentElement.clientWidth,
		};
	} );
}

/**
 * The edge is on the inline-start side, in the given colour, and nowhere else.
 *
 * @param {Object} measured What `measure()` read.
 * @param {string} colour   The edge's colour.
 */
function expectStartEdge( measured, colour ) {
	const [ start, end ] =
		measured.dir === 'rtl'
			? [ measured.right, measured.left ]
			: [ measured.left, measured.right ];
	expect( start.width ).toBe( '4px' );
	expect( start.color ).toBe( colour );
	expect( end.width ).toBe( '0px' );
}

/**
 * The panel's text reads at 4.5:1 or more on its own background.
 *
 * @param {Object} measured What `measure()` read.
 */
function expectReadable( measured ) {
	expect(
		contrast( measured.title, measured.background )
	).toBeGreaterThanOrEqual( 4.5 );
	expect(
		contrast( measured.message, measured.background )
	).toBeGreaterThanOrEqual( 4.5 );
}

for ( const theme of [ 'light', 'dark' ] ) {
	for ( const locale of [ 'en', 'ar' ] ) {
		for ( const width of WIDTHS ) {
			test( `FR-021: the test-site notice sits between the heading and the form, small, warning-edged and readable (${ theme }, ${ locale }, ${ width }px)`, async ( {
				browser,
				baseURL,
			} ) => {
				setMode( 'development' );
				const { context, page } = await visitor(
					browser,
					baseURL,
					locale,
					width
				);
				const errors = collectConsoleErrors( page );
				try {
					await open( page, fixture.pages.flow.link, theme );
					const notice = page.locator( '.corex-flow-notice--test' );
					await expect( notice ).toHaveCount( 1 );
					await expect(
						notice.locator( '.corex-flow-notice__title' )
					).toHaveText( TEXT[ locale ].testTitle );

					const measured = await measure(
						page,
						'.corex-flow-notice--test'
					);
					expect( measured.dir ).toBe(
						locale === 'ar' ? 'rtl' : 'ltr'
					);
					expectStartEdge( measured, measured.tokens.warning );
					expect( measured.background ).toBe(
						measured.tokens.surfaceAlt
					);
					expect( measured.fontSize ).toBe( measured.tokens.small );
					expectReadable( measured );
					expect( measured.role ).toBeNull();
					expect( measured.live ).toBeNull();
					expect( measured.focusable ).toBe( 0 );
					expect( measured.notice.top ).toBeGreaterThanOrEqual(
						measured.intro.bottom
					);
					expect( measured.intro.top ).toBeGreaterThanOrEqual(
						measured.heading.bottom
					);
					expect( measured.notice.bottom ).toBeLessThanOrEqual(
						measured.form.top
					);
					// As wide as the block, which is as wide as its form.
					expect(
						Math.abs( measured.notice.left - measured.content.left )
					).toBeLessThanOrEqual( 1 );
					expect(
						Math.abs(
							measured.notice.right - measured.content.right
						)
					).toBeLessThanOrEqual( 1 );
					expect( measured.fits ).toBe( true );

					// The two half fields side by side on a column, one above the other on a phone.
					const [ name, email ] = await Promise.all(
						[ 'name', 'email' ].map( ( field ) =>
							page
								.locator( `[data-corex-field="${ field }"]` )
								.boundingBox()
						)
					);
					if ( width === 375 ) {
						expect( email.y ).toBeGreaterThanOrEqual(
							name.y + name.height
						);
					} else {
						expect( email.y ).toBe( name.y );
					}
				} finally {
					await context.close();
				}
				expect( errors ).toEqual( [] );
			} );
		}
	}
}

for ( const mode of [ 'development', 'staging' ] ) {
	test( `FR-021 (#523, #650): the test-site notice says what the mail gate does with the form's emails, and the administrator reads it too (${ mode })`, async ( {
		page,
	} ) => {
		setMode( mode );
		await page.goto( fixture.pages.flow.link );
		await expect(
			page.locator(
				'.corex-flow-notice--test .corex-flow-notice__message'
			)
		).toHaveText( testSiteSentence() );
		await expect( page.locator( 'form.corex-form' ) ).toHaveCount( 1 );
	} );
}

test( 'FR-021: the Form block with a flow keeps 24px between its heading, intro, notice and form, and its status line spans the form', async ( {
	browser,
	baseURL,
} ) => {
	setMode( 'development' );
	const { context, page } = await visitor( browser, baseURL, 'en', 1280 );
	try {
		await page.goto( fixture.pages.formflow.link );
		const boxes = await page
			.locator( '.corex-flow--form > *' )
			.evaluateAll( ( children ) =>
				children.map( ( child ) => {
					const box = child.getBoundingClientRect();
					return {
						className: child.className,
						top: box.top,
						bottom: box.bottom,
						left: box.left,
						right: box.right,
					};
				} )
			);
		expect( boxes.map( ( box ) => box.className ) ).toEqual( [
			'corex-flow__title',
			'corex-flow__intro',
			'corex-flow-notice corex-flow-notice--test',
			'corex-form',
		] );
		// The same gap as every other flow block: the section's own, spacing 40.
		const gap = await page
			.locator( '.corex-flow--form' )
			.evaluate( ( section ) =>
				parseFloat( window.getComputedStyle( section ).rowGap )
			);
		expect( gap ).toBe( 24 );
		for ( let index = 1; index < boxes.length; index++ ) {
			expect( boxes[ index ].top - boxes[ index - 1 ].bottom ).toBe(
				gap
			);
			expect( boxes[ index ].left ).toBe( boxes[ 0 ].left );
			expect( boxes[ index ].right ).toBe( boxes[ 0 ].right );
		}
		// A status line spans the form while it says something; empty, it keeps its old cell.
		await page
			.locator( '.corex-form__status' )
			.evaluate( ( line ) => ( line.textContent = 'Sent.' ) );
		const [ status, form ] = await Promise.all( [
			page.locator( '.corex-form__status' ).boundingBox(),
			page.locator( 'form.corex-form' ).boundingBox(),
		] );
		expect( status.x ).toBe( form.x );
		expect( status.width ).toBe( form.width );
	} finally {
		await context.close();
	}
} );

for ( const theme of [ 'light', 'dark' ] ) {
	for ( const locale of [ 'en', 'ar' ] ) {
		for ( const width of WIDTHS ) {
			test( `FR-021: while forms are paused a visitor reads the paused panel where the form was, and in the Call to action it stands on surface (${ theme }, ${ locale }, ${ width }px)`, async ( {
				browser,
				baseURL,
			} ) => {
				setMode( 'forms-paused' );
				const { context, page } = await visitor(
					browser,
					baseURL,
					locale,
					width
				);
				try {
					for ( const [ block, background ] of [
						[ 'flow', 'surfaceAlt' ],
						[ 'cta', 'surface' ],
					] ) {
						await open( page, fixture.pages[ block ].link, theme );
						await expect(
							page.locator( 'form.corex-form' )
						).toHaveCount( 0 );
						await expect(
							page.locator( '.corex-flow-notice--test' )
						).toHaveCount( 0 );
						const panel = page.locator(
							'.corex-flow--paused .corex-flow-notice'
						);
						await expect(
							panel.locator( '.corex-flow-notice__title' )
						).toHaveText( TEXT[ locale ].pausedTitle );

						const measured = await measure(
							page,
							'.corex-flow--paused .corex-flow-notice'
						);
						expectStartEdge( measured, measured.tokens.info );
						expect( measured.background ).toBe(
							measured.tokens[ background ]
						);
						expect( measured.fontSize ).not.toBe(
							measured.tokens.small
						);
						expectReadable( measured );
						expect( measured.role ).toBeNull();
						expect( measured.notice.top ).toBeGreaterThanOrEqual(
							measured.heading.bottom
						);
						expect(
							Math.abs(
								measured.notice.left - measured.content.left
							)
						).toBeLessThanOrEqual( 1 );
						expect(
							Math.abs(
								measured.notice.right - measured.content.right
							)
						).toBeLessThanOrEqual( 1 );
						expect( measured.fits ).toBe( true );
					}
				} finally {
					await context.close();
				}
			} );
		}
	}
}

test( 'FR-021: in the Call to action block the test-site notice stands on surface too', async ( {
	browser,
	baseURL,
} ) => {
	setMode( 'development' );
	const { context, page } = await visitor( browser, baseURL, 'en', 1280 );
	try {
		for ( const theme of [ 'light', 'dark' ] ) {
			await open( page, fixture.pages.cta.link, theme );
			const measured = await measure( page, '.corex-flow-notice--test' );
			expect( measured.background ).toBe( measured.tokens.surface );
			expectReadable( measured );
		}
	} finally {
		await context.close();
	}
} );

test( 'FR-021: an administrator of a paused site gets the real form and no panel', async ( {
	page,
} ) => {
	setMode( 'forms-paused' );
	await page.goto( fixture.pages.flow.link );
	await expect( page.locator( 'form.corex-form' ) ).toHaveCount( 1 );
	await expect( page.locator( '.corex-flow-notice' ) ).toHaveCount( 0 );
} );

for ( const mode of [ 'production', 'coming-soon', 'maintenance' ] ) {
	test( `FR-021: ${ mode } shows no notice`, async ( { page } ) => {
		setMode( mode );
		await page.goto( fixture.pages.flow.link );
		await expect( page.locator( 'form.corex-form' ) ).toHaveCount( 1 );
		await expect( page.locator( '.corex-flow-notice' ) ).toHaveCount( 0 );
	} );
}

for ( const theme of [ 'light', 'dark' ] ) {
	for ( const locale of [ 'en', 'ar' ] ) {
		for ( const width of WIDTHS ) {
			test( `FR-021: paused after the page was loaded, the paused panel is drawn above the form, the answers stay and the status line says it once (${ theme }, ${ locale }, ${ width }px)`, async ( {
				browser,
				baseURL,
			} ) => {
				setMode( 'production' );
				const { context, page } = await visitor(
					browser,
					baseURL,
					locale,
					width
				);
				try {
					await open( page, fixture.pages.flow.link, theme );
					await expect(
						page.locator( '.corex-flow-notice' )
					).toHaveCount( 0 );
					const name = page.locator( '[name="name"]' );
					const email = page.locator( '[name="email"]' );
					await name.fill( 'Late visitor' );
					await email.fill( 'late@example.test' );
					setMode( 'forms-paused' );

					const refused = page.waitForResponse( ( response ) =>
						response.url().includes( '/submit' )
					);
					await page.locator( '.corex-form__submit' ).click();
					expect( ( await refused ).status() ).toBe( 503 );

					const panel = page.locator(
						'.corex-flow .corex-flow-notice'
					);
					await expect( panel ).toHaveCount( 1 );
					await expect(
						panel.locator( '.corex-flow-notice__title' )
					).toHaveText( TEXT[ locale ].pausedTitle );
					await expect(
						panel.locator( '.corex-flow-notice__message' )
					).toHaveText( TEXT[ locale ].paused );
					// No back-by time is set, so there is no line for it.
					await expect(
						panel.locator( '.corex-flow-notice__when' )
					).toHaveCount( 0 );

					const measured = await measure(
						page,
						'.corex-flow .corex-flow-notice'
					);
					expectStartEdge( measured, measured.tokens.info );
					expect( measured.background ).toBe(
						measured.tokens.surfaceAlt
					);
					expectReadable( measured );
					expect( measured.role ).toBeNull();
					expect( measured.live ).toBeNull();
					expect( measured.notice.top ).toBeGreaterThanOrEqual(
						measured.intro.bottom
					);
					expect( measured.notice.bottom ).toBeLessThanOrEqual(
						measured.form.top
					);
					expect(
						Math.abs( measured.notice.left - measured.content.left )
					).toBeLessThanOrEqual( 1 );
					expect(
						Math.abs(
							measured.notice.right - measured.content.right
						)
					).toBeLessThanOrEqual( 1 );
					expect( measured.fits ).toBe( true );

					const status = page.locator( '.corex-form__status' );
					await expect( status ).toHaveAttribute( 'role', 'status' );
					const [ line, sent ] = await Promise.all( [
						status.boundingBox(),
						page.locator( 'form.corex-form' ).boundingBox(),
					] );
					expect( line.x ).toBe( sent.x );
					expect( line.width ).toBe( sent.width );
					await expect(
						page.locator( '.corex-form__submit' )
					).toBeFocused();
					await expect( status ).toHaveText( TEXT[ locale ].paused );
					await expect(
						page
							.locator( '[role="status"], [aria-live]' )
							.filter( { hasText: TEXT[ locale ].paused } )
					).toHaveCount( 1 );
					await expect( name ).toHaveValue( 'Late visitor' );
					await expect( email ).toHaveValue( 'late@example.test' );
					await expect(
						page.locator( '[aria-invalid="true"]' )
					).toHaveCount( 0 );
				} finally {
					await context.close();
				}
			} );
		}
	}
}

test( 'FR-021: on a test copy, a pause after the page was loaded draws the paused panel between the test-site notice and the form', async ( {
	browser,
	baseURL,
} ) => {
	setMode( 'development' );
	const { context, page } = await visitor( browser, baseURL, 'en', 1280 );
	try {
		await page.goto( fixture.pages.flow.link );
		await page.locator( '[name="name"]' ).fill( 'Late visitor' );
		await page.locator( '[name="email"]' ).fill( 'late@example.test' );
		setMode( 'forms-paused' );
		await page.locator( '.corex-form__submit' ).click();

		await expect(
			page.locator( '.corex-flow > .corex-flow-notice' )
		).toHaveCount( 2 );
		const order = await page
			.locator( '.corex-flow > *' )
			.evaluateAll( ( children ) =>
				children.map( ( child ) => child.className )
			);
		expect( order.slice( 2 ) ).toEqual( [
			'corex-flow-notice corex-flow-notice--test',
			'corex-flow-notice',
			'corex-form',
		] );
	} finally {
		await context.close();
	}
} );

test( 'FR-021: a code-defined form in the Form block says it is on a test copy, and is replaced by the paused panel while forms are paused', async ( {
	browser,
	baseURL,
	page: administrator,
} ) => {
	const { context, page } = await visitor( browser, baseURL, 'en', 375 );
	try {
		setMode( 'staging' );
		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.goto( fixture.pages.code.link );
		const [ wide, wideForm ] = await Promise.all( [
			page.locator( '.corex-flow-notice--test' ).boundingBox(),
			page.locator( 'form.corex-form' ).boundingBox(),
		] );
		expect( wide.x ).toBe( wideForm.x );
		expect( wide.width ).toBe( wideForm.width );
		expect( wideForm.y - ( wide.y + wide.height ) ).toBe( 24 );

		await page.setViewportSize( { width: 375, height: 900 } );
		await page.goto( fixture.pages.code.link );
		const notice = page.locator( '.corex-flow-notice--test' );
		await expect( notice ).toHaveCount( 1 );
		const tested = await measure( page, '.corex-flow-notice--test' );
		expectStartEdge( tested, tested.tokens.warning );
		expect( tested.fontSize ).toBe( tested.tokens.small );
		const [ panel, form ] = await Promise.all( [
			notice.boundingBox(),
			page.locator( 'form.corex-form' ).boundingBox(),
		] );
		expect( form.y ).toBeGreaterThan( panel.y + panel.height );

		setMode( 'forms-paused' );
		await page.goto( fixture.pages.code.link );
		await expect( page.locator( 'form.corex-form' ) ).toHaveCount( 0 );
		await expect(
			page.locator( '.corex-flow-notice .corex-flow-notice__title' )
		).toHaveText( 'Paused' );
		const paused = await measure( page, '.corex-flow-notice' );
		expectStartEdge( paused, paused.tokens.info );
		expect( paused.background ).toBe( paused.tokens.surfaceAlt );
		expectReadable( paused );

		await administrator.goto( fixture.pages.code.link );
		await expect( administrator.locator( 'form.corex-form' ) ).toHaveCount(
			1
		);
		await expect(
			administrator.locator( '.corex-flow-notice' )
		).toHaveCount( 0 );
	} finally {
		await context.close();
	}
} );
