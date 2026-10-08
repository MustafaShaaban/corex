import {
	createRoot,
	render,
	useEffect,
	useId,
	useRef,
	useState,
} from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import CorexDialog from '../admin/components/CorexDialog.js';
import CorexSelect from '../admin/components/CorexSelect.js';
import CorexTime from '../admin/components/CorexTime.js';
import CorexErrorState from '../admin/components/CorexErrorState.js';
import DetailPane from './detail/DetailPane.js';
import ExportDialog from './export/ExportDialog.js';
import {
	DeliveryBadge,
	STATUS_LABELS,
	STATUSES,
	StatusBadge,
} from './badges.js';
import {
	VIEW_INBOX,
	VIEW_TRASH,
	bulkActionsFor,
	deleteConfirmation,
	deletesAt,
	inboxFiltersFromUrl,
	inboxSubmissionFromUrl,
	toggleSubmission,
	trashConfirmation,
	trashKeeps,
	trashNotice,
	viewCount,
} from './inbox.js';
import { useInbox } from './useInbox.js';

const config = window.corexSubmissions || { restUrl: '', nonce: '', flows: [] };
// Empty when the forms add-on is absent (Principle IX) — the form filter simply does not render.
const FLOWS = Array.isArray( config.flows ) ? config.flows : [];
const OWNER_TYPES = [
	{ value: 'user', label: __( 'User', 'corex' ) },
	{ value: 'team', label: __( 'Team', 'corex' ) },
	{ value: 'role', label: __( 'Role', 'corex' ) },
	{ value: 'none', label: __( 'Unassigned', 'corex' ) },
];

