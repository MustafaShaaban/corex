import { useState } from '@wordpress/element';
import CorexErrorState from '../components/CorexErrorState.js';
import CorexLoadable from '../components/CorexLoadable.js';
import CorexSkeleton, { SkeletonBar } from '../components/CorexSkeleton.js';
import { __ } from '@wordpress/i18n';
import { viewState } from '../dataClient.js';
import BulkBar from './BulkBar.js';
import BulkEditDialog from './BulkEditDialog.js';
import DataExportDialog from '../../DataModels/DataExportDialog.js';
import MutationPreviewDialog from './MutationPreviewDialog.js';
import Pagination from './Pagination.js';
import QueryBar from './QueryBar.js';
import RecordDetail from './RecordDetail.js';
import RecordDialog from './RecordDialog.js';
import RecordsSkeleton from './RecordsSkeleton.js';
import RecordsTable from './RecordsTable.js';
import Schema from './Schema.js';
import SourceRail from './SourceRail.js';
import { useDataExplorer } from './useDataExplorer.js';

/** The list's own states, as the four a loadable surface has. An empty source is an answer. */
const LOADABLE_STATUS = {
	loading: 'loading',
	refreshing: 'refreshing',
	error: 'error',
	empty: 'ready',
	'empty-filtered': 'ready',
	ready: 'ready',
};

/**
 * Nothing has been asked yet, and something is about to be: the sources are still being read,
 * or a source is chosen and its rows have not been asked for. That is loading. It used to be
 * read as an empty source, and "No records yet." was drawn about a request nobody had sent.
 *
 * @param {Object} explorer The Data hook's value.
 * @return {boolean} Whether the list is waiting for its first answer.
 */
function awaitsFirstAnswer( explorer ) {
	return (
		explorer.state.status === 'idle' &&
		( Boolean( explorer.source ) || explorer.catalogStatus === 'loading' )
	);
}

