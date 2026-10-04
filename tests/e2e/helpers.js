/**
 * Shared Playwright E2E helpers (spec 052): a console-error collector with a documented
 * allow-list. Admin authentication is handled once in `global-setup.js` (storageState),
 * so specs start already logged in and don't each re-run a flaky login.
 */

/**
 * Every CoreX admin route, as `[ slug, rail label ]`.
 *
 * Lifted out of `admin-command-center.spec.js` by spec 097, which needed the same list to prove no
 * route carries a contextual Help tab. Two copies would drift, and the one that drifted would be
 * whichever spec nobody was editing — so a new screen would quietly go unchecked by one of them.
 *
 * A route added to CoreX belongs here, which is what makes both matrices grow with the product.
 */
const COREX_ROUTES = [
	[ 'corex-settings', 'Overview' ],
	[ 'corex-addons', 'Add-ons' ],
	[ 'corex-forms', 'Forms & Flows' ],
	[ 'corex-submissions', 'Submissions' ],
	[ 'corex-email-studio', 'Email Studio' ],
	// Spec 069: one Data entry. `corex-data` rendered the same explorer and now redirects here.
	[ 'corex-data-models', 'Data' ],
	[ 'corex-operations-security', 'Operations & Security' ],
	[ 'corex-access', 'Access & Abilities' ],
	[ 'corex-blog-pro', 'Blog Pro' ],
	[ 'corex-insights', 'Insights' ],
	[ 'corex-notifications', 'Notifications' ],
	[ 'corex-guides', 'Guides' ],
	[ 'corex-setup', 'Setup Wizard' ],
	[ 'corex-settings-config', 'Settings' ],
];

/**
 * Known, non-Corex console noise that must NOT fail the sweep (documented allow-list).
 * Keep this tiny and justified — the default is zero tolerated errors.
 */
const ALLOW_LIST = [
	/Failed to load resource: net::ERR_/i, // transient network/infra, not a code regression
	/favicon\.ico/i, // missing favicon on a bare dev install
];

/**
 * Attach console + pageerror listeners; returns the array of real errors (allow-list
 * filtered). Warnings are ignored — only errors fail the sweep (FR-004).
 *
 * @param {import('@playwright/test').Page} page The page to listen on.
 * @return {Array} The collected errors, filled as the page runs.
 */
function collectConsoleErrors( page ) {
	const errors = [];

	page.on( 'console', ( msg ) => {
		if ( msg.type() !== 'error' ) {
			return;
		}
		const text = msg.text();
		if ( ALLOW_LIST.some( ( re ) => re.test( text ) ) ) {
			return;
		}
		// Record where it came from. "Failed to load resource: 404" names no resource on its own,
		// which makes a failure report unactionable — you know something 404s, not what. Filtering
		// still happens on the raw text so the allow-list keeps matching.
		const { url } = msg.location();
		errors.push( url ? `${ text }  <- ${ url }` : text );
	} );

	page.on( 'pageerror', ( err ) => errors.push( err.message ) );

	return errors;
}

/**
 * A published flow with one real and one marked-test submission, so a spec that needs the inbox to
 * have rows can make some rather than skip.
 *
 * Lifted out of `submissions-inbox.spec.js` when a second spec needed it (spec 087). Two copies of
 * this would drift, and the one that drifted silently would be whichever spec was not the one
 * somebody was debugging.
 */
const { expect: helperExpect } = require( '@playwright/test' );

const FLOW_SLUG = 'corex-inbox-e2e';

/** Unique per run, so a seeded row is searchable to exactly one result on a shared site. */
const defaultEmail = () => `corex-inbox-e2e-${ Date.now() }@example.com`;

/**
 * The configuration the inbox fixture needs.
 *
 * `protection.captcha: 'off'` is load-bearing rather than incidental: this spec exercises the
 * inbox, and on any site where a captcha driver *is* configured the protection stage judges an
 * empty token and rejects every seeded submission. Opting the fixture out keeps the spec about
 * the inbox on a bare CI site and on a real one alike.
 */