function App() {
	const [ filters, setFilters ] = useState( () => ( {
		search: '',
		flow: '',
		status: '',
		owner: '',
		dateFrom: '',
		dateTo: '',
		includeTest: false,
		view: VIEW_INBOX,
		page: 1,
		perPage: 25,
		...inboxFiltersFromUrl(
			typeof window === 'undefined' ? '' : window.location.href
		),
	} ) );
	const inbox = useInbox( config, filters );
	// A `corex_submission` in the address opens that row's detail once, on arrival. Deliberately not
	// in the filter state above: this is where you were sent, not what you are filtering by, and
	// re-running it on every filter change would reopen a drawer somebody had closed.
	const deepLinked = useRef(
		inboxSubmissionFromUrl(
			typeof window === 'undefined' ? '' : window.location.href
		)
	);
	const openDetail = inbox.open;
	useEffect( () => {
		if ( deepLinked.current ) {
			const id = deepLinked.current;
			deepLinked.current = 0;
			openDetail( id );
		}
	}, [ openDetail ] );
	const [ bulkAction, setBulkAction ] = useState( 'mark_read' );
	const [ bulkOwner, setBulkOwner ] = useState( {
		owner_type: 'user',
		owner_key: '',
	} );
	const [ preview, setPreview ] = useState( null );
	const [ exportOpen, setExportOpen ] = useState( false );
	// Whether somebody asked to move the open submission to the trash, until they confirm or
	// cancel. Not the record itself: opening a submission marks it read, which changes it, and a
	// copy taken at the click was refused as stale when the confirmation came.
	const [ trashing, setTrashing ] = useState( false );
	const view = filters.view;
	// The ids somebody asked to delete for good, until they confirm or cancel.
	const [ deleting, setDeleting ] = useState( null );
	const actions = bulkActionsFor( view, inbox.state.mayDelete );
	// What was chosen in the other view is not offered in this one.
	const action = actions.some( ( item ) => item.value === bulkAction )
		? bulkAction
		: actions[ 0 ].value;
	const setView = ( next ) => {
		inbox.dispatch( { type: 'selectionChanged', ids: [] } );
		inbox.close();
		setFilters( ( current ) => ( { ...current, view: next, page: 1 } ) );
	};
	const updateFilter = ( key, value ) =>
		setFilters( ( current ) => ( {
			...current,
			[ key ]: value,
			page: key === 'page' ? value : 1,
		} ) );

	return (
		<div className="corex-inbox" data-status={ inbox.state.status }>
			<InboxHeader
				view={ view }
				total={ inbox.state.total }
				onExport={ () => setExportOpen( true ) }
			/>
			{ inbox.state.message && (
				<div className="corex-inbox__notice is-success" role="status">
					<span>{ inbox.state.message }</span>
					{ inbox.state.undo.length > 0 && (
						<Button
							variant="link"
							onClick={ () => inbox.restore( inbox.state.undo ) }
						>
							{ __( 'Undo', 'corex' ) }
						</Button>
					) }
				</div>
			) }
			{ inbox.state.error && (
				<CorexErrorState
					scale="action"
					message={ inbox.state.error }
					onRetry={ inbox.load }
				/>
			) }
			<Views view={ view } setView={ setView } />
			<Filters
				filters={ filters }
				update={ updateFilter }
				flows={ FLOWS }
			/>
			<BulkToolbar
				count={ inbox.state.selectedIds.length }
				actions={ actions }
				action={ action }
				setAction={ setBulkAction }
				owner={ bulkOwner }
				setOwner={ setBulkOwner }
				onClear={ () =>
					inbox.dispatch( { type: 'selectionChanged', ids: [] } )
				}
				onPreview={ async () => {
					const ids = inbox.state.selectedIds;
					// A permanent delete has a confirmation of its own, which lists what goes.
					if ( action === 'delete' ) {
						setDeleting( ids );
						return;
					}
					const data = await inbox.previewBulk(
						action,
						ids,
						action === 'assign' ? bulkOwner : {}
					);
					if ( data ) {
						// The ids are kept with the preview: they are what "Undo" restores.
						setPreview( { ...data.preview, ids } );
					}
				} }
			/>
			{ view === VIEW_TRASH && (
				<p className="corex-inbox__trash-keeps">
					{ trashKeeps( inbox.state.trashDays ) }
				</p>
			) }
			<InboxTable
				view={ view }
				state={ inbox.state }
				dispatch={ inbox.dispatch }
				open={ inbox.open }
			/>
			<Pagination
				state={ inbox.state }
				filters={ filters }
				update={ updateFilter }
			/>
			{ inbox.state.drawer.open && (
				<DetailPane
					id={ DRAWER_ID }
					drawer={ inbox.state.drawer }
					inbox={ inbox }
					onTrash={ () => setTrashing( true ) }
					mayDelete={ inbox.state.mayDelete }
					onDelete={ () =>
						setDeleting( [ inbox.state.drawer.record.id ] )
					}
				/>
			) }
			{ preview && (
				<ConfirmBulk
					preview={ preview }
					close={ () => setPreview( null ) }
					apply={ async () => {
						await inbox.applyBulk(
							preview.token,
							bulkOutcome( preview )
						);
						setPreview( null );
					} }
				/>
			) }
			{ trashing && inbox.state.drawer.record && (
				<ConfirmTrash
					close={ () => setTrashing( false ) }
					apply={ async () => {
						await inbox.trash( inbox.state.drawer.record );
						setTrashing( false );
					} }
				/>
			) }
			{ deleting && (
				<ConfirmDelete
					count={ deleting.length }
					loadExports={ inbox.loadExports }
					close={ () => setDeleting( null ) }
					apply={ async () => {
						await inbox.destroy( deleting );
						setDeleting( null );
					} }
				/>
			) }
			{ exportOpen && (
				<ExportDialog
					close={ () => setExportOpen( false ) }
					inbox={ inbox }
					filters={ filters }
					selectedIds={ inbox.state.selectedIds }
					flows={ FLOWS }
					statusLabels={ STATUS_LABELS }
				/>
			) }
		</div>
	);
}

