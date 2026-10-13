/**
 * The admin's screens on a site without pretty permalinks.
 *
 * There a REST route is a query, `/index.php?rest_route=/corex/v1/submissions`, and not a path.
 * Eight clients added their own query to it with a second `?`, which made that query part of
 * the route: WordPress answered 404 "No route was found", and CoreX → Submissions opened on
 * "The submissions could not be loaded".
 *
 * The suite's site has pretty permalinks, as a real site has. The fixture
 * `corex-e2e-rest-address.php` hands a page its REST addresses in the other spelling for a
 * request that carries the header below, and WordPress answers a `rest_route` address whatever
 * the permalinks are, so what these tests see is what such a site does. On a site that really
 * has none, the header changes nothing.
 */
const { test, expect } = require( '@playwright/test' );

test.use( {
	extraHTTPHeaders: { 'X-Corex-E2E-Rest-Address': 'plain' },
} );

/**
 * The route a response is for, as the page asked for it.
 *
 * A query joined with a second `?` stays in it (`/corex/v1/submissions?page`), which is how a
 * client that joins by hand is told from one that does not.
 *
 * @param {import('@playwright/test').Response} response An answer the page received.
 * @return {string} The value of `rest_route`, or nothing for an address that is not a route's.
 */
function routeAsked( response ) {
	return new URL( response.url() ).searchParams.get( 'rest_route' ) || '';
}

/**
 * Open a CoreX screen and hold it to the spelling these tests are about.
 *
 * Without the fixture the page would be handed `/wp-json/…` addresses and every test below
 * would wait for a request that is never made. This says so in a sentence instead.
 *
 * @param {import('@playwright/test').Page} page   The page.
 * @param {string}                          slug   The screen's `page=` value.
 * @param {string}                          config The name of the global the screen is configured by.
 */
async function openScreen( page, slug, config ) {
	await page.goto( `/wp-admin/admin.php?page=${ slug }` );
	const restUrl = await page.evaluate(
		( name ) => window[ name ]?.restUrl ?? '',
		config
	);
	expect(
		restUrl,
		'the page is handed its REST address without pretty permalinks (is tests/e2e/fixtures/corex-e2e-rest-address.php in mu-plugins?)'
	).toContain( 'index.php?rest_route=' );
}

/**
 * Do something, and read the answer to the request it makes of one route.
 *
 * @param {import('@playwright/test').Page} page  The page.
 * @param {string}                          route The route, such as `/corex/v1/flows`.
 * @param {() => Promise<void>}             act   What makes the page ask.
 * @return {Promise<{route: string, status: number, query: URLSearchParams}>} What was asked and how it was answered.
 */
async function answerTo( page, route, act ) {
	const [ response ] = await Promise.all( [
		page.waitForResponse( ( candidate ) => {
			const asked = routeAsked( candidate );
			return asked === route || asked.startsWith( `${ route }?` );
		} ),
		act(),
	] );

	return {
		route: routeAsked( response ),
		status: response.status(),
		query: new URL( response.url() ).searchParams,
	};
}

test( 'the Submissions inbox opens, and a search is answered', async ( {
	page,
} ) => {
	const list = await answerTo( page, '/corex/v1/submissions', () =>
		openScreen( page, 'corex-submissions', 'corexSubmissions' )
	);

	expect( list.route ).toBe( '/corex/v1/submissions' );
	expect( list.status ).toBe( 200 );
	await expect( page.locator( '.corex-inbox' ) ).toHaveAttribute(
		'data-status',
		'ready'
	);
	await expect(
		page.getByText( 'The submissions could not be loaded' )
	).toHaveCount( 0 );

	const search = await answerTo( page, '/corex/v1/submissions', () =>
		page.getByRole( 'searchbox' ).first().fill( 'nobody-by-this-name' )
	);

	expect( search.route ).toBe( '/corex/v1/submissions' );
	expect( search.query.get( 'search' ) ).toBe( 'nobody-by-this-name' );
	expect( search.status ).toBe( 200 );
} );

test( 'Forms & Flows answers a search of its list', async ( { page } ) => {
	// The list the screen opens with has no query of its own. It is waited for so that the
	// answer read below is the search's.
	await answerTo( page, '/corex/v1/flows', () =>
		openScreen( page, 'corex-forms', 'corexFlows' )
	);

	const list = await answerTo( page, '/corex/v1/flows', async () => {
		await page
			.getByRole( 'searchbox', { name: 'Search forms' } )
			.fill( 'contact' );
		await page.getByRole( 'button', { name: 'Apply filters' } ).click();
	} );

	expect( list.route ).toBe( '/corex/v1/flows' );
	expect( list.query.get( 'search' ) ).toBe( 'contact' );
	expect( list.status ).toBe( 200 );
} );

test( 'Data lists a model’s records and its table changes', async ( {
	page,
} ) => {
	await openScreen( page, 'corex-data-models', 'corexDataModels' );
	const sections = page.getByRole( 'navigation', {
		name: 'Data model sections',
	} );

	const records = await answerTo( page, '/corex/v1/data/submissions', () =>
		sections.getByRole( 'button', { name: 'Records' } ).click()
	);

	expect( records.route ).toBe( '/corex/v1/data/submissions' );
	expect( records.query.get( 'per_page' ) ).toBe( '20' );
	expect( records.status ).toBe( 200 );

	const migrations = await answerTo( page, '/corex/v1/data/migrations', () =>
		sections.getByRole( 'button', { name: 'Migrations' } ).click()
	);

	expect( migrations.route ).toBe( '/corex/v1/data/migrations' );
	expect( migrations.query.get( 'source' ) ).not.toBeNull();
	expect( migrations.status ).toBe( 200 );
} );

test( 'Blog Pro reads a post’s figures again', async ( { page } ) => {
	await openScreen( page, 'corex-blog-pro', 'corexBlogPro' );

	const analytics = await answerTo( page, '/corex/v1/blog/analytics', () =>
		page.getByRole( 'button', { name: 'Refresh', exact: true } ).click()
	);

	expect( analytics.route ).toBe( '/corex/v1/blog/analytics' );
	expect( analytics.query.get( 'post_id' ) ).toMatch( /^\d+$/ );
	expect( analytics.status ).toBe( 200 );
} );

test( 'the Setup Wizard reads the plan of the chosen kit', async ( {
	page,
} ) => {
	await openScreen( page, 'corex-setup', 'corexSetup' );
	const next = page.locator( '#corex-setup-next' );
	await expect( page.locator( '.corex-setup__step' ) ).toHaveCount( 9 );

	// Welcome → Brand → Kit → Demo, and the plan is asked for on the way into the fifth step.
	await next.click();
	await next.click();
	await expect(
		page.locator( 'input[name="corex-kit"]' ).first()
	).toBeVisible();
	await next.click();
	await expect(
		page.locator( 'input[name="corex-level"]' ).first()
	).toBeVisible();

	const plan = await answerTo( page, '/corex/v1/setup/plan', () =>
		next.click()
	);

	expect( plan.route ).toBe( '/corex/v1/setup/plan' );
	expect( plan.query.get( 'kit' ) ).not.toBeNull();
	expect( plan.status ).toBe( 200 );
} );
