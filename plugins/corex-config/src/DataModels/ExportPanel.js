import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import DataExportDialog from './DataExportDialog.js';
import DataExportSetDialog from './DataExportSetDialog.js';
import ExportHistory from './ExportHistory.js';
import ModelChecklist from './ModelChecklist.js';
import { dataExportSetRequests, downloadArtifact } from './dataModelsApi.js';
import { exportableModels, modelsNotOffered } from './exportSetState.js';
import CorexLoadable from '../admin/components/CorexLoadable.js';
import HistorySkeleton, { loadingFrom } from './HistorySkeleton.js';

/** Steps to take of an unfinished export before leaving the rest to the next press. */
const STEPS_AT_A_TIME = 200;

/**
 * What the button that opens the dialog says: nothing to open, one model by its name, or how
 * many models.
 *
 * @param {Array<Object>} chosen The ticked models.
 * @return {string} The button's label.
 */
function openerLabel( chosen ) {
	if ( chosen.length === 0 ) {
		return __( 'Export…', 'corex' );
	}
	if ( chosen.length === 1 ) {
		return sprintf(
			/* translators: %s: the name of a data model, e.g. "Contacts". */
			__( 'Export %s…', 'corex' ),
			chosen[ 0 ].label
		);
	}

	return sprintf(
		/* translators: %s: a number of data models. */
		_n( 'Export %s model…', 'Export %s models…', chosen.length, 'corex' ),
		chosen.length.toLocaleString()
	);
}

/**
 * The Export tab: the models to tick, the export dialog, and what was exported before.
 *
 * It had one dropdown named "Model", so every model was the same dialog again and a file of its
 * own. Any number of models are ticked and exported into one file (spec 109, slice 2). One
 * ticked model opens the dialog the Records tab opens (spec 103, D12c), over everything in it.
 *
 * @param {Object}        props
 * @param {Object}        props.config  The screen's `{restUrl, exportsUrl, nonce}`.
 * @param {Array<Object>} props.sources The sources this person can see.
 * @return {import('react').ReactElement} The tab's panel.
 */
export default function ExportPanel( { config, sources } ) {
	const models = useMemo( () => exportableModels( sources ), [ sources ] );
	const notOffered = modelsNotOffered( sources );
	const requests = useMemo(
		() => dataExportSetRequests( config ),
		[ config ]
	);
	const [ ticked, setTicked ] = useState( [] );
	const [ counts, setCounts ] = useState( null );
	const [ exporting, setExporting ] = useState( false );
	const [ history, setHistory ] = useState( [] );
	const [ status, setStatus ] = useState( 'loading' );
	// What the server said when the list could not be read. It is the list's failure, and is
	// said where the list is, with a way to ask again; the notice above is for actions.
	const [ loadFailure, setLoadFailure ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	// In the list's order, whatever order they were ticked in: it is the order of the sheets.
	const chosen = models.filter( ( source ) => ticked.includes( source.key ) );

	const load = useCallback( async () => {
		setStatus( loadingFrom );
		try {
			setHistory( ( await requests.history() ).exports || [] );
			setStatus( 'ready' );
		} catch ( error ) {
			setLoadFailure( error.message );
			setStatus( 'error' );
		}
	}, [ requests ] );

	useEffect( () => {
		load();
	}, [ load ] );

	// How many records each model holds, for the list. Asked once: a count that is a few
	// records behind is still what the dialog asks again before anything is exported.
	useEffect( () => {
		if ( models.length === 0 ) {
			return;
		}
		requests
			.preview( models.map( ( source ) => source.key ) )
			.then( ( data ) => setCounts( data.counts ) )
			.catch( ( error ) => setNotice( error.message ) );
	}, [ models, requests ] );

	const acting = async ( action ) => {
		try {
			await action();
			setNotice( '' );
		} catch ( error ) {
			setNotice( error.message );
		}
	};
	const download = ( id ) =>
		acting( async () =>
			downloadArtifact( ( await requests.download( id ) ).artifact )
		);
	const remove = ( id ) =>
		acting( async () => {
			await requests.remove( id );
			await load();
		} );
	const takeOn = ( id ) =>
		acting( async () => {
			for ( let step = 0; step < STEPS_AT_A_TIME; step++ ) {
				const { progress } = await requests.advance( id );
				if ( progress.state === 'failed' ) {
					throw new Error(
						progress.error ||
							__( 'The export could not be finished.', 'corex' )
					);
				}
				if ( progress.state === 'completed' ) {
					break;
				}
			}
			await load();
		} );

	if ( ! models.length ) {
		return (
			<p className="corex-data-models__empty">
				{ __(
					'No model on this site can be exported by you right now. The Models tab lists what each model supports.',
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
						'Tick the models to export; several are written into one file. To export the rows you ticked or the filters you set, use Export on the Records tab.',
						'corex'
					) }
				</p>
			</header>
			<ModelChecklist
				models={ models }
				ticked={ ticked }
				setTicked={ setTicked }
				counts={ counts }
			/>
			{ notOffered > 0 && (
				<p className="corex-data-models__checklist-note">
					{ sprintf(
						/* translators: %s: a number of data models. */
						_n(
							'%s model is not listed: it cannot be exported, or not by you. The Models tab says what each supports.',
							'%s models are not listed: they cannot be exported, or not by you. The Models tab says what each supports.',
							notOffered,
							'corex'
						),
						notOffered.toLocaleString()
					) }
				</p>
			) }
			<div className="corex-data-models__export-action">
				<Button
					variant="primary"
					disabled={ chosen.length === 0 }
					onClick={ () => setExporting( true ) }
				>
					{ openerLabel( chosen ) }
				</Button>
				{ chosen.length === 0 && (
					<span className="corex-data-models__checklist-note">
						{ __( 'Tick at least one model.', 'corex' ) }
					</span>
				) }
			</div>
			{ notice && <p role="alert">{ notice }</p> }
			<div className="corex-data-models__history-head">
				<h3>{ __( 'Export history', 'corex' ) }</h3>
			</div>
			<CorexLoadable
				status={ status }
				skeleton={ <HistorySkeleton /> }
				loadingLabel={ __( 'Loading the export history…', 'corex' ) }
				errorMessage={ __(
					'The export history could not be loaded.',
					'corex'
				) }
				errorDetail={ loadFailure }
				onRetry={ load }
			>
				{ history.length ? (
					<ExportHistory
						entries={ history }
						sources={ sources }
						download={ download }
						remove={ remove }
						takeOn={ takeOn }
					/>
				) : (
					<p>{ __( 'No exports yet.', 'corex' ) }</p>
				) }
			</CorexLoadable>
			{ exporting && chosen.length === 1 && (
				<DataExportDialog
					config={ config }
					source={ chosen[ 0 ] }
					close={ () => setExporting( false ) }
					onExported={ load }
				/>
			) }
			{ exporting && chosen.length > 1 && (
				<DataExportSetDialog
					config={ config }
					models={ chosen }
					close={ () => setExporting( false ) }
					onExported={ load }
				/>
			) }
		</section>
	);
}