const FIXTURE_CONFIGURATION = {
	schema: [
		{
			uuid: 'email-field',
			key: 'email',
			label: 'Email',
			type: 'email',
			required: true,
		},
	],
	validation: { email: [ 'required', 'email' ] },
	routing: { rules: [], fallback: { type: 'flow_owner', config: {} } },
	email_routes: [],
	success: { type: 'inline', message: 'Received.' },
	placement_snapshot: { type: 'block' },
	protection: { captcha: 'off' },
};

async function seedSubmission(
	page,
	slug = FLOW_SLUG,
	email = defaultEmail()
) {
	await page.goto( '/wp-admin/admin.php?page=corex-forms' );
	await helperExpect( page.getByText( 'Loading forms…' ) ).toBeHidden();
	return page.evaluate(
		async ( fixture ) => {
			const api = window.Corex.api;
			const config = window.corexFlows;

			// Every answer is checked before it is read. `Corex.api` never throws — a refusal
			// comes back as a result with no `data` — so the unchecked seed reported a 500 from
			// the flow search as "Cannot read properties of undefined (reading 'flows')", three
			// tests in a row, and discarded the one thing the server had said about it. The step
			// and the answer are the report.
			//
			// It is not retried. Those three failures spanned three seconds with nothing else
			// running, so a second attempt would have met the same answer — and a seed that
			// quietly outlasts a 500 hides a fault in the product, not in the test.
			const answered = async ( step, request ) => {
				const result = await request;
				if ( ! result.envelope.ok ) {
					throw new Error(
						`seedSubmission, ${ step }: HTTP ${ result.status } ${ result.envelope.code } — ${ result.envelope.message }`
					);
				}
				return result;
			};

			const list = await answered(
				'finding the flow',
				api.get( `${ config.restUrl }?search=${ fixture.slug }`, {
					nonce: config.nonce,
				} )
			);
			let flow = list.envelope.data.flows.find(
				( item ) => item.slug === fixture.slug
			);
			if ( ! flow ) {
				const created = await answered(
					'creating the flow',
					api.post(
						config.restUrl,
						{
							slug: fixture.slug,
							name: 'Inbox E2E flow',
							owner_id: config.ownerId,
							placement_type: 'block',
							configuration: fixture.configuration,
						},
						{ nonce: config.nonce }
					)
				);
				flow = created.envelope.data.flow;
			}

			const readFlow = () =>
				answered(
					'reading the flow',
					api.get( `${ config.restUrl }/${ flow.id }`, {
						nonce: config.nonce,
					} )
				);

			// Bring an existing fixture back to the shape this spec needs, rather than trusting
			// whatever state a previous run — or a person clicking around the builder — left it in.
			// The old seed only configured and published a flow it had just created, so once this
			// flow drifted, every test in the file failed permanently and told you nothing useful.
			let detail = await readFlow();
			let latest = detail.envelope.data.versions.at( -1 );
			const captchaOff =
				latest.configuration?.protection?.captcha === 'off';

			if ( ! captchaOff ) {
				await answered(
					'saving its configuration',
					api.patch(
						`${ config.restUrl }/${ flow.id }`,
						{
							expected_version: latest.version_number,
							expected_checksum: latest.checksum,
							configuration: fixture.configuration,
						},
						{ nonce: config.nonce }
					)
				);
				detail = await readFlow();
				latest = detail.envelope.data.versions.at( -1 );
			}

			if (
				detail.envelope.data.flow.state !== 'published' ||
				detail.envelope.data.flow.published_version !==
					latest.version_number
			) {
				if ( detail.envelope.data.flow.state === 'published' ) {
					await answered(
						'unpublishing it',
						api.post(
							`${ config.restUrl }/${ flow.id }/unpublish`,
							{ expected_version: latest.version_number },
							{ nonce: config.nonce }
						)
					);
				}
				await answered(
					'publishing it',
					api.post(
						`${ config.restUrl }/${ flow.id }/publish`,
						{ expected_version: latest.version_number },
						{ nonce: config.nonce }
					)
				);
				detail = await readFlow();
				latest = detail.envelope.data.versions.at( -1 );
			}

			const version = latest.version_number;
			const real = await answered(
				'submitting to it',
				api.post(
					`${ config.restUrl }/${ flow.id }/submit`,
					{ email: fixture.email, utm_source: 'playwright' },
					{ nonce: config.nonce }
				)
			);
			const marked = await answered(
				'running its marked test',
				api.post(
					`${ config.restUrl }/${ flow.id }/test`,
					{
						expected_version: version,
						values: { email: 'marked-test@example.com' },
					},
					{ nonce: config.nonce }
				)
			);
			return { real, marked };
		},
		{ slug, email, configuration: FIXTURE_CONFIGURATION }
	);
}

