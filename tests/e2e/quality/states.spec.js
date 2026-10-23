/**
 * The four states of every screen (spec 116, US-NFR-05; Definition of Done item 8).
 *
 * Every screen WordPress's menu lists must have an entry in `screen-states.json`, its state table.
 * Then each screen is opened once to learn which CoreX requests it makes, and each request is in
 * turn:
 *
 * - **held back**: while it waits, the screen shows that it is loading, and does not say there is
 *   nothing;
 * - **answered 500**: the screen says it failed, shows no raw error code, and stops loading;
 * - **answered with empty lists**: the screen says there is nothing yet, rather than showing a
 *   blank area.
 *
 * A subscriber who opens each screen's address gets CoreX's access-denied page. A save on the
 * Settings screen answered 500 keeps what was typed and says it failed.
 */
const { test, expect } = require( '@playwright/test' );

const { signInAs } = require( '../helpers' );
const { installQualityHelpers } = require( './in-page' );
const { assertNoNewFindings } = require( './findings' );
const { site, DESKTOP } = require( './site' );
const STATES = require( './screen-states.json' ).screens;

const SUBSCRIBER = 'corex-quality-subscriber';
const SUBSCRIBER_PASS = 'CorexQuality!subscriber1';

const RAW_CODE = 'corex_quality_failure';
const REQUESTS_PER_SCREEN = 4;

/**
 * A CoreX REST request, whichever way the site spells REST addresses.
 * @param {import('@playwright/test').Request} request A request the page made.
 */
const isCorexRead = ( request ) =>
	request.method() === 'GET' &&
	/\/wp-json\/corex\/|[?&]rest_route=(%2F|\/)corex(%2F|\/)/i.test(
		request.url()
	);

/**
 * The route a request asked for, ids and query left out, so one route is one key.
 * @param {string} url A request address.
 */
const routeOf = ( url ) => {
	const address = new URL( url );
	const route =
		address.searchParams.get( 'rest_route' ) ||
		address.pathname.replace( /^.*\/wp-json/, '' );
	return route.replace( /\/\d+(?=\/|$)/g, '/{id}' );
};

const EMPTY_WORDS =
	/\bno [a-z -]{0,30}(yet|here|found)\b|\bnothing (here|yet|to show|to review)\b|\bno (records|results|items|entries|submissions|forms|flows|notifications|templates|exports|releases|guides|posts|comments|requests|jobs|events|activity)\b|\bis empty\b/i;
const FAILED_WORDS =
	/could not|couldn[’']t|failed|did not load|didn[’']t load|went wrong|try again|unavailable|not available|error/i;
const LOADING =
	'[aria-busy="true"], [data-corex-state="loading"], [role="progressbar"], .corex-skeleton, .corex-loader, .components-spinner';

async function textOf( page ) {
	return page.evaluate(
		() =>
			( document.querySelector( '.corex-admin' ) || document.body )
				.innerText
	);
}

async function visibleCount( page, selector ) {
	return page
		.locator( `.corex-admin :is(${ selector })` )
		.filter( { visible: true } )
		.count();
}

/**
 * The CoreX reads a screen makes on load.
 * @param {import('@playwright/test').Page} page The page under test.
 * @param {string}                          path The address to open.
 */
async function readsOf( page, path ) {
	const routes = new Map();
	const listen = ( request ) => {
		if (
			isCorexRead( request ) &&
			! routes.has( routeOf( request.url() ) )
		) {
			routes.set( routeOf( request.url() ), request.url() );
		}
	};

	page.on( 'request', listen );
	await page.goto( path, { waitUntil: 'load' } );
	await page
		.waitForLoadState( 'networkidle', { timeout: 10_000 } )
		.catch( () => {} );
	page.off( 'request', listen );

	return [ ...routes.keys() ].slice( 0, REQUESTS_PER_SCREEN );
}

const isCorexUrl = ( url ) =>
	/\/wp-json\/corex\/|[?&]rest_route=(%2F|\/)corex(%2F|\/)/i.test(
		url.toString()
	);
const matching = ( route ) => ( url ) =>
	isCorexUrl( url ) && routeOf( url.toString() ) === route;

/**
 * Every array in a CoreX answer's `data`, one level down, made empty.
 * @param {Object|Array|null} body A parsed JSON answer.
 */
