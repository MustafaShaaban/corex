/**
 * Corex E2E — front-office product surfaces (Spec 068 US9, FR-155/158/161/164).
 *
 * Covers the account block end to end: it provisions a page carrying the `corex/account`
 * block through the REST API (admin session), then verifies the guest account panel
 * (login / register / recovery forms) renders and enhances, the member panel is
 * reachable, and neither the page nor the account block scrolls the document sideways at
 * 375px. Docs navigation (version selector, sidebar, search) is verified separately by
 * the PHP `DocsNavigationTest`, since the docs site is a static build.
 *
 * It also holds where the block says what the server answered (spec 068, FR-161a): beside the
 * button that was pressed, inside the window, on a desktop and on a phone, read left to right
 * and right to left. The answers asked for here are refusals the account service gives before
 * it reaches WordPress, so no account is made, no sign-in is counted and no email is sent.
 *
 * And that a label belongs to the field under it (DECISIONS #337): the signed-out block prints
 * several forms, two of which ask for the same thing, so a label is followed to its field in
 * the browser, and a field's name is read from the browser's own accessibility tree.
 *
 * And what the block says when there is no answer to show: a request that does not arrive or
 * is answered with a page (FR-161c), and a browser that runs no scripts (FR-161d).
 *
 * And what a signed-in person is shown under "Active sessions" and "Recent activity": the
 * list, or that there is nothing to list, or that the list could not be loaded (FR-161f).
 * The lists are answered for in the test where a failure or an exact row is wanted, and
 * signing out everywhere is always answered for here, so the suite's own session is kept.
 *
 * ENVIRONMENT-GATED: needs Apache up (http://corex.local) + `npx playwright install`.
 */
const { test, expect } = require( '@playwright/test' );

let accountPath = '/?page_id=';

test.beforeAll( async ( { browser } ) => {
	// Provision an Account page via REST using the saved admin session + a REST nonce.
	const context = await browser.newContext( {
		storageState: require( './global-setup' ).STORAGE_STATE,
	} );
	const page = await context.newPage();
	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate(
		() => window.wpApiSettings && window.wpApiSettings.nonce
	);

	const res = await page.request.post( '/wp-json/wp/v2/pages', {
		headers: { 'X-WP-Nonce': nonce },
		data: {
			title: 'Account E2E',
			status: 'publish',
			content: '<!-- wp:corex/account /-->',
		},
	} );
	const body = await res.json();
	accountPath = `/?page_id=${ body.id }`;
	await context.close();
} );

const SIGNED_OUT = { cookies: [], origins: [] };

/** What a browser that runs no scripts is shown above the forms (FR-161d). */
const SCRIPTS_OFF_NOTE = '.corex-account__noscript';

/** The windows a message is looked for in. */
const WINDOWS = [
	{
		name: 'a desktop window',
		viewport: { width: 1280, height: 720 },
		direction: 'ltr',
	},
	{
		name: 'a phone',
		viewport: { width: 375, height: 812 },
		direction: 'ltr',
	},
	{
		name: 'a desktop window read right to left',
		viewport: { width: 1280, height: 720 },
		direction: 'rtl',
	},
	{
		name: 'a phone read right to left',
		viewport: { width: 375, height: 812 },
		direction: 'rtl',
	},
];

/**
 * Opens the account page in a window of its own.
 *
 * @param {import('@playwright/test').Browser}                       browser      The browser of the test.
 * @param {Object}                                                   view         One of WINDOWS.
 * @param {Object|string}                                            storageState Who is looking: SIGNED_OUT, or a saved session.
 * @param {boolean}                                                  scripts      Whether the browser runs the page's scripts.
 * @param {(page: import('@playwright/test').Page) => Promise<void>} before       What is set on the page before it opens: a request answered in the test.
 * @return {Promise<import('@playwright/test').Page>} The page, with the block in it.
 */
async function openAccount(
	browser,
	view,
	storageState,
	scripts = true,
	before = async () => {}
) {
	const context = await browser.newContext( {
		storageState,
		viewport: view.viewport,
		javaScriptEnabled: scripts,
	} );
	const page = await context.newPage();
	await before( page );
	await page.goto( accountPath );
	await page.evaluate( ( direction ) => {
		document.documentElement.setAttribute( 'dir', direction );
		document.body.setAttribute( 'dir', direction );
	}, view.direction );
	await expect( page.locator( '[data-corex-account]' ) ).toBeVisible();

	return page;
}

