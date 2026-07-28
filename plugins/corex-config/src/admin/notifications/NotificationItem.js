/**
 * One notification, rendered the same way wherever it appears (spec 074, FR-4.4/FR-4.9).
 *
 * The screen and the header drawer share this component so they cannot drift — the drawer used to
 * show a shorter, differently-worded version of the same record, which meant the answer to "what
 * does this want from me" depended on where you happened to be looking.
 *
 * Everything the record already knows is shown: what happened, how serious it is, where it came
 * from, when, how many times, whether the condition is still live, and what you can do about it.
 * The old item showed a title, a body, and a "Mark read" button.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { CorexRelativeTime } from '../components/CorexTime.js';

/** Severity said in words. Colour reinforces the label; it never carries the meaning alone. */
const SEVERITY_LABELS = {
	critical: __( 'Critical', 'corex' ),
	error: __( 'Error', 'corex' ),
	warning: __( 'Warning', 'corex' ),
	action: __( 'Action', 'corex' ),
	information: __( 'Information', 'corex' ),
	success: __( 'Success', 'corex' ),
};

/**
 * One glyph per category, reusing the navigation rail's own icons.
 *
 * Reused rather than newly drawn on purpose: the same glyph then means the same area of the product
 * wherever you meet it, so a submissions notification carries the mark that is already next to
 * Submissions in the rail. Deliberately not emoji — they render differently on every platform, are
 * announced by their dictionary name, and cannot inherit the surface's colour.
 */
const CATEGORY_ICONS = {
	submissions: 'submissions',
	email: 'mail',
	jobs: 'setup',
	security: 'security',
	access: 'security',
	operations: 'security',
	readiness: 'insights',
	imports_exports: 'data',
	editorial: 'forms',
	setup: 'setup',
	system: 'settings',
};

/** Source modules, named as a person would name them rather than by module slug. */
const SOURCE_LABELS = {
	'corex-forms': __( 'Forms', 'corex' ),
	'corex-config': __( 'CoreX', 'corex' ),
	'corex-email': __( 'Email', 'corex' ),
	'corex-core': __( 'CoreX', 'corex' ),
};

function sourceLabel( module ) {
	return SOURCE_LABELS[ module ] || module;
}

/**
 * The state of the condition, in words. Never conflated with whether it has been read.
 *
 * @param {Object} item One notification row.
 * @return {string} The translated condition state.
 */
function conditionLabel( item ) {
	const status = item.user_state?.status;

	if ( status === 'resolved' ) {
		return __( 'Resolved', 'corex' );
	}
	if ( status === 'dismissed' ) {
		return __( 'Dismissed', 'corex' );
	}
	if ( status === 'expired' ) {
		return __( 'Expired', 'corex' );
	}
	if ( status === 'snoozed' ) {
		return __( 'Snoozed', 'corex' );
	}

	return item.user_state?.needs_action
		? __( 'Still needs action', 'corex' )
		: __( 'No action needed', 'corex' );
}

