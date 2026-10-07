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
import { __, sprintf } from '@wordpress/i18n';
import CorexDialog from '../../admin/components/CorexDialog.js';
import CorexSelect from '../../admin/components/CorexSelect.js';
import CorexTime from '../../admin/components/CorexTime.js';
import { formatDateTime } from '../../admin/adminDateTime.js';
import { buildExportPayload } from '../inbox.js';
import {
	blockedReason,
	columnsFor,
	DEFAULT_CHOICES,
	defaultScope,
	describeFilters,
	exportLabel,
	fileFrom,
	holdsPersonalData,
	offeredChoices,
	progressOf,
	scopeOptions,
} from './exportState.js';

/** Steps to wait through before telling the person the export will finish on its own. */
const STEPS_TO_WAIT = 400;

/** A pause when a step moved nothing: the scheduler is taking that step, and will finish it. */
const PAUSE_WHEN_IDLE_MS = 700;

const FAILED_STATES = [ 'failed', 'cancelled' ];

function separators() {
	return [
		{ value: 'comma', label: __( 'Comma', 'corex' ) },
		{ value: 'semicolon', label: __( 'Semicolon', 'corex' ) },
		{ value: 'tab', label: __( 'Tab', 'corex' ) },
	];
}

function save( artifact ) {
	const url = URL.createObjectURL( fileFrom( artifact ) );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = artifact.filename;
	link.click();
	URL.revokeObjectURL( url );
}

