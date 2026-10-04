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
 * pattern has to stay anchored to this directory — a run started inside a worktree has that
 * directory in every test path, and an unanchored pattern would leave it nothing to run. It is
 * one of the local-only paths in the ownership map, and is anchored with them below.
 */
const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );
const {
	clientOwned,
	localOnly,
} = require( './.github/repository-ownership.json' );

const escapeForRegExp = ( text ) =>
	text.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );

/**
 * This directory, written with forward slashes.
 *
 * Used instead of `<rootDir>` for the patterns below, because `<rootDir>` is not safe on Windows
 * when the checkout's own path contains a dot-directory. Jest rewrites path separators inside each
 * pattern and deliberately leaves a backslash that precedes a dot alone, taking it for an escape —
 * so `C:\repo\.claude\worktrees\x` becomes a pattern that matches `C:\repo.claude\…` and nothing
 * else, and every ignore anchored to it silently stops applying. That is the path of every agent
 * session worktree. Written with forward slashes and then escaped as a whole, the same rewrite
 * produces the right expression on Windows and changes nothing elsewhere.
 */
const rootDirectory = __dirname.split( /[\\/]/ ).join( '/' );

/**
 * Client sites and local-only directories, as path patterns Jest understands (spec 102).
 *
 * A client site under `sites/` carries its own Jest config and runs from its own directory, so
 * sweeping it in from here runs its suites without the module mapping they depend on. Anchored to
 * this directory so that a run started inside one of them still finds its own tests.
 */
const ownedElsewhere = [ ...clientOwned, ...localOnly ]
	.filter( ( pattern ) => pattern.endsWith( '/**' ) )
	.map(
		( pattern ) =>
			`^${ escapeForRegExp(
				`${ rootDirectory }/${ pattern.slice( 0, -2 ) }`
			) }`
	);

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
		'<rootDir>/wp-ms/',
		'<rootDir>/dist/',
		'<rootDir>/docs-app/',
		...ownedElsewhere,
	],
};
