import { __ } from '@wordpress/i18n';

/**
 * Each tab names the ability it needs, and the operation some source must actually support.
 *
 * Records browses rows, which every data source gates on `data` (see DataSourceService). The rest
 * change the shape of the models themselves, which is `models`. The two are independent abilities:
 * neither implies the other, so a screen-wide gate on either one alone locks somebody out of work
 * they are entitled to do — Records used to gate on `models` while its sources gated on `data`,
 * so a models-only user reached an explorer that could read nothing.
 *
 * Permission is only half the question, though. Import and Migrations were shown to anyone holding
 * `models`, and then said "No registered model provides an import adapter" — because no registered
 * source declared the capability, and none could. A tab that leads only to that sentence is a dead
 * end wearing a workspace tab's clothes, so each capability tab now also requires at least one
 * source that genuinely supports its operation (spec 074, FR-3.1). Where a capability is missing,
 * the Models catalog is where that is explained, in words rather than in the word "adapter".
 */
export const DATA_MODEL_TABS = [
	{ key: 'models', ability: 'models' },
	{ key: 'records', ability: 'data' },
	{ key: 'import', ability: 'models', operation: 'import_dry_run' },
	{ key: 'export', ability: 'models', operation: 'export_csv' },
	{ key: 'migrations', ability: 'models', operation: 'migrations' },
];

/**
 * The tabs this user may open AND that some source can actually satisfy.
 *
 * @param {Object} abilities CoreX abilities for the current user, keyed by ability.
 * @param {Array}  sources   Catalog entries from DataSourceService::describe().
 * @return {Array} The subset of DATA_MODEL_TABS that is genuinely reachable.
 */
export function allowedTabs( abilities = {}, sources = [] ) {
	return DATA_MODEL_TABS.filter(
		( tab ) =>
			abilities[ tab.ability ] === true &&
			( ! tab.operation ||
				actionSources( sources, tab.operation ).length > 0 )
	);
}

/**
 * Which tab to show: the requested one when it is available, otherwise the first that is.
 *
 * Falling back rather than rendering an empty shell matters because the Data screen's old address
 * redirects here with ?tab=records — a user who kept `data` but never had `models` must land on
 * something, and so must anyone following a link to a tab that no longer has an eligible source.
 *
 * @param {string} requested The tab asked for, from the URL.
 * @param {Object} abilities CoreX abilities for the current user, keyed by ability.
 * @param {Array}  sources   Catalog entries from DataSourceService::describe().
 * @return {string} The tab key to render, or '' when none is available.
 */
export function resolveTab( requested, abilities = {}, sources = [] ) {
	const allowed = allowedTabs( abilities, sources );
	if ( allowed.length === 0 ) {
		return '';
	}

	return allowed.some( ( tab ) => tab.key === requested )
		? requested
		: allowed[ 0 ].key;
}

/**
 * Read the tab out of a URL so a view can be linked to and shared.
 *
 * @param {string} url       The address to read `?tab=` from.
 * @param {Object} abilities CoreX abilities for the current user, keyed by ability.
 * @param {Array}  sources   Catalog entries from DataSourceService::describe().
 * @return {string} The tab key to render, or '' when none is available.
 */
export function tabFromUrl( url, abilities = {}, sources = [] ) {
	let requested = '';
	try {
		requested =
			new URL( String( url ), 'http://localhost' ).searchParams.get(
				'tab'
			) || '';
	} catch {
		requested = '';
	}

	return resolveTab( requested, abilities, sources );
}

export function actionSources( sources, action ) {
	return ( Array.isArray( sources ) ? sources : [] ).filter( ( source ) =>
		Boolean( source?.actions?.[ action ]?.visible )
	);
}

export function importSummary( run ) {
	const accepted = Array.isArray( run?.accepted_rows )
		? run.accepted_rows.length
		: 0;
	const rejected = Array.isArray( run?.rejected_rows )
		? run.rejected_rows.length
		: 0;
	return {
		accepted,
		rejected,
		total: accepted + rejected,
		unknown: Array.isArray( run?.unknown_columns )
			? run.unknown_columns
			: [],
	};
}

export function migrationState( run ) {
	if ( ! run ) {
		return 'pending';
	}
	return run.state === 'rolled_back' ? 'rolled-back' : run.state;
}

/**
 * What a model can be used for, in the words a person would use.
 *
 * The Models catalog is where an unavailable capability gets explained, so this returns plain
 * statements rather than capability keys — "adapter" is a word for the developer diagnostics, not
 * for somebody trying to work out why they cannot import a list.
 *
 * @param {Object} source A catalog entry from DataSourceService::describe().
 * @return {Array} `{ key, label, available, explanation }` per capability.
 */
export function capabilitySummary( source ) {
	const can = ( operation ) => Boolean( source?.capabilities?.[ operation ] );

	const entry = ( key, available, label, explanation ) => ( {
		key,
		available,
		label,
		explanation,
	} );

	return [
		can( 'read' )
			? entry(
					'read',
					true,
					__( 'Readable', 'corex' ),
					__( 'You can browse and search these records.', 'corex' )
			  )
			: entry(
					'read',
					false,
					__( 'Not readable', 'corex' ),
					__(
						'This model does not expose its records for browsing.',
						'corex'
					)
			  ),
		can( 'create' ) || can( 'update' )
			? entry(
					'write',
					true,
					__( 'Editable', 'corex' ),
					__(
						'Records can be added and changed from CoreX.',
						'corex'
					)
			  )
			: entry(
					'write',
					false,
					__( 'Read-only', 'corex' ),
					__(
						'Records are written by the feature that owns them, not by hand.',
						'corex'
					)
			  ),
		can( 'import_dry_run' )
			? entry(
					'import',
					true,
					__( 'Importable', 'corex' ),
					__(
						'You can bring records in from a CSV, with a dry run first.',
						'corex'
					)
			  )
			: entry(
					'import',
					false,
					__( 'No import', 'corex' ),
					__(
						'This model does not accept imported records.',
						'corex'
					)
			  ),
		can( 'export_csv' )
			? entry(
					'export',
					true,
					__( 'Exportable', 'corex' ),
					__( 'You can download these records as a CSV.', 'corex' )
			  )
			: entry(
					'export',
					false,
					__( 'No export', 'corex' ),
					__( 'This model cannot be exported.', 'corex' )
			  ),
		can( 'migrations' )
			? entry(
					'migrations',
					true,
					__( 'Has migrations', 'corex' ),
					can( 'rollback' )
						? __(
								'Ships schema changes you can preview, apply, and roll back.',
								'corex'
						  )
						: __(
								'Ships schema changes you can preview and apply. They cannot be undone.',
								'corex'
						  )
			  )
			: entry(
					'migrations',
					false,
					__( 'No migrations', 'corex' ),
					__( 'This model ships no schema changes.', 'corex' )
			  ),
	];
}
