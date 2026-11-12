/**
 * Playwright config for the quality checks (spec 116): `npm run test:quality`.
 *
 * Every CoreX admin screen and theme template, in both appearances, both directions and two
 * widths, checked for what the definition of done asks of every release: no sideways scroll,
 * WCAG 2.2 AA, phone width, focus, motion, the four states, translatable words without raw keys,
 * and a front-end budget. What is checked comes from the site (`discover.js`), so a screen added
 * later is checked without an edit here.
 *
 * In CI this is the `quality` job of .github/workflows/ci.yml, on a WordPress of its own prepared
 * by `seed-quality-site.php`. Its findings are compared with `known-failures.json`; the reference
 * is CI's run. To run it locally, point it at a scratch site prepared the same way, never at a
 * site that matters:
 *
 *   wp eval-file tests/e2e/quality/seed-quality-site.php
 *   COREX_BASE_URL=http://127.0.0.1:8916 COREX_ADMIN_PASS=… npm run test:quality
 *
 * The main browser suite's config ignores this folder.
 */
const { defineConfig, devices } = require( '@playwright/test' );

const { STORAGE_STATE } = require( '../global-setup' );

// Runs the discovery once, here, so every worker inherits the same lists (site.js).
require( './site' );

module.exports = defineConfig( {
	testDir: '.',
	testMatch: /\.spec\.js$/,
	timeout: 180_000,
	fullyParallel: true,
	// A served site, not a browser, is what limits this: php-fpm in CI answers a few requests at
	// once, and PHP's built-in server on a developer's machine answers one.
	workers: process.env.CI ? 3 : 1,
	reporter: process.env.CI ? [ [ 'list' ], [ 'github' ] ] : 'list',
	globalSetup: require.resolve( './setup.js' ),
	globalTeardown: require.resolve( './teardown.js' ),
	use: {
		baseURL: process.env.COREX_BASE_URL || 'http://corex.local',
		storageState: STORAGE_STATE,
		screenshot: 'only-on-failure',
		trace: 'off',
	},
	projects: [
		{
			name: 'quality',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