/**
 * What the inbox says after a bulk action that moved submissions to the trash or out of it. The
 * trash is undone from its notice; the other actions keep their own.
 *
 * @param {Object} preview The confirmed preview, with the ids it was made for.
 * @return {{message?:string,undo?:number[]}} What to say, and what "Undo" restores.
 */
function bulkOutcome( preview ) {
	if ( preview.action === 'trash' ) {
		return {
			message: trashNotice( 'trash', preview.count ),
			undo: preview.ids,
		};
	}
	if ( preview.action === 'restore' ) {
		return { message: trashNotice( 'restore', preview.count ) };
	}

	return {};
}

/**
 * The inbox and its trash, as two views of one list (spec 105). Two buttons that say which is
 * pressed, not tabs: the filters and the table under them are the same in both.
 *
 * @param {Object}   props
 * @param {string}   props.view    The view shown.
 * @param {Function} props.setView Shows the other.
 * @return {import('react').ReactElement} The switch.
 */
function Views( { view, setView } ) {
	const views = [
		{ value: VIEW_INBOX, label: __( 'Inbox', 'corex' ) },
		{ value: VIEW_TRASH, label: __( 'Trash', 'corex' ) },
	];

	return (
		<div
			className="corex-inbox__views"
			role="group"
			aria-label={ __( 'Submissions shown', 'corex' ) }
		>
			{ views.map( ( item ) => (
				<button
					key={ item.value }
					type="button"
					className="corex-inbox__view"
					aria-pressed={ view === item.value }
					onClick={ () => setView( item.value ) }
				>
					{ item.label }
				</button>
			) ) }
		</div>
	);
}

function InboxHeader( { view, total, onExport } ) {
	// The three lines are one stack, so their spacing is the stack's job. They used to be loose
	// children of a bare <div> whose only separation came from a margin on each <p>, which is why
	// the eyebrow, the title, and the count read as one compressed block.
	return (
		<header className="corex-inbox__header">
			<div className="corex-inbox__heading">
				<p className="corex-inbox__eyebrow">
					{ __( 'Team workspace', 'corex' ) }
				</p>
				<h2>{ __( 'Submission Inbox', 'corex' ) }</h2>
				<p className="corex-inbox__count">
					{ viewCount( view, total ) }
				</p>
			</div>
			{ /* An export is of what is in the inbox. The trash holds what was taken out of it. */ }
			{ view === VIEW_INBOX && (
				<Button variant="primary" onClick={ onExport }>
					{ __( 'Export', 'corex' ) }
				</Button>
			) }
		</header>
	);
}

function FormFilter( { flows, value, update } ) {
	// No forms yet — say so rather than present an empty control that looks broken. The filter is
	// also absent entirely when the forms add-on is not installed (flows arrives empty).
	if ( ! flows.length ) {
		return null;
	}

	return (
		<div className="corex-field">
			<span>{ __( 'Form', 'corex' ) }</span>
			{ /* The value is the flow ID because that is what the inbox stores (meta corex_flow_id) —
		     the data explorer keys the same list by slug instead. The owner picks a name either
		     way; they used to have to know the number.

		     A form registered in code has no flow row, so it arrives with id 0 and is identified
		     by slug instead; it is sent as `slug:<slug>` so the inbox matches corex_form_slug.
		     Sending 0 would read as "all forms" and silently ignore the choice.

		     aria-label because a <label> wrapping a control names it from the label's whole
		     subtree, and an embedded control contributes its VALUE — so this would announce as
		     "Form All forms" and rename itself every time the selection changed. */ }
			<CorexSelect
				label={ __( 'Form', 'corex' ) }
				value={ value }
				options={ [
					{ value: '', label: __( 'All forms', 'corex' ) },
					...flows.map( ( flow ) => ( {
						value:
							flow.id > 0
								? String( flow.id )
								: `slug:${ flow.slug }`,
						label: flow.name,
					} ) ),
				] }
				onChange={ ( flow ) => update( 'flow', flow ) }
				block
			/>
		</div>
	);
}