/**
 * Puts a control against the top or the foot of the window, as a person who scrolled there.
 *
 * @param {import('@playwright/test').Locator} control The button or field.
 * @param {'start'|'end'}                      edge    The window's top, or its foot.
 */
async function scrollToEdge( control, edge ) {
	await control.evaluate(
		( el, block ) => el.scrollIntoView( { block, behavior: 'instant' } ),
		edge
	);
}

/**
 * How far a form's message line reaches outside the window, in pixels on each side.
 *
 * @param {import('@playwright/test').Locator} line The message line.
 * @return {Promise<Object>} Zero on every side when the whole line is in the window.
 */
function outsideWindow( line ) {
	return line.evaluate( ( el ) => {
		const box = el.getBoundingClientRect();
		const root = document.documentElement;
		const beyond = ( px ) => Math.max( 0, Math.round( px ) );

		return {
			above: beyond( -box.top ),
			below: beyond( box.bottom - root.clientHeight ),
			left: beyond( -box.left ),
			right: beyond( box.right - root.clientWidth ),
		};
	} );
}

/**
 * Where a form's message line sits against the form's own button and fields.
 *
 * @param {import('@playwright/test').Locator} form The form.
 * @return {Promise<Object>} Distances in pixels, and whether anything is drawn over the line.
 */
function placement( form ) {
	return form.evaluate( ( el ) => {
		const line = el.querySelector( '.corex-account__status' );
		const box = line.getBoundingClientRect();
		const button = el
			.querySelector( 'button[type="submit"]' )
			.getBoundingClientRect();
		const field = el
			.querySelector( '.corex-account__row input' )
			.getBoundingClientRect();
		const round = ( px ) => Math.round( px * 10 ) / 10;

		return {
			underButtonBy: round( box.top - button.bottom ),
			gapBetweenRows: round(
				parseFloat( window.getComputedStyle( el ).rowGap )
			),
			leftOfFieldBy: round( field.left - box.left ),
			rightOfFieldBy: round( box.right - field.right ),
			covered:
				document.elementFromPoint(
					box.left + box.width / 2,
					box.top + box.height / 2
				) !== line,
		};
	} );
}

/**
 * Each label of the block's fields: the form it is in, the form of the field the browser ties
 * it to, and whether that field is the one under it.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @return {Promise<Object[]>} One entry for each label, in the order of the page.
 */
function labelTies( page ) {
	return page
		.locator( '.corex-account__row label' )
		.evaluateAll( ( labels ) =>
			labels.map( ( label ) => {
				const kind = ( form ) =>
					form
						? form.getAttribute( 'data-corex-account-form' )
						: null;

				return {
					label: label.textContent.trim(),
					form: kind( label.closest( 'form' ) ),
					pointsInto: kind( label.control && label.control.form ),
					atTheFieldUnderIt:
						label.control === label.nextElementSibling,
				};
			} )
		);
}

/** Why a test that reads the browser's own accessibility tree is skipped outside Chromium. */
const CHROMIUM_ONLY =
	'The accessibility tree is read over the DevTools protocol, which only Chromium speaks.';

/**
 * The names Chromium itself gives the block's text fields, read from its own accessibility
 * tree. A field it does not present (the one left for bots) is left out.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @return {Promise<string[]>} Their names, in the order of the page. A field with no name gives an empty one.
 */
async function fieldNamesInChromiumsTree( page ) {
	const session = await page.context().newCDPSession( page );
	const { result } = await session.send( 'Runtime.evaluate', {
		expression: 'document.querySelector( "[data-corex-account]" )',
	} );
	const { nodes } = await session.send( 'Accessibility.queryAXTree', {
		objectId: result.objectId,
		role: 'textbox',
	} );
	await session.detach();

	return nodes
		.filter( ( node ) => ! node.ignored )
		.map( ( node ) => node.name?.value ?? '' );
}