export default function DataExplorer( { config } ) {
	const explorer = useDataExplorer( config );
	// The record whose detail is open: its id from the press, and the record itself when it
	// has arrived. The dialog opens on the press; it used to open when the record did.
	const [ open, setOpen ] = useState( null );
	const [ editor, setEditor ] = useState( null );
	const [ bulkEdit, setBulkEdit ] = useState( false );
	const [ exporting, setExporting ] = useState( false );
	const stateKey = viewState( {
		status: awaitsFirstAnswer( explorer )
			? 'loading'
			: explorer.state.status,
		rowCount: explorer.state.rows.length,
		hasQuery: Boolean(
			explorer.state.query.search ||
			Object.values( explorer.state.query.filters ).some( Boolean )
		),
	} );
	const openRecord = async ( row ) => {
		setOpen( { id: row.id, record: null } );
		const record = await explorer.detail( row.id );
		setOpen( ( current ) => {
			// Closed, or another record opened, while this one was on its way.
			if ( current?.id !== row.id ) {
				return current;
			}
			// One that could not be read closes the dialog; the notice says why.
			return record ? { id: row.id, record } : null;
		} );
	};

	if ( explorer.catalogStatus === 'error' && ! explorer.source ) {
		return (
			<CorexErrorState
				scale="panel"
				title={ __( 'The data sources could not be loaded', 'corex' ) }
				message={ __(
					'The request did not complete. Nothing has been changed.',
					'corex'
				) }
				onRetry={ explorer.loadCatalog }
			/>
		);
	}

	return (
		<div className="corex-data corex-data--explorer">
			<aside className="corex-data__rail">
				<SourceRail explorer={ explorer } />
				<Schema source={ explorer.source } />
			</aside>
			<main className="corex-data__explorer-main">
				<div
					className="corex-data__metrics"
					data-corex-waiting={
						stateKey === 'refreshing' ? 'true' : undefined
					}
				>
					{ /* A label over its number. Both were bare inline elements, so the
					     tile read "Fields4": the classes the stylesheet has for the two
					     were never put on them. Each is a block of its own line height,
					     which is also what lets a placeholder stand in for the number
					     without making the tile taller. */ }
					<div className="corex-data__metric">
						<p className="corex-data__metric-label">
							{ __( 'Total rows', 'corex' ) }
						</p>
						<p className="corex-data__metric-value">
							{ /* A total of 0 before the first answer is a number nobody
							     counted. */ }
							{ stateKey === 'loading' ? (
								<CorexSkeleton as="span">
									<SkeletonBar width="short" />
								</CorexSkeleton>
							) : (
								explorer.state.total
							) }
						</p>
					</div>
					<div className="corex-data__metric">
						<p className="corex-data__metric-label">
							{ __( 'Fields', 'corex' ) }
						</p>
						<p className="corex-data__metric-value">
							{ explorer.source?.fields?.length || 0 }
						</p>
					</div>
				</div>
				<section className="corex-data__panel" aria-live="polite">
					<QueryBar
						// What is typed in the search box belongs to the source it was
						// typed for.
						key={ explorer.state.sourceKey }
						explorer={ explorer }
						openCreate={ () => setEditor( {} ) }
						openExport={ () => setExporting( true ) }
						flows={
							Array.isArray( config.flows ) ? config.flows : []
						}
					/>
					{ explorer.state.notice && (
						<p
							className={ `corex-data__notice is-${ explorer.state.notice.tone }` }
							role={
								explorer.state.notice.tone === 'error'
									? 'alert'
									: 'status'
							}
						>
							{ explorer.state.notice.message }
						</p>
					) }
					<BulkBar
						explorer={ explorer }
						edit={ () => setBulkEdit( true ) }
						exportRows={ () => setExporting( true ) }
					/>
					<div className="corex-data__panel-body">
						<CorexLoadable
							status={ LOADABLE_STATUS[ stateKey ] }
							skeleton={ <RecordsSkeleton /> }
							loadingLabel={ __( 'Loading records…', 'corex' ) }
							errorTitle={ __(
								'These records could not be loaded',
								'corex'
							) }
							errorMessage={ __(
								'The request did not complete. Nothing has been changed.',
								'corex'
							) }
							errorDetail={ explorer.state.error }
							onRetry={ explorer.reload }
						>
							{ stateKey === 'empty' && (
								<p className="corex-data__empty">
									{ __( 'No records yet.', 'corex' ) }
								</p>
							) }
							{ stateKey === 'empty-filtered' && (
								<p className="corex-data__empty">
									{ __(
										'No records match the current query.',
										'corex'
									) }
								</p>
							) }
							{ ( stateKey === 'ready' ||
								stateKey === 'refreshing' ) && (
								<RecordsTable
									explorer={ explorer }
									open={ openRecord }
								/>
							) }
						</CorexLoadable>
					</div>
					<Pagination explorer={ explorer } />
				</section>
			</main>
			{ open && (
				<RecordDetail
					explorer={ explorer }
					record={ open.record }
					close={ () => setOpen( null ) }
					edit={ () => {
						setEditor( open.record );
						setOpen( null );
					} }
				/>
			) }
			{ editor && (
				<RecordDialog
					source={ explorer.source }
					record={ editor.id ? editor : null }
					close={ () => setEditor( null ) }
					preview={ explorer.previewMutation }
				/>
			) }
			{ bulkEdit && (
				<BulkEditDialog
					source={ explorer.source }
					count={ explorer.state.selected.length }
					close={ () => setBulkEdit( false ) }
					preview={ ( fieldValues ) =>
						explorer.previewMutation(
							'bulk_update',
							explorer.state.selected,
							fieldValues
						)
					}
				/>
			) }
			{ exporting && (
				<DataExportDialog
					config={ config }
					source={ explorer.source }
					selectedIds={ explorer.state.selected }
					query={ explorer.state.query }
					close={ () => setExporting( false ) }
				/>
			) }
			{ explorer.state.preview && (
				<MutationPreviewDialog
					preview={ explorer.state.preview }
					pending={ explorer.state.pending }
					close={ () =>
						explorer.dispatch( { type: 'dismiss-preview' } )
					}
					apply={ explorer.applyMutation }
				/>
			) }
		</div>
	);
}
