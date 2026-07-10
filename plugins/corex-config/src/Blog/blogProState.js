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

export function normalizeAnalytics( payload = {} ) {
	const views = numberValue( payload.views );
	const reads = numberValue( payload.reads );
	const averageReadSeconds = numberValue( payload.average_read_seconds );
	const topPosts = Array.isArray( payload.top_posts ) ? payload.top_posts : [];

	return {
		cards: {
			views,
			reads,
			shareClicks: numberValue( payload.share_clicks ),
			uniqueVisitors: numberValue( payload.unique_visitors ),
			averageReadSeconds,
			engagement: engagement( reads, views ),
		},
		chart: ( Array.isArray( payload.chart ) ? payload.chart : [] ).map( ( row ) => ( {
			date: String( row.date || '' ),
			views: numberValue( row.views ),
			reads: numberValue( row.reads ),
		} ) ),
		topPosts: topPosts.map( ( post ) => {
			const postViews = numberValue( post.views );
			const postReads = numberValue( post.reads );
			return {
				id: numberValue( post.id ),
				title: String( post.title || '' ),
				views: postViews,
				reads: postReads,
				comments: numberValue( post.comments ),
				averageReadSeconds: numberValue( post.average_read_seconds ),
				engagement: engagement( postReads, postViews ),
			};
		} ),
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

export function buildShareClickPayload( draft = {} ) {
	return {
		post_id: numberValue( draft.postId ),
		target: targetKey( draft.target ),
		visitor_key: String( draft.visitorKey || '' ),
		consented: Boolean( draft.consented ),
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
				comments: Array.isArray( action.payload?.comments ) ? action.payload.comments : [],
				authors: Array.isArray( action.payload?.authors ) ? action.payload.authors : [],
				shareControls: Array.isArray( action.payload?.shareControls ) ? action.payload.shareControls : [],
			};
		case 'transitioned':
			return {
				...state,
				editorial: action.editorial,
				notice: { tone: 'success', message: 'Editorial state updated.' },
			};
		case 'commentModerated':
			return {
				...state,
				comments: state.comments.map( ( comment ) =>
					numberValue( comment.comment_id ) === numberValue( action.commentId )
						? { ...comment, state: action.state }
						: comment
				),
				notice: { tone: 'success', message: 'Comment updated.' },
			};
		case 'shareRecorded':
			return {
				...state,
				notice: { tone: 'success', message: 'Share click recorded.' },
			};
		case 'error':
			return {
				...state,
				notice: { tone: 'error', message: action.message || 'Blog update failed.' },
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