function emptied( body ) {
	let changed = false;
	const empty = ( value ) => {
		if ( Array.isArray( value ) ) {
			changed = changed || value.length > 0;
			return [];
		}
		if ( value && typeof value === 'object' ) {
			return Object.fromEntries(
				Object.entries( value ).map( ( [ key, inner ] ) => [
					key,
					Array.isArray( inner ) ? empty( inner ) : inner,
				] )
			);
		}
		return value;
	};
	const data =
		body && typeof body === 'object' && 'data' in body
			? { ...body, data: empty( body.data ) }
			: empty( body );
	return { data, changed };
}

async function stateFindings( page, screen, route, finding ) {
	const findings = [];

	// Held back.
	let release;
	const held = new Promise( ( resolve ) => {
		release = resolve;
	} );
	await page.route( matching( route ), async ( intercepted ) => {
		await held;
		await intercepted.continue().catch( () => {} );
	} );
	await page.goto( screen.path, { waitUntil: 'load' } );
	await page.waitForTimeout( 1500 );
	const waiting = await textOf( page );
	if ( EMPTY_WORDS.test( waiting ) ) {
		findings.push(
			finding(
				'held',
				'empty-before-answer',
				`says "${ waiting.match( EMPTY_WORDS )[ 0 ] }" before ${ route } answered`
			)
		);
	}
	if (
		( await visibleCount( page, LOADING ) ) === 0 &&
		! /loading/i.test( waiting )
	) {
		findings.push(
			finding(
				'held',
				'loading-not-shown',
				`nothing says it is loading while ${ route } waits`
			)
		);
	}
	release();
	await page.unroute( matching( route ) );

	// Answered 500.
	const before = (
		( await textOf( page ) ).match( new RegExp( FAILED_WORDS, 'gi' ) ) || []
	).length;
	await page.route( matching( route ), ( intercepted ) =>
		intercepted.fulfill( {
			status: 500,
			contentType: 'application/json',
			body: JSON.stringify( {
				ok: false,
				code: RAW_CODE,
				message: RAW_CODE,
				data: { status: 500 },
			} ),
		} )
	);
	await page.goto( screen.path, { waitUntil: 'load' } );
	await page
		.waitForLoadState( 'networkidle', { timeout: 10_000 } )
		.catch( () => {} );
	await page.waitForTimeout( 1000 );
	const failed = await textOf( page );
	if ( failed.includes( RAW_CODE ) ) {
		findings.push(
			finding(
				'failed',
				'raw-error-shown',
				`shows "${ RAW_CODE }" when ${ route } fails`
			)
		);
	}
	if ( ( await visibleCount( page, LOADING ) ) > 0 ) {
		findings.push(
			finding(
				'failed',
				'loading-never-ends',
				`still loading after ${ route } failed`
			)
		);
	}
	const after = ( failed.match( new RegExp( FAILED_WORDS, 'gi' ) ) || [] )
		.length;
	const alerts = await visibleCount(
		page,
		'[role="alert"], [data-corex-state="error"]'
	);
	if ( after <= before && alerts === 0 ) {
		findings.push(
			finding(
				'failed',
				'failure-not-said',
				`nothing says ${ route } failed`
			)
		);
	}
	await page.unroute( matching( route ) );

	// Answered with empty lists.
	let changed = false;
	await page.route( matching( route ), async ( intercepted ) => {
		const response = await intercepted.fetch();
		const body = await response.json().catch( () => null );
		const result = emptied( body );
		changed = changed || result.changed;
		await intercepted.fulfill( { response, json: result.data } );
	} );
	await page.goto( screen.path, { waitUntil: 'load' } );
	await page
		.waitForLoadState( 'networkidle', { timeout: 10_000 } )
		.catch( () => {} );
	await page.waitForTimeout( 500 );
	const empty = await textOf( page );
	if (
		changed &&
		! EMPTY_WORDS.test( empty ) &&
		( await visibleCount( page, '[data-corex-state="empty"]' ) ) === 0
	) {
		findings.push(
			finding(
				'empty',
				'empty-not-said',
				`nothing says there is nothing when ${ route } answers empty lists`
			)
		);
	}
	await page.unroute( matching( route ) );

	return findings;
}

const screensBySlug = [
	...new Map(
		site.screens.map( ( screen ) => [ screen.slug, screen ] )
	).values(),
];

