/**
 * Pure state and immutable editing helpers for the Forms & Flows builder.
 */

import { generateUuid } from './uuid.js';

const FLOW_ACTIONS = [ 'preview', 'publish', 'unpublish', 'close', 'test' ];

export const initialFlowState = {
	status: 'idle',
	flows: [],
	selected: null,
	draft: null,
	extensions: null,
	testResult: null,
	message: '',
};

export function normalizeFlowPayload( payload ) {
	const source = payload && typeof payload === 'object' ? payload : {};
	return {
		flows: Array.isArray( source.flows ) ? source.flows : [],
		extensions:
			source.extensions && typeof source.extensions === 'object'
				? source.extensions
				: null,
	};
}

export function flowReducer( state, action ) {
	switch ( action.type ) {
		case 'load':
			return { ...state, status: 'loading', message: '' };
		case 'loaded':
			return {
				...state,
				...normalizeFlowPayload( action.payload ),
				status: 'ready',
				message: action.message || '',
			};
		case 'selected':
			return {
				...state,
				status: 'ready',
				selected: action.payload.flow.id,
				draft: action.payload,
				message: '',
			};
		case 'draft_changed':
			return { ...state, draft: action.payload, message: '' };
		case 'cleared':
			return {
				...state,
				selected: null,
				draft: null,
				testResult: null,
				message: '',
			};
		case 'mutating':
			return { ...state, status: 'mutating', message: '' };
		case 'test_result':
			return {
				...state,
				status: 'ready',
				testResult: action.payload,
				message: action.message || '',
			};
		case 'failed':
			return { ...state, status: 'error', message: action.message || '' };
		default:
			return state;
	}
}

export function addField( draft, type ) {
	const field = {
		uuid: fieldUuid(),
		key: uniqueKey( draft.configuration.schema, type ),
		label: typeLabel( type ),
		type,
		placeholder: '',
		help_text: '',
		default_value: '',
		required: false,
		options: [],
		validation_rules: [],
		width: 'full',
		step_key: '',
		personal_data_class: 'none',
		extension_config: {},
	};
	return withSchema( draft, [ ...draft.configuration.schema, field ] );
}

export function updateField( draft, uuid, changes ) {
	return withSchema(
		draft,
		draft.configuration.schema.map( ( field ) =>
			field.uuid === uuid
				? { ...field, ...changes, uuid: field.uuid }
				: field
		)
	);
}

export function removeField( draft, uuid ) {
	return withSchema(
		draft,
		draft.configuration.schema.filter( ( field ) => field.uuid !== uuid )
	);
}

export function updateConfiguration( draft, key, value ) {
	return {
		...draft,
		configuration: { ...draft.configuration, [ key ]: value },
	};
}

export function moveField( draft, uuid, direction ) {
	const schema = [ ...draft.configuration.schema ];
	const from = schema.findIndex( ( field ) => field.uuid === uuid );
	const offset = directionOffset( direction );
	const to = from + offset;
	if ( from < 0 || offset === 0 || to < 0 || to >= schema.length ) {
		return draft;
	}
	[ schema[ from ], schema[ to ] ] = [ schema[ to ], schema[ from ] ];
	return withSchema( draft, schema );
}

export function stageStatus( draft ) {
	const configuration = draft?.configuration || {};
	return {
		form: configuration.schema?.length > 0 ? 'ready' : 'incomplete',
		validation:
			Object.keys( configuration.validation || {} ).length > 0
				? 'ready'
				: 'incomplete',
		routing: configuration.routing?.fallback ? 'ready' : 'incomplete',
		emails: configuration.email_routes?.some( ( route ) => route.enabled )
			? 'ready'
			: 'incomplete',
		success: configuration.success?.type ? 'ready' : 'incomplete',
	};
}

export function flowEndpoint( base, id = '', action = '' ) {
	if ( action && ! FLOW_ACTIONS.includes( action ) ) {
		throw new Error( `Unknown flow action: ${ action }` );
	}
	const root = base.replace( /\/$/, '' );
	const flow = id === '' ? root : `${ root }/${ encodeURIComponent( id ) }`;
	return action ? `${ flow }/${ action }` : flow;
}

export function buildFlowListUrl( base, search = '', state = '' ) {
	const query = new URLSearchParams();
	if ( search ) {
		query.set( 'search', search );
	}
	if ( state ) {
		query.set( 'state', state );
	}
	return query.toString()
		? `${ flowEndpoint( base ) }?${ query }`
		: flowEndpoint( base );
}

export function draftFromDetail( detail ) {
	const versions = Array.isArray( detail?.versions ) ? detail.versions : [];
	const current = versions.find(
		( version ) =>
			version.version_number === detail?.flow?.current_draft_version
	);
	return current
		? {
				flow: detail.flow,
				version: current,
				configuration: current.configuration,
		  }
		: null;
}

function withSchema( draft, schema ) {
	return { ...draft, configuration: { ...draft.configuration, schema } };
}
function fieldUuid() {
	return generateUuid();
}
function directionOffset( direction ) {
	if ( direction === 'up' ) {
		return -1;
	}
	if ( direction === 'down' ) {
		return 1;
	}
	return 0;
}
function uniqueKey( schema, type ) {
	const base = type.replace( /[^a-z0-9]+/g, '_' );
	const keys = new Set( schema.map( ( field ) => field.key ) );
	let candidate = base;
	let suffix = 2;
	while ( keys.has( candidate ) ) {
		candidate = `${ base }_${ suffix }`;
		suffix += 1;
	}
	return candidate;
}
function typeLabel( type ) {
	return type
		.split( '-' )
		.map( ( word ) => word.charAt( 0 ).toUpperCase() + word.slice( 1 ) )
		.join( ' ' );
}
