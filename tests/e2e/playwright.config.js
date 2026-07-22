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
 * The individual tests that cannot pass on a freshly provisioned site.
 *
 * Matched by TITLE, not by file, and that distinction matters: most of these live in specs whose
 * other tests pass perfectly well on a clean install. Excluding whole files threw away real
 * coverage — security-access.spec.js has one environment-dependent test and a dozen good ones.
 *
 * Two causes, both environmental rather than defects:
 *   - seeded content: published posts, built forms, stored submissions, declared data sources —
 *     these assume a developer install that has been used;
 *   - the block editor: Gutenberg never becomes interactive under PHP's built-in server (raising
 *     PHP_CLI_SERVER_WORKERS from 4 to 12 changed nothing, so it is not throughput).
 *
 * Removing an entry means seeding its fixtures — or serving the site properly — in the CI job.
 */
const CANNOT_RUN_ON_A_FRESH_INSTALL = new RegExp(
	[
		// Needs published blog content.
		'single post exposes share, newsletter, and comment surfaces',
		// Need declared data sources and records.
		'redirects the retired Data address to the Records tab',
		'queries source records, opens detail, and queues a declared export',
		'renders every Data workflow from declared source capabilities',
		// Both need the account page its beforeAll builds, which does not come up on a fresh
		// install — the second reported 0ms because the shared setup had already failed.
		'the guest account block renders the sign-in, register, and recovery forms',
		'the account surface does not scroll sideways at 375px',
		// Needs a /contact/ page carrying the form block. Note it appeared to pass before the CI
		// site was given pretty permalinks — under plain ones every path served the home page, so
		// the assertion was hitting a different page than the one it names.
		'the contact form validates and accepts a submission',
		// Need stored submissions.
		'filters works assigns notes bulk actions and audits personal-data exports',
		'contains the Inbox at mobile tablet desktop wide and RTL viewports',
		// Need the block editor (see above).
		'the block editor loads with no console errors',
		'creates publishes tests and submits a persisted flow without console errors',
		'a corex block is recognised in the editor inserter',
	]
		.map( ( title ) => title.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) )
		.join( '|' )
);

module.exports = defineConfig( {
	testDir: '.',
	// Unset locally, so a developer always runs the whole suite against their real install.
	grepInvert: process.env.COREX_E2E_FRESH_INSTALL
		? CANNOT_RUN_ON_A_FRESH_INSTALL
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