function Filters( { filters, update, flows } ) {
	// Each control is named by its own `for`/`id` pair rather than by being wrapped: a
	// wrapping <label> is not announced by every assistive technology WordPress supports.
	const fieldId = useId();

	return (
		<section
			className="corex-inbox__filters"
			aria-label={ __( 'Submission filters', 'corex' ) }
		>
			{ /* The panel measures itself and this grid follows: see the stylesheet. */ }
			<div className="corex-inbox__filter-grid">
				<label className="is-wide" htmlFor={ `${ fieldId }-search` }>
					<span>{ __( 'Search', 'corex' ) }</span>
					<input
						id={ `${ fieldId }-search` }
						type="search"
						value={ filters.search }
						onChange={ ( event ) =>
							update( 'search', event.target.value )
						}
						placeholder={ __( 'Name, email, or flow', 'corex' ) }
					/>
				</label>
				<FormFilter
					flows={ flows }
					value={ filters.flow }
					update={ update }
				/>
				<div className="corex-field">
					<span>{ __( 'Status', 'corex' ) }</span>
					<CorexSelect
						label={ __( 'Status', 'corex' ) }
						value={ filters.status }
						options={ [
							{ value: '', label: __( 'All statuses', 'corex' ) },
							...STATUSES.map( ( status ) => ( {
								value: status,
								label: STATUS_LABELS[ status ],
							} ) ),
						] }
						onChange={ ( status ) => update( 'status', status ) }
						block
					/>
				</div>
				<label
					className="corex-inbox__owner"
					htmlFor={ `${ fieldId }-owner` }
				>
					<span>{ __( 'Owner', 'corex' ) }</span>
					<input
						id={ `${ fieldId }-owner` }
						value={ filters.owner }
						onChange={ ( event ) =>
							update( 'owner', event.target.value )
						}
						placeholder="team:sales"
					/>
				</label>
				{ /* A range is one thing: its two ends stay side by side at every width. */ }
				<div className="corex-inbox__dates">
					<label htmlFor={ `${ fieldId }-date-from` }>
						<span>{ __( 'From', 'corex' ) }</span>
						<input
							id={ `${ fieldId }-date-from` }
							type="date"
							value={ filters.dateFrom }
							onChange={ ( event ) =>
								update( 'dateFrom', event.target.value )
							}
						/>
					</label>
					<label htmlFor={ `${ fieldId }-date-to` }>
						<span>{ __( 'To', 'corex' ) }</span>
						<input
							id={ `${ fieldId }-date-to` }
							type="date"
							value={ filters.dateTo }
							onChange={ ( event ) =>
								update( 'dateTo', event.target.value )
							}
						/>
					</label>
				</div>
				<label
					className="is-check"
					htmlFor={ `${ fieldId }-include-test` }
				>
					<input
						id={ `${ fieldId }-include-test` }
						type="checkbox"
						checked={ filters.includeTest }
						onChange={ ( event ) =>
							update( 'includeTest', event.target.checked )
						}
					/>
					<span>{ __( 'Include marked tests', 'corex' ) }</span>
				</label>
			</div>
		</section>
	);
}

