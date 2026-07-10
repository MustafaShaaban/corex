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
	Object.entries( query.filters || {} ).forEach( ( [ key, value ] ) => {
		if ( value !== '' && value != null ) {
			params.set( `filters[${ key }]`, String( value ) );
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

export function normalizeCatalog( sources ) {
	return ( Array.isArray( sources ) ? sources : [] ).map( ( source ) => ( {
		key: String( source.key || '' ),
		label: String( source.label || source.key || '' ),
		access: source.access || 'denied',
		capabilities: source.capabilities || {},
		actions: source.actions || {},
		fields: Array.isArray( source.fields ) ? source.fields : [],
	} ) );
}

export function canAction( source, operation ) {
	return Boolean( source?.actions?.[ operation ]?.visible );
}

export function initialDataState( sourceKey = '' ) {
	return {
		sourceKey,
		query: {
			search: '',
			filters: {},
			sort: '',
			dir: 'desc',
			page: 1,
			perPage: 20,
		},
		rows: [],
		columns: [],
		fields: [],
		total: 0,
		status: 'idle',
		error: '',
		selected: [],
		preview: null,
		pending: '',
		notice: null,
	};
}

export function dataReducer( state, action ) {
	switch ( action.type ) {
		case 'source':
			return initialDataState( action.sourceKey );
		case 'query': {
			const patch = action.patch || {};
			const onlyPage = Object.keys( patch ).every( ( key ) => key === 'page' );
			return {
				...state,
				query: { ...state.query, ...patch, page: onlyPage ? patch.page : 1 },
				selected: [],
			};
		}
		case 'loading':
			return { ...state, status: 'loading', error: '' };
		case 'loaded':
			return {
				...state,
				...action.payload,
				status: 'ready',
				error: '',
				selected: [],
			};
		case 'select':
			return { ...state, selected: toggleSelection( state.selected, action.id ) };
		case 'select-all':
			return {
				...state,
				selected: allRowsSelected( state.selected, state.rows )
					? []
					: state.rows.map( ( row ) => row.id ),
			};
		case 'preview':
			return { ...state, preview: action.preview, pending: '' };
		case 'request':
			return { ...state, pending: action.request, notice: null };
		case 'success':
			return {
				...state,
				pending: '',
				preview: null,
				notice: { tone: 'success', message: action.message },
			};
		case 'error':
			return {
				...state,
				status: state.rows.length ? state.status : 'error',
				pending: '',
				error: action.message,
				notice: { tone: 'error', message: action.message },
			};
		case 'dismiss-preview':
			return { ...state, preview: null };
		default:
			return state;
	}
}

export function dataEndpoint( restUrl, sourceKey, action, id = null ) {
	const source = sourceKey ? `${ restUrl }/${ sourceKey }` : restUrl;
	const routes = {
		'mutation-preview': `${ source }/mutations/preview`,
		'mutation-apply': `${ source }/mutations/apply`,
		import: `${ source }/imports${ id ? `/${ id }` : '' }`,
		'import-commit': `${ source }/imports/${ id }/commit`,
		'import-report': `${ source }/imports/${ id }/report`,
		export: `${ source }/exports`,
		'export-download': `${ source }/exports/${ id }/download`,
		migrations: `${ restUrl }/migrations`,
		'migration-preview': `${ restUrl }/migrations/preview`,
		'migration-apply': `${ restUrl }/migrations/apply`,
		'migration-rollback': `${ restUrl }/migrations/${ id }/rollback`,
	};

	return routes[ action ] || source;
}
