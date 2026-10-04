/**
 * The shared helpers, against answers this file controls.
 *
 * Every spec that needs a second user trusts `signInAs` to put one in front of wp-admin, and every
 * spec that needs an inbox with rows trusts `seedSubmission` to make some. When one of them fails,
 * the failure lands in a spec that changed nothing — guides.spec.js on 2026-09-21, and both
 * access-request.spec.js and submissions-inbox.spec.js on 2026-10-04 — so what they must survive,
 * and what they must say when they cannot, is pinned here, where a regression names itself.
 *
 * The answers come from `page.route`, not from WordPress. That is what lets a test hold a login
 * form in a state that is otherwise a few milliseconds wide, or have a healthy site refuse a
 * request.
 */

const { test, expect } = require( '@playwright/test' );
const { seedSubmission, signIn } = require( './helpers' );

const USER = 'corex-fixture-user';
const PASSWORD = 'not-a-real-password';

/**
 * wp-login.php's form, reduced to what the helper touches.
 *
 * Both fields are `required`, as they are in WordPress. That attribute is what turns a misplaced
 * password into a form the browser refuses to submit at all.
 *
 * @param {Object} [options]        What varies between the forms a test serves.
 * @param {string} [options.action] Where the form posts.
 * @param {string} [options.after]  Markup to follow the form.
 * @return {string} The document.
 */
const loginForm = ( {
	action = '/wp-login.php',
	after = '',
} = {} ) => `<!doctype html>
<form method="post" action="${ action }">
	<input type="text" name="log" id="user_login" required>
	<input type="password" name="pwd" id="user_pass" required>
	<input type="submit" id="wp-submit" value="Log In">
</form>${ after }`;

/**
 * WordPress's own `wp_attempt_focus()`, with its timing pinned.
 *
 * wp-login.php focuses and selects the username field 200ms after the form renders. Playwright's
 * `fill` is two steps — focus the field, then insert the text into whatever holds focus — so a
 * timer that fires between them sends the password into the username field, over the selected
 * username. Against WordPress that needs the fill to straddle the 200ms mark; here the focus moves
 * the moment the script that gave it to the password field has returned, which is that same gap
 * every time.
 *
 * A microtask, deliberately. It runs as that script's stack unwinds, so it cannot lose to the
 * text arriving: 60 fills in 60 went astray. A zero-delay timer is itself a race — 58 in 60 — and
 * a test built on one passes on the two.
 */
const FOCUS_MOVES_MID_FILL = `<script>
	document.getElementById( 'user_pass' ).addEventListener( 'focus', () => {
		queueMicrotask( () => {
			const login = document.getElementById( 'user_login' );
			login.focus();
			login.select();
		} );
	}, { once: true } );
</script>`;

/**
 * Answer every request the page makes from a table of `METHOD /path` handlers.
 *
 * Anything not in the table is a 404, which is also what a hidden login address is.
 *
 * @param {import('@playwright/test').Page} page  The page to answer.
 * @param {Object}                          pages Handlers keyed by `METHOD /path`, each returning a document.
 */
async function serve( page, pages ) {
	await page.route( '**/*', ( route ) => {
		const request = route.request();
		const handler =
			pages[
				`${ request.method() } ${ new URL( request.url() ).pathname }`
			];

		return route.fulfill( {
			status: handler ? 200 : 404,
			contentType: 'text/html',
			body: handler ? handler( request ) : '<h1>Not found</h1>',
		} );
	} );
}

test( 'the credentials reach their own fields when WordPress moves focus mid-fill', async ( {
	page,
} ) => {
	let formsServed = 0;
	let submitted = null;
	await serve( page, {
		'GET /wp-login.php': () => {
			formsServed++;
			return loginForm( {
				action: '/wp-admin/',
				after: FOCUS_MOVES_MID_FILL,
			} );
		},
		'POST /wp-admin/': ( request ) => {
			submitted = request.postDataJSON();
			return '<h1>Dashboard</h1>';
		},
	} );

	await signIn( page, USER, PASSWORD );

	expect( submitted ).toEqual( { log: USER, pwd: PASSWORD } );
	// From the first form. A sign-in that needed a second one has already spent eight seconds
	// waiting on a navigation the browser never started.
	expect( formsServed ).toBe( 1 );
} );

test( 'a login that bounces without saying why is tried again, not waited on', async ( {
	page,
} ) => {
	let formsServed = 0;
	await serve( page, {
		// The first form posts back to its own address and is answered with the bare form: still on
		// wp-login.php, and no #login_error to read. The second posts into wp-admin.
		'GET /wp-login.php': () =>
			loginForm( {
				action: ++formsServed === 1 ? '/wp-login.php' : '/wp-admin/',
			} ),
		'POST /wp-login.php': () => loginForm(),
		'POST /wp-admin/': () => '<h1>Dashboard</h1>',
	} );

	await signIn( page, USER, PASSWORD );

	expect( new URL( page.url() ).pathname ).toBe( '/wp-admin/' );
} );

test( 'a refusal WordPress prints is carried into the failure', async ( {
	page,
} ) => {
	await serve( page, {
		'GET /wp-login.php': () => loginForm(),
		'POST /wp-login.php': () =>
			loginForm( {
				after: '<div id="login_error">Too many failed attempts.</div>',
			} ),
	} );

	await expect( signIn( page, USER, PASSWORD ) ).rejects.toThrow(
		/Too many failed attempts\./
	);
} );

test( 'a seed the server refuses says what the server said', async ( {
	page,
} ) => {
	// The body CoreX sends when something throws behind a REST route, as it did under
	// submissions-inbox.spec.js on 2026-10-04. Only the flow search is answered here; the Forms
	// screen the seed opens first is the real one.
	await page.route( /corex\/v1\/flows\?search=/, ( route ) =>
		route.fulfill( {
			status: 500,
			contentType: 'application/json',
			body: JSON.stringify( {
				ok: false,
				code: 'flow_error',
				message: 'Request could not be processed.',
				details: {},
			} ),
		} )
	);

	await expect( seedSubmission( page ) ).rejects.toThrow(
		/finding the flow.*HTTP 500.*flow_error.*Request could not be processed\./
	);
} );
