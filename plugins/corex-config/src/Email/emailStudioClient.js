/**
 * Pure state, endpoint, validation, and preview helpers for Email Studio.
 */

export const TABS = [
	{ key: 'overview' },
	{ key: 'templates' },
	{ key: 'layouts' },
	{ key: 'partials' },
	{ key: 'variables' },
	{ key: 'routing' },
	{ key: 'preview' },
	{ key: 'plain' },
	{ key: 'test' },
	{ key: 'logs' },
	{ key: 'health' },
];

export const initialEmailStudioState = {
	status: 'idle',
	mutating: false,
	message: '',
	data: normalizeOverview( {} ),
};

export function normalizeOverview( payload ) {
	const source = payload && typeof payload === 'object' ? payload : {};
	return {
		delivery: source.delivery || {},
		counts: source.counts || {},
		templates: Array.isArray( source.templates ) ? source.templates : [],
		layouts: Array.isArray( source.layouts ) ? source.layouts : [],
		partials: Array.isArray( source.partials ) ? source.partials : [],
		routes: Array.isArray( source.routes ) ? source.routes : [],
		captures: Array.isArray( source.captures ) ? source.captures : [],
		attempts: Array.isArray( source.attempts ) ? source.attempts : [],
		recentTestSends: Array.isArray( source.recent_test_sends )
			? source.recent_test_sends
			: [],
		health: Array.isArray( source.health ) ? source.health : [],
		variables:
			source.variables && typeof source.variables === 'object'
				? source.variables
				: {},
	};
}

export function emailStudioReducer( state, action ) {
	switch ( action.type ) {
		case 'load':
			return { ...state, status: 'loading', message: '' };
		case 'loaded':
			return {
				...state,
				status: 'ready',
				mutating: false,
				message: action.message || '',
				data: normalizeOverview( action.payload ),
			};
		case 'mutating':
			return { ...state, mutating: true, message: '' };
		case 'failed':
			return {
				...state,
				status: 'error',
				mutating: false,
				message: action.message || '',
			};
		case 'notice':
			return {
				...state,
				status: 'ready',
				mutating: false,
				message: action.message || '',
			};
		default:
			return state;
	}
}

const ENDPOINTS = {
	templates: () => 'templates',
	template: ( id ) => `templates/${ id }`,
	draft: ( id ) => `templates/${ id }/draft`,
	activate: ( id ) => `templates/${ id }/activate`,
	health: ( id ) => `templates/${ id }/health`,
	layouts: () => 'layouts',
	partials: () => 'partials',
	routes: () => 'routes',
	test: () => 'test',
	resend: ( id ) => `attempts/${ id }/resend`,
};

export function buildEndpoint( base, kind, id = '' ) {
	if ( ! ENDPOINTS[ kind ] ) {
		throw new Error( `Unknown Email Studio endpoint: ${ kind }` );
	}

	return `${ String( base ).replace( /\/$/, '' ) }/${ ENDPOINTS[ kind ](
		id
	) }`;
}

export function validateDraftForm( draft, translate = ( value ) => value ) {
	const errors = {};
	if ( ! String( draft.subject || '' ).trim() ) {
		errors.subject = translate( 'Subject is required.', 'corex' );
	}
	if (
		! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
			String( draft.from_address || '' )
		)
	) {
		errors.from_address = translate(
			'A valid sender address is required.',
			'corex'
		);
	}
	if ( ! String( draft.html_body || '' ).trim() ) {
		errors.html_body = translate( 'HTML content is required.', 'corex' );
	}
	if (
		! Number.isInteger( Number( draft.layout_id ) ) ||
		Number( draft.layout_id ) < 1 ||
		! Number.isInteger( Number( draft.layout_version ) ) ||
		Number( draft.layout_version ) < 1
	) {
		errors.layout_id = translate( 'Choose a layout version.', 'corex' );
	}
	if (
		draft.plain_text_mode === 'manual' &&
		! String( draft.plain_text || '' ).trim()
	) {
		errors.plain_text = translate(
			'Manual plain text is required.',
			'corex'
		);
	}

	return errors;
}

function escapeHtml( value ) {
	return String( value )
		.replaceAll( '&', '&amp;' )
		.replaceAll( '<', '&lt;' )
		.replaceAll( '>', '&gt;' )
		.replaceAll( '"', '&quot;' )
		.replaceAll( "'", '&#039;' );
}

export function mergePreviewVariables( html, values = {} ) {
	return String( html || '' ).replace(
		/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/g,
		( match, key ) => escapeHtml( values[ key ] ?? `[${ key }]` )
	);
}

export function previewDocument( html, values = {}, direction = 'ltr' ) {
	const dir = direction === 'rtl' ? 'rtl' : 'ltr';
	return `<!doctype html><html dir="${ dir }"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src data: https:; style-src 'unsafe-inline'"></head><body>${ mergePreviewVariables(
		html,
		values
	) }</body></html>`;
}

function expandPreviewPartials( html, partials ) {
	const catalog = Object.fromEntries(
		partials
			.filter( ( partial ) => partial.status === 'active' )
			.map( ( partial ) => [ partial.slug, partial.html_body ] )
	);
	return String( html || '' ).replace(
		/\{\{\s*>\s*([a-z][a-z0-9-]*)\s*\}\}/g,
		( match, slug ) => catalog[ slug ] ?? `<p>[${ slug }]</p>`
	);
}

export function composePreviewHtml( draft, layouts = [], partials = [] ) {
	const content = expandPreviewPartials( draft.html_body, partials );
	const layout = layouts.find(
		( candidate ) =>
			Number( candidate.id ) === Number( draft.layout_id ) &&
			Number( candidate.version ) === Number( draft.layout_version )
	);
	if ( ! layout ) {
		return content;
	}

	const regions = Object.fromEntries(
		Object.entries( layout.regions || {} ).map( ( [ key, value ] ) => [
			key,
			expandPreviewPartials( value, partials ),
		] )
	);
	const body = String( regions.body || '{{ content }}' ).replace(
		/\{\{\s*content\s*\}\}/g,
		content
	);
	const accent = /^#[0-9a-f]{6}$/i.test( regions.accent || '' )
		? `border-block-start:4px solid ${ regions.accent };`
		: '';

	return `<div style="${ accent }">${ regions.header || '' }${ body }${
		regions.button || ''
	}${ regions.footer || '' }</div>`;
}

export function plainTextFromHtml( html ) {
	const element = document.createElement( 'div' );
	element.innerHTML = String( html || '' );
	return ( element.textContent || '' ).replace( /\s+/g, ' ' ).trim();
}
