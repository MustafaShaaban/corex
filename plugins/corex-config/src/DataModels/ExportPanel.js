import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import CorexTime from '../admin/components/CorexTime.js';
import DataExportDialog from './DataExportDialog.js';
import { dataExportRequests, downloadArtifact } from './dataModelsApi.js';
import { actionSources } from './modelClient.js';
import SourceSelect from './SourceSelect.js';
import { usePending, workingProps } from '../admin/components/working.js';

function scopeName( scope ) {
	const scopes = {
		all: __( 'Everything', 'corex' ),
		filtered: __( 'Current filters', 'corex' ),
		selected: __( 'Selected rows', 'corex' ),
	};

	return scopes[ scope ] || scope;
}

function stateName( state ) {
	const states = {
		queued: __( 'Not finished', 'corex' ),
		completed: __( 'Ready', 'corex' ),
	};

	return states[ state ] || state;
}

/**
 * The Export tab: a source to choose, the export dialog, and what was exported before.
 *
 * It was a second export form of its own that queued a job and said to refresh the history. It
 * opens the dialog the Records tab opens (spec 103, D12c), over everything in the source, and its
 * history reloads when an export was made.
 *
 * @param {Object}        props
 * @param {Object}        props.config  The screen's `{restUrl, nonce}`.
 * @param {Array<Object>} props.sources The sources this person can see.
 * @return {import('react').ReactElement} The tab's panel.
 */
export default function ExportPanel( { config, sources } ) {
	const candidates = useMemo(
		() => actionSources( sources, 'export_csv' ),
		[ sources ]
	);
	const [ sourceKey, setSourceKey ] = useState( candidates[ 0 ]?.key || '' );
	const source = sources.find( ( item ) => item.key === sourceKey );
	const [ exporting, setExporting ] = useState( false );
	const [ history, setHistory ] = useState( [] );
	const [ notice, setNotice ] = useState( '' );
	const requests = useMemo(
		() => dataExportRequests( config, sourceKey ),
		[ config, sourceKey ]
	);

	const load = useCallback( async () => {
		if ( ! sourceKey ) {
			return;
		}
		try {
			setHistory( ( await requests.history() ).exports || [] );
			setNotice( '' );
		} catch ( error ) {
			setNotice( error.message );
		}
	}, [ requests, sourceKey ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const [ pending, during ] = usePending();

	const download = async ( run ) => {
		try {
			downloadArtifact( ( await requests.download( run.id ) ).artifact );
			setNotice( '' );
		} catch ( error ) {
			setNotice( error.message );
		}
	};

	if ( ! candidates.length ) {
		return (
			<p className="corex-data-models__empty">
				{ __(
					'No registered model provides an export adapter.',
					'corex'
				) }
			</p>
		);
	}

	return (
		<section className="corex-surface corex-data-models__workspace">
			<header>
				<h2>{ __( 'Model exports', 'corex' ) }</h2>
				<p>
					{ __(
						'Export every record of a model here. To export the rows you ticked or the filters you set, use Export on the Records tab.',
						'corex'
					) }
				</p>
			</header>
			<SourceSelect
				sources={ candidates }
				value={ sourceKey }
				onChange={ setSourceKey }
			/>
			<div>
				<Button
					variant="primary"
					onClick={ () => setExporting( true ) }
				>
					{ sprintf(
						/* translators: %s: the name of a data source, e.g. "Contacts". */
						__( 'Export %s…', 'corex' ),
						source?.label || ''
					) }
				</Button>
			</div>
			{ notice && <p role="alert">{ notice }</p> }
			<div className="corex-data-models__history-head">
				<h3>{ __( 'Export history', 'corex' ) }</h3>
			</div>
			{ history.length ? (
				<ul className="corex-data-models__history">
					{ history.map( ( run ) => (
						<li key={ run.id }>
							<span>
								{ sprintf(
									/* translators: 1: what an export covered. 2: its format. 3: a number of records. 4: whether its file is ready. */
									__( '%1$s · %2$s · %3$s · %4$s', 'corex' ),
									scopeName( run.scope ),
									String( run.format ).toUpperCase(),
									Number(
										run.exported_rows || run.record_count
									).toLocaleString(),
									stateName( run.state )
								) }
								{ ' · ' }
								<CorexTime value={ run.created_at } />
							</span>
							{ run.state === 'completed' && (
								<Button
									variant="link"
									// A second press used to fetch, and save, the file again.
									disabled={ pending !== '' }
									onClick={ () =>
										during( `download:${ run.id }`, () =>
											download( run )
										)
									}
									{ ...workingProps(
										pending === `download:${ run.id }`
									) }
								>
									{ __( 'Download', 'corex' ) }
								</Button>
							) }
						</li>
					) ) }
				</ul>
			) : (
				<p>{ __( 'No exports yet.', 'corex' ) }</p>
			) }
			{ exporting && source && (
				<DataExportDialog
					config={ config }
					source={ source }
					close={ () => setExporting( false ) }
					onExported={ load }
				/>
			) }
		</section>
	);
}