test( 'the guest account block renders the sign-in, register, and recovery forms', async ( {
	browser,
} ) => {
	// A fresh, unauthenticated context — the visitor's view. The config's `use`
	// options (including the admin `storageState`) are inherited by
	// `browser.newContext()`, so an explicit empty state is required to log out;
	// without it the block would render the signed-in member panel.
	const guest = await browser.newContext( {
		storageState: { cookies: [], origins: [] },
	} );
	const page = await guest.newPage();
	await page.goto( accountPath );

	const account = page.locator( '[data-corex-account]' );
	await expect( account ).toBeVisible();
	await expect(
		page.locator( '[data-corex-account-form="login"]' )
	).toBeVisible();
	// Registration form appears when the site allows sign-ups; the recovery form always does.
	await expect(
		page.locator( '[data-corex-account-form="reset-request"]' )
	).toBeVisible();
	// The note for a browser that runs no scripts is not this browser's.
	await expect( page.locator( SCRIPTS_OFF_NOTE ) ).toHaveCount( 0 );

	await guest.close();
} );

test( 'the account surface does not scroll sideways at 375px', async ( {
	browser,
} ) => {
	// Anonymous visitor (see note above) at a phone width.
	const guest = await browser.newContext( {
		storageState: { cookies: [], origins: [] },
		viewport: { width: 375, height: 812 },
	} );
	const page = await guest.newPage();
	await page.goto( accountPath );
	await expect( page.locator( '[data-corex-account]' ) ).toBeVisible();

	const overflow = await page.evaluate(
		() =>
			document.documentElement.scrollWidth >
			document.documentElement.clientWidth
	);
	expect( overflow ).toBe( false );

	await guest.close();
} );

test( 'a label of the signed-out account block belongs to the field under it, in its own form', async ( {
	browser,
} ) => {
	const page = await openAccount( browser, WINDOWS[ 0 ], SIGNED_OUT );

	const ties = await labelTies( page );
	// Sign-in asks for two things and the forgotten password form for one, on every site.
	expect( ties.length ).toBeGreaterThanOrEqual( 3 );
	expect(
		ties.filter(
			( tie ) => tie.pointsInto !== tie.form || ! tie.atTheFieldUnderIt
		),
		'labels the browser ties to another field'
	).toEqual( [] );

	// Both forms ask for "Username or email": pressing the second one's label is what told them apart.
	const forgot = page.locator( '[data-corex-account-form="reset-request"]' );
	await forgot.locator( 'label' ).click();
	await expect( forgot.locator( 'input[name="login"]' ) ).toBeFocused();

	await page.context().close();
} );

test( 'every field of the signed-out account block has the name its label gives it', async ( {
	browser,
	browserName,
} ) => {
	test.skip( browserName !== 'chromium', CHROMIUM_ONLY );
	const page = await openAccount( browser, WINDOWS[ 0 ], SIGNED_OUT );

	const labels = ( await labelTies( page ) ).map( ( tie ) => tie.label );
	expect( labels.length ).toBeGreaterThanOrEqual( 3 );
	expect( await fieldNamesInChromiumsTree( page ) ).toEqual( labels );

	await page.context().close();
} );

for ( const view of WINDOWS ) {
	test( `a refused sign-in says so under the Sign in button, in ${ view.name }`, async ( {
		browser,
	} ) => {
		const page = await openAccount( browser, view, SIGNED_OUT );
		const form = page.locator( '[data-corex-account-form="login"]' );
		const button = form.getByRole( 'button', { name: 'Sign in' } );

		// The button against the top of the window: everything above it is out of sight.
		await scrollToEdge( button, 'start' );
		await button.click();

		const line = form.getByRole( 'status' );
		await expect( line ).toHaveText(
			'Please enter your username and password.'
		);
		await expect
			.poll( () => outsideWindow( line ) )
			.toEqual( { above: 0, below: 0, left: 0, right: 0 } );

		const at = await placement( form );
		expect( at.covered, 'nothing is drawn over the message' ).toBe( false );
		// One row's gap under the button, like every other row of the form.
		expect( at.underButtonBy ).toBeCloseTo( at.gapBetweenRows, 0 );
		// As wide as the fields above it, on both sides.
		expect( at.leftOfFieldBy ).toBeCloseTo( 0, 0 );
		expect( at.rightOfFieldBy ).toBeCloseTo( 0, 0 );

		await page.context().close();
	} );

	test( `a reset link asked for with Enter at the foot of ${ view.name } is answered in the window`, async ( {
		browser,
	} ) => {
		const page = await openAccount( browser, view, SIGNED_OUT );
		const form = page.locator(
			'[data-corex-account-form="reset-request"]'
		);
		const field = form.locator( 'input[name="login"]' );

		// The field against the foot of the window: the button and the line are below it.
		await scrollToEdge( field, 'end' );
		await field.focus();
		await page.keyboard.press( 'Enter' );

		const line = form.getByRole( 'status' );
		await expect( line ).toHaveText(
			'Please enter your username or email.'
		);
		await expect
			.poll( () => outsideWindow( line ) )
			.toEqual( { above: 0, below: 0, left: 0, right: 0 } );
		// Said in one place: a screen reader is told once.
		await expect(
			page.getByText( 'Please enter your username or email.' )
		).toHaveCount( 1 );

		await page.context().close();
	} );
}

