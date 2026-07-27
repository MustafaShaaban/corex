/**
 * Clearing the comment queue (spec 075, FR-3).
 *
 * The panel this replaces listed `author · state` and offered nothing, so the only way to act on a
 * comment was to leave for the native Comments screen — and `POST /blog/comments/{id}/moderate` sat
 * there with no caller in the product.
 *
 * Three actions, not five. `CommentModerationService::queue()` returns only comments held for review,
 * so there is nothing for an "unapprove" to act on, and the service's `edit` and `reply` are excluded
 * by spec 075 §10 — this screen moderates, it does not author.
 *
 * The comment's own text is shown. A queue that gives you an author and a state cannot be moderated
 * from: you would have to open every comment somewhere else to decide anything.
 */
import { __ } from '@wordpress/i18n';

const ACTIONS = [
	{ key: 'approve', label: __( 'Approve', 'corex' ), destructive: false },
	{ key: 'spam', label: __( 'Mark as spam', 'corex' ), destructive: true },
	{ key: 'trash', label: __( 'Move to trash', 'corex' ), destructive: true },
];

/** The arrival time, in the reader's own locale — ordering and age are most of the judgement. */
function arrived( iso ) {
	const parsed = Date.parse( iso );

	if ( Number.isNaN( parsed ) ) {
		return '';
	}

	return new Date( parsed ).toLocaleString();
}

export default function ModerationPanel( { comments = [], busy = false, onModerate } ) {
	if ( comments.length === 0 ) {
		// A positive state, not an absence: nothing is waiting, which is the good outcome.
		return (
			<p className="corex-blog-pro__empty">
				{ __( 'Nothing is waiting for you here.', 'corex' ) }
			</p>
		);
	}

	return (
		<ul className="corex-blog-pro__queue">
			{ comments.map( ( comment ) => (
				<li key={ comment.comment_id } className="corex-blog-pro__comment">
					<p className="corex-blog-pro__comment-meta">
						<strong>{ comment.author || __( 'Anonymous', 'corex' ) }</strong>
						<span>{ comment.state_label }</span>
						{ comment.submitted_at ? (
							<time dateTime={ comment.submitted_at }>
								{ arrived( comment.submitted_at ) }
							</time>
						) : null }
						{ comment.likely_spam ? (
							<span className="corex-blog-pro__flag">
								{ __( 'Looks like spam', 'corex' ) }
							</span>
						) : null }
						{ comment.first_comment ? (
							<span className="corex-blog-pro__flag">
								{ __( 'First comment from this person', 'corex' ) }
							</span>
						) : null }
					</p>

					<p className="corex-blog-pro__comment-body">{ comment.content }</p>

					{ /* Hidden rather than shown and refused, from the same capability the route
					     enforces (DECISIONS #159). */ }
					{ comment.can_moderate ? (
						<p className="corex-blog-pro__comment-actions">
							{ ACTIONS.map( ( action ) => (
								<button
									key={ action.key }
									type="button"
									className={ action.destructive ? 'is-destructive' : '' }
									data-corex-blog-moderate={ action.key }
									disabled={ busy }
									onClick={ () => onModerate( comment.comment_id, action.key ) }
								>
									{ action.label }
								</button>
							) ) }
						</p>
					) : null }
				</li>
			) ) }
		</ul>
	);
}
