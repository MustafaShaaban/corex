/**
 * Playwright config for the Corex E2E smoke.
 *
 * ENVIRONMENT-GATED: requires a running site (Apache/WAMP up) at COREX_BASE_URL — this
 * headless WAMP setup has Apache stopped (no elevation), so these specs are authored and
 * ready but not executed here. To run locally:
 *   1. Start full WAMP from the tray (so http://corex.local serves).
 *   2. npx playwright install chromium
 *   3. npm run test:e2e
 *
 * Kept out of the default `npm test` / CI lanes that lack a browser; wire into CI behind a
 * job that boots WP (wp-env) first.
 */
const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: '.',
	timeout: 30_000,
	fullyParallel: false,
	reporter: 'list',
	use: {
		baseURL: process.env.COREX_BASE_URL || 'http://corex.local',
		trace: 'on-first-retry',
	},
	projects: [
		{ name: 'chromium', use: { ...devices[ 'Desktop Chrome' ] } },
	],
} );