test( 'every screen the menu lists has a state table in screen-states.json', () => {
	const missing = screensBySlug
		.map( ( screen ) => screen.slug )
		.filter( ( slug ) => ! STATES[ slug ] );
	const incomplete = Object.entries( STATES )
		.filter(
			( [ , entry ] ) =>
				! entry.panels?.length ||
				entry.panels.some(
					( panel ) =>
						! [ 'empty', 'loading', 'failed', 'refused' ].every(
							( state ) => panel[ state ]
						)
				)
		)
		.map( ( [ slug ] ) => slug );

	expect(
		missing,
		'screens with no state table: add one to tests/e2e/quality/screen-states.json'
	).toEqual( [] );
	expect(
		incomplete,
		'state tables missing a panel or one of empty, loading, failed and refused'
	).toEqual( [] );
} );

test.describe( 'each request held back, failed and emptied', () => {
	for ( const screen of screensBySlug ) {
		test( `${ screen.slug }: loading, failed and empty states`, async ( {
			page,
		} ) => {
			await page.addInitScript( installQualityHelpers );
			await page.setViewportSize( DESKTOP );
			const findings = [];

			for ( const route of await readsOf( page, screen.path ) ) {
				const finding = ( state, rule, detail ) => ( {
					screen: screen.slug,
					cell: `1280-light-ltr-${ state }`,
					check: 'states',
					rule,
					signature: route,
					detail,
				} );
				findings.push(
					...( await stateFindings( page, screen, route, finding ) )
				);
			}

			assertNoNewFindings( expect, findings );
		} );
	}
} );

test( 'a subscriber gets the CoreX access-denied page on every screen', async ( {
	browser,
} ) => {
	const page = await signInAs(
		browser,
		process.env.COREX_BASE_URL || 'http://corex.local',
		SUBSCRIBER,
		SUBSCRIBER_PASS
	);
	const findings = [];

	for ( const screen of screensBySlug ) {
		await page.goto( screen.path, { waitUntil: 'load' } );
		const bodyClass =
			( await page.locator( 'body' ).getAttribute( 'class' ) ) || '';
		const refused =
			bodyClass.includes( 'corex-standalone' ) &&
			( await page.locator( '.corex-standalone__card' ).count() ) > 0;
		// A screen a subscriber may open (Guides is for everyone who signs in) draws its shell.
		const opened = ( await page.locator( '.corex-admin' ).count() ) > 0;

		if ( ! refused && ! opened ) {
			findings.push( {
				screen: screen.slug,
				cell: '1280-light-ltr-refused',
				check: 'states',
				rule: 'refusal-not-corex',
				signature: screen.slug,
				detail: 'a subscriber gets neither the screen nor the CoreX access-denied page',
			} );
		}
	}

	await page.context().close();
	assertNoNewFindings( expect, findings );
} );

test( 'a Settings save that fails keeps what was typed and says so', async ( {
	page,
} ) => {
	const settings = site.screens.find(
		( screen ) => screen.slug === 'corex-settings-config'
	);
	test.skip( ! settings, 'no Settings screen in the menu' );

	await page.goto( settings.path, { waitUntil: 'load' } );
	const field = page
		.locator( '.corex-settings-form input[type="text"]:visible' )
		.first();
	const typed = `Typed before a failed save ${ Date.now() }`;
	await field.fill( typed );

	await page.route(
		( url ) => url.toString().includes( 'page=corex-settings-config' ),
		( route ) =>
			route.request().method() === 'POST'
				? route.fulfill( {
						status: 500,
						contentType: 'text/html',
						body: '<html><body>corex_quality_failure</body></html>',
					} )
				: route.continue()
	);
	await page
		.locator(
			'.corex-settings__save button, .corex-settings-form [type="submit"]'
		)
		.first()
		.click();
	await page.waitForLoadState( 'load' );

	const kept = await page
		.locator( '.corex-settings-form input[type="text"]' )
		.evaluateAll(
			( inputs, value ) =>
				inputs.some( ( input ) => input.value === value ),
			typed
		)
		.catch( () => false );
	const said = FAILED_WORDS.test( await page.locator( 'body' ).innerText() );

	assertNoNewFindings(
		expect,
		kept && said
			? []
			: [
					{
						screen: 'corex-settings-config',
						cell: '1280-light-ltr-failed',
						check: 'states',
						rule: 'failed-save-loses-input',
						signature: 'corex-settings-form',
						detail: kept
							? 'the typed value stayed, but nothing said the save failed'
							: 'what was typed is gone after a failed save',
					},
				]
	);
} );
