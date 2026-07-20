import { useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { viewState } from '../dataClient.js';
import BulkBar from './BulkBar.js';
import BulkEditDialog from './BulkEditDialog.js';
import ExportDialog from './ExportDialog.js';
import MutationPreviewDialog from './MutationPreviewDialog.js';
import Pagination from './Pagination.js';
import QueryBar from './QueryBar.js';
import RecordDetail from './RecordDetail.js';
import RecordDialog from './RecordDialog.js';
import RecordsTable from './RecordsTable.js';
import Schema from './Schema.js';
import SourceRail from './SourceRail.js';
import { useDataExplorer } from './useDataExplorer.js';

export default function DataExplorer( { config } ) {
	const explorer = useDataExplorer( config );
	const [ record, setRecord ] = useState( null );
	const [ editor, setEditor ] = useState( null );
	const [ bulkEdit, setBulkEdit ] = useState( false );
	const [ exporting, setExporting ] = useState( false );
	const stateKey = viewState( {
		status: explorer.state.status,
		rowCount: explorer.state.rows.length,
		hasQuery: Boolean( explorer.state.query.search || Object.values( explorer.state.query.filters ).some( Boolean ) ),
	} );
	const openRecord = async ( row ) => {
		const detail = await explorer.detail( row.id );
		if ( detail ) setRecord( detail );
	};

	return <div className="corex-data corex-data--explorer">
		<aside className="corex-data__rail"><SourceRail explorer={ explorer } /><Schema source={ explorer.source } /></aside>
		<main className="corex-data__explorer-main">
			<div className="corex-data__metrics"><div className="corex-data__metric"><span>{ __( 'Total rows', 'corex' ) }</span><strong>{ explorer.state.total }</strong></div>
				<div className="corex-data__metric"><span>{ __( 'Fields', 'corex' ) }</span><strong>{ explorer.source?.fields?.length || 0 }</strong></div></div>
			<section className="corex-data__panel" aria-live="polite">
				<QueryBar explorer={ explorer } openCreate={ () => setEditor( {} ) } openExport={ () => setExporting( true ) }
					flows={ Array.isArray( config.flows ) ? config.flows : [] } />
				{ explorer.state.notice && <p className={ `corex-data__notice is-${ explorer.state.notice.tone }` }
					role={ explorer.state.notice.tone === 'error' ? 'alert' : 'status' }>{ explorer.state.notice.message }</p> }
				<BulkBar explorer={ explorer } edit={ () => setBulkEdit( true ) } exportRows={ () => setExporting( true ) } />
				<div className="corex-data__panel-body">
					{ stateKey === 'loading' && <p className="corex-data__loading" role="status"><Spinner /> { __( 'Loading records…', 'corex' ) }</p> }
					{ stateKey === 'error' && <div className="corex-data__error" role="alert"><p>{ explorer.state.error }</p><Button onClick={ explorer.reload }>{ __( 'Retry', 'corex' ) }</Button></div> }
					{ stateKey === 'empty' && <p className="corex-data__empty">{ __( 'No records yet.', 'corex' ) }</p> }
					{ stateKey === 'empty-filtered' && <p className="corex-data__empty">{ __( 'No records match the current query.', 'corex' ) }</p> }
					{ stateKey === 'ready' && <RecordsTable explorer={ explorer } open={ openRecord } /> }
				</div>
				<Pagination explorer={ explorer } />
			</section>
		</main>
		{ record && <RecordDetail explorer={ explorer } record={ record } close={ () => setRecord( null ) }
			edit={ () => { setEditor( record ); setRecord( null ); } } /> }
		{ editor && <RecordDialog source={ explorer.source } record={ editor.id ? editor : null } close={ () => setEditor( null ) } preview={ explorer.previewMutation } /> }
		{ bulkEdit && <BulkEditDialog source={ explorer.source } count={ explorer.state.selected.length } close={ () => setBulkEdit( false ) }
			preview={ ( fieldValues ) => explorer.previewMutation( 'bulk_update', explorer.state.selected, fieldValues ) } /> }
		{ exporting && <ExportDialog source={ explorer.source } state={ explorer.state } close={ () => setExporting( false ) } create={ explorer.createExport } /> }
		{ explorer.state.preview && <MutationPreviewDialog preview={ explorer.state.preview } pending={ explorer.state.pending }
			close={ () => explorer.dispatch( { type: 'dismiss-preview' } ) } apply={ explorer.applyMutation } /> }
	</div>;
}
