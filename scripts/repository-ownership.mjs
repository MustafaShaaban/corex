/**
 * Which paths in this repository are a client's, and which repository this is (spec 102).
 *
 * One definition, read by everything that walks the tree: a path is **client-owned** if it matches
 * a pattern in `.github/repository-ownership.json`, and **framework-owned** otherwise. Defining the
 * framework as the complement is deliberate. A list of framework directories is wrong in the
 * direction that matters — whatever is missing from it goes unchecked — whereas the complement
 * covers a new framework directory the day it is added.
 *
 * Pure apart from `loadOwnership`, which reads the one file. Node built-ins only, so a client's CI
 * can run the checks built on this without installing anything.
 */
import { readFileSync } from 'node:fs';
import path from 'node:path';

const OWNERSHIP_FILE = '.github/repository-ownership.json';
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
 * @return {{frameworkRepository:string, clientOwned:string[], localOnly:string[]}} The map.
 */
export const parseOwnership = ( raw ) => {
	const { frameworkRepository, clientOwned, localOnly = [] } = raw ?? {};

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

	return { frameworkRepository, clientOwned, localOnly };
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
 * The `owner/name` a git remote URL points at, in either the SSH or the HTTPS form.
 *
 * @param {string} url A remote URL.
 * @return {string|null} `owner/name`, or null when the URL does not name one.
 */
export const repositoryFromRemoteUrl = ( url ) => {
	const match = String( url ?? '' )
		.trim()
		.match( /[:/]([\w.-]+\/[\w.-]+?)(?:\.git)?\/?$/ );

	return match ? match[ 1 ] : null;
};

const sameRepository = ( left, right ) =>
	left.toLowerCase() === right.toLowerCase();

/**
 * Whether this checkout is the framework's own repository or a client's.
 *
 * Resolved in a fixed order — an explicit role, then what CI reports, then the `origin` remote —
 * and defaulting to `framework`. The default is the strict one on purpose: a repository that
 * cannot say what it is gets the rule that forbids `sites/`, not the one that allows it.
 *
 * @param {Object}                       context
 * @param {{frameworkRepository:string}} context.ownership   The map.
 * @param {Object}                       context.env         Usually `process.env`.
 * @param {string}                       [context.originUrl] The `origin` remote, if there is one.
 * @return {'framework'|'client'} The role.
 */
export const resolveRole = ( { ownership, env, originUrl = '' } ) => {
	const explicit = env.COREX_REPOSITORY_ROLE;

	if ( explicit !== undefined && explicit !== '' ) {
		if ( ! ROLES.includes( explicit ) ) {
			throw new Error(
				`COREX_REPOSITORY_ROLE must be one of: ${ ROLES.join(
					', '
				) }. Received "${ explicit }".`
			);
		}

		return explicit;
	}

	const repository =
		env.GITHUB_REPOSITORY || repositoryFromRemoteUrl( originUrl );

	if ( ! repository ) {
		return 'framework';
	}

	return sameRepository( repository, ownership.frameworkRepository )
		? 'framework'
		: 'client';
};