/** The ways the sign-in request ends with nothing of the server's to show (FR-161c). */
const WITHOUT_AN_ANSWER = [
	{
		name: 'does not arrive',
		answer: ( route ) => route.abort(),
	},
	{
		name: 'is answered with an error page and not with JSON',
		answer: ( route ) =>
			route.fulfill( {
				status: 500,
				contentType: 'text/html',
				body: '<h1>Internal Server Error</h1>',
			} ),
	},
];

for ( const ending of WITHOUT_AN_ANSWER ) {
	test( `a sign-in whose request ${ ending.name } says so under the Sign in button, on a phone`, async ( {
		browser,
	} ) => {
		const page = await openAccount( browser, WINDOWS[ 1 ], SIGNED_OUT );
		// Answered here, so the site is not asked and no sign-in is counted.
		await page.route( /account\/login/, ending.answer );
		const form = page.locator( '[data-corex-account-form="login"]' );
		const field = form.locator( 'input[name="password"]' );

		// The field against the foot of the window: the button and the line are below it.
		await scrollToEdge( field, 'end' );
		await field.focus();
		await page.keyboard.press( 'Enter' );

		const line = form.getByRole( 'status' );
		await expect( line ).toHaveText(
			'The site could not be reached, or its answer could not be read. Please try again.'
		);
		// The sentence is the one the page handed the script, which a translation replaces.
		expect(
			await page.evaluate( () => window.corexAccount.i18n.noAnswer )
		).toBe( await line.textContent() );
		await expect( line ).toHaveAttribute( 'data-state', 'error' );
		await expect
			.poll( () => outsideWindow( line ) )
			.toEqual( { above: 0, below: 0, left: 0, right: 0 } );

		await page.context().close();
	} );
}

for ( const view of WINDOWS ) {
	test( `with scripts off the block says above its forms that they need JavaScript, in ${ view.name }`, async ( {
		browser,
	} ) => {
		const page = await openAccount( browser, view, SIGNED_OUT, false );
		const note = page.locator( SCRIPTS_OFF_NOTE );

		await expect( note ).toContainText( 'need JavaScript' );
		// Read before a form is filled in: in the window as the page opens.
		expect( await outsideWindow( note ) ).toEqual( {
			above: 0,
			below: 0,
			left: 0,
			right: 0,
		} );

		const at = await note.evaluate( ( el ) => {
			const box = el.getBoundingClientRect();
			const block = el
				.closest( '[data-corex-account]' )
				.getBoundingClientRect();
			const form = document
				.querySelector( '[data-corex-account-form]' )
				.getBoundingClientRect();
			const field = document
				.querySelector( '.corex-account__row input' )
				.getBoundingClientRect();
			const style = window.getComputedStyle( el );
			const inset =
				parseFloat( style.paddingInlineStart ) +
				parseFloat( style.borderInlineStartWidth );
			const round = ( px ) => Math.round( px * 10 ) / 10;

			return {
				textStartsFromTheFieldsBy: round(
					style.direction === 'rtl'
						? field.right - ( box.right - inset )
						: box.left + inset - field.left
				),
				aboveTheFirstFormBy: round( form.top - box.bottom ),
				gapOfTheBlock: round(
					parseFloat(
						window.getComputedStyle(
							el.closest( '[data-corex-account]' )
						).rowGap
					)
				),
				narrowerThanTheBlockBy: round( block.width - box.width ),
			};
		} );
		// One gap of the block above the forms, and as wide as the block.
		expect( at.aboveTheFirstFormBy ).toBeCloseTo( at.gapOfTheBlock, 0 );
		expect( at.narrowerThanTheBlockBy ).toBeCloseTo( 0, 0 );
		// Its text starts where the fields of the form under it start.
		expect( at.textStartsFromTheFieldsBy ).toBeCloseTo( 0, 0 );

		await page.context().close();
	} );
}

