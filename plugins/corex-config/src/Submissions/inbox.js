export const initialInboxState = {
	status: 'idle',
	items: [],
	total: 0,
	page: 1,
	perPage: 25,
	selectedIds: [],
	error: '',
	message: '',
	drawer: {
		open: false,
		id: 0,
		status: 'idle',
		record: null,
		error: '',
	},
};

export function inboxReducer( state, action ) {
	switch ( action.type ) {
		case 'loading':
			return { ...state, status: 'loading', error: '', message: '' };
		case 'loaded': {
			const page = normalizeInboxPage( action.page );
			const visible = new Set( page.items.map( ( item ) => item.id ) );
			return {
				...state,
				...page,
				status: 'ready',
				selectedIds: state.selectedIds.filter( ( id ) =>
					visible.has( id )
				),
				error: '',
			};
		}
		case 'failed':
			return { ...state, status: 'error', error: action.message || '' };
		case 'selectionChanged':
			return { ...state, selectedIds: action.ids };
		case 'message':
			return { ...state, message: action.message || '', error: '' };
		case 'detailLoading':
			return {
				...state,
				drawer: {
					open: true,
					id: action.id,
					status: 'loading',
					record: null,
					error: '',
				},
			};
		case 'detailLoaded':
			return {
				...state,
				drawer: {
					...state.drawer,
					status: 'ready',
					record: action.record,
					error: '',
				},
			};
		case 'detailFailed':
			return {
				...state,
				drawer: {
					...state.drawer,
					status: 'error',
					error: action.message || '',
				},
			};
		case 'drawerClosed':
			return {
				...state,
				drawer: { ...initialInboxState.drawer },
			};
		default:
			return state;
	}
}

export function buildInboxUrl( base, filters ) {
	const params = new URLSearchParams();
	const values = [
		[ 'search', filters.search ],
		[ 'flow', filters.flow ],
		[ 'status', filters.status ],
		[ 'owner', filters.owner ],
		[ 'date_from', filters.dateFrom ],
		[ 'date_to', filters.dateTo ],
		[ 'include_test', filters.includeTest ? '1' : '' ],
		[ 'page', filters.page ],
		[ 'per_page', filters.perPage ],
	];
	values.forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			params.set( key, String( value ) );
		}
	} );

	return `${ base }?${ params.toString() }`;
}

export function normalizeInboxPage( payload = {} ) {
	const items = Array.isArray( payload.items )
		? payload.items
				.filter( ( item ) => Number( item?.id ) > 0 )
				.map( ( item ) => ( {
					...item,
					id: Number( item.id ),
					is_test: Boolean( item.is_test ),
					status: item.status || 'new',
					owner_type: item.owner_type || 'none',
					owner_key: item.owner_key || '',
				} ) )
		: [];

	return {
		items,
		total: Math.max( 0, Number( payload.total ) || 0 ),
		page: Math.max( 1, Number( payload.page ) || 1 ),
		perPage: Math.max( 1, Number( payload.per_page ) || 25 ),
	};
}

export function toggleSubmission( selectedIds, id ) {
	const selected = new Set( selectedIds.map( Number ) );
	if ( selected.has( Number( id ) ) ) {
		selected.delete( Number( id ) );
	} else {
		selected.add( Number( id ) );
	}

	return [ ...selected ].sort( ( left, right ) => left - right );
}

export function buildExportPayload( options ) {
	return {
		scope: options.scope,
		selected_ids:
			options.scope === 'selected'
				? [ ...options.selectedIds ].map( Number ).sort( ( a, b ) => a - b )
				: [],
		columns: [ ...options.columns ],
		query: options.scope === 'filtered' ? { ...options.filters } : {},
		include_test: Boolean( options.includeTest ),
		personal_data_acknowledged: Boolean( options.acknowledged ),
	};
}
