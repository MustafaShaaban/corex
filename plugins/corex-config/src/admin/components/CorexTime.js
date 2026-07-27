/**
 * The one way a CoreX React screen renders a date (spec 076, FR-012/FR-013).
 *
 * Two things it exists to make automatic:
 *
 *   - **Semantic markup, always.** A visible date is a `<time>` whose `datetime` holds the machine
 *     value. When there is no instant, it is a `<span>` instead — a `<time>` with an empty or
 *     invented `datetime` tells assistive technology and every parser that a machine-readable date
 *     is present when it is not, which is worse than not marking it up at all.
 *   - **A relative time whose exact value is reachable.** The surface this replaces put the exact
 *     timestamp in a `title` attribute, which a touch user cannot open and a screen reader does not
 *     reliably announce. Here the exact value is real text.
 */
import { formatDateTime } from '../adminDateTime.js';

/**
 * A formatted date.
 *
 * @param {Object}             props             Component props.
 * @param {number|string|null} props.value       The stored timestamp.
 * @param {string}             [props.kind]      full | date | time | relative | exact.
 * @param {string}             [props.absent]    What to say when there is no instant. The caller
 *                                               chooses: "Never", "No expiry" and "Not recorded"
 *                                               are different statements, and only the field
 *                                               knows which it means.
 * @param {string}             [props.className] Class for the rendered element.
 * @return {Element} The date, marked up semantically.
 */
export default function CorexTime( {
	value,
	kind = 'full',
	absent = '',
	className = '',
} ) {
	const { human, machine, isPresent } = formatDateTime( value, kind, absent );

	if ( ! isPresent ) {
		return <span className={ className || undefined }>{ human }</span>;
	}

	return (
		<time dateTime={ machine } className={ className || undefined }>
			{ human }
		</time>
	);
}

/**
 * A relative time — "2 hours ago" — with the exact date beside it rather than behind a hover.
 *
 * The exact value is rendered as its own element, so it is announced in reading order, reachable on
 * a touch device, and selectable. Screens that want it visually quieter style
 * `.corex-time__exact`; screens that want it available only to assistive technology can pass
 * `exactClassName="screen-reader-text"`, which is still a route that does not require a pointer.
 *
 * @param {Object}             props                  Component props.
 * @param {number|string|null} props.value            The stored timestamp.
 * @param {string}             [props.absent]         What to say when there is no instant.
 * @param {string}             [props.className]      Class for the relative element.
 * @param {string}             [props.exactClassName] Class for the exact value beside it.
 * @return {Element} The relative time, with its exact value present.
 */
export function CorexRelativeTime( {
	value,
	absent = '',
	className = '',
	exactClassName = 'corex-time__exact',
} ) {
	const relative = formatDateTime( value, 'relative', absent );

	if ( ! relative.isPresent ) {
		return (
			<span className={ className || undefined }>{ relative.human }</span>
		);
	}

	const exact = formatDateTime( value, 'full', absent );

	return (
		<>
			<time
				dateTime={ relative.machine }
				className={ className || undefined }
			>
				{ relative.human }
			</time>{ ' ' }
			<span className={ exactClassName || undefined }>
				{ exact.human }
			</span>
		</>
	);
}