function BulkToolbar( props ) {
	if ( props.count === 0 ) {
		return null;
	}
	return (
		<div
			className="corex-inbox__bulk"
			role="region"
			aria-label={ __( 'Bulk actions', 'corex' ) }
		>
			<strong>
				{ sprintf(
					/* translators: %d: number of selected submissions. */
					__( '%d selected', 'corex' ),
					props.count
				) }
			</strong>
			<CorexSelect
				label={ __( 'Bulk action', 'corex' ) }
				value={ props.action }
				options={ props.actions }
				onChange={ props.setAction }
			/>
			{ props.action === 'assign' && (
				<>
					<CorexSelect
						label={ __( 'Owner type', 'corex' ) }
						value={ props.owner.owner_type }
						options={ OWNER_TYPES }
						onChange={ ( ownerType ) =>
							props.setOwner( {
								...props.owner,
								owner_type: ownerType,
							} )
						}
					/>
					<input
						value={ props.owner.owner_key }
						disabled={ props.owner.owner_type === 'none' }
						onChange={ ( e ) =>
							props.setOwner( {
								...props.owner,
								owner_key: e.target.value,
							} )
						}
						placeholder={ __( 'Owner key', 'corex' ) }
					/>
				</>
			) }
			<Button variant="primary" onClick={ props.onPreview }>
				{ __( 'Preview action', 'corex' ) }
			</Button>
			<Button variant="tertiary" onClick={ props.onClear }>
				{ __( 'Clear selection', 'corex' ) }
			</Button>
		</div>
	);
}

// The detail pane's id, so the row that opened it can say which element it controls.
const DRAWER_ID = 'corex-submission-detail';

function rowClassName( item, openId ) {
	return [
		item.read_at ? '' : 'is-unread',
		item.id === openId ? 'is-open' : '',
	]
		.filter( Boolean )
		.join( ' ' );
}

