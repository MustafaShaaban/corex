/**
 * Pure state, endpoint, validation, and preview helpers for Email Studio.
 */
import { withQuery } from '../admin/restAddress.js';

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
	// Whether the studio has been read at all. It was guessed from "no templates yet", which
	// is also true of a studio that has been read and has none: every save on a new site
	// took the open form away to say "Loading Email Studio…".
	loaded: false,
	mutating: false,
	// The action whose request is out, by the name it is posted under, so that the button that
	// was pressed can say so. Every button is disabled by `mutating`; this is which one.
	pending: '',
	message: '',
	data: normalizeOverview( {} ),
};

export function normalizeOverview( payload ) {
	const source = payload && typeof payload === 'object' ? payload : {};
	return {
		delivery: source.delivery || {},
		// What happens to mail on this site, said on every tab (spec 113).
		outcome:
			source.outcome && typeof source.outcome === 'object'
				? source.outcome
				: null,
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
		// Grouped by the emails that supply them, each with a sample (spec 120).
		variables: Array.isArray( source.variables ) ? source.variables : [],
	};
}

/**
 * The studio is read once, and after that it is on screen for good: a save reads it again
 * behind the button that is working, and takes nothing away.
 *
 * @param {Object} state The studio's state.
 * @return {string} `loading`, `error` or `ready`.
 */
export function studioStatus( state ) {
	if ( state.loaded ) {
		return 'ready';
	}
	return state.status === 'error' ? 'error' : 'loading';
}

export function emailStudioReducer( state, action ) {
	switch ( action.type ) {
		case 'load':
			return { ...state, status: 'loading', message: '' };
		case 'loaded':
			return {
				...state,
				status: 'ready',
				loaded: true,
				mutating: false,
				pending: '',
				message: action.message || '',
				data: normalizeOverview( action.payload ),
			};
		case 'mutating':
			return {
				...state,
				mutating: true,
				pending: action.control || '',
				message: '',
			};
		case 'failed':
			return {
				...state,
				status: 'error',
				mutating: false,
				pending: '',
				message: action.message || '',
			};
		case 'notice':
			return {
				...state,
				status: 'ready',
				mutating: false,
				pending: '',
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
	preview: ( id ) => `templates/${ id }/preview`,
	reset: ( id ) => `templates/${ id }/reset`,
	resend: ( id ) => `attempts/${ id }/resend`,
	captureScope: () => 'capture-scope',
};

export function buildEndpoint( base, kind, id = '' ) {
	if ( ! ENDPOINTS[ kind ] ) {
		throw new Error( `Unknown Email Studio endpoint: ${ kind }` );
	}

	return `${ String( base ).replace( /\/$/, '' ) }/${ ENDPOINTS[ kind ](
		id
	) }`;
}

/**
 * The address of the health checks of one version of a template.
 *
 * @param {string} base       The Email Studio routes' base.
 * @param {number} templateId The template.
 * @param {number} version    Which of its versions is checked.
 * @return {string} The request URL.
 */
export function buildHealthUrl( base, templateId, version ) {
	return withQuery(
		buildEndpoint( base, 'health', templateId ),
		`version=${ encodeURIComponent( version ) }`
	);
}

/**
 * Whether a value is written as an email address. The server decides; this only spares a
 * request that it would refuse.
 *
 * @param {string} value What was typed.
 * @return {boolean} Whether it reads as an address.
 */
export function isEmailAddress( value ) {
	return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( String( value || '' ).trim() );
}

export function validateDraftForm( draft, translate = ( value ) => value ) {
	const errors = {};
	if ( ! String( draft.subject || '' ).trim() ) {
		errors.subject = translate( 'Subject is required.', 'corex' );
	}
	// Optional: a template with no From address leaves from the site's sender (spec 120).
	const from = String( draft.from_address || '' ).trim();
	if ( from !== '' && ! isEmailAddress( from ) ) {
		errors.from_address = translate(
			'The From address is not a valid email address.',
			'corex'
		);
	}
	if ( /[\r\n]/.test( String( draft.from_name || '' ) ) ) {
		errors.from_name = translate(
			'The From name cannot contain a line break.',
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

/** The draft fields the server draws a template from. */
const DRAFT_FIELDS = [
	'subject',
	'from_name',
	'from_address',
	'html_body',
	'plain_text',
	'plain_text_mode',
	'layout_id',
	'layout_version',
];

/**
 * What asks the server to draw the draft being edited, as it would be sent (spec 120).
 *
 * @param {Object} draft     The draft in the editor, saved or not.
 * @param {Object} samples   Sample values typed beside the preview, by variable.
 * @param {string} direction `rtl` for the right-to-left view, otherwise the site's own.
 * @return {Object} The request's body.
 */
export function previewRequest( draft, samples = {}, direction = 'ltr' ) {
	const body = Object.fromEntries(
		DRAFT_FIELDS.map( ( key ) => [ key, draft[ key ] ?? '' ] )
	);

	return {
		...body,
		samples,
		direction: direction === 'rtl' ? 'rtl' : '',
	};
}

/**
 * What sends the previewed email to one address.
 *
 * @param {number} templateId The template.
 * @param {Object} draft      The draft in the editor.
 * @param {Object} samples    The sample values the preview used.
 * @param {string} to         The address typed.
 * @return {Object} The request's body.
 */
export function testSendRequest( templateId, draft, samples, to ) {
	const { direction, ...preview } = previewRequest( draft, samples );

	return { ...preview, template_id: templateId, to: [ String( to ).trim() ] };
}

/**
 * The reasons a template cannot be drawn, in the order the server gave them.
 *
 * @param {Object} envelope A failed response.
 * @return {string[]} One sentence each, naming the variable or partial.
 */
export function drawingErrors( envelope ) {
	const fields = envelope?.details?.fields;
	const reasons = fields ? Object.values( fields ).filter( Boolean ) : [];

	return reasons.length > 0
		? reasons
		: [ envelope?.message || 'The template could not be drawn.' ];
}
