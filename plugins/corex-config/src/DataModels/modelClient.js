/**
 * Each tab names the ability it actually needs.
 *
 * Records browses rows, which every data source gates on `data` (see DataSourceService). The rest
 * change the shape of the models themselves, which is `models`. The two are independent abilities:
 * neither implies the other, so a screen-wide gate on either one alone locks somebody out of work
 * they are entitled to do — Records used to gate on `models` while its sources gated on `data`,
 * so a models-only user reached an explorer that could read nothing.
 */
export const DATA_MODEL_TABS = [
	{ key: 'models', ability: 'models' },
	{ key: 'records', ability: 'data' },
	{ key: 'import', ability: 'models' },
	{ key: 'export', ability: 'models' },
	{ key: 'migrations', ability: 'models' },
];

/** The tabs this user may actually open. */
export function allowedTabs( abilities = {} ) {
	return DATA_MODEL_TABS.filter( ( tab ) => abilities[ tab.ability ] === true );
}

/**
 * Which tab to show: the requested one when it is permitted, otherwise the first that is.
 *
 * Falling back rather than rendering an empty shell matters because the Data screen's old address
 * redirects here with ?tab=records — a user who kept `data` but never had `models` must land on
 * something.
 */
export function resolveTab( requested, abilities = {} ) {
	const allowed = allowedTabs( abilities );
	if ( allowed.length === 0 ) {
		return '';
	}

	return allowed.some( ( tab ) => tab.key === requested ) ? requested : allowed[ 0 ].key;
}

/** Read the tab out of a URL so a view can be linked to and shared. */
export function tabFromUrl( url, abilities = {} ) {
	let requested = '';
	try {
		requested = new URL( String( url ), 'http://localhost' ).searchParams.get( 'tab' ) || '';
	} catch {
		requested = '';
	}

	return resolveTab( requested, abilities );
}

export function actionSources( sources, action ) {
	return ( Array.isArray( sources ) ? sources : [] ).filter(
		( source ) => Boolean( source?.actions?.[ action ]?.visible )
	);
}

export function importSummary( run ) {
	const accepted = Array.isArray( run?.accepted_rows ) ? run.accepted_rows.length : 0;
	const rejected = Array.isArray( run?.rejected_rows ) ? run.rejected_rows.length : 0;
	return {
		accepted,
		rejected,
		total: accepted + rejected,
		unknown: Array.isArray( run?.unknown_columns ) ? run.unknown_columns : [],
	};
}

export function migrationState( run ) {
	if ( ! run ) return 'pending';
	return run.state === 'rolled_back' ? 'rolled-back' : run.state;
}
