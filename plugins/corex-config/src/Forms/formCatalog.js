/**
 * One catalog row per form, whatever kind of form it is (spec 074, FR-1.9).
 *
 * Forms & Flows used to render the flow table alone, so the framework's own `contact` form — which
 * is registered in code — did not appear on the screen named after forms. The server hands over a
 * merged catalog; this turns it into rows the list can render, keeping the live flow records
 * authoritative for the flows they describe.
 *
 * Pure on purpose: the interesting decisions (which record wins, what a code form's state means,
 * how the filters apply to something with no lifecycle) are all testable without a DOM.
 */

export const SOURCE_VISUAL_FLOW = 'visual_flow';
export const SOURCE_CODE_FORM = 'code_form';
export const SOURCE_EXTERNAL = 'external';

/**
 * A code form is registered or it is not — there is no draft, closed, or expired. Calling that
 * "published" would borrow a lifecycle it does not have, so it gets its own state and the
 * lifecycle filter treats it as an active form.
 */
const STATE_REGISTERED = 'registered';

function asArray( value ) {
	return Array.isArray( value ) ? value : [];
}

/**
 * The live flow record, which is richer than the catalog entry for the same flow.
 *
 * @param {Object} flow A flow row from the flows REST route.
 * @return {Object} The list row for that flow.
 */
function flowRow( flow ) {
	return {
		key: `flow-${ flow.id }`,
		id: flow.id,
		slug: flow.slug,
		name: flow.name,
		source: SOURCE_VISUAL_FLOW,
		state: flow.state,
		editable: true,
		fieldCount: Number( flow.field_count ) || 0,
		routingTarget: flow.routing_target || '',
		updatedAt: flow.updated_at || '',
		fields: [],
		validationSummary: '',
		submissionCount: null,
	};
}

function catalogRow( entry ) {
	return {
		key: `form-${ entry.slug }`,
		id: null,
		slug: entry.slug,
		name: entry.label || entry.slug,
		source: entry.source,
		state: entry.active ? STATE_REGISTERED : 'inactive',
		editable: false,
		fieldCount: Number( entry.field_count ) || 0,
		routingTarget: '',
		updatedAt: '',
		fields: asArray( entry.fields ),
		validationSummary: entry.validation_summary || '',
		// null means "nobody could measure it", which is not the same fact as zero.
		submissionCount:
			entry.submission_count === null ||
			entry.submission_count === undefined
				? null
				: Number( entry.submission_count ),
	};
}

function matchesSearch( row, search ) {
	const term = String( search || '' )
		.trim()
		.toLowerCase();
	if ( ! term ) {
		return true;
	}

	return (
		row.name.toLowerCase().includes( term ) ||
		row.slug.toLowerCase().includes( term )
	);
}

/**
 * Flows are filtered by lifecycle on the server, so only the rows added here need filtering.
 * A code form has no lifecycle, so it belongs in "all states" and nowhere else — asking for
 * drafts and being shown something that can never be a draft would be a lie.
 *
 * @param {Object} row       One list row.
 * @param {string} lifecycle The requested lifecycle, or '' for all states.
 * @return {boolean} Whether the row belongs in that filter.
 */
function matchesLifecycle( row, lifecycle ) {
	return ! lifecycle || row.state === lifecycle;
}

/**
 * The catalog as the list renders it: live flow records first, then every form the flows do not
 * already account for, all sorted by name.
 *
 * @param {Array}  flows   Flow records from the REST client.
 * @param {Array}  entries Catalog entries localised by the server.
 * @param {Object} filters `{ search, lifecycle }` as the list's controls hold them.
 * @return {Array} Rows.
 */
export function catalogRows( flows, entries, filters = {} ) {
	const rows = asArray( flows ).map( flowRow );
	const claimed = new Set( rows.map( ( row ) => row.slug ) );

	asArray( entries ).forEach( ( entry ) => {
		if ( ! entry?.slug || claimed.has( entry.slug ) ) {
			return;
		}
		// A visual flow the current filter excluded must not reappear here as an unfiltered row.
		if ( entry.source === SOURCE_VISUAL_FLOW ) {
			return;
		}
		claimed.add( entry.slug );
		rows.push( catalogRow( entry ) );
	} );

	return rows
		.filter(
			( row ) =>
				row.editable ||
				( matchesSearch( row, filters.search ) &&
					matchesLifecycle( row, filters.lifecycle ) )
		)
		.sort( ( a, b ) => a.name.localeCompare( b.name ) );
}

/**
 * How many of the rendered rows cannot be opened in the visual builder.
 *
 * @param {Array} rows The rendered list rows.
 * @return {number} How many of them are read-only.
 */
export function readOnlyCount( rows ) {
	return asArray( rows ).filter( ( row ) => ! row.editable ).length;
}