test( 'with scripts off a sign-in that is pressed anyway comes back to the page, which still says why', async ( {
	browser,
} ) => {
	const page = await openAccount( browser, WINDOWS[ 1 ], SIGNED_OUT, false );
	const asked = [];
	page.on( 'request', ( request ) => asked.push( request.url() ) );
	const form = page.locator( '[data-corex-account-form="login"]' );

	await form.locator( 'input[name="login"]' ).fill( 'nobody' );
	await Promise.all( [
		page.waitForEvent( 'load' ),
		form.getByRole( 'button', { name: 'Sign in' } ).click(),
	] );

	await expect( page.locator( SCRIPTS_OFF_NOTE ) ).toBeInViewport( {
		ratio: 1,
	} );
	// The form went to the page and not to the sign-in route: no sign-in was counted.
	expect( asked.filter( ( url ) => /account\/login/.test( url ) ) ).toEqual(
		[]
	);
	await expect( form.getByRole( 'status' ) ).toHaveText( '' );

	await page.context().close();
} );

test( 'a profile that was not saved says so under the Save profile button, on a phone', async ( {
	browser,
} ) => {
	const page = await openAccount(
		browser,
		WINDOWS[ 1 ],
		require( './global-setup' ).STORAGE_STATE
	);
	const form = page.locator( '[data-corex-account-form="profile"]' );
	const button = form.getByRole( 'button', { name: 'Save profile' } );

	await form.locator( 'input[name="display_name"]' ).fill( '' );
	await scrollToEdge( button, 'start' );
	await button.click();

	const line = form.getByRole( 'status' );
	await expect( line ).toHaveText( 'Display name cannot be empty.' );
	await expect
		.poll( () => outsideWindow( line ) )
		.toEqual( { above: 0, below: 0, left: 0, right: 0 } );
	// A signed-in person has the toolbar across the top of the window.
	expect(
		( await placement( form ) ).covered,
		'nothing is drawn over the message'
	).toBe( false );

	await page.context().close();
} );

/** The member view's two lists (FR-161f): the panel of each, its request, and its sentences. */
const LISTS = [
	{
		name: 'sessions',
		panel: '[data-corex-account-sessions]',
		request: /account\/sessions(\?|$)/,
		notLoaded:
			'Your sessions could not be loaded. Load this page again to see them.',
		handedAs: 'sessionsNotLoaded',
	},
	{
		name: 'recent activity',
		panel: '[data-corex-account-notifications]',
		request: /account\/notifications(\?|$)/,
		notLoaded:
			'Your recent activity could not be loaded. Load this page again to see it.',
		handedAs: 'activityNotLoaded',
	},
];

const LIST_NOTE = '.corex-account__list-note';

/** The ways a request for a list ends with no list in it. */
const WITHOUT_A_LIST = [
	...WITHOUT_AN_ANSWER,
	{
		name: 'is refused as WordPress refuses a signed-out person',
		answer: ( route ) =>
			route.fulfill( {
				status: 403,
				contentType: 'application/json',
				body: JSON.stringify( {
					code: 'rest_cookie_invalid_nonce',
					message: 'Cookie check failed',
					data: { status: 403 },
				} ),
			} ),
	},
];

/**
 * Opens the account page as the signed-in administrator, with both lists ending one way.
 *
 * @param {import('@playwright/test').Browser}                         browser The browser of the test.
 * @param {Object}                                                     view    One of WINDOWS.
 * @param {(route: import('@playwright/test').Route) => Promise<void>} answer  What a request for either list is answered with.
 * @return {Promise<import('@playwright/test').Page>} The page, once both requests have ended.
 */
async function openWithLists( browser, view, answer ) {
	const page = await openAccount(
		browser,
		view,
		require( './global-setup' ).STORAGE_STATE,
		true,
		async ( opening ) => {
			for ( const list of LISTS ) {
				await opening.route( list.request, answer );
			}
		}
	);
	for ( const list of LISTS ) {
		await expect(
			page.locator( list.panel ).locator( LIST_NOTE )
		).toBeVisible();
	}

	return page;
}

/**
 * Where the sentence in a list's place sits in its panel, in pixels.
 *
 * @param {import('@playwright/test').Locator} panel The sessions panel, or the activity one.
 * @return {Promise<Object>} Distances, the two spacing steps they are held to, and whether anything is drawn over the sentence.
 */
