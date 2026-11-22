/**
 * Which paths in this repository are a client's, and which repository this is (spec 102).
 *
 * One definition, read by everything that walks the tree: a path is **client-owned** if it matches
 * a pattern in `.github/repository-ownership.json`, and **framework-owned** otherwise. Defining the
 * framework as the complement is deliberate. A list of framework directories is wrong in the
 * direction that matters — whatever is missing from it goes unchecked — whereas the complement
 * covers a new framework directory the day it is added.
 *
 * Pure apart from `loadOwnership`, which reads the one file, and `recordedBaselines`, which looks
 * for the baseline records under `sites/` (spec 135). Node built-ins only, so a client's CI can run
 * the checks built on this without installing anything.
 */
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';

const OWNERSHIP_FILE = '.github/repository-ownership.json';
/** Where a client repository records its baseline: one file per site directory. */
export const BASELINE_PATTERN = 'sites/<client>/corex-baseline.json';
const [ SITES_DIRECTORY, , BASELINE_FILE ] = BASELINE_PATTERN.split( '/' );
const ROLES = [ 'framework', 'client' ];
const REPOSITORY_NAME = /^[\w.-]+\/[\w.-]+$/;

const invalid = ( problem ) =>
	new Error( `${ OWNERSHIP_FILE } is invalid: ${ problem }.` );

const isPatternList = ( value ) =>
	Array.isArray( value ) &&
	value.every( ( entry ) => typeof entry === 'string' && entry !== '' );

/**
 * Validate the map and return it in the shape the rest of this module expects.
 *
 * @param {Object} raw The decoded JSON.
 *                     `clientMayRemove` is the short list of framework files a client repository may delete, and
 *                     only delete: files that act on whatever repository they are in and cannot be made conditional.
 *                     A map written before the list existed allows none.
 *
 * @return {{frameworkRepository:string, clientOwned:string[], localOnly:string[], clientMayRemove:string[]}} The map.
 */
export const parseOwnership = ( raw ) => {
	const {
		frameworkRepository,
		clientOwned,
		localOnly = [],
		clientMayRemove = [],
	} = raw ?? {};

	if (
		typeof frameworkRepository !== 'string' ||
		! REPOSITORY_NAME.test( frameworkRepository )
	) {
		throw invalid( '"frameworkRepository" must be an owner/name pair' );
	}

	if ( ! isPatternList( clientOwned ) || clientOwned.length === 0 ) {
		throw invalid( '"clientOwned" must list at least one path pattern' );
	}

	if ( ! isPatternList( localOnly ) ) {
		throw invalid( '"localOnly" must be a list of path patterns' );
	}

	if ( ! isPatternList( clientMayRemove ) ) {
		throw invalid( '"clientMayRemove" must be a list of path patterns' );
	}

	return { frameworkRepository, clientOwned, localOnly, clientMayRemove };
};

/**
 * @param {string} repositoryRoot Absolute path of the checkout.
 * @return {{frameworkRepository:string, clientOwned:string[], localOnly:string[]}} The map.
 */
export const loadOwnership = ( repositoryRoot ) =>
	parseOwnership(
		JSON.parse(
			readFileSync( path.join( repositoryRoot, OWNERSHIP_FILE ), 'utf8' )
		)
	);

/**
 * Whether a repository-relative path matches a pattern.
 *
 * Two wildcards and nothing else: `**` crosses directories, `*` stays inside one segment. Every
 * other character is literal, and the pattern is anchored at the repository root.
 *
 * @param {string} pattern  For example `sites/**`.
 * @param {string} filePath Repository-relative, forward slashes.
 * @return {boolean} Whether it matches.
 */
export const matchesPattern = ( pattern, filePath ) => {
	const source = pattern
		.split( '**' )
		.map( ( part ) =>
			part
				.split( '*' )
				.map( ( literal ) =>
					literal.replace( /[.+?^${}()|[\]\\]/g, '\\$&' )
				)
				.join( '[^/]*' )
		)
		.join( '.*' );

	return new RegExp( `^${ source }$` ).test( filePath );
};

/**
 * @param {{clientOwned:string[]}} ownership The map.
 * @param {string}                 filePath  Repository-relative, forward slashes.
 * @return {boolean} Whether the client owns it. Everything else is the framework's.
 */
export const isClientOwned = ( ownership, filePath ) =>
	ownership.clientOwned.some( ( pattern ) =>
		matchesPattern( pattern, filePath )
	);

