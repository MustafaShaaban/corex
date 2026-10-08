/**
 * The export dialog (spec 103, US2 and US3).
 *
 * In the order the decision is made: what to export, with how many submissions each choice
 * covers; which columns; how the file is separated; then one line of what will happen, above one
 * button. The file is saved when it is ready. Nothing has to be refreshed.
 *
 * It asks the server to take the export forward a step at a time, because an export is otherwise
 * moved only when the site's scheduler fires, and on a quiet site that can be minutes.
 */
import { useEffect, useId, useRef, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import CorexDialog from '../../admin/components/CorexDialog.js';
import CorexTime from '../../admin/components/CorexTime.js';
import {
	ExportCheck,
	ExportColumns,
	ExportFooter,
	ExportFormat,
	ExportOutcome,
	ExportScopes,
} from '../../admin/components/export/ExportParts.js';
import { offeredFormats } from '../../admin/components/export/exportFormats.js';
import { runExport } from '../../admin/components/export/runExport.js';
import { formatDateTime } from '../../admin/adminDateTime.js';
import { buildExportPayload } from '../inbox.js';
import {
	DEFAULT_CHOICES,
	blockedReason,
	columnsFor,
	coverageOf,
	defaultScope,
	describeFilters,
	exportLabel,
	fileFrom,
	fileNoteOf,
	formatName,
	holdsPersonalData,
	offeredChoices,
	progressOf,
	scopeOptions,
	sizeOf,
} from './exportState.js';

/**
 * What each format is for, in a line under the choice.
 *
 * @param {string} format  The chosen format.
 * @param {number} pdfMost The most submissions one PDF holds.
 * @return {string} The line.
 */
function formatDetail( format, pdfMost ) {
	if ( format === 'pdf' ) {
		return sprintf(
			/* translators: %s: the most submissions one PDF holds. */
			__(
				'A document to keep or print. Every page has the site’s name, its number and CoreX’s signature. Up to %s submissions.',
				'corex'
			),
			Number( pdfMost ).toLocaleString()
		);
	}

	return format === 'xlsx'
		? __(
				'Dates and numbers sort and filter. The headings stay in view. Each form is a sheet of its own.',
				'corex'
		  )
		: __(
				'Plain text that any spreadsheet or tool opens. Several forms arrive as one file per form, in a zip.',
				'corex'
		  );
}

function save( artifact ) {
	const url = URL.createObjectURL( fileFrom( artifact ) );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = artifact.filename;
	link.click();
	URL.revokeObjectURL( url );
}

/**
 * @param {Object}        props
 * @param {Function}      props.close        Stops showing the dialog.
 * @param {Object}        props.inbox        The inbox's data and requests.
 * @param {Object}        props.filters      The inbox's filters in force.
 * @param {number[]}      props.selectedIds  The rows ticked in the inbox.
 * @param {Array<Object>} props.flows        The forms the inbox can filter by.
 * @param {Object}        props.statusLabels Status key => its name.
 * @return {import('react').ReactElement} The dialog.
 */
export default function ExportDialog( {
	close,
	inbox,
	filters,
	selectedIds,
	flows,
	statusLabels,
} ) {
	const fieldId = useId();
	const showing = useRef( true );
	const [ scope, setScope ] = useState( () =>
		defaultScope( selectedIds.length )
	);
	const [ chosen, setChosen ] = useState( DEFAULT_CHOICES );
	const [ format, setFormat ] = useState( 'xlsx' );
	const [ separator, setSeparator ] = useState( 'comma' );
	const [ includeTest, setIncludeTest ] = useState( false );
	const [ acknowledged, setAcknowledged ] = useState( false );
	const [ preview, setPreview ] = useState( null );
	const [ history, setHistory ] = useState( null );
	const [ run, setRun ] = useState( { phase: 'idle' } );

	const query = {
		search: filters.search,
		flow: filters.flow,
		status: filters.status,
		owner: filters.owner,
		date_from: filters.dateFrom,
		date_to: filters.dateTo,
	};
	const { previewExport, loadExports } = inbox;

	useEffect(
		() => () => {
			showing.current = false;
		},
		[]
	);

	// The counts depend on whether tests are included, so they are asked for again when that
	// changes. The filters and the selection cannot change while the dialog is open.
	useEffect( () => {
		let current = true;
		setPreview( null );
		previewExport( {
			selected_ids: selectedIds,
			query,
			include_test: includeTest,
		} ).then( ( data ) => {
			if ( current && data ) {
				setPreview( data );
			}
		} );

		return () => {
			current = false;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps -- see the comment above.
	}, [ includeTest ] );

	const refreshHistory = async () => {
		const result = await loadExports();
		if ( showing.current && result.envelope.ok ) {
			setHistory( result.envelope.data.exports );
		}
	};
	useEffect( () => {
		refreshHistory();
		// eslint-disable-next-line react-hooks/exhaustive-deps -- once, when the dialog opens.
	}, [] );

	const describe = ( source ) =>
		describeFilters( source, flows, statusLabels, readableDate );
	const mayExportPersonal = Boolean( preview?.permissions?.personal_data );
	const choices = offeredChoices( mayExportPersonal || preview === null );
	const allowed = chosen.filter( ( id ) =>
		choices.some( ( choice ) => choice.id === id )
	);
	const personal = holdsPersonalData( allowed );
	const options = scopeOptions( {
		counts: preview?.counts ?? null,
		selectedCount: selectedIds.length,
		filters: describe( filters ),
	} );
	const count =
		options.find( ( option ) => option.value === scope )?.count ?? null;
	const pdfMost = preview?.formats?.pdf_most_records ?? 0;
	const blocked = blockedReason( {
		count,
		chosen: allowed,
		acknowledged,
		format,
		pdfMost,
	} );
	const running = run.phase === 'running';

	const report = ( next ) => {
		if ( showing.current ) {
			setRun( next );
		}
	};

	const start = async () => {
		const started = await runExport( {
			create: async () =>
				(
					await inbox.createExport(
						buildExportPayload( {
							scope,
							selectedIds,
							columns: columnsFor( allowed ),
							includeTest,
							acknowledged,
							filters: query,
							format,
							separator,
						} )
					)
				)?.export.id,
			advance: async ( id ) =>
				( await inbox.advanceExport( id ) )?.progress,
			download: async ( id ) => {
				const downloaded = await inbox.downloadExport( id );
				return downloaded.envelope.ok
					? downloaded.envelope.data.artifact
					: null;
			},
			save,
			report,
			total: count,
			notDownloaded: __(
				'The export is ready, but it could not be downloaded. It is listed under Recent exports.',
				'corex'
			),
		} );
		if ( started ) {
			refreshHistory();
		}
	};

	const toggle = ( id ) =>
		setChosen( ( current ) =>
			current.includes( id )
				? current.filter( ( item ) => item !== id )
				: [ ...current, id ]
		);

	return (
		<CorexDialog
			title={ __( 'Export submissions', 'corex' ) }
			onClose={ close }
			className="corex-export"
			footer={
				<ExportFooter
					blocked={ blocked }
					run={ run }
					label={ exportLabel( count ) }
					close={ close }
					start={ start }
				/>
			}
		>
			<ExportScopes
				fieldId={ fieldId }
				options={ options }
				scope={ scope }
				setScope={ setScope }
				disabled={ running }
			>
				<ExportCheck
					id={ `${ fieldId }-include-test` }
					checked={ includeTest }
					onChange={ setIncludeTest }
				>
					{ __( 'Include submissions marked as tests', 'corex' ) }
				</ExportCheck>
			</ExportScopes>

			<ExportColumns
				fieldId={ fieldId }
				choices={ choices }
				chosen={ allowed }
				toggle={ toggle }
				disabled={ running }
			>
				{ preview !== null && ! mayExportPersonal && (
					<p className="corex-export__detail">
						{ __(
							'Columns that hold personal data are not offered: your role cannot export it.',
							'corex'
						) }
					</p>
				) }
			</ExportColumns>

			<ExportFormat
				format={ format }
				setFormat={ setFormat }
				formats={ offeredFormats( preview?.formats?.available ) }
				separator={ separator }
				setSeparator={ setSeparator }
				detail={ formatDetail( format, pdfMost ) }
				disabled={ running }
			/>

			{ personal && (
				<ExportCheck
					id={ `${ fieldId }-acknowledged` }
					className="corex-export__notice"
					checked={ acknowledged }
					disabled={ running }
					onChange={ setAcknowledged }
				>
					{ __(
						'I understand this export contains personal data and will handle it according to policy.',
						'corex'
					) }
				</ExportCheck>
			) }

			<ExportOutcome
				run={ run }
				describeProgress={ progressOf }
				onSaveAgain={ save }
				later={ __(
					'This export is large and is still being written. It will be listed under Recent exports when it is ready; you can close this.',
					'corex'
				) }
			/>

			<RecentExports
				history={ history }
				describe={ describe }
				remove={ async ( id ) => {
					const result = await inbox.deleteExport( id );
					if ( showing.current && result.envelope.ok ) {
						setHistory( result.envelope.data.exports );
					}
					return result.envelope.ok;
				} }
				download={ async ( id ) => {
					const result = await inbox.downloadExport( id );
					if ( result.envelope.ok ) {
						save( result.envelope.data.artifact );
					}
					return result.envelope.ok;
				} }
			/>
		</CorexDialog>
	);
}

/** How many past exports are listed before "Show all". */
const RECENT_SHOWN = 5;

/**
 * A stored date as a person reads it. `formatDateTime` answers the text with its machine form;
 * handing the whole answer to a sentence printed "[object Object]" where a date belonged.
 *
 * @param {string} value A stored date.
 * @return {string} The date, or what was stored when it names no date.
 */
function readableDate( value ) {
	return formatDateTime( value, 'date', String( value || '' ) ).human;
}

/**
 * What was exported before (spec 103, US9): what each export covered, in what format and how
 * large, who made it and when, and what became of its file. A file that is still kept can be
 * downloaded again or deleted.
 *
 * @param {Object}        props
 * @param {Array<Object>} props.history  The exports this person may see, newest first.
 * @param {Function}      props.describe Turns the inbox's filters into words.
 * @param {Function}      props.download Saves one export again; resolves to whether it could.
 * @param {Function}      props.remove   Deletes one export's file; resolves to whether it could.
 * @return {import('react').ReactElement|null} The history, or nothing when there is none.
 */
function RecentExports( { history, describe, download, remove } ) {
	const headingId = useId();
	const heading = useRef( null );
	const [ showAll, setShowAll ] = useState( false );
	const [ confirming, setConfirming ] = useState( 0 );
	const [ problem, setProblem ] = useState( '' );

	if ( ! history || history.length === 0 ) {
		return null;
	}

	const attempt = async ( action, id, failure ) => {
		setProblem( '' );
		if ( ! ( await action( id ) ) ) {
			setProblem( failure );
		}
	};
	const deleteFile = async ( id ) => {
		await attempt(
			remove,
			id,
			__( 'The file could not be deleted.', 'corex' )
		);
		setConfirming( 0 );
		// The buttons that had focus are gone with the file.
		heading.current?.focus();
	};

	return (
		<section className="corex-export__recent" aria-labelledby={ headingId }>
			<h3 id={ headingId } ref={ heading } tabIndex={ -1 }>
				{ __( 'Recent exports', 'corex' ) }
			</h3>
			{ problem && (
				<p className="corex-export__recent-problem" role="alert">
					{ problem }
				</p>
			) }
			<ul>
				{ ( showAll ? history : history.slice( 0, RECENT_SHOWN ) ).map(
					( item ) => (
						<li
							key={ item.id }
							className={ `corex-export__entry is-${ item.state }` }
						>
							<PastExport item={ item } describe={ describe } />
							{ item.state === 'ready' && (
								<PastExportActions
									confirming={ confirming === item.id }
									ask={ () => setConfirming( item.id ) }
									keep={ () => setConfirming( 0 ) }
									deleteFile={ () => deleteFile( item.id ) }
									download={ () =>
										attempt(
											download,
											item.id,
											__(
												'The file could not be downloaded.',
												'corex'
											)
										)
									}
								/>
							) }
						</li>
					)
				) }
			</ul>
			{ history.length > RECENT_SHOWN && (
				<Button
					variant="link"
					className="corex-export__recent-more"
					aria-expanded={ showAll }
					onClick={ () => setShowAll( ! showAll ) }
				>
					{ showAll
						? __( 'Show fewer', 'corex' )
						: sprintf(
								/* translators: %s: how many more past exports can be listed. */
								__( 'Show %s more', 'corex' ),
								(
									history.length - RECENT_SHOWN
								).toLocaleString()
						  ) }
				</Button>
			) }
		</section>
	);
}

function PastExport( { item, describe } ) {
	const size = sizeOf( item.file_size );

	return (
		<div className="corex-export__entry-text">
			<p className="corex-export__entry-what">
				{ coverageOf( item, describe ) }
			</p>
			<p className="corex-export__entry-facts">
				<span>{ formatName( item.format ) }</span>
				<span>
					{ sprintf(
						/* translators: %s: a number of submissions. */
						_n(
							'%s submission',
							'%s submissions',
							Number( item.record_count ),
							'corex'
						),
						Number( item.record_count ).toLocaleString()
					) }
				</span>
				{ size && <span>{ size }</span> }
			</p>
			<p className="corex-export__entry-facts">
				{ item.actor_name && <span>{ item.actor_name }</span> }
				<CorexTime value={ item.created_at } />
			</p>
			<p className="corex-export__entry-file" aria-live="polite">
				{ fileNoteOf( item, readableDate ) }
			</p>
		</div>
	);
}

function PastExportActions( { confirming, ask, keep, deleteFile, download } ) {
	if ( confirming ) {
		return (
			<div
				className="corex-export__entry-actions is-confirming"
				role="group"
				aria-label={ __( 'Delete this file?', 'corex' ) }
			>
				<span>{ __( 'Delete this file?', 'corex' ) }</span>
				<Button
					variant="secondary"
					isDestructive
					onClick={ deleteFile }
				>
					{ __( 'Delete file', 'corex' ) }
				</Button>
				{ /* The safe answer is the one focus lands on. */ }
				{ /* eslint-disable-next-line jsx-a11y/no-autofocus */ }
				<Button variant="tertiary" onClick={ keep } autoFocus>
					{ __( 'Keep it', 'corex' ) }
				</Button>
			</div>
		);
	}

	return (
		<div className="corex-export__entry-actions">
			<Button variant="link" onClick={ download }>
				{ __( 'Download', 'corex' ) }
			</Button>
			<Button variant="link" isDestructive onClick={ ask }>
				{ __( 'Delete', 'corex' ) }
			</Button>
		</div>
	);
}