/**
 * Where the login lives, in the order worth trying.
 *
 * `security-access.spec.js` turns login protection on for its own probes, which makes
 * /wp-login.php answer the theme's 404 like any missing address — the feature working, not a
 * fault. `fullyParallel: false` orders the tests *inside* a file; it does not stop files from
 * running on different workers, so that toggle lands mid-run in whichever other file happens to
 * be signing somebody in.
 */
const LOGIN_PATHS = [
	process.env.COREX_LOGIN_PATH,
	'/wp-login.php',
	'/corex-login/',
].filter( Boolean );

/**
 * Put the credentials in the login form, and prove each is in its own field before anything is
 * submitted.
 *
 * wp-login.php runs `wp_attempt_focus()`: 200ms after the form renders, it focuses the username
 * field and selects its contents. Playwright's `fill` is two steps — focus the field, then insert
 * the text into whatever holds focus — and nothing joins them. A timer that fires in between
 * sends the password into the username field, over the selected username, and leaves the password
 * field empty. Both fields are `required`, so the browser then refuses to submit the form at all:
 * no request, no error on the page, nothing in a log.
 *
 * That is what failed access-request.spec.js on 2026-10-04 — the screenshot shows the password
 * sitting in the username field. The window is a few milliseconds wide: against WordPress's own
 * script, 1 fill in 120 hit it when timed to the 200ms mark.
 *
 * The timer fires once. So a form that does not hold what was typed is filled again, and the
 * second time nothing is waiting to move the focus.
 *
 * @param {import('@playwright/test').Page} page     A page showing the login form.
 * @param {string}                          user     The login name.
 * @param {string}                          password The password.
 */
async function fillLoginForm( page, user, password ) {
	const login = page.locator( '#user_login' );
	const pass = page.locator( '#user_pass' );

	await helperExpect( async () => {
		await login.fill( user );
		await pass.fill( password );

		// A boolean, so that a failure does not print the credentials it was comparing.
		helperExpect(
			( await login.inputValue() ) === user &&
				( await pass.inputValue() ) === password,
			'the login form holds each credential in its own field'
		).toBe( true );
	} ).toPass( { timeout: 5_000 } );
}

/**
 * Sign a non-administrator in, and survive the login endpoint moving underneath us.
 *
 * The administrator session is established once by `global-setup.js` and reused, so this is only
 * for the fixture users a spec needs in addition to it.
 *
 * Three copies of this function existed, in `admin-errors`, `guides` and `access-request`. All
 * three walked LOGIN_PATHS once, and two of them carried a comment explaining that the login page
 * moves while they run. One pass is not enough for that: the window is between the *attempts*.
 * Protection comes on, /wp-login.php stops serving a form, the loop falls through to
 * /corex-login/ — and if protection went off again in between, that 404s too. Both candidates
 * fail, `signedIn` is false, and the report says "could not sign in" in a file that changed
 * nothing. It took down guides.spec.js on 2026-09-03 and admin-errors.spec.js on 2026-09-04, on
 * pull requests that touched no browser test at all.
 *
 * So rediscover rather than retry-in-place: each pass re-reads where the login is now. This is
 * not papering over a flake — the endpoint genuinely moves, and re-finding it is what a correct
 * client does. Every attempt is recorded so a real failure names its cause instead of arriving
 * as a bare false.
 *
 * @param {import('@playwright/test').Browser} browser  The Playwright browser.
 * @param {string}                             baseURL  Where the site is served.
 * @param {string}                             user     The login name.
 * @param {string}                             password The password.
 * @return {Promise<import('@playwright/test').Page>} The signed-in page.
 */
