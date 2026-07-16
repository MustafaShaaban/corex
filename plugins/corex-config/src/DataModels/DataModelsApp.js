import { useCallback, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import DataExplorer from '../admin/data/DataExplorer.js';
import { normalizeCatalog } from '../admin/dataClient.js';
import ExportPanel from './ExportPanel.js';
import ImportPanel from './ImportPanel.js';
import MigrationsPanel from './MigrationsPanel.js';
import ModelsPanel from './ModelsPanel.js';
import { allowedTabs, tabFromUrl } from './modelClient.js';

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

/**
 * Keep the address bar in step with the visible tab.
 *
 * Tabs were component state only, so no view here could be linked to, bookmarked, or reopened —
 * and the retired Data screen redirects to ?tab=records, which needs this to land anywhere.
 * replaceState rather than pushState: switching tabs is not a navigation to walk back through.
 */
function syncTabToUrl( tab ) {
	if ( typeof window === 'undefined' || ! window.history?.replaceState ) {
		return;
	}

	const url = new URL( window.location.href );
	url.searchParams.set( 'tab', tab );
	window.history.replaceState( {}, '', url );
}

export default function DataModelsApp( { config } ) {
	const abilities = config.abilities || {};
	const tabs = useMemo( () => allowedTabs( abilities ), [ abilities ] );
	const sources = useMemo( () => normalizeCatalog( config.sources ), [ config.sources ] );
	const [ tab, setTab ] = useState( () =>
		tabFromUrl( typeof window === 'undefined' ? '' : window.location.href, abilities )
	);

	const selectTab = useCallback( ( next ) => {
		setTab( next );
		syncTabToUrl( next );
	}, [] );

	if ( tabs.length === 0 ) {
		return <div className="corex-data-models__app">
			<p className="corex-admin__description">
				{ __( 'You do not have permission to view data models or records.', 'corex' ) }
			</p>
		</div>;
	}

	return <div className="corex-data-models__app">
		<nav className="corex-data-models__tabs" aria-label={ __( 'Data model sections', 'corex' ) }>
			{ tabs.map( ( item ) => <button key={ item.key } type="button"
				className={ tab === item.key ? 'is-active' : undefined }
				aria-current={ tab === item.key ? 'page' : undefined }
				onClick={ () => selectTab( item.key ) }>{ tabLabel( item.key ) }</button> ) }
		</nav>
		{ tab === 'models' && <ModelsPanel sources={ sources } /> }
		{ tab === 'records' && <DataExplorer config={ config } /> }
		{ tab === 'import' && <ImportPanel config={ config } sources={ sources } /> }
		{ tab === 'export' && <ExportPanel config={ config } sources={ sources } /> }
		{ tab === 'migrations' && <MigrationsPanel config={ config } sources={ sources } /> }
	</div>;
}
