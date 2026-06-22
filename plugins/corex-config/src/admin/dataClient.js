/**
 * Pure client helpers for the Corex → Data screen (spec 053 US2). Kept separate from the
 * React component so the client<->server contract (contracts/data-screen.md) is unit-tested
 * headlessly: no DOM, no network. The screen (index.js) is a thin shell over these.
 */

/**
 * Append a param only when it carries a non-empty value (so the server never sees blank
 * search/form/sort, which would otherwise widen or confuse the query).
 *
 * @param {URLSearchParams} params
 * @param {Object}          query
 */
function appendQuery( params, query ) {
	[ 'search', 'form', 'sort', 'dir' ].forEach( ( key ) => {
		if ( query[ key ] ) {
			params.set( key, String( query[ key ] ) );
		}
	} );
}

/**
 * The REST list URL for a source, carrying the params DataController::queryFrom() sanitises
 * (search/form/sort/dir/page/per_page). Paging is always sent; the rest only when set.
 *
 * @param {string} restUrl   REST base, e.g. .../wp-json/corex/v1/data
 * @param {string} sourceKey
 * @param {Object} query     { search, form, sort, dir, page, perPage }
 * @return {string} the request URL
 */
export function buildListUrl( restUrl, sourceKey, query = {} ) {
	const params = new URLSearchParams();
	appendQuery( params, query );
	params.set( 'page', String( query.page || 1 ) );
	params.set( 'per_page', String( query.perPage || 20 ) );
	return `${ restUrl }/${ sourceKey }?${ params.toString() }`;
}

/**
 * The admin-post export URL — the *current* filtered/sorted view + the export nonce. The
 * browser navigates to this (a real file download), so it must be a full GET URL. The
 * server (DataExportController) re-checks the cap + nonce and bounds the row count.
 *
 * @param {string} adminPostUrl admin-post.php URL
 * @param {string} sourceKey
 * @param {Object} query        { search, form, sort, dir }
 * @param {string} nonce        the corex_data_export nonce
 * @return {string} the download URL
 */
export function buildExportUrl( adminPostUrl, sourceKey, query = {}, nonce = '' ) {
	const params = new URLSearchParams();
	params.set( 'action', 'corex_data_export' );
	params.set( 'source', sourceKey );
	appendQuery( params, query );
	params.set( '_wpnonce', nonce );
	return `${ adminPostUrl }?${ params.toString() }`;
}

/**
 * Sort toggle: a new column sorts ascending; the active column flips direction.
 *
 * @param {Object} state  { sort, dir }
 * @param {string} column the clicked column id
 * @return {{sort: string, dir: 'asc'|'desc'}} the next sort state
 */
export function toggleSort( state, column ) {
	if ( state.sort !== column ) {
		return { sort: column, dir: 'asc' };
	}
	return { sort: column, dir: state.dir === 'asc' ? 'desc' : 'asc' };
}

/**
 * Accumulate the distinct, non-empty `form` values seen so far, so the form filter stays
 * populated across pages and after a filter narrows the visible rows. No backend change.
 *
 * @param {string[]} known existing form keys
 * @param {Object[]} rows  the just-loaded rows
 * @return {string[]} the merged, de-duplicated list (insertion order preserved)
 */
export function mergeForms( known, rows ) {
	const seen = new Set( known );
	rows.forEach( ( row ) => {
		if ( row && row.form ) {
			seen.add( row.form );
		}
	} );
	return Array.from( seen );
}

/**
 * Toggle one row id in the bulk-selection list (add when absent, remove when present).
 * Order of the remaining ids is preserved.
 *
 * @param {Array<number|string>} selected current selection
 * @param {number|string}        id       the toggled row id
 * @return {Array<number|string>} the next selection
 */
export function toggleSelection( selected, id ) {
	return selected.includes( id )
		? selected.filter( ( current ) => current !== id )
		: [ ...selected, id ];
}

/**
 * Whether every currently-visible row is selected (and there is at least one row) — the
 * "select all" checkbox checked state.
 *
 * @param {Array<number|string>} selected selected ids
 * @param {Object[]}             rows     the visible rows
 * @return {boolean} true when all visible rows are selected
 */
export function allRowsSelected( selected, rows ) {
	return rows.length > 0 && selected.length === rows.length;
}

/**
 * Which of the five render states the screen is in. Loading and error win first; an empty
 * result splits into "empty source" vs "no matches under a query" so the two are visibly
 * distinct (spec 053 FR-011).
 *
 * @param {Object}  args
 * @param {string}  args.status   idle|loading|error|ready
 * @param {number}  args.rowCount loaded row count
 * @param {boolean} args.hasQuery whether a search/form filter is active
 * @return {'loading'|'error'|'empty'|'empty-filtered'|'ready'} the state key
 */
export function viewState( { status, rowCount, hasQuery } ) {
	if ( status === 'loading' ) {
		return 'loading';
	}
	if ( status === 'error' ) {
		return 'error';
	}
	if ( rowCount === 0 ) {
		return hasQuery ? 'empty-filtered' : 'empty';
	}
	return 'ready';
}
