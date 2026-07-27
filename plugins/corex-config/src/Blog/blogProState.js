export function blogEndpoint( root, path ) {
	const base = String( root || '' ).replace( /\/+$/, '' );
	const suffix = String( path || '' ).replace( /^\/+/, '' );
	return `${ base }/${ suffix }`;
}

function numberValue( value ) {
	const parsed = Number( value );
	return Number.isFinite( parsed ) ? parsed : 0;
}

function engagement( reads, views ) {
	return views > 0 ? Math.round( ( reads / views ) * 1000 ) / 10 : 0;
}

/**
 * The aggregate, in the shape the panel renders.
 *
 * It used to also shape a `chart` series and a `topPosts` list. Nothing on the server has ever sent
 * either — `BlogAnalyticsService::aggregate()` returns one post's counts for one window — so both were
 * permanently empty, and displaying them would have needed analytics capability spec 075 §10 excludes.
 * Shaping data that never arrives is the same dead-code defect as a reducer nothing dispatches to,
 * just quieter, so they are gone rather than carried (spec 075, T052).
 *
 * @param {Object} payload The `/blog/analytics` response body.
 * @return {Object} The card values the screen renders.
 */
export function normalizeAnalytics( payload = {} ) {
	const views = numberValue( payload.views );
	const reads = numberValue( payload.reads );

	return {
		cards: {
			views,
			reads,
			shareClicks: numberValue( payload.share_clicks ),
			uniqueVisitors: numberValue( payload.unique_visitors ),
			averageReadSeconds: numberValue( payload.average_read_seconds ),
			engagement: engagement( reads, views ),
		},
	};
}

export function buildTransitionPayload( draft = {} ) {
	return {
		state: targetKey( draft.state ),
		assignee_id: numberValue( draft.assigneeId ),
		due_at: String( draft.dueAt || '' ),
		scheduled_at: String( draft.scheduledAt || '' ),
		note: String( draft.note || '' ).trim(),
	};
}

export function initialBlogState() {
	return {
		analytics: normalizeAnalytics(),
		editorial: null,
		comments: [],
		authors: [],
		shareControls: [],
		notice: null,
		status: 'idle',
	};
}

export function blogReducer( state, action ) {
	switch ( action.type ) {
		case 'loaded':
			return {
				...state,
				status: 'ready',
				analytics: normalizeAnalytics( action.payload?.analytics ),
				editorial: action.payload?.editorial || null,
				comments: Array.isArray( action.payload?.comments )
					? action.payload.comments
					: [],
				authors: Array.isArray( action.payload?.authors )
					? action.payload.authors
					: [],
				shareControls: Array.isArray( action.payload?.shareControls )
					? action.payload.shareControls
					: [],
			};
		case 'transitioned':
			return {
				...state,
				editorial: action.editorial,
				notice: {
					tone: 'success',
					message: 'Editorial state updated.',
				},
			};
		case 'commentModerated':
			return {
				...state,
				comments: state.comments.map( ( comment ) =>
					numberValue( comment.comment_id ) ===
					numberValue( action.commentId )
						? { ...comment, state: action.state }
						: comment
				),
				notice: { tone: 'success', message: 'Comment updated.' },
			};
		// There was a `shareRecorded` case here, and a `buildShareClickPayload` above. Both are gone:
		// `POST /blog/share-click` records that a *visitor* shared a post, and the only caller that
		// could honestly make that claim is the visitor-facing surface, which spec 075 §10 excludes.
		// Firing it from the admin screen would have written analytics nobody generated — a button
		// that reports a click that never happened is worse than no button (spec 075, T053).
		case 'error':
			return {
				...state,
				notice: {
					tone: 'error',
					message: action.message || 'Blog update failed.',
				},
			};
		default:
			return state;
	}
}

function targetKey( target ) {
	return String( target || '' )
		.trim()
		.toLowerCase()
		.replace( /[^a-z0-9_-]+/g, '-' )
		.replace( /^-+|-+$/g, '' );
}
