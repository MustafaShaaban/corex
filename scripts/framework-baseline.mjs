/**
 * Whether a client repository's framework files still match the release it recorded (spec 102).
 *
 * A client site records the framework release it is synchronised to in
 * `sites/<client>/corex-baseline.json`. This module holds the two rules that record is checked
 * against, with no git and no filesystem in them: what a record must contain, and how a list of
 * changed paths divides into drift, recorded exceptions and stale exceptions.
 * `verify-framework.mjs` supplies the paths and owns the exit code.
 */
import { isClientOwned } from './repository-ownership.mjs';

const FULL_COMMIT_HASH = /^[0-9a-f]{40}$/;
const EXCEPTION_FIELDS = [ 'path', 'reason', 'upstream' ];

/** A baseline record that cannot be used, with a message fit to print as it stands. */
export class BaselineError extends Error {}

const isFilled = ( value ) => typeof value === 'string' && value.trim() !== '';

const validException = ( exception, index ) => {
	const missing = EXCEPTION_FIELDS.find(
		( field ) => ! isFilled( exception?.[ field ] )
	);

	if ( missing ) {
		throw new BaselineError(
			`exception ${ index + 1 } has no "${ missing }" — each one needs ` +
				`${ EXCEPTION_FIELDS.join( ', ' ) }`
		);
	}

	return {
		path: exception.path,
		reason: exception.reason,
		upstream: exception.upstream,
	};
};

/**
 * Validate a decoded `corex-baseline.json`.
 *
 * @param {Object} raw The decoded JSON.
 * @return {{release:string, commit:string, exceptions:Object[]}} The usable record.
 * @throws {BaselineError} When the record cannot be compared against.
 */
export const parseBaseline = ( raw ) => {
	const { release, commit, exceptions = [] } = raw ?? {};

	if ( ! isFilled( release ) ) {
		throw new BaselineError( '"release" is missing' );
	}

	if ( ! isFilled( commit ) ) {
		throw new BaselineError(
			'"commit" is empty — record one with ' +
				'`npm run verify:framework -- --record <release tag>`'
		);
	}

	if ( ! FULL_COMMIT_HASH.test( commit ) ) {
		throw new BaselineError(
			'"commit" must be a full 40-character commit hash'
		);
	}

	if ( ! Array.isArray( exceptions ) ) {
		throw new BaselineError( '"exceptions" must be a list' );
	}

	return { release, commit, exceptions: exceptions.map( validException ) };
};

/**
 * The one commit every record in the repository names.
 *
 * A repository holds one framework, so two client sites that record different commits cannot both
 * be right, and comparing against either would report the other's truth as drift.
 *
 * @param {{file:string, commit:string}[]} records One entry per record found.
 * @return {string} The commit.
 * @throws {BaselineError} When the records disagree.
 */
export const sharedCommit = ( records ) => {
	const [ first, ...rest ] = records;
	const other = rest.find(
		( candidate ) => candidate.commit !== first.commit
	);

	if ( other ) {
		throw new BaselineError(
			`${ first.file } and ${ other.file } record different commits — ` +
				'a repository has one framework, so they must agree'
		);
	}

	return first.commit;
};

/**
 * Divide the paths that differ from the baseline.
 *
 * @param {Object}   input
 * @param {Object}   input.ownership  The ownership map.
 * @param {Object[]} input.exceptions The recorded exceptions.
 * @param {string[]} input.changed    Every path that differs from the baseline commit.
 * @return {{drift:string[], excepted:Object[], stale:Object[]}} Framework-owned paths that changed
 *         without an exception; exceptions whose path did change; exceptions whose path did not.
 */
export const evaluateDrift = ( { ownership, exceptions, changed } ) => {
	const frameworkChanges = new Set(
		changed.filter( ( file ) => ! isClientOwned( ownership, file ) )
	);
	const exceptedPaths = new Set(
		exceptions.map( ( exception ) => exception.path )
	);

	return {
		drift: [ ...frameworkChanges ]
			.filter( ( file ) => ! exceptedPaths.has( file ) )
			.sort(),
		excepted: exceptions.filter( ( exception ) =>
			frameworkChanges.has( exception.path )
		),
		stale: exceptions.filter(
			( exception ) => ! frameworkChanges.has( exception.path )
		),
	};
};