function notePlacement( panel ) {
	return panel.evaluate( ( el ) => {
		const round = ( px ) => Math.round( px * 10 ) / 10;
		// A spacing step as this page resolves it: the presets are not a fixed number of pixels.
		const step = ( slug ) => {
			const probe = document.createElement( 'div' );
			probe.style.blockSize = `var(--wp--preset--spacing--${ slug })`;
			el.appendChild( probe );
			const size = probe.getBoundingClientRect().height;
			probe.remove();
			return round( size );
		};
		const note = el.querySelector( '.corex-account__list-note' );
		const box = note.getBoundingClientRect();
		const heading = el.querySelector( 'h3' ).getBoundingClientRect();
		const buttons = el.querySelector( '.corex-account__actions' );

		return {
			underHeadingBy: round( box.top - heading.bottom ),
			buttonsUnderItBy: buttons
				? round( buttons.getBoundingClientRect().top - box.bottom )
				: null,
			narrowerThanHeadingBy: round( heading.width - box.width ),
			startsFromHeadingBy: round( box.left - heading.left ),
			smallStep: step( '20' ),
			step: step( '30' ),
			covered:
				document.elementFromPoint(
					box.left + box.width / 2,
					box.top + box.height / 2
				) !== note,
		};
	} );
}

for ( const ending of WITHOUT_A_LIST ) {
	test( `a signed-in person's lists whose request ${ ending.name } say so under their headings, on a phone`, async ( {
		browser,
	} ) => {
		const uncaught = [];
		const page = await openAccount(
			browser,
			WINDOWS[ 1 ],
			require( './global-setup' ).STORAGE_STATE,
			true,
			async ( opening ) => {
				opening.on( 'pageerror', ( error ) =>
					uncaught.push( error.message )
				);
				for ( const list of LISTS ) {
					await opening.route( list.request, ending.answer );
				}
			}
		);

		for ( const list of LISTS ) {
			const panel = page.locator( list.panel );
			const note = panel.locator( LIST_NOTE );

			await expect( note ).toHaveText( list.notLoaded );
			await expect( note ).toHaveAttribute( 'data-state', 'error' );
			// The sentence is the one the page handed the script, which a translation replaces.
			expect(
				await page.evaluate(
					( key ) => window.corexAccount.i18n[ key ],
					list.handedAs
				)
			).toBe( list.notLoaded );
			// No empty list is left under the heading for a screen reader to find.
			await expect( panel.getByRole( 'list' ) ).toHaveCount( 0 );
		}
		// Nobody pressed anything: no message line is written, and the page is where it opened.
		await expect(
			page.locator( '.corex-account__status:not(:empty)' )
		).toHaveCount( 0 );
		expect( await page.evaluate( () => window.scrollY ) ).toBe( 0 );
		expect( uncaught, 'no error is left for nobody to catch' ).toEqual(
			[]
		);

		await page.context().close();
	} );
}

for ( const view of WINDOWS ) {
	test( `a list that could not be loaded is said under its heading and clear of the buttons, in ${ view.name }`, async ( {
		browser,
	} ) => {
		const page = await openWithLists( browser, view, ( route ) =>
			route.abort()
		);

		for ( const list of LISTS ) {
			const panel = page.locator( list.panel );
			// The panels are under the profile form, below the foot of the window as it opens.
			await panel.locator( LIST_NOTE ).scrollIntoViewIfNeeded();
			const at = await notePlacement( panel );

			expect(
				at.covered,
				`nothing is drawn over the ${ list.name }`
			).toBe( false );
			// A small step under the heading it belongs to, and as wide as that heading.
			expect( at.underHeadingBy ).toBeCloseTo( at.smallStep, 0 );
			expect( at.startsFromHeadingBy ).toBeCloseTo( 0, 0 );
			expect( at.narrowerThanHeadingBy ).toBeCloseTo( 0, 0 );
			expect(
				await outsideWindow( panel.locator( LIST_NOTE ) ).then(
					( { left, right } ) => ( { left, right } )
				)
			).toEqual( { left: 0, right: 0 } );
		}
		// The session buttons stand one step under it, as their message line does under them.
		const sessions = await notePlacement(
			page.locator( LISTS[ 0 ].panel )
		);
		expect( sessions.buttonsUnderItBy ).toBeCloseTo( sessions.step, 0 );
		expect(
			await page.evaluate(
				() =>
					document.documentElement.scrollWidth >
					document.documentElement.clientWidth
			),
			'the page does not scroll sideways'
		).toBe( false );

		await page.context().close();
	} );
}

