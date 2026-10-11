/**
 * The export of several models at once (spec 109, slice 2).
 *
 * The dialog one model's export opens, over several: which models and how many records each
 * holds, the columns of each for whoever wants to narrow them, the format of the one file, and
 * the file itself when it is ready. Every record of each model is exported; ticked rows and
 * filters belong to one model's table, on the Records tab.
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
} from '../admin/components/export/ExportParts.js';
import { runExport } from '../admin/components/export/runExport.js';
import { dataExportSetRequests, downloadArtifact } from './dataModelsApi.js';
import {
	dataColumnChoices,
	dataProgress,
	withheldColumns,
} from './dataExportState.js';
import {
	buildSetPayload,
	columnsOf,
	modelsInFile,
	setBlockedReason,
	setExportLabel,
	setFormatDetail,
	setFormats,
	setKinds,
	setTotal,
} from './exportSetState.js';

/**
 * One model of the export: its name, how many records it gives, and its columns behind a
 * disclosure, for whoever wants fewer of them.
 *
 * @param {Object}                   props
 * @param {string}                   props.fieldId  A prefix for the ids of its controls.
 * @param {Object}                   props.source   The model.
 * @param {number|null}              props.count    Its records; null while they are counted.
 * @param {string[]}                 props.chosen   The keys of its columns that will be exported.
 * @param {(keys: string[]) => void} props.narrow   Receives the keys chosen afterwards.
 * @param {boolean}                  props.disabled True while an export runs.
 * @return {import('react').ReactElement} The model's row.
 */
function ExportModel( { fieldId, source, count, chosen, narrow, disabled } ) {
	const mayExportPersonal = source.permissions?.personal_data === true;
	const choices = dataColumnChoices( source.fields || [], mayExportPersonal );
	const withheld = withheldColumns( source.fields || [], mayExportPersonal );
	const toggle = ( key ) =>
		narrow(
			chosen.includes( key )
				? chosen.filter( ( item ) => item !== key )
				: // Kept in the model's order: it is the order of the file's columns.
					choices
						.map( ( choice ) => choice.id )
						.filter(
							( item ) => item === key || chosen.includes( item )
						)
		);

	return (
		<li className="corex-export__model">
			<details>
				<summary>
					<span className="corex-export__model-head">
						<span className="corex-export__model-name">
							{ source.label }
						</span>
						<span className="corex-export__count">
							{ count === null
								? '…'
								: Number( count ).toLocaleString() }
						</span>
						<span className="corex-export__detail">
							{ count === 0
								? __(
										'Holds no records, and will be left out.',
										'corex'
									)
								: sprintf(
										/* translators: 1: how many columns of a model will be exported. 2: how many it has to choose from. */
										_n(
											'%1$s of %2$s column',
											'%1$s of %2$s columns',
											choices.length,
											'corex'
										),
										chosen.length.toLocaleString(),
										choices.length.toLocaleString()
									) }
						</span>
					</span>
				</summary>
				<ExportColumns
					fieldId={ `${ fieldId }-${ source.key }` }
					choices={ choices }
					chosen={ chosen }
					toggle={ toggle }
					disabled={ disabled }
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
			</details>
		</li>
	);
}

/**
 * @param {Object}        props
 * @param {Object}        props.config       The screen's `{exportsUrl, nonce}`.
 * @param {Array<Object>} props.models       The ticked models, in the list's order.
 * @param {() => void}    props.close        Stops showing the dialog.
 * @param {() => void}    [props.onExported] Called when an export was started, whatever became of it.
 * @return {import('react').ReactElement} The dialog.
 */
export default function DataExportSetDialog( {
	config,
	models,
	close,
	onExported = () => {},
} ) {
	const fieldId = useId();
	const showing = useRef( true );
	const requests = useMemo(
		() => dataExportSetRequests( config ),
		[ config ]
	);
	const [ counts, setCounts ] = useState( null );
	const [ writable, setWritable ] = useState( null );
	const [ columns, setColumns ] = useState( {} );
	const formats = setFormats( models, writable?.available );
	const pdfMost = writable?.pdf_most_records ?? 0;
	const [ format, setFormat ] = useState( formats[ 0 ].value );
	const [ separator, setSeparator ] = useState( 'comma' );
	const [ acknowledged, setAcknowledged ] = useState( false );
	const [ problem, setProblem ] = useState( '' );
	const [ run, setRun ] = useState( { phase: 'idle' } );

	useEffect(
		() => () => {
			showing.current = false;
		},
		[]
	);

	// Asked once: the models cannot change while the dialog is open.
	useEffect( () => {
		requests
			.preview( models.map( ( source ) => source.key ) )
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

	const total = setTotal( models, counts );
	// What is asked and what the button says are about the models the file will hold: one
	// with no record is left out of it.
	const held = modelsInFile( models, counts );
	const kinds = setKinds( held, columns );
	const blocked = setBlockedReason( {
		models,
		counts,
		columns,
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
		// The server's own words for a refusal say more than "could not be started".
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
							buildSetPayload( {
								models,
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
			total,
			notDownloaded: __(
				'The export is ready, but it could not be downloaded. It is listed under Export history below.',
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

	return (
		<CorexDialog
			title={ sprintf(
				/* translators: %s: a number of data models. */
				_n(
					'Export %s model',
					'Export %s models',
					models.length,
					'corex'
				),
				models.length.toLocaleString()
			) }
			onClose={ close }
			className="corex-export"
			footer={
				<ExportFooter
					blocked={ blocked }
					run={ run }
					label={ setExportLabel( total, held.length ) }
					close={ close }
					start={ start }
				/>
			}
		>
			<fieldset className="corex-export__group" disabled={ running }>
				<legend>{ __( 'What to export', 'corex' ) }</legend>
				<p className="corex-export__detail">
					{ __(
						'Every record of each model. Open a model to choose which of its columns.',
						'corex'
					) }
				</p>
				<ul className="corex-export__models">
					{ models.map( ( source ) => (
						<ExportModel
							key={ source.key }
							fieldId={ fieldId }
							source={ source }
							count={
								counts ? ( counts[ source.key ] ?? null ) : null
							}
							chosen={ columnsOf( source, columns ) }
							narrow={ ( chosen ) =>
								setColumns( ( current ) => ( {
									...current,
									[ source.key ]: chosen,
								} ) )
							}
							disabled={ running }
						/>
					) ) }
				</ul>
			</fieldset>

			<ExportFormat
				format={ format }
				setFormat={ setFormat }
				formats={ formats }
				separator={ separator }
				setSeparator={ setSeparator }
				detail={ setFormatDetail( format, pdfMost ) }
				disabled={ running }
			/>

			{ kinds.length > 0 && (
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
					'This export is large and is still being written. It will be listed under Export history when it is ready; you can close this.',
					'corex'
				) }
			/>
		</CorexDialog>
	);
}