function InboxTable( { view, state, dispatch, open } ) {
	const trash = view === VIEW_TRASH;
	const openId = state.drawer.open ? state.drawer.id : 0;
	const all =
		state.items.length > 0 &&
		state.items.every( ( item ) => state.selectedIds.includes( item.id ) );
	if ( state.status === 'loading' && state.items.length === 0 ) {
		return (
			<div className="corex-inbox__state">
				<Spinner />
				{ __( 'Loading submissions…', 'corex' ) }
			</div>
		);
	}
	if ( state.status !== 'loading' && state.items.length === 0 ) {
		return (
			<div className="corex-inbox__state">
				<h3>
					{ trash
						? __( 'Nothing in the trash', 'corex' )
						: __( 'No matching submissions', 'corex' ) }
				</h3>
				<p>
					{ trash
						? __(
								'A submission moved to the trash is listed here, and can be restored.',
								'corex'
							)
						: __(
								'Change the filters or wait for a published flow to receive a response.',
								'corex'
							) }
				</p>
			</div>
		);
	}
	return (
		<div className="corex-inbox__table-wrap">
			<table className="corex-inbox__table">
				<thead>
					<tr>
						<th>
							<input
								type="checkbox"
								checked={ all }
								onChange={ () =>
									dispatch( {
										type: 'selectionChanged',
										ids: all
											? []
											: state.items.map(
													( item ) => item.id
												),
									} )
								}
								aria-label={ __( 'Select page', 'corex' ) }
							/>
						</th>
						<th>{ __( 'Submitter', 'corex' ) }</th>
						<th>{ __( 'Flow', 'corex' ) }</th>
						<th>{ __( 'Status', 'corex' ) }</th>
						{ /* Whether a notification went out says nothing of a submission in the
						     trash, and with an eighth column the table ran past its edge. */ }
						{ ! trash && (
							<th>{ __( 'Notification', 'corex' ) }</th>
						) }
						<th>{ __( 'Owner', 'corex' ) }</th>
						<th>{ __( 'Received', 'corex' ) }</th>
						{ trash && (
							<th>{ __( 'Moved to trash', 'corex' ) }</th>
						) }
					</tr>
				</thead>
				<tbody>
					{ state.items.map( ( item ) => (
						<tr
							key={ item.id }
							className={ rowClassName( item, openId ) }
							aria-current={
								item.id === openId ? 'true' : undefined
							}
						>
							<td className="corex-inbox__select-cell">
								<input
									type="checkbox"
									checked={ state.selectedIds.includes(
										item.id
									) }
									onChange={ () =>
										dispatch( {
											type: 'selectionChanged',
											ids: toggleSubmission(
												state.selectedIds,
												item.id
											),
										} )
									}
									aria-label={ sprintf(
										/* translators: %d: submission ID. */
										__( 'Select submission %d', 'corex' ),
										item.id
									) }
								/>
							</td>
							<td>
								{ /* The row's one control. Its hit area is stretched over the
								     whole row in the stylesheet, so any cell opens the
								     submission and the keyboard still meets one stop per row. */ }
								<button
									type="button"
									className="corex-inbox__row-button"
									aria-expanded={ item.id === openId }
									aria-controls={ DRAWER_ID }
									onClick={ () => open( item.id ) }
								>
									<span
										className="corex-inbox__unread"
										aria-hidden="true"
									/>
									<strong>
										{ item.submitter_name ||
											__( 'Anonymous', 'corex' ) }
									</strong>
									<small>
										{ item.submitter_email ||
											`#${ item.id }` }
									</small>
									{ item.is_test && (
										<em>{ __( 'Test', 'corex' ) }</em>
									) }
								</button>
							</td>
							<td>{ item.flow || item.form }</td>
							<td>
								<StatusBadge status={ item.status } />
							</td>
							{ ! trash && (
								<td>
									<DeliveryBadge delivery={ item.delivery } />
								</td>
							) }
							<td>
								{ item.owner_type === 'none'
									? __( 'Unassigned', 'corex' )
									: `${ item.owner_type }:${ item.owner_key }` }
							</td>
							<td>
								<CorexTime
									value={ item.created_at }
									absent={ __( 'Not recorded', 'corex' ) }
								/>
							</td>
							{ trash && (
								<td className="corex-inbox__trashed">
									<CorexTime
										value={ item.trashed_at }
										absent={ __( 'Not recorded', 'corex' ) }
									/>
									{ item.trashed_by_name && (
										<small>{ item.trashed_by_name }</small>
									) }
									{ deletesAt(
										item.trashed_at,
										state.trashDays
									) && (
										<small>
											{ __(
												'Deleted for good:',
												'corex'
											) }{ ' ' }
											<CorexTime
												value={ deletesAt(
													item.trashed_at,
													state.trashDays
												) }
											/>
										</small>
									) }
								</td>
							) }
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}

function Pagination( { state, filters, update } ) {
	const pages = Math.max( 1, Math.ceil( state.total / filters.perPage ) );
	return (
		<nav
			className="corex-inbox__pagination"
			aria-label={ __( 'Inbox pages', 'corex' ) }
		>
			<span>
				{ sprintf(
					/* translators: 1: current page, 2: total pages. */
					__( 'Page %1$d of %2$d', 'corex' ),
					filters.page,
					pages
				) }
			</span>
			<Button
				disabled={ filters.page <= 1 }
				onClick={ () => update( 'page', filters.page - 1 ) }
			>
				{ __( 'Previous', 'corex' ) }
			</Button>
			<Button
				disabled={ filters.page >= pages }
				onClick={ () => update( 'page', filters.page + 1 ) }
			>
				{ __( 'Next', 'corex' ) }
			</Button>
		</nav>
	);
}

/**
 * Asked before the open submission is moved to the trash (spec 105, FR-002).
 *
 * @param {Object}   props
 * @param {Function} props.close Leaves without moving it.
 * @param {Function} props.apply Moves it.
 * @return {import('react').ReactElement} The dialog.
 */
function ConfirmTrash( { close, apply } ) {
	const words = trashConfirmation( 'trash', 1 );

	return (
		<CorexDialog
			title={ words.title }
			onClose={ close }
			footer={
				<div className="corex-inbox__modal-actions">
					<Button variant="tertiary" onClick={ close }>
						{ __( 'Cancel', 'corex' ) }
					</Button>
					<Button variant="primary" onClick={ apply }>
						{ words.confirm }
					</Button>
				</div>
			}
		>
			<p>{ words.body }</p>
		</CorexDialog>
	);
}

/**
 * Asked before submissions are deleted for good (spec 105, FR-012 and FR-014): what goes with
 * them, that it cannot be undone, and a box to tick before the action can be taken.
 *
 * @param {Object}   props
 * @param {number}   props.count       How many submissions.
 * @param {Function} props.loadExports Asks for the export files that are kept.
 * @param {Function} props.close       Leaves without deleting.
 * @param {Function} props.apply       Deletes.
 * @return {import('react').ReactElement} The dialog.
 */
function ConfirmDelete( { count, loadExports, close, apply } ) {
	const words = deleteConfirmation( count );
	const acknowledgeId = useId();
	const [ acknowledged, setAcknowledged ] = useState( false );
	const [ working, setWorking ] = useState( false );
	// Said only where there is a kept export file for it to be true of.
	const [ keptExports, setKeptExports ] = useState( false );
	useEffect( () => {
		let showing = true;
		loadExports().then( ( result ) => {
			const entries = result?.envelope?.data?.exports;
			if ( showing && Array.isArray( entries ) ) {
				setKeptExports(
					entries.some( ( entry ) => entry.state === 'ready' )
				);
			}
		} );
		return () => {
			showing = false;
		};
	}, [ loadExports ] );

	return (
		<CorexDialog
			title={ words.title }
			onClose={ close }
			footer={
				<div className="corex-inbox__modal-actions">
					<Button variant="tertiary" onClick={ close }>
						{ __( 'Cancel', 'corex' ) }
					</Button>
					<Button
						variant="primary"
						isDestructive
						disabled={ ! acknowledged || working }
						onClick={ async () => {
							setWorking( true );
							await apply();
						} }
					>
						{ words.confirm }
					</Button>
				</div>
			}
		>
			<div className="corex-inbox__deletion">
				<p>{ words.body }</p>
				<div>
					<p>{ __( 'What goes with it:', 'corex' ) }</p>
					<ul>
						{ words.removes.map( ( line ) => (
							<li key={ line }>{ line }</li>
						) ) }
					</ul>
				</div>
				{ keptExports && (
					<p className="corex-inbox__deletion-note">
						{ words.exports }
					</p>
				) }
				<div className="corex-inbox__acknowledge">
					<input
						id={ acknowledgeId }
						type="checkbox"
						checked={ acknowledged }
						onChange={ ( event ) =>
							setAcknowledged( event.target.checked )
						}
					/>
					<label htmlFor={ acknowledgeId }>
						{ words.acknowledge }
					</label>
				</div>
			</div>
		</CorexDialog>
	);
}

function ConfirmBulk( { preview, close, apply } ) {
	// Moving to the trash and out of it say what becomes of the submissions. The older actions
	// keep the sentence they had.
	const words = [ 'trash', 'restore' ].includes( preview.action )
		? trashConfirmation( preview.action, preview.count )
		: null;

	return (
		<CorexDialog
			title={ words ? words.title : __( 'Confirm bulk action', 'corex' ) }
			onClose={ close }
			footer={
				<div className="corex-inbox__modal-actions">
					<Button variant="tertiary" onClick={ close }>
						{ __( 'Cancel', 'corex' ) }
					</Button>
					<Button variant="primary" onClick={ apply }>
						{ words
							? words.confirm
							: __( 'Confirm and apply', 'corex' ) }
					</Button>
				</div>
			}
		>
			<p>
				{ words
					? words.body
					: sprintf(
							/* translators: 1: bulk action name, 2: number of submissions affected. */
							__(
								'%1$s will affect exactly %2$d submissions.',
								'corex'
							),
							preview.action,
							preview.count
						) }
			</p>
		</CorexDialog>
	);
}

const root = document.getElementById( 'corex-submissions-app' );
if ( root ) {
	if ( typeof createRoot === 'function' ) {
		createRoot( root ).render( <App /> );
	} else {
		render( <App />, root );
	}
}
