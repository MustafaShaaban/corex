/**
 * Playwright config for the CoreX browser suite: every spec under tests/e2e/.
 *
 * ENVIRONMENT-GATED: the suite starts no site. It requires one already served at COREX_BASE_URL
 * (default http://corex.local) and an administrator's credentials in COREX_ADMIN_USER /
 * COREX_ADMIN_PASS (the defaults, and COREX_LOGIN_PATH, are in global-setup.js). To run locally:
 *   1. Serve the install (on the WAMP setup: start WAMP, so http://corex.local serves) with the
 *      bundles built: npm run build.
 *   2. Have every tests/e2e/fixtures/corex-e2e-*.php in its wp-content/mu-plugins/.
 *      scripts/setup-wordpress.ps1 copies them into ./wp; copy them into any other install.
 *   3. npx playwright install chromium
 *   4. npm run test:e2e
 *
 * A few specs also reach the install through WP-CLI (`wpEval` in helpers.js), to set up what has
 * no route and to remove the rows they leave. That acts on `./wp`; set COREX_WP_PATH when
 * COREX_BASE_URL serves an install somewhere else. Without WP-CLI those steps are skipped.
 *
 * In CI this is the `e2e` job of .github/workflows/ci.yml, on every pull request: that job
 * provisions a WordPress, seeds it, serves it with nginx and php-fpm, and sets
 * COREX_E2E_FRESH_INSTALL, which applies the exclusions below. CONTRIBUTING.md, "Browser
 * verification", has both runs in full.
 */
const { defineConfig, devices } = require( '@playwright/test' );

const { STORAGE_STATE } = require( './global-setup' );

/**
 * The individual tests that cannot pass on a freshly provisioned site.
 *
 * Matched by TITLE, not by file, and that distinction matters: most of these live in specs whose
 * other tests pass perfectly well on a clean install. Excluding whole files threw away real
 * coverage — security-access.spec.js has one environment-dependent test and a dozen good ones.
 *
 * Three tests are left, with two causes, neither traced to its root (the entries say what was
 * ruled out):
 *   - the block editor: whichever spec opens it first in a fresh CI browser does not see the
 *     inserter. The server is ruled out: the job has run on PHP's built-in server and on nginx
 *     with php-fpm, which it uses now, and the two behave identically;
 *   - the flow builder, which times out mid-interaction and has not been shown to be
 *     environmental.
 * Nothing is excluded for a missing fixture any more: the job seeds what those specs needed.
 *
 * Treat every reason here as a hypothesis until CI disproves it. This list was once eleven entries
 * and most were wrong: tests blamed on missing content actually failed because the CI site had
 * plain permalinks, so every path served the home page and /wp-json/ did not resolve. Fixing the
 * site recovered them, and one spec had been *passing* against the home page. The last three were
 * blamed on the block editor, which turned out to be true — but only checked by re-running it.
 * Before seeding anything, read the failing assertion.
 *
 * Empty is the goal, and is handled: an empty list means no exclusions, NOT `new RegExp('')`,
 * which matches every title and would silently skip the entire suite while reporting green.
 */
const CANNOT_RUN_ON_A_FRESH_INSTALL = [
	// The two block-editor specs, excluded together because they trade the failure: whichever one
	// opens the editor FIRST fails to see the inserter, and excluding only one hands that slot to
	// the other (demonstrated in both directions).
	//
	// A trace of a failing run rules out the obvious causes: no console errors beyond a jQuery
	// Migrate notice, no failed requests, and the editor header — "Block Inserter" among 25 buttons
	// — present in a snapshot captured just after the assertion gave up. Also ruled out: php -S
	// (nginx behaves identically), worker starvation (4 → 12), and the welcome-guide modal.
	// Budgets of 30s, 45s, 120s and 150s all failed, and the 150s attempt destabilised neighbouring
	// specs, so this is NOT simply "wait longer". Something about the first open in a fresh CI
	// browser differs from every later one; that is the thread to pull, with the trace as evidence.
	//
	// The editor works — smoke.spec.js clicks that inserter successfully whenever it is not first.
	'the block editor loads with no console errors',
	'a corex block is recognised in the editor inserter',
	// The flow builder times out mid-interaction (locator.click, 60s) even on nginx, where the
	// block editor itself now works. Not diagnosed further — unlike the editor specs, this one has
	// not been shown to be environmental, so it may be a real slow path worth its own look.
	'creates publishes tests and submits a persisted flow without console errors',
];

/** The one spec that changes what the whole site serves, and so runs in a project of its own. */
const COMING_SOON_SPEC = /coming-soon\.spec\.js$/;

const freshInstallExclusions = () => {
	if (
		! process.env.COREX_E2E_FRESH_INSTALL ||
		CANNOT_RUN_ON_A_FRESH_INSTALL.length === 0
	) {
		return undefined;
	}

	return new RegExp(
		CANNOT_RUN_ON_A_FRESH_INSTALL.map( ( title ) =>
			title.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' )
		).join( '|' )
	);
};

module.exports = defineConfig( {
	testDir: '.',
	// Unset locally, so a developer always runs the whole suite against their real install.
	grepInvert: freshInstallExclusions(),
	// The block editor is a heavy React app; on a cold OPcache / loaded box it can take a
	// while to become interactive. 60s gives headroom without masking a real hang.
	timeout: 60_000,
	fullyParallel: false,
	reporter: 'list',
	// Authenticate once (global-setup) and reuse the session — no per-test login race.
	globalSetup: require.resolve( './global-setup.js' ),
	use: {
		baseURL: process.env.COREX_BASE_URL || 'http://corex.local',
		storageState: STORAGE_STATE,
		// Opt-in, because it is not free: 'retain-on-failure' records every test and then discards
		// the passes, and that overhead alone was enough to make email-studio's console-error sweep
		// fail reproducibly — a spec that passes without it. Default stays 'on-first-retry'.
		// Set COREX_E2E_TRACE=1 to capture traces when diagnosing (the workflow uploads
		// test-results on failure); note that doing so may itself perturb timing-sensitive specs.
		trace: process.env.COREX_E2E_TRACE
			? 'retain-on-failure'
			: 'on-first-retry',
		// Unlike a trace, this costs nothing on a passing run — the image is captured only when a
		// test has already failed. It is here because its absence cost real time: spec 097's two
		// layout failures came back from CI as a 60-second click timeout and a boolean, with no
		// picture of a screen whose control had been clipped out of existence. One screenshot would
		// have said so immediately. (`retries` is unset, so `on-first-retry` traces never fire in
		// CI — this is the evidence that actually arrives.)
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
			testIgnore: COMING_SOON_SPEC,
		},
		// Coming soon mode (spec 101), after everything else and alone.
		//
		// Turning the mode on changes what every signed-out and subscriber request receives, for
		// the whole site, until it is turned off. Other specs drive both — access-request signs in
		// as a subscriber — so a mode switched on by one spec in the shared run would fail others
		// at random, and nowhere near the cause. A project that depends on the main one runs only
		// once that has finished; the spec restores the mode it found when it is done.
		{
			name: 'coming-soon',
			use: { ...devices[ 'Desktop Chrome' ] },
			testMatch: COMING_SOON_SPEC,
			dependencies: [ 'chromium' ],
		},
	],
} );
