export const DATA_MODEL_TABS = [
	{ key: 'models' },
	{ key: 'records' },
	{ key: 'import' },
	{ key: 'export' },
	{ key: 'migrations' },
];

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