async function signInAs( browser, baseURL, user, password ) {
	// newContext() does not inherit `use.baseURL` the way the `page` fixture does, so it is passed
	// through explicitly — otherwise every relative navigation below is invalid, whatever the
	// config says.
	const context = await browser.newContext( {
		storageState: undefined,
		baseURL,
	} );
	const page = await context.newPage();

	await signIn( page, user, password );

	return page;
}

/**
 * The sign-in itself, on a page that already exists.
 *
 * Separate from `signInAs` so that `helpers.spec.js` can drive it against a login form the test
 * controls. The states that break a sign-in are a few milliseconds wide on a real site, and
 * a helper that only ever meets the real site is one whose failures cannot be reproduced.
 *
 * @param {import('@playwright/test').Page} page     A page with no session.
 * @param {string}                          user     The login name.
 * @param {string}                          password The password.
 */
async function signIn( page, user, password ) {
	const attempts = [];
	let signedIn = false;

	// Bounded by the clock, not only by the pass count, and stopped dead when the browser is.
	//
	// The first version of this retried three times with a 15s navigation wait each — up to 45s of
	// waiting inside a 60s test budget, before any page load. On the first CI run that hit a genuine
	// sign-in failure the test timed out, Playwright tore the context down mid-loop, and the
	// remaining attempts each reported "Target page, context or browser has been closed": twelve
	// useless attempts against a dead browser, and a retry that made the timeout it was supposed to
	// survive more likely.
	const deadline = Date.now() + 30_000;

	for ( let pass = 1; pass <= 3 && ! signedIn; pass++ ) {
		if ( page.isClosed() || Date.now() > deadline ) {
			attempts.push(
				page.isClosed()
					? `pass ${ pass }: abandoned — the browser context was closed`
					: `pass ${ pass }: abandoned — 30s sign-in budget spent`
			);
			break;
		}

		for ( const path of LOGIN_PATHS ) {
			// The context can die between candidates, not only between passes — a test timeout
			// tears it down wherever the loop happens to be.
			if ( page.isClosed() ) {
				break;
			}

			const response = await page.goto( path ).catch( ( error ) => {
				attempts.push(
					`pass ${ pass } ${ path }: navigation threw — ${ error.message }`
				);
				return null;
			} );

			if (
				! ( await page
					.locator( '#user_login' )
					.isVisible()
					.catch( () => false ) )
			) {
				attempts.push(
					`pass ${ pass } ${ path }: no #user_login (status ${
						response ? response.status() : 'none'
					}, landed on ${ page.url() })`
				);
				continue;
			}

			await fillLoginForm( page, user, password );

			// Bounded: an unbounded wait inside a loop whose purpose is to *try* an address spends
			// the whole test budget on the first one that half-answers.
			// 8s, not 15s. Three passes have to fit inside the 60s test budget alongside the page
			// loads, and a login that has not navigated in eight seconds is not going to.
			await Promise.all( [
				page.waitForNavigation( { timeout: 8000 } ).catch( () => {} ),
				page.click( '#wp-submit' ).catch( () => {} ),
			] );
			signedIn = ! page.url().includes( 'wp-login.php' );

			if ( signedIn ) {
				break;
			}

			// A refusal is rendered on the login screen we are still looking at. Without it the
			// message cannot tell a wrong password from "too many attempts from this address",
			// and those have opposite fixes.
			//
			// Read what is there now, do not wait for it. `innerText()` waits for the element to
			// exist and has no timeout of its own, so a login that stayed put *without* printing a
			// reason was waited on until the test's own timeout closed the page — and the passes
			// below, which exist for exactly that login, never ran.
			const [ refusal = '' ] = await page
				.locator( '#login_error, .notice-error' )
				.allInnerTexts()
				.catch( () => [] );
			attempts.push(
				`pass ${ pass } ${ path }: submitted and stayed on ${ page.url() }${
					refusal
						? ` — ${ refusal.trim().replace( /\s+/g, ' ' ) }`
						: ''
				}`
			);
		}
	}

	helperExpect(
		signedIn,
		`signed in as ${ user }\n  ` + attempts.join( '\n  ' )
	).toBe( true );
}

module.exports = {
	collectConsoleErrors,
	seedSubmission,
	signIn,
	signInAs,
	FLOW_SLUG,
	COREX_ROUTES,
	LOGIN_PATHS,
};