function pause( milliseconds ) {
	return new Promise( ( resolve ) => {
		setTimeout( resolve, milliseconds );
	} );
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

	const mayExportPersonal = Boolean( preview?.permissions?.personal_data );
	const choices = offeredChoices( mayExportPersonal || preview === null );
	const allowed = chosen.filter( ( id ) =>
		choices.some( ( choice ) => choice.id === id )
	);
	const personal = holdsPersonalData( allowed );
	const options = scopeOptions( {
		counts: preview?.counts ?? null,
		selectedCount: selectedIds.length,
		filters: describeFilters( filters, flows, statusLabels, ( date ) =>
			formatDateTime( date, 'date', date )
		),
	} );
	const count =
		options.find( ( option ) => option.value === scope )?.count ?? null;
	const blocked = blockedReason( { count, chosen: allowed, acknowledged } );
	const running = run.phase === 'running';

	const report = ( next ) => {
		if ( showing.current ) {
			setRun( next );
		}
	};

	const start = async () => {
		report( {
			phase: 'running',
			progress: { processed: 0, total: count },
		} );
		const created = await inbox.createExport(
			buildExportPayload( {
				scope,
				selectedIds,
				columns: columnsFor( allowed ),
				includeTest,
				acknowledged,
				filters: query,
				format: 'csv',
				separator,
			} )
		);
		if ( ! created ) {
			report( {
				phase: 'failed',
				message: __( 'The export could not be started.', 'corex' ),
			} );
			return;
		}

		const id = created.export.id;
		let progress = { state: 'queued', processed: 0, total: count };
		for (
			let step = 0;
			step < STEPS_TO_WAIT && progress.state !== 'completed';
			step++
		) {
			const before = progress.processed;
			const advanced = await inbox.advanceExport( id );
			if (
				! advanced ||
				FAILED_STATES.includes( advanced.progress.state )
			) {
				report( {
					phase: 'failed',
					message:
						advanced?.progress.error ||
						__( 'The export stopped before it finished.', 'corex' ),
				} );
				refreshHistory();
				return;
			}
			progress = advanced.progress;
			report( { phase: 'running', progress } );
			if (
				progress.state !== 'completed' &&
				progress.processed === before
			) {
				await pause( PAUSE_WHEN_IDLE_MS );
			}
		}

		if ( progress.state !== 'completed' ) {
			report( { phase: 'later' } );
			refreshHistory();
			return;
		}

		const downloaded = await inbox.downloadExport( id );
		if ( ! downloaded.envelope.ok ) {
			report( {
				phase: 'failed',
				message: __(
					'The export is ready, but it could not be downloaded. It is listed under Recent exports.',
					'corex'
				),
			} );
			refreshHistory();
			return;
		}

		const artifact = downloaded.envelope.data.artifact;
		save( artifact );
		report( { phase: 'done', artifact } );
		refreshHistory();
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
				<>
					<p
						className="corex-export__summary"
						role="status"
						aria-live="polite"
					>
						{ blocked }
					</p>
					<div className="corex-export__actions">
						<Button variant="tertiary" onClick={ close }>
							{ run.phase === 'done' || run.phase === 'later'
								? __( 'Close', 'corex' )
								: __( 'Cancel', 'corex' ) }
						</Button>
						<Button
							variant="primary"
							disabled={ blocked !== '' || running }
							isBusy={ running }
							onClick={ start }
						>
							{ exportLabel( count ) }
						</Button>
					</div>
				</>
			}
		>
			<fieldset className="corex-export__group" disabled={ running }>
				<legend>{ __( 'What to export', 'corex' ) }</legend>
				<div className="corex-export__scopes">
					{ options.map( ( option ) => (
						<label
							key={ option.value }
							className={ [
								'corex-export__scope',
								scope === option.value ? 'is-chosen' : '',
								option.disabled ? 'is-disabled' : '',
							]
								.filter( Boolean )
								.join( ' ' ) }
							htmlFor={ `${ fieldId }-scope-${ option.value }` }
						>
							<input
								id={ `${ fieldId }-scope-${ option.value }` }
								type="radio"
								name={ `${ fieldId }-scope` }
								value={ option.value }
								checked={ scope === option.value }
								disabled={ option.disabled }
								onChange={ () => setScope( option.value ) }
								aria-describedby={ `${ fieldId }-scope-${ option.value }-detail` }
							/>
							<span className="corex-export__scope-name">
								{ option.label }
							</span>
							<span className="corex-export__count">
								{ option.count === null
									? '…'
									: option.count.toLocaleString() }
							</span>
							<span
								id={ `${ fieldId }-scope-${ option.value }-detail` }
								className="corex-export__detail"
							>
								{ option.detail }
							</span>
						</label>
					) ) }
				</div>
				<label
					className="corex-export__check"
					htmlFor={ `${ fieldId }-include-test` }
				>
					<input
						id={ `${ fieldId }-include-test` }
						type="checkbox"
						checked={ includeTest }
						onChange={ ( event ) =>
							setIncludeTest( event.target.checked )
						}
					/>
					<span>
						{ __( 'Include submissions marked as tests', 'corex' ) }
					</span>
				</label>
			</fieldset>

			<fieldset className="corex-export__group" disabled={ running }>
				<legend>{ __( 'Columns', 'corex' ) }</legend>
				<div className="corex-export__columns">
					{ choices.map( ( choice ) => (
						// The label is the choice's name alone. What it holds, and that it is
						// personal data, describe the checkbox instead of lengthening its name.
						<div key={ choice.id } className="corex-export__column">
							<input
								id={ `${ fieldId }-column-${ choice.id }` }
								type="checkbox"
								checked={ allowed.includes( choice.id ) }
								onChange={ () => toggle( choice.id ) }
								aria-describedby={ [
									choice.personal
										? `${ fieldId }-column-${ choice.id }-tag`
										: '',
									`${ fieldId }-column-${ choice.id }-hint`,
								]
									.filter( Boolean )
									.join( ' ' ) }
							/>
							<div>
								<span className="corex-export__column-head">
									<label
										className="corex-export__column-name"
										htmlFor={ `${ fieldId }-column-${ choice.id }` }
									>
										{ choice.label }
									</label>
									{ choice.personal && (
										<span
											id={ `${ fieldId }-column-${ choice.id }-tag` }
											className="corex-export__tag"
										>
											{ __( 'Personal data', 'corex' ) }
										</span>
									) }
								</span>
								<span
									id={ `${ fieldId }-column-${ choice.id }-hint` }
									className="corex-export__detail"
								>
									{ choice.hint }
								</span>
							</div>
						</div>
					) ) }
				</div>
				{ preview !== null && ! mayExportPersonal && (
					<p className="corex-export__detail">
						{ __(
							'Columns that hold personal data are not offered: your role cannot export it.',
							'corex'
						) }
					</p>
				) }
			</fieldset>

			<fieldset className="corex-export__group" disabled={ running }>
				<legend>{ __( 'Format', 'corex' ) }</legend>
				<p className="corex-export__detail">
					{ __(
						'CSV, which opens in Excel, Numbers and Google Sheets. Several forms arrive as one file per form, in a zip.',
						'corex'
					) }
				</p>
				<div className="corex-field corex-export__separator">
					<span>{ __( 'Separator', 'corex' ) }</span>
					<CorexSelect
						label={ __( 'Separator', 'corex' ) }
						value={ separator }
						options={ separators() }
						onChange={ setSeparator }
					/>
				</div>
			</fieldset>

			{ personal && (
				<label
					className="corex-export__check corex-export__notice"
					htmlFor={ `${ fieldId }-acknowledged` }
				>
					<input
						id={ `${ fieldId }-acknowledged` }
						type="checkbox"
						checked={ acknowledged }
						disabled={ running }
						onChange={ ( event ) =>
							setAcknowledged( event.target.checked )
						}
					/>
					<span>
						{ __(
							'I understand this export contains personal data and will handle it according to policy.',
							'corex'
						) }
					</span>
				</label>
			) }

			<ExportOutcome run={ run } onSaveAgain={ save } />

			<RecentExports
				history={ history }
				download={ async ( id ) => {
					const result = await inbox.downloadExport( id );
					if ( result.envelope.ok ) {
						save( result.envelope.data.artifact );
					}
				} }
			/>
		</CorexDialog>
	);
}

