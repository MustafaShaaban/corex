import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import DataExplorer from '../admin/data/DataExplorer.js';
import { normalizeCatalog } from '../admin/dataClient.js';
import ExportPanel from './ExportPanel.js';
import ImportPanel from './ImportPanel.js';
import MigrationsPanel from './MigrationsPanel.js';
import ModelsPanel from './ModelsPanel.js';
import { DATA_MODEL_TABS } from './modelClient.js';

function tabLabel( tab ) {
	const labels = {
		models: __( 'Models', 'corex' ),
		records: __( 'Records', 'corex' ),
		import: __( 'Import', 'corex' ),
		export: __( 'Export', 'corex' ),
		migrations: __( 'Migrations', 'corex' ),
	};

	return labels[ tab ];
}

export default function DataModelsApp( { config } ) {
	const [ tab, setTab ] = useState( 'models' );
	const sources = useMemo( () => normalizeCatalog( config.sources ), [ config.sources ] );

	return <div className="corex-data-models__app">
		<nav className="corex-data-models__tabs" aria-label={ __( 'Data model sections', 'corex' ) }>
			{ DATA_MODEL_TABS.map( ( item ) => <button key={ item.key } type="button"
				className={ tab === item.key ? 'is-active' : undefined }
				aria-current={ tab === item.key ? 'page' : undefined }
				onClick={ () => setTab( item.key ) }>{ tabLabel( item.key ) }</button> ) }
		</nav>
		{ tab === 'models' && <ModelsPanel sources={ sources } /> }
		{ tab === 'records' && <DataExplorer config={ config } /> }
		{ tab === 'import' && <ImportPanel config={ config } sources={ sources } /> }
		{ tab === 'export' && <ExportPanel config={ config } sources={ sources } /> }
		{ tab === 'migrations' && <MigrationsPanel config={ config } sources={ sources } /> }
	</div>;
}