test( 'a signed-in person is shown their sessions, a step above the buttons', async ( {
	browser,
} ) => {
	// Asked of the site itself: the suite's own session is in the answer.
	const page = await openAccount(
		browser,
		WINDOWS[ 0 ],
		require( './global-setup' ).STORAGE_STATE
	);
	const panel = page.locator( LISTS[ 0 ].panel );

	await expect( panel.locator( 'ul > li' ).first() ).toBeVisible();
	await expect( panel.locator( LIST_NOTE ) ).toBeHidden();

	const at = await panel.evaluate( ( el ) => {
		const list = el.querySelector( 'ul' ).getBoundingClientRect();
		const buttons = el.querySelector( '.corex-account__actions' );
		const line = el.querySelector( '.corex-account__status' );

		return {
			buttonsUnderTheListBy:
				buttons.getBoundingClientRect().top - list.bottom,
			lineUnderTheButtonsBy: parseFloat(
				window.getComputedStyle( line ).marginBlockStart
			),
		};
	} );
	expect( at.buttonsUnderTheListBy ).toBeGreaterThan( 0 );
	expect( at.buttonsUnderTheListBy ).toBeCloseTo(
		at.lineUnderTheButtonsBy,
		0
	);

	await page.context().close();
} );

test( 'recent activity with nothing in it says so, and not that it could not be loaded', async ( {
	browser,
} ) => {
	const page = await openAccount(
		browser,
		WINDOWS[ 1 ],
		require( './global-setup' ).STORAGE_STATE,
		true,
		( opening ) =>
			opening.route( LISTS[ 1 ].request, ( route ) =>
				route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( {
						success: true,
						notifications: [],
					} ),
				} )
			)
	);
	const note = page.locator( LISTS[ 1 ].panel ).locator( LIST_NOTE );

	await expect( note ).toHaveText( 'No activity yet.' );
	await expect( note ).toHaveAttribute( 'data-state', 'empty' );
	// It reads as a row of the list would: straight under the heading, from where that starts.
	const at = await notePlacement( page.locator( LISTS[ 1 ].panel ) );
	expect( at.underHeadingBy ).toBeCloseTo( 0, 0 );
	expect( at.startsFromHeadingBy ).toBeCloseTo( 0, 0 );

	await page.context().close();
} );

test( 'signing out everywhere loads the page again without asking for the sessions list', async ( {
	browser,
} ) => {
	const RELOADED = 'the page is loaded again';
	const page = await openAccount(
		browser,
		WINDOWS[ 0 ],
		require( './global-setup' ).STORAGE_STATE,
		true,
		// Answered here: nothing is signed out, and the suite keeps its session.
		( opening ) =>
			opening.route( /account\/sessions\/revoke/, ( route ) =>
				route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( {
						success: true,
						message: 'Signed out of every session.',
					} ),
				} )
			)
	);
	const panel = page.locator( LISTS[ 0 ].panel );
	await expect( panel.locator( 'ul > li' ).first() ).toBeVisible();

	const seen = [];
	page.on( 'request', ( request ) => {
		const route = request.url().match( /corex\/v1\/(account\/[^?]*)/ );
		if ( route ) {
			seen.push( `${ request.method() } ${ route[ 1 ] }` );
		}
	} );
	page.on( 'framenavigated', () => seen.push( RELOADED ) );

	await panel.getByRole( 'button', { name: 'Sign out everywhere' } ).click();

	await expect( panel.getByRole( 'status' ) ).toHaveText(
		'Signed out of every session.'
	);
	await expect.poll( () => seen ).toContain( RELOADED );
	expect( seen.slice( 0, seen.indexOf( RELOADED ) ) ).toEqual( [
		'POST account/sessions/revoke-all',
	] );

	await page.context().close();
} );

test.afterAll( async ( { browser } ) => {
	// Remove the provisioned page.
	const match = accountPath.match( /page_id=(\d+)/ );
	if ( ! match ) {
		return;
	}
	const context = await browser.newContext( {
		storageState: require( './global-setup' ).STORAGE_STATE,
	} );
	const page = await context.newPage();
	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate(
		() => window.wpApiSettings && window.wpApiSettings.nonce
	);
	await page.request
		.delete( `/wp-json/wp/v2/pages/${ match[ 1 ] }?force=true`, {
			headers: { 'X-WP-Nonce': nonce },
		} )
		.catch( () => {} );
	await context.close();
} );
