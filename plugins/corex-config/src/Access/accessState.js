export const EFFECTS = [ 'inherit', 'allow', 'deny' ];

export function accessEndpoint( root, path = '' ) {
	const base = String( root || '' ).replace( /\/$/, '' );
	const suffix = String( path || '' ).replace( /^\//, '' );
	return suffix ? `${ base }/${ suffix }` : base;
}

export function normalizeAccessPayload( payload = {} ) {
	return {
		roles: Array.isArray( payload.roles ) ? payload.roles : [],
		rows: Array.isArray( payload.rows )
			? payload.rows.map( normalizeRow )
			: [],
		conflicts: Array.isArray( payload.conflicts ) ? payload.conflicts : [],
		nativeCapabilitiesEditable:
			payload.nativeCapabilitiesEditable !== false,
	};
}

export function normalizeRow( row ) {
	const cells = {};
	Object.entries( row?.cells || {} ).forEach( ( [ role, cell ] ) => {
		cells[ role ] = {
			effect: EFFECTS.includes( cell?.effect ) ? cell.effect : 'inherit',
			editable: cell?.editable === true,
			reason: cell?.reason || null,
		};
	} );
	return {
		key: row?.key || '',
		label: row?.label || row?.key || '',
		group: row?.group || '',
		risk: row?.risk || 'normal',
		locked: row?.locked === true,
		cells,
	};
}

export function initialAccessState() {
	return {
		status: 'idle',
		roles: [],
		rows: [],
		conflicts: [],
		nativeCapabilitiesEditable: true,
		selectedRole: '',
		draft: {},
		preview: null,
		notice: null,
		requestQueue: [],
		modal: null,
	};
}

export function accessReducer( state = initialAccessState(), action = {} ) {
	switch ( action.type ) {
		case 'loading':
			return { ...state, status: 'loading', notice: null };
		case 'loaded': {
			const normalized = normalizeAccessPayload( action.payload );
			return {
				...state,
				...normalized,
				status: 'ready',
				selectedRole:
					state.selectedRole || normalized.roles[ 0 ]?.key || '',
				draft: snapshotEffects( normalized.rows ),
				preview: null,
			};
		}
		case 'selectRole':
			return { ...state, selectedRole: action.role || '', preview: null };
		case 'setEffect':
			return setEffect(
				state,
				action.role,
				action.ability,
				action.effect
			);
		case 'preview':
			return { ...state, preview: action.preview || null, notice: null };
		case 'applied':
			return {
				...state,
				preview: null,
				draft: {},
				notice: {
					tone: 'success',
					message: action.message || 'Access updated.',
				},
			};
		case 'requestQueueLoaded':
			return {
				...state,
				requestQueue: Array.isArray( action.requests )
					? action.requests
					: [],
			};
		case 'modal':
			return { ...state, modal: action.modal || null };
		case 'error':
			return {
				...state,
				status: 'ready',
				notice: {
					tone: 'error',
					message: action.message || 'Access action failed.',
				},
			};
		default:
			return state;
	}
}

export function buildRoleChanges( originalRows, draft, role ) {
	const changes = {};
	( originalRows || [] ).forEach( ( row ) => {
		const original = row.cells?.[ role ]?.effect || 'inherit';
		const next = draft?.[ role ]?.[ row.key ] || original;
		if ( next !== original ) {
			changes[ row.key ] = next;
		}
	} );
	return changes;
}

function setEffect( state, role, ability, effect ) {
	if ( ! role || ! ability || ! EFFECTS.includes( effect ) ) {
		return state;
	}
	const row = state.rows.find( ( candidate ) => candidate.key === ability );
	if ( ! row?.cells?.[ role ]?.editable ) {
		return state;
	}
	return {
		...state,
		draft: {
			...state.draft,
			[ role ]: {
				...( state.draft[ role ] || {} ),
				[ ability ]: effect,
			},
		},
		preview: null,
	};
}

function snapshotEffects( rows ) {
	const draft = {};
	rows.forEach( ( row ) => {
		Object.entries( row.cells ).forEach( ( [ role, cell ] ) => {
			draft[ role ] = {
				...( draft[ role ] || {} ),
				[ row.key ]: cell.effect,
			};
		} );
	} );
	return draft;
}
