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
 * And that a field tells the browser what it is for (spec 068, FR-161c): the `autocomplete`
 * value a browser and a password manager read, on the page a visitor is served.
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
 * @param {import('@playwright/test').Browser} browser      The browser of the test.
 * @param {Object}                             view         One of WINDOWS.
 * @param {Object|string}                      storageState Who is looking: SIGNED_OUT, or a saved session.
 * @return {Promise<import('@playwright/test').Page>} The page, with the block in it.
 */
async function openAccount( browser, view, storageState ) {
	const context = await browser.newContext( {
		storageState,
		viewport: view.viewport,
	} );
	const page = await context.newPage();
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

/**
 * What each field of a signed-out form tells the browser it is for: its `autocomplete` value,
 * and null for a field that has none. The field left for bots is never to be filled in.
 * Chromium writes the person's address into a field marked `username`, registration's own
 * among them, and the account takes that address as no username (DECISIONS #347).
 */
const SIGNED_OUT_PURPOSES = {
	login: { login: 'username', password: 'current-password' },
	register: {
		corex_hp: 'off',
		email: 'email',
		username: 'username',
		password: 'new-password',
		password_confirm: 'new-password',
	},
	'reset-request': { login: 'username' },
};

/**
 * The `autocomplete` value of each field of the block somebody fills in, by form and field
 * name. A tick box collects nothing about the person, and a hidden field is not theirs to fill:
 * the forgotten password form carries the page it is on in one (DECISIONS #341).
 *
 * @param {import('@playwright/test').Page} page The page.
 * @return {Promise<Object>} For each form on the page, its fields' values; null where a field has none.
 */
function fieldPurposes( page ) {
	return page
		.locator(
			'[data-corex-account-form] input:not([type="checkbox"]):not([type="hidden"])'
		)
		.evaluateAll( ( fields ) => {
			const purposes = {};
			fields.forEach( ( field ) => {
				const form = field.form.getAttribute(
					'data-corex-account-form'
				);
				purposes[ form ] = purposes[ form ] || {};
				purposes[ form ][ field.name ] =
					field.getAttribute( 'autocomplete' );
			} );

			return purposes;
		} );
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

test( 'a field of the signed-out account block tells the browser what it is for', async ( {
	browser,
} ) => {
	const page = await openAccount( browser, WINDOWS[ 0 ], SIGNED_OUT );

	const purposes = await fieldPurposes( page );
	// Sign-in and the forgotten password form are on every site; registration where it is open.
	expect( Object.keys( purposes ) ).toEqual(
		expect.arrayContaining( [ 'login', 'reset-request' ] )
	);
	for ( const [ form, fields ] of Object.entries( purposes ) ) {
		expect( fields, `the fields of the ${ form } form` ).toEqual(
			SIGNED_OUT_PURPOSES[ form ]
		);
	}

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