function ExportOutcome( { run, onSaveAgain } ) {
	if ( run.phase === 'idle' ) {
		return null;
	}

	if ( run.phase === 'running' ) {
		const progress = progressOf( run.progress );

		return (
			<div className="corex-export__outcome" role="status">
				<progress
					className="corex-export__progress"
					value={ progress.value }
					max={ progress.max }
					aria-label={ __( 'Export progress', 'corex' ) }
				/>
				<p>{ progress.text }</p>
			</div>
		);
	}

	if ( run.phase === 'done' ) {
		return (
			<div className="corex-export__outcome is-success" role="status">
				<p>
					{ sprintf(
						/* translators: %s: the name of the file that was saved. */
						__( 'Saved %s.', 'corex' ),
						run.artifact.filename
					) }
				</p>
				<Button
					variant="link"
					onClick={ () => onSaveAgain( run.artifact ) }
				>
					{ __( 'Save it again', 'corex' ) }
				</Button>
			</div>
		);
	}

	if ( run.phase === 'later' ) {
		return (
			<div className="corex-export__outcome" role="status">
				<p>
					{ __(
						'This export is large and is still being written. It will be listed under Recent exports when it is ready; you can close this.',
						'corex'
					) }
				</p>
			</div>
		);
	}

	return (
		<div className="corex-export__outcome is-error" role="alert">
			<p>{ run.message }</p>
		</div>
	);
}

function RecentExports( { history, download } ) {
	if ( ! history || history.length === 0 ) {
		return null;
	}

	return (
		<section className="corex-export__recent">
			<h3>{ __( 'Recent exports', 'corex' ) }</h3>
			<ul>
				{ history.slice( 0, 5 ).map( ( item ) => (
					<li key={ item.id }>
						<span>
							<CorexTime value={ item.created_at } />
							{ ' · ' }
							{ sprintf(
								/* translators: %s: a number of submissions. */
								__( '%s submissions', 'corex' ),
								Number( item.record_count ).toLocaleString()
							) }
							{ ' · ' }
							{ String( item.format || 'csv' ).toUpperCase() }
						</span>
						<Button
							variant="link"
							onClick={ () => download( item.id ) }
						>
							{ __( 'Download', 'corex' ) }
						</Button>
					</li>
				) ) }
			</ul>
		</section>
	);
}
