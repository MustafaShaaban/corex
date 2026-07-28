/**
 * CorexErrorState — the one way a React admin surface says something went wrong (spec 083, FR-011;
 * spec 079's unbuilt FR-022).
 *
 * Four scales, because a failure is not one size. A field that would not validate, an action that
 * would not complete, a panel whose data would not load and a screen that would not mount are the
 * same event at four magnitudes, and before this they were written ad hoc at each site — which is
 * how a screen ends up shouting about a failed poll and whispering about a lost save.
 *
 * What the scale changes is prominence and how much room the message gets. What it never changes is
 * the contract: say what happened, in words that mean something to the person reading, and offer a
 * way forward only when there genuinely is one. A retry button on something that cannot be retried
 * is worse than no button, because it costs a click to learn the same thing again.
 *
 * `role="alert"` at field and action scale, where the failure is the consequence of something the
 * person just did and needs announcing. Panel and page are the surface they are already looking at,
 * so they are `role="status"` — announced, but not interrupting.
 */
import { __ } from '@wordpress/i18n';

/** Prominence, not meaning. Every scale says the same kind of thing at a different volume. */
const SCALES = [ 'field', 'action', 'panel', 'page' ];

/** The scales where the message is a consequence of a just-taken action, so it interrupts. */
const ASSERTIVE_SCALES = [ 'field', 'action' ];

/**
 * One way forward.
 *
 * A destination is an anchor and a behaviour is a button, rather than a button that navigates:
 * middle-click, "open in new tab" and "copy link" all work on the first and none work on the
 * second, and a keyboard user is told which one they are on.
 *
 * @param {Object} props        Component props.
 * @param {Object} props.action `{ label, href, onClick, primary }`.
 * @return {Element} The rendered control.
 */
function ErrorAction( { action } ) {
	const className = `button${ action.primary ? ' button-primary' : '' }`;

	if ( action.href ) {
		return (
			<a className={ className } href={ action.href }>
				{ action.label }
			</a>
		);
	}

	return (
		<button
			type="button"
			className={ className }
			onClick={ action.onClick }
		>
			{ action.label }
		</button>
	);
}

export default function CorexErrorState( {
	// What happened, in the reader's terms. Required at panel and page scale; at field and action
	// scale the message alone is usually the whole story.
	title,
	message,
	// Whatever the server said, kept verbatim below our own words. Shown as text, never as markup:
	// this is the one part of the component whose content CoreX did not write.
	detail,
	scale = 'panel',
	// Offered only when it can actually help. `onRetry` without a handler renders nothing rather
	// than a button that does nothing.
	onRetry,
	retryLabel = __( 'Try again', 'corex' ),
	// Anything else worth offering: [ { label, href, onClick, primary } ].
	actions = [],
	children,
} ) {
	const level = SCALES.includes( scale ) ? scale : 'panel';
	const assertive = ASSERTIVE_SCALES.includes( level );

	const hasRetry = typeof onRetry === 'function';
	const showTitle =
		Boolean( title ) && ( level === 'panel' || level === 'page' );

	return (
		<div
			className={ `corex-error-state corex-error-state--${ level }` }
			role={ assertive ? 'alert' : 'status' }
			aria-live={ assertive ? 'assertive' : 'polite' }
		>
			{ ( level === 'panel' || level === 'page' ) && (
				<span className="corex-error-state__icon" aria-hidden="true">
					<svg
						viewBox="0 0 24 24"
						fill="none"
						stroke="currentColor"
						strokeWidth="1.8"
						strokeLinecap="round"
						strokeLinejoin="round"
						focusable="false"
					>
						<circle cx="12" cy="12" r="9" />
						<path d="M12 8v5" />
						<path d="M12 16.5h.01" />
					</svg>
				</span>
			) }

			{ showTitle && (
				<p className="corex-error-state__title">{ title }</p>
			) }

			{ message && (
				<p className="corex-error-state__message">{ message }</p>
			) }

			{ detail && (
				<p className="corex-error-state__detail">{ detail }</p>
			) }

			{ children }

			{ ( hasRetry || actions.length > 0 ) && (
				<div className="corex-error-state__actions">
					{ hasRetry && (
						<button
							type="button"
							className="button"
							onClick={ onRetry }
						>
							{ retryLabel }
						</button>
					) }
					{ actions.map( ( action ) => (
						<ErrorAction key={ action.label } action={ action } />
					) ) }
				</div>
			) }
		</div>
	);
}
