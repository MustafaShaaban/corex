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

module.exports = defineConfig( {
	testDir: '.',
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