export default function NotificationItem( {
	item,
	actions = {},
	compact = false,
} ) {
	const severity = item.severity || 'information';
	const state = item.user_state || {};
	const occurrences = Number( item.occurrences ) || 1;
	const icon = CATEGORY_ICONS[ item.category ] || 'notifications';
	const canResolve = Boolean( item.can_resolve );
	const closed = [ 'resolved', 'dismissed', 'expired' ].includes(
		state.status
	);

	return (
		<article
			className={
				'corex-notification' +
				( state.read ? ' is-read' : ' is-unread' ) +
				( state.needs_action ? ' needs-action' : '' )
			}
			data-severity={ severity }
			data-category={ item.category }
		>
			{ /* Decorative: the category is already carried by the severity badge, the source, and
			     the title, so the glyph adds recognition rather than information. */ }
			<span
				className={ `corex-notification__icon corex-admin__nav-icon corex-admin__nav-icon--${ icon }` }
				aria-hidden="true"
			/>

			<div className="corex-notification__body">
				{ /*
				   The title is the way to the thing, when there is one. The button below says the
				   same thing, but a title that names a place is what people reach for first, and
				   the whole card is deliberately not the target: the card holds Mark read, Snooze
				   and Dismiss, and nesting those inside a link is a trap for both a pointer and a
				   screen reader (spec 087, FR-013).
				*/ }
				<p className="corex-notification__title">
					{ item.action?.url ? (
						<a href={ item.action.url }>
							{ item.rendered?.title ?? '' }
						</a>
					) : (
						item.rendered?.title ?? ''
					) }
				</p>
				<p className="corex-notification__text">
					{ item.rendered?.body ?? '' }
				</p>

				<p className="corex-notification__meta">
					<span
						className={ `corex-notification__severity is-${ severity }` }
					>
						{ SEVERITY_LABELS[ severity ] || severity }
					</span>
					<span className="corex-notification__source">
						{ sourceLabel( item.source_module ) }
					</span>
					{ item.environment ? (
						<span className="corex-notification__environment">
							{ item.environment }
						</span>
					) : null }
					{ /*
					   The exact value used to live only in a `title` attribute, alongside a
					   `dateTime` holding the same raw ISO string. A title is not openable by
					   touch and not reliably announced, so the one route to the precise moment
					   was a mouse hover (FR-013). It is text now.
					*/ }
					<CorexRelativeTime
						value={ item.latest_occurred_at }
						className="corex-notification__time"
						absent={ __( 'Time not recorded', 'corex' ) }
					/>
					{ occurrences > 1 ? (
						<span className="corex-notification__occurrences">
							{ sprintf(
								/* translators: %d: how many times this condition has occurred. */
								_n(
									'%d time',
									'%d times',
									occurrences,
									'corex'
								),
								occurrences
							) }
						</span>
					) : null }
					<span className="corex-notification__condition">
						{ conditionLabel( item ) }
					</span>
					{ ! state.read ? (
						<span className="corex-notification__unread">
							{ __( 'Unread', 'corex' ) }
						</span>
					) : null }
				</p>
			</div>

			<div className="corex-notification__actions">
				{ /*
				   `label` is the already-translated string; `label_key` beside it is a translation
				   key nothing in this pipeline resolves. The server used to send only the key, so
				   every server-produced action fell through to "Open" and the authored label was
				   never seen. "Open" survives as the floor for an action that carries neither.
				*/ }
				{ item.action?.url ? (
					<a
						className="button button-primary"
						href={ item.action.url }
					>
						{ item.action.label || __( 'Open', 'corex' ) }
					</a>
				) : null }

				{ ! compact && actions.markRead && ! state.read ? (
					<button
						type="button"
						onClick={ () => actions.markRead( item.id ) }
					>
						{ __( 'Mark read', 'corex' ) }
					</button>
				) : null }
				{ ! compact && actions.markUnread && state.read && ! closed ? (
					<button
						type="button"
						onClick={ () => actions.markUnread( item.id ) }
					>
						{ __( 'Mark unread', 'corex' ) }
					</button>
				) : null }
				{ ! compact && actions.snooze && ! closed ? (
					<button
						type="button"
						onClick={ () => actions.snooze( item.id ) }
					>
						{ __( 'Snooze for a day', 'corex' ) }
					</button>
				) : null }
				{ ! compact && actions.dismiss && ! closed ? (
					<button
						type="button"
						onClick={ () => actions.dismiss( item.id ) }
					>
						{ __( 'Dismiss', 'corex' ) }
					</button>
				) : null }
				{ /* Resolve ends the condition for everyone, so it is shown only to actors who may
				     do it — hiding beats offering a control that answers with a permission error. */ }
				{ ! compact && actions.resolve && canResolve && ! closed ? (
					<button
						type="button"
						className="is-destructive"
						onClick={ () => actions.resolve( item.id ) }
					>
						{ __( 'Mark resolved', 'corex' ) }
					</button>
				) : null }
			</div>
		</article>
	);
}
