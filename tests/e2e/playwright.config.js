/**
 * Playwright config for the Corex E2E smoke.
 *
 * ENVIRONMENT-GATED: requires a running site at COREX_BASE_URL and valid CoreX admin
 * credentials in COREX_ADMIN_USER / COREX_ADMIN_PASS. To run locally:
 *   1. Start full WAMP from the tray (so http://corex.local serves).
 *   2. npx playwright install chromium
 *   3. npm run test:e2e
 *
 * Kept out of the default `npm test` / CI lanes that lack a browser; wire into CI behind a
 * job that boots WP (wp-env) first.
 */
const { defineConfig, devices } = require( '@playwright/test' );

const { STORAGE_STATE } = require( './global-setup' );

/**
 * Specs that need a site with content already in it — published posts, built forms, stored
 * submissions, declared data sources. They are real tests, not flaky ones; they simply assume a
 * developer install that has been used. A freshly provisioned WordPress has none of that, so CI
 * skips them via COREX_E2E_FRESH_INSTALL rather than reporting a red suite nobody can act on.
 *
 * Removing an entry here means seeding its fixtures in the CI job first.
 */
const NEEDS_SEEDED_CONTENT = [
	'**/blog-pro.spec.js',
	'**/console.spec.js',
	'**/data-management.spec.js',
	'**/forms-flow.spec.js',
	'**/product-surfaces.spec.js',
	'**/security-access.spec.js',
	'**/submissions-inbox.spec.js',
];

/**
 * The block editor never becomes interactive under PHP's built-in server: the inserter toggle
 * stays unclickable until the 60s timeout, and raising PHP_CLI_SERVER_WORKERS from 4 to 12 changed
 * nothing, so it is not throughput. Gutenberg wants a real web server. The rest of smoke.spec.js
 * runs, so this is excluded by title rather than by file.
 */
const NEEDS_A_REAL_WEB_SERVER = /block is recognised in the editor inserter/;

module.exports = defineConfig( {
	testDir: '.',
	testIgnore: process.env.COREX_E2E_FRESH_INSTALL
		? NEEDS_SEEDED_CONTENT
		: [],
	grepInvert: process.env.COREX_E2E_FRESH_INSTALL
		? NEEDS_A_REAL_WEB_SERVER
		: undefined,
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
		trace: 'on-first-retry',
	},
	projects: [
		{ name: 'chromium', use: { ...devices[ 'Desktop Chrome' ] } },
	],
} );