/**
 * Whether a client repository may delete this framework file.
 *
 * Delete, not edit: the file stays the framework's, and a client that changes one has drifted like
 * any other. What a client may do is not have it.
 *
 * @param {{clientMayRemove:string[]}} ownership The parsed ownership map.
 * @param {string}                     filePath  A repository-relative path.
 * @return {boolean} True when the map lists it as removable.
 */
export const mayBeRemovedByClient = ( ownership, filePath ) =>
	ownership.clientMayRemove.some( ( pattern ) =>
		matchesPattern( pattern, filePath )
	);

/**
 * The `owner/name` a git remote URL points at, in either the SSH or the HTTPS form.
 *
 * @param {string} url A remote URL.
 * @return {string|null} `owner/name`, or null when the URL does not name one.
 */
export const repositoryFromRemoteUrl = ( url ) => {
	const match = url.trim().match( /[:/]([\w.-]+\/[\w.-]+?)(?:\.git)?\/?$/ );

	return match ? match[ 1 ] : null;
};

const sameRepository = ( left, right ) =>
	left.toLowerCase() === right.toLowerCase();

const knownRole = ( role ) => {
	if ( ! ROLES.includes( role ) ) {
		throw new Error(
			`COREX_REPOSITORY_ROLE must be one of: ${ ROLES.join(
				', '
			) }. Received "${ role }".`
		);
	}

	return role;
};

/**
 * The baseline records this checkout holds: every `sites/<client>/corex-baseline.json` on disk.
 *
 * The one definition of "a baseline is recorded" (spec 135, FR-002), read by
 * `verify-framework.mjs`, `client-merge-check.mjs` and the repository hygiene test alike. A site
 * directory that is a link or a junction counts: only the record has to be there.
 *
 * @param {string} repositoryRoot Absolute path of the checkout.
 * @return {string[]} Their repository-relative paths, forward slashes; empty when there is none.
 */
export const recordedBaselines = ( repositoryRoot ) => {
	const sites = path.join( repositoryRoot, SITES_DIRECTORY );

	if ( ! existsSync( sites ) ) {
		return [];
	}

	return readdirSync( sites )
		.map( ( site ) => `${ SITES_DIRECTORY }/${ site }/${ BASELINE_FILE }` )
		.filter( ( file ) => existsSync( path.join( repositoryRoot, file ) ) );
};

/**
 * Whether the environment states the role outright, which then stands whatever the repository holds.
 *
 * @param {Object} env Usually `process.env`.
 * @return {boolean} True when `COREX_REPOSITORY_ROLE` is set.
 */
export const roleIsStated = ( env ) => Boolean( env.COREX_REPOSITORY_ROLE );

/**
 * Whether this checkout is the framework's own repository or a client's.
 *
 * Resolved in a fixed order (spec 102, and spec 135 FR-001):
 *
 * 1. an explicit `COREX_REPOSITORY_ROLE`;
 * 2. a repository named by CI or by the `origin` remote: `framework` when it is the framework's
 *    own, `client` when it is any other;
 * 3. for a repository nothing names, `client` when a baseline is recorded and `framework` when
 *    none is.
 *
 * A named repository is decided by its name alone, so committing a baseline into the framework's
 * own repository does not switch off its rule against `sites/`. The last default is the strict one
 * on purpose: a repository that cannot say what it is and records no baseline gets the rule that
 * forbids `sites/`, not the one that allows it.
 *
 * @param {Object}                       context
 * @param {{frameworkRepository:string}} context.ownership          The map.
 * @param {Object}                       context.env                Usually `process.env`.
 * @param {string}                       [context.originUrl]        The `origin` remote, if there is one.
 * @param {boolean}                      [context.baselineRecorded] Whether `recordedBaselines()` found any.
 * @return {'framework'|'client'} The role.
 */
export const resolveRole = ( {
	ownership,
	env,
	originUrl = '',
	baselineRecorded = false,
} ) => {
	if ( roleIsStated( env ) ) {
		return knownRole( env.COREX_REPOSITORY_ROLE );
	}

	const repository =
		env.GITHUB_REPOSITORY || repositoryFromRemoteUrl( originUrl );

	if ( ! repository ) {
		return baselineRecorded ? 'client' : 'framework';
	}

	return sameRepository( repository, ownership.frameworkRepository )
		? 'framework'
		: 'client';
};
