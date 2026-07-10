/**
 * Pure client helpers for the CoreX Insights screen (spec 068). Endpoint construction and
 * truthful normalization of the REST payloads (run results + aggregated recommendations) served
 * by the cap+nonce-gated InsightsController. No DOM, no network — unit-testable.
 */

export function insightsEndpoint( root, path = '' ) {
	const base = String( root || '' ).replace( /\/+$/, '' );
	const suffix = String( path || '' ).replace( /^\/+/, '' );
	return suffix === '' ? base : `${ base }/${ suffix }`;
}

function intValue( value ) {
	const parsed = Number.parseInt( value, 10 );
	return Number.isFinite( parsed ) ? parsed : 0;
}

function stringList( value ) {
	return Array.isArray( value )
		? value.map( ( item ) => String( item ) )
		: [];
}

/**
 * Map a letter grade to a display tone. Unknown grades stay neutral rather than guessing.
 *
 * @param {string} grade Letter grade (A–F).
 * @return {string} Display tone: success, warning, critical, or neutral.
 */
export function gradeTone( grade ) {
	switch ( String( grade || '' ).toUpperCase() ) {
		case 'A':
		case 'B':
			return 'success';
		case 'C':
			return 'warning';
		case 'D':
		case 'E':
		case 'F':
			return 'critical';
		default:
			return 'neutral';
	}
}

/**
 * Normalize a single provider run result. A missing/empty payload yields an honest
 * "not run yet" shape — never a fabricated score or grade.
 *
 * @param {Object|null} payload Raw run-result payload from the REST envelope.
 * @return {Object} Normalized result with a derived grade tone.
 */
export function normalizeResult( payload ) {
	if ( ! payload || typeof payload !== 'object' ) {
		return {
			ran: false,
			provider: '',
			label: '',
			score: 0,
			grade: '',
			tone: 'neutral',
			summary: '',
			recommendations: [],
			metrics: [],
			checkedAt: 0,
		};
	}

	const grade = String( payload.grade || '' );

	return {
		ran: true,
		provider: String( payload.provider || '' ),
		label: String( payload.label || '' ),
		score: intValue( payload.score ),
		grade,
		tone: gradeTone( grade ),
		status: String( payload.status || '' ),
		summary: String( payload.summary || '' ),
		recommendations: stringList( payload.recommendations ),
		metrics: Array.isArray( payload.metrics ) ? payload.metrics : [],
		checkedAt: intValue( payload.checkedAt ),
	};
}

/**
 * Normalize the aggregated recommendations payload — only entries that actually carry
 * recommendation text survive.
 *
 * @param {Array} payload Raw recommendations list from the REST envelope.
 * @return {Array} Normalized entries, each with a derived grade tone.
 */
export function normalizeRecommendations( payload ) {
	const list = Array.isArray( payload ) ? payload : [];

	return list
		.map( ( entry ) => ( {
			provider: String( entry.provider || '' ),
			label: String( entry.label || '' ),
			grade: String( entry.grade || '' ),
			tone: gradeTone( entry.grade ),
			recommendations: stringList( entry.recommendations ),
		} ) )
		.filter( ( entry ) => entry.recommendations.length > 0 );
}

/**
 * Normalize the designed Insights widget set (the `InsightWidgets` model output) into a stable
 * render-ready shape. Each widget keeps its honest state; runnable widgets keep their `mount` id
 * so the live run-check card can attach. Never fabricates a widget or a row.
 *
 * @param {Array} payload Raw widget list from the localized model / REST envelope.
 * @return {Array} Normalized widgets ready for rendering.
 */
export function normalizeWidgets( payload ) {
	const list = Array.isArray( payload ) ? payload : [];

	return list.map( ( widget ) => ( {
		key: String( widget.key || '' ),
		title: String( widget.title || '' ),
		sub: String( widget.sub || '' ),
		state: String( widget.state || '' ),
		chip: String( widget.chip || '' ),
		chipTone: String( widget.chipTone || 'subtle' ),
		mount: widget.mount ? String( widget.mount ) : null,
		note: String( widget.note || '' ),
		rows: ( Array.isArray( widget.rows ) ? widget.rows : [] ).map(
			( row ) => ( {
				label: String( row.label || '' ),
				value: String( row.value || '' ),
				tone: String( row.tone || 'subtle' ),
			} )
		),
		alt:
			widget.alt && typeof widget.alt === 'object'
				? {
						title: String( widget.alt.title || '' ),
						message: String( widget.alt.message || '' ),
						ctaLabel: String( widget.alt.ctaLabel || '' ),
						ctaHref: String( widget.alt.ctaHref || '' ),
				  }
				: null,
	} ) );
}
