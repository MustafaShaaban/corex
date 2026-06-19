/**
 * Jest config for Corex JS unit tests (block editor scripts + the shared form validator).
 *
 * Extends @wordpress/scripts' default unit config (JSX transform, jsdom, the wp babel
 * preset) and excludes the bundled WordPress install under `wp/` so the run covers only
 * Corex source — not the WooCommerce/core tests that ship inside the WP checkout.
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
		'<rootDir>/wp/',
		'<rootDir>/docs-app/',
	],
};
