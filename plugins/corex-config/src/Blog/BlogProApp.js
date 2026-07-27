/**
 * Blog Pro — the editorial workspace (spec 075).
 *
 * What this replaces: a screen that rendered five cards of raw values and offered not one control.
 * It called `useReducer` and discarded the dispatch, so `blogProState.js` — the reducer's outcome
 * cases, the payload builders, the endpoint helper — was unreachable while fully covered by tests,
 * and the seven REST routes had no caller in the product at all.
 *
 * Two things it now refuses to do:
 *
 * 1. **Imply a total it does not have.** Every panel is about one post, and says which. The old
 *    screen computed everything for `$posts[0]` and titled it "First-party reading signals", which
 *    reads as the whole site.
 * 2. **Print a slug.** States arrive as `{ key, label }` from the server, which owns the vocabulary
 *    (spec 075, FR-2).
 */
import { useCallback, useReducer, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import CorexSelect from '../admin/components/CorexSelect.js';
import EditorialPanel from './EditorialPanel.js';
import ModerationPanel from './ModerationPanel.js';
import {
	loadBlogData,
	moderateComment,
	transitionPost,
} from './blogProClient.js';
import {
	blogReducer,
	initialBlogState,
	normalizeAnalytics,
} from './blogProState.js';

/**
 * Seconds, said the way a person would say them.
 *
 * @param {number} seconds An average read time.
 * @return {string} That duration in translated words.
 */
function readingTime( seconds ) {
	if ( seconds < 60 ) {
		return sprintf(
			/* translators: %d: a number of seconds. */
			_n( '%d second', '%d seconds', seconds, 'corex' ),
			seconds
		);
	}

	const minutes = Math.round( seconds / 60 );

	return sprintf(
		/* translators: %d: a number of minutes. */
		_n( '%d minute', '%d minutes', minutes, 'corex' ),
		minutes
	);
}

export default function BlogProApp( { config = {} } ) {
	const posts = Array.isArray( config.posts ) ? config.posts : [];
	const [ state, dispatch ] = useReducer( blogReducer, {
		...initialBlogState(),
		status: 'ready',
		analytics: normalizeAnalytics( config.analytics || {} ),
		editorial: config.editorial || null,
		comments: Array.isArray( config.comments ) ? config.comments : [],
		authors: Array.isArray( config.authors ) ? config.authors : [],
		shareControls: Array.isArray( config.shareControls )
			? config.shareControls
			: [],
	} );
	const [ busy, setBusy ] = useState( false );

	const hasAnalytics = Boolean( config.analytics?.has_data );
	const selectedPost = config.selectedPost || null;

	/**
	 * Pull the server's answer back after something changed.
	 *
	 * The reducer's `loaded` case had no caller either; this is it.
	 */
	const refresh = useCallback( async () => {
		setBusy( true );
		try {
			const payload = await loadBlogData( config, config.selectedPostId );
			dispatch( { type: 'loaded', payload } );
		} catch ( failure ) {
			dispatch( { type: 'error', message: failure.message } );
		} finally {
			setBusy( false );
		}
	}, [ config ] );

	/**
	 * Move the post, then pull everything else back.
	 *
	 * The transition's own response carries the new editorial item — nothing else can, since no GET
	 * route returns one — so `transitioned` takes it directly and the refresh fills in the panels the
	 * move may also have changed.
	 */
	const applyTransition = useCallback(
		async ( payload ) => {
			setBusy( true );
			try {
				const editorial = await transitionPost(
					config,
					config.selectedPostId,
					payload
				);
				dispatch( { type: 'transitioned', editorial } );
				await refresh();
			} catch ( failure ) {
				dispatch( { type: 'error', message: failure.message } );
			} finally {
				setBusy( false );
			}
		},
		[ config, refresh ]
	);

	const applyModeration = useCallback(
		async ( commentId, action ) => {
			setBusy( true );
			try {
				const result = await moderateComment(
					config,
					commentId,
					action
				);
				dispatch( {
					type: 'commentModerated',
					commentId,
					state: result?.state ?? action,
				} );
				// The server decides what is left in the queue — a trashed comment leaves it
				// entirely, which a local edit of one row cannot express.
				await refresh();
			} catch ( failure ) {
				dispatch( { type: 'error', message: failure.message } );
			} finally {
				setBusy( false );
			}
		},
		[ config, refresh ]
	);

	/**
	 * Choosing a post *is* navigating to it.
	 *
	 * The URL is the selection, so a view can be linked and reloaded, and the server renders the whole
	 * payload for it — which it alone can do, since no GET route returns a post's editorial item
	 * (spec 075, FR-1).
	 */
	const choosePost = useCallback( ( id ) => {
		const url = new URL( window.location.href );
		url.searchParams.set( 'post', String( id ) );
		window.location.assign( url.toString() );
	}, [] );

	if ( posts.length === 0 ) {
		return (
			<div className="corex-blog-pro-app">
				<p className="corex-blog-pro__empty">
					{ __(
						'Create a WordPress post to start using Blog Pro workflows.',
						'corex'
					) }
				</p>
			</div>
		);
	}

	return (
		<div className="corex-blog-pro-app">
			<div className="corex-blog-pro__subject">
				<div
					className="corex-blog-pro__chooser"
					data-corex-blog-post-selector
				>
					<CorexSelect
						label={ __( 'Post', 'corex' ) }
						value={ String( config.selectedPostId || '' ) }
						options={ posts.map( ( post ) => ( {
							value: String( post.id ),
							label: post.title || __( 'Untitled post', 'corex' ),
						} ) ) }
						onChange={ ( next ) => choosePost( next ) }
					/>
				</div>
				<p className="corex-blog-pro__subject-meta">
					{ selectedPost?.status_label }
					{ ' · ' }
					{ sprintf(
						/* translators: %d: number of days the figures cover. */
						_n(
							'Figures cover the last %d day',
							'Figures cover the last %d days',
							config.periodDays || 30,
							'corex'
						),
						config.periodDays || 30
					) }
				</p>
				<button
					type="button"
					className="corex-blog-pro__refresh"
					data-corex-blog-refresh
					onClick={ refresh }
					disabled={ busy }
				>
					{ busy
						? __( 'Refreshing…', 'corex' )
						: __( 'Refresh', 'corex' ) }
				</button>
			</div>

			{ /* The notice the reducer has always built and nothing has ever shown. */ }
			{ state.notice ? (
				<p
					className={ `corex-blog-pro__notice is-${ state.notice.tone }` }
					role={ state.notice.tone === 'error' ? 'alert' : 'status' }
				>
					{ state.notice.message }
				</p>
			) : null }

			<section className="corex-blog-pro__panel">
				<h2>
					{ sprintf(
						/* translators: %s: the post title. */
						__( 'Reading signals for “%s”', 'corex' ),
						selectedPost?.title || __( 'this post', 'corex' )
					) }
				</h2>

				{ hasAnalytics ? (
					<div className="corex-blog-pro__stats">
						<Metric
							label={ __( 'Views', 'corex' ) }
							value={ state.analytics.cards.views }
						/>
						<Metric
							label={ __( 'Reads', 'corex' ) }
							value={ state.analytics.cards.reads }
						/>
						<Metric
							label={ __( 'Share clicks', 'corex' ) }
							value={ state.analytics.cards.shareClicks }
						/>
						<Metric
							label={ __( 'Unique visitors', 'corex' ) }
							value={ state.analytics.cards.uniqueVisitors }
						/>
						<Metric
							label={ __( 'Average read time', 'corex' ) }
							value={ readingTime(
								state.analytics.cards.averageReadSeconds
							) }
						/>
						<Metric
							label={ __( 'Read rate', 'corex' ) }
							value={ sprintf(
								/* translators: %s: a percentage, e.g. 36.7. */
								__( '%s%%', 'corex' ),
								state.analytics.cards.engagement
							) }
						/>
					</div>
				) : (
					// Not the same as zero: a zero says nobody read it, this says nothing has been
					// recorded at all. The old panel showed four large zeros for both (FR-4).
					<p className="corex-blog-pro__empty">
						{ __(
							'No reading data yet — CoreX has not recorded a visit to this post in this period.',
							'corex'
						) }
					</p>
				) }
			</section>

			<section className="corex-blog-pro__grid">
				<Card title={ __( 'Editorial workflow', 'corex' ) }>
					<EditorialPanel
						editorial={ state.editorial }
						busy={ busy }
						onTransition={ applyTransition }
					/>
				</Card>

				<Card
					title={ sprintf(
						/* translators: %d: number of comments awaiting review. */
						_n(
							'%d comment awaiting review',
							'%d comments awaiting review',
							state.comments.length,
							'corex'
						),
						state.comments.length
					) }
				>
					<ModerationPanel
						comments={ state.comments }
						busy={ busy }
						onModerate={ applyModeration }
					/>
				</Card>

				<Card title={ __( 'Authors', 'corex' ) }>
					<ul className="corex-blog-pro__list">
						{ state.authors.map( ( author, index ) => (
							<li key={ author.id || author.name || index }>
								<strong>{ author.name }</strong>
								<span>
									{ sprintf(
										/* translators: %d: number of published posts. */
										_n(
											'%d published post',
											'%d published posts',
											Number( author.post_count ) || 0,
											'corex'
										),
										Number( author.post_count ) || 0
									) }
								</span>
							</li>
						) ) }
					</ul>
				</Card>

				<Card title={ __( 'Sharing', 'corex' ) }>
					<ul className="corex-blog-pro__list">
						{ state.shareControls.map( ( control ) => (
							<li key={ control.target }>
								<strong>{ control.label }</strong>
							</li>
						) ) }
					</ul>
				</Card>
			</section>
		</div>
	);
}

function Metric( { label, value } ) {
	return (
		<div className="corex-blog-pro__metric">
			<span>{ label }</span>
			<strong>{ value }</strong>
		</div>
	);
}

function Card( { title, children } ) {
	return (
		<div className="corex-blog-pro__card">
			<h3>{ title }</h3>
			{ children }
		</div>
	);
}
