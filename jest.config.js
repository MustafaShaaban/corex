/**
 * Jest config for Corex JS unit tests (block editor scripts + the shared form validator).
 *
 * Extends @wordpress/scripts' default unit config (JSX transform, jsdom, the wp babel
 * preset) and excludes the bundled WordPress install under `wp/` so the run covers only
 * Corex source — not the WooCommerce/core tests that ship inside the WP checkout.
 *
 * `dist/` and `build/` are excluded for the same reason the linters exclude them: they are
 * generated copies of the source, so including them ran 17 suites twice against whatever
 * the last `npm run build:dist` happened to leave behind. That also made the local suite
 * count (62) disagree with CI's (45), because `dist/` is git-ignored and never exists there
 * — a number that changes with your working directory is not a number worth reporting.
 */
const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...defaultConfig,
	transform: {
		...defaultConfig.transform,
		'\\.mjs$': require.resolve(
			'@wordpress/scripts/config/babel-transform'
		),
	},
	testPathIgnorePatterns: [
		'/node_modules/',
		'/build/',
		'<rootDir>/wp/',
		'<rootDir>/dist/',
		'<rootDir>/docs-app/',
	],
};
