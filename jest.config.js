/**
 * Jest config for Corex JS unit tests (block editor scripts + the shared form validator).
 *
 * Extends @wordpress/scripts' default unit config (JSX transform, jsdom, the wp babel
 * preset) and excludes the local WordPress installs under `wp/` and `wp-ms/` (the multisite
 * one) so the run covers only Corex source — not the WooCommerce/core tests that ship inside
 * a WP checkout.
 *
 * `dist/` and `build/` are excluded for the same reason the linters exclude them: they are
 * generated copies of the source, so including them ran 17 suites twice against whatever
 * the last `npm run build:dist` happened to leave behind. That also made the local suite
 * count (62) disagree with CI's (45), because `dist/` is git-ignored and never exists there
 * — a number that changes with your working directory is not a number worth reporting.
 *
 * `.claude/worktrees/` is excluded for that reason too: every agent session worktree is a full
 * checkout inside this one, so each worktree on disk ran the whole suite one more time. The
 * pattern has to stay anchored to `<rootDir>` — a run started inside a worktree has that
 * directory in every test path, and an unanchored pattern would leave it nothing to run.
 *
 * The directories in `notCorexSource` are in `modulePathIgnorePatterns` as well, because
 * skipping a directory's tests does not stop Jest indexing its files. Every `package.json` in
 * `dist/` or in a worktree carries the same `name` as the one it was copied from, and a run
 * with a cold cache printed a "Haste module naming collision" warning for each name it found
 * twice — all nine of this repository's, on a suite that passed. The same anchoring applies,
 * for the same reason.
 */
const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

const notCorexSource = [
	'<rootDir>/wp/',
	'<rootDir>/wp-ms/',
	'<rootDir>/dist/',
	'<rootDir>/.claude/worktrees/',
];

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
		'<rootDir>/docs-app/',
		...notCorexSource,
	],
	modulePathIgnorePatterns: notCorexSource,
};
