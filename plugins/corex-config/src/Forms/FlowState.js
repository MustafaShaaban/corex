/**
 * The flow's state as the editor header says it (spec 122, US-FLW-01): a labelled chip, and the
 * live version with when and by whom it was published.
 */
import { __, sprintf } from '@wordpress/i18n';
import { formatDateTime } from '../admin/adminDateTime.js';
import { displayState } from './flowEditor.js';

const STATE_LABELS = {
	draft: __( 'Draft', 'corex' ),
	published: __( 'Published', 'corex' ),
	scheduled: __( 'Scheduled', 'corex' ),
	closed: __( 'Closed', 'corex' ),
	expired: __( 'Expired', 'corex' ),
};

/**
 * A state's name in words.
 *
 * @param {string} state A state from `displayState()`.
 * @return {string} Its translated name.
 */
export function stateName( state ) {
	return STATE_LABELS[ state ] || state;
}

/**
 * @param {Object} props
 * @param {Object} props.flow The flow.
 * @return {import('react').ReactElement} The chip: the state in words, never by colour alone.
 */
export function FlowStateChip( { flow } ) {
	const state = displayState( flow );

	return (
		<span className={ `corex-flow-list__state is-${ state }` }>
			{ stateName( state ) }
		</span>
	);
}

/**
 * The live line: "Live: version 3, published 21 October 2026 at 10:00 by Sara Ahmed", or why a
 * published flow is not taking answers ("Opens on …", "Closed on …").
 *
 * @param {Object} flow The flow, with its `publications` (newest first) and `status`.
 * @return {string} The line, or '' for a flow that was never published.
 */
export function liveLine( flow ) {
	const reason = flow?.status?.label || '';
	if ( ! flow?.published_version ) {
		return reason;
	}
	const live = ( flow.publications || [] ).find(
		( publication ) => publication.version === flow.published_version
	);
	const when = formatDateTime(
		live?.at || flow.published_at,
		'exact',
		''
	).human;
	const line = live?.by_name
		? sprintf(
				/* translators: 1: version number, 2: date and time, 3: a person's name. */
				__( 'Live: version %1$d, published %2$s by %3$s', 'corex' ),
				flow.published_version,
				when,
				live.by_name
			)
		: sprintf(
				/* translators: 1: version number, 2: date and time. */
				__( 'Live: version %1$d, published %2$s', 'corex' ),
				flow.published_version,
				when
			);

	return reason ? `${ line }. ${ reason }` : line;
}

/**
 * @param {Object} props
 * @param {Object} props.flow The flow.
 * @return {import('react').ReactElement|null} The live line, when there is one.
 */
export function LiveLine( { flow } ) {
	const line = liveLine( flow );

	return line ? <p className="corex-flow-editor__live">{ line }</p> : null;
}
