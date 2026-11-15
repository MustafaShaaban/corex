/**
 * What the quality checks open, read from the site itself (spec 116, FR-001).
 *
 * Run once, by `playwright.quality.config.js`, before any test is listed: a test list has to be
 * known when the spec files load, and these lists come from a running WordPress. Prints one JSON
 * object to stdout:
 *
 * - `screens`: every `admin.php?page=corex…` link in WordPress's admin menu, and for each screen
 *   its tabs: the `&tab=` links of its tab bar (the one marked `aria-current` is the screen's own
 *   address and is not repeated) and the in-page tabs of Settings, which take `&corex_tab=`.
 * - `templates`: every file in the theme's `templates/` folder, with the address `templates.json`
 *   gives it, or `null` when the map does not name it, which fails the matrix.
 * - `pages`: every published page, for the front-end budget.
 *
 * A screen registered tomorrow is in the list without a change here. Nothing is typed by hand
 * but the template map, and a template missing from it is a failure, not a skip.
 *
 * Usage: node tests/e2e/quality/discover.js (reads COREX_BASE_URL, COREX_ADMIN_USER,
 * COREX_ADMIN_PASS and COREX_LOGIN_PATH, as the browser suite does).
 */
const fs = require( 'fs' );
const path = require( 'path' );

const BASE_URL = ( process.env.COREX_BASE_URL || '' ).replace( /\/$/, '' );
const ADMIN_USER = process.env.COREX_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.COREX_ADMIN_PASS || 'password';
const LOGIN_PATHS = [
	process.env.COREX_LOGIN_PATH,
	'/wp-login.php',
	'/corex-login/',
].filter( Boolean );

const THEME_TEMPLATES = path.join(
	__dirname,
	'..',
	'..',
	'..',
	'theme',
	'templates'
);
const TEMPLATE_MAP = require( './templates.json' ).templates;

/** Cookies the site set, kept between requests the way a browser keeps them. */
const jar = new Map();

const cookieHeader = () =>
	[ ...jar ]
		.map( ( [ name, value ] ) => `${ name }=${ value }` )
		.join( '; ' );

async function request( address, options = {} ) {
	const response = await fetch(
		address.startsWith( 'http' ) ? address : BASE_URL + address,
		{
			redirect: 'manual',
			...options,
			headers: { ...( options.headers || {} ), Cookie: cookieHeader() },
		}
	);

	for ( const cookie of response.headers.getSetCookie() ) {
		const [ pair ] = cookie.split( ';' );
		const [ name, ...value ] = pair.split( '=' );
		jar.set( name.trim(), value.join( '=' ) );
	}

	return response;
}

async function signIn() {
	jar.set( 'wordpress_test_cookie', 'WP%20Cookie%20check' );

	for ( const loginPath of LOGIN_PATHS ) {
		const form = await request( loginPath );

		if (
			form.status !== 200 ||
			! ( await form.text() ).includes( 'user_login' )
		) {
			continue;
		}

		await request( loginPath, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams( {
				log: ADMIN_USER,
				pwd: ADMIN_PASS,
				testcookie: '1',
				'wp-submit': 'Log In',
				redirect_to: '/wp-admin/',
			} ).toString(),
		} );

		const admin = await request( '/wp-admin/' );

		if ( admin.status === 200 ) {
			return admin.text();
		}
	}

	throw new Error(
		`The quality checks could not sign in to ${ BASE_URL } as ${ ADMIN_USER } (tried ${ LOGIN_PATHS.join(
			', '
		) }).`
	);
}

const decode = ( href ) => href.replace( /&#0?38;|&amp;/g, '&' );

/**
 * The CoreX screens in WordPress's admin menu, in menu order.
 * @param {string} html The HTML the site answered with.
 */
function menuScreens( html ) {
	const menu = html.slice( html.indexOf( 'id="adminmenu"' ) );
	const slugs = [];

	for ( const match of menu.matchAll(
		/href=['"](?:[^'"]*\/)?admin\.php\?page=(corex[a-z0-9_-]*)['"]/g
	) ) {
		if ( ! slugs.includes( match[ 1 ] ) ) {
			slugs.push( match[ 1 ] );
		}
	}

	return slugs;
}

/**
 * A screen's own tabs, as addresses relative to the site.
 * @param {string} slug The screen's page slug.
 * @param {string} html The HTML the site answered with.
 */
function tabsOf( slug, html ) {
	const tabs = [];

	for ( const match of html.matchAll( /<a\b[^>]*>/g ) ) {
		const tag = match[ 0 ];
		const href = decode(
			( tag.match( /href=["']([^"']+)["']/ ) || [] )[ 1 ] || ''
		);
		// `tab=` for a screen's tabs; `section=` for the Settings pages (spec 119).
		const found = href.match(
			new RegExp( `[?&]page=${ slug }&(tab|section)=([a-z0-9_-]+)` )
		);
		const tab = found ? `${ found[ 1 ] }=${ found[ 2 ] }` : '';

		if (
			tab &&
			! tag.includes( 'aria-current' ) &&
			! tabs.includes( tab )
		) {
			tabs.push( tab );
		}
	}

	const inPage = [
		...html.matchAll( /role="tab"[^>]*data-corex-tab="([a-z0-9_-]+)"/g ),
	];

	inPage
		.slice( 1 )
		.forEach( ( match ) => tabs.push( `corex_tab=${ match[ 1 ] }` ) );

	return tabs;
}

function templates() {
	return fs
		.readdirSync( THEME_TEMPLATES )
		.filter( ( file ) => file.endsWith( '.html' ) )
		.map( ( file ) => file.replace( /\.html$/, '' ) )
		.sort()
		.map( ( template ) => ( {
			template,
			path: TEMPLATE_MAP[ template ]?.path ?? null,
			status: TEMPLATE_MAP[ template ]?.status ?? 200,
		} ) );
}

async function pages() {
	const response = await request(
		'/wp-json/wp/v2/pages?per_page=100&status=publish&_fields=slug,link'
	);

	if ( response.status !== 200 ) {
		return [];
	}

	return ( await response.json() ).map( ( { slug, link } ) => ( {
		slug,
		path: new URL( link ).pathname + new URL( link ).search,
	} ) );
}

async function discover() {
	// No default site, as the browser suite's setup has none (spec 116).
	if ( ! BASE_URL ) {
		throw new Error(
			'COREX_BASE_URL is not set. The browser tests sign in, submit forms and remove the test-marked submissions they find, so they run only against a site named on purpose: a scratch site or the one CI makes, for example COREX_BASE_URL=http://127.0.0.1:8916.'
		);
	}

	const dashboard = await signIn();
	const screens = [];

	for ( const slug of menuScreens( dashboard ) ) {
		const base = `/wp-admin/admin.php?page=${ slug }`;
		const html = await ( await request( base ) ).text();

		screens.push( { id: slug, slug, path: base } );

		for ( const tab of tabsOf( slug, html ) ) {
			screens.push( {
				id: `${ slug }&${ tab }`,
				slug,
				path: `${ base }&${ tab }`,
			} );
		}
	}

	return {
		site: BASE_URL,
		screens,
		templates: templates(),
		pages: await pages(),
	};
}

if ( require.main === module ) {
	discover()
		.then( ( result ) => process.stdout.write( JSON.stringify( result ) ) )
		.catch( ( error ) => {
			process.stderr.write( `${ error.message }\n` );
			process.exit( 1 );
		} );
}

module.exports = { menuScreens, tabsOf };
