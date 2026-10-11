/**
 * The Data export (spec 103, US10): what to export with its count, which columns, the format,
 * then one line and one button, and the file when it is ready.
 *
 * The Data screen had two exports. The Records tab had a dialog that closed and said the export
 * was queued; the Export tab had a panel that said to refresh. Both open this, which is the
 * Submissions export's dialog over a source's records: the same parts, the same order, the same
 * waiting on the export until its file is saved.
 */
import {
	useEffect,
	useId,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import CorexDialog from '../admin/components/CorexDialog.js';
import { listed } from '../admin/personalData.js';
import {
	ExportCheck,
	ExportColumns,
	ExportFooter,
	ExportFormat,
	ExportOutcome,
	ExportScopes,
} from '../admin/components/export/ExportParts.js';
import { runExport } from '../admin/components/export/runExport.js';
import { dataExportRequests, downloadArtifact } from './dataModelsApi.js';
import {
	buildDataExportPayload,
	dataBlockedReason,
	dataColumnChoices,
	dataExportLabel,
	dataFormatDetail,
	dataFormats,
	dataProgress,
	dataScopes,
	defaultDataScope,
	describeDataQuery,
	personalKinds,
	withheldColumns,
} from './dataExportState.js';

/**
 * @param {Object}      props
 * @param {Object}      props.config        The screen's `{restUrl, nonce}`.
 * @param {Object}      props.source        The source to export, with its fields, its actions and
 *                                          whether this person may take personal data out.
 * @param {number[]}    [props.selectedIds] The rows ticked in the table behind the dialog.
 * @param {Object|null} [props.query]       The table's search and filters; null where the dialog
 *                                          is opened with no table behind it.
 * @param {Function}    props.close         Stops showing the dialog.
 * @param {Function}    [props.onExported]  Called when an export was started, whatever became of it.
 * @return {import('react').ReactElement} The dialog.
 */
export default function DataExportDialog( {
	config,
	source,
	selectedIds = [],
	query = null,
	close,
	onExported = () => {},
} ) {
	const fieldId = useId();
	const showing = useRef( true );
	const withRows = query !== null;
	const fields = useMemo( () => source?.fields || [], [ source ] );
	const mayExportPersonal = source?.permissions?.personal_data === true;
	// What this person is offered, in the source's order: it is the order of the file's columns.
	const offered = useMemo(
		() => dataColumnChoices( fields, mayExportPersonal ),
		[ fields, mayExportPersonal ]
	);
	const withheld = withheldColumns( fields, mayExportPersonal );
	const [ writable, setWritable ] = useState( null );
	const formats = dataFormats( source, writable?.available );
	const pdfMost = writable?.pdf_most_records ?? 0;
	const requests = useMemo(
		() => dataExportRequests( config, source?.key ),
		[ config, source?.key ]
	);
	const [ scope, setScope ] = useState( () =>
		defaultDataScope( selectedIds.length, withRows )
	);
	const [ columns, setColumns ] = useState( () =>
		offered.map( ( choice ) => choice.id )
	);
	const [ format, setFormat ] = useState( formats[ 0 ].value );
	const [ separator, setSeparator ] = useState( 'comma' );
	const [ acknowledged, setAcknowledged ] = useState( false );
	const [ counts, setCounts ] = useState( null );
	const [ problem, setProblem ] = useState( '' );
	const [ run, setRun ] = useState( { phase: 'idle' } );

	useEffect(
		() => () => {
			showing.current = false;
		},
		[]
	);

	// Asked once: the rows and the filters cannot change while the dialog is open.
	useEffect( () => {
		requests
			.preview( {
				selected_ids: selectedIds,
				query: query || {},
			} )
			.then( ( data ) => {
				if ( showing.current ) {
					setCounts( data.counts );
					setWritable( data.formats || null );
				}
			} )
			.catch( ( error ) => {
				if ( showing.current ) {
					setProblem( error.message );
				}
			} );
		// eslint-disable-next-line react-hooks/exhaustive-deps -- see the comment above.
	}, [] );

	const kinds = personalKinds( fields, columns );
	const personal = kinds.length > 0;
	const options = dataScopes( {
		counts,
		selectedCount: selectedIds.length,
		filters: describeDataQuery( query, fields ),
		withRows,
	} );
	const count =
		options.find( ( option ) => option.value === scope )?.count ?? null;
	const blocked = dataBlockedReason( {
		count,
		columns,
		personal,
		acknowledged,
		problem,
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
		// The server's own words for a refusal: "There is nothing to export." says more than
		// "could not be started".
		let refusal = '';
		const quietly = async ( request ) => {
			try {
				return await request();
			} catch ( error ) {
				refusal = error.message;
				return null;
			}
		};

		const started = await runExport( {
			create: async () =>
				(
					await quietly( () =>
						requests.create(
							buildDataExportPayload( {
								scope,
								selectedIds,
								query,
								columns,
								format,
								separator,
								acknowledged,
							} )
						)
					)
				)?.export.id,
			// A step the server refused is a stopped export, with the server's reason for it.
			advance: async ( id ) =>
				( await quietly( () => requests.advance( id ) ) )?.progress || {
					state: 'failed',
					error: refusal,
				},
			download: async ( id ) =>
				( await quietly( () => requests.download( id ) ) )?.artifact,
			save: downloadArtifact,
			report,
			total: count,
			notDownloaded: __(
				'The export is ready, but it could not be downloaded. It is listed under Export history on the Export tab.',
				'corex'
			),
		} );
		if ( ! started && refusal ) {
			report( { phase: 'failed', message: refusal } );
		}
		if ( started ) {
			onExported();
		}
	};

	const toggle = ( key ) =>
		setColumns( ( current ) =>
			current.includes( key )
				? current.filter( ( item ) => item !== key )
				: // Kept in the source's order, whatever order they were ticked in: that is the
					// order of the file's columns.
					offered
						.map( ( choice ) => choice.id )
						.filter(
							( item ) => item === key || current.includes( item )
						)
		);

	return (
		<CorexDialog
			title={ sprintf(
				/* translators: %s: the name of a data source, e.g. "Contacts". */
				__( 'Export %s', 'corex' ),
				source?.label || ''
			) }
			onClose={ close }
			className="corex-export"
			footer={
				<ExportFooter
					blocked={ blocked }
					run={ run }
					label={ dataExportLabel( count ) }
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
			/>

			<ExportColumns
				fieldId={ fieldId }
				choices={ offered }
				chosen={ columns }
				toggle={ toggle }
				disabled={ running }
			>
				{ withheld > 0 && (
					<p className="corex-export__detail">
						{ sprintf(
							/* translators: %d: a number of columns. */
							_n(
								'%d column that holds personal data is not offered: your role cannot export it.',
								'%d columns that hold personal data are not offered: your role cannot export it.',
								withheld,
								'corex'
							),
							withheld
						) }
					</p>
				) }
			</ExportColumns>

			<ExportFormat
				format={ format }
				setFormat={ setFormat }
				formats={ formats }
				separator={ separator }
				setSeparator={ setSeparator }
				detail={ dataFormatDetail( format, pdfMost ) }
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
					{ sprintf(
						/* translators: %s: the kinds of personal data in the export, e.g. "contact details, names and identities". */
						__(
							'I understand this export contains personal data (%s) and will handle it according to policy.',
							'corex'
						),
						listed( kinds )
					) }
				</ExportCheck>
			) }

			<ExportOutcome
				run={ run }
				describeProgress={ dataProgress }
				onSaveAgain={ downloadArtifact }
				later={ __(
					'This export is large and is still being written. It will be listed under Export history on the Export tab when it is ready; you can close this.',
					'corex'
				) }
			/>
		</CorexDialog>
	);
}
