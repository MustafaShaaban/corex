import {
	createRoot,
	render,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { Button, Modal, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import CorexSelect from '../admin/components/CorexSelect.js';
import { buildExportPayload, toggleSubmission } from './inbox.js';
import { useInbox } from './useInbox.js';

const config = window.corexSubmissions || { restUrl: '', nonce: '', flows: [] };
// Empty when the forms add-on is absent (Principle IX) — the form filter simply does not render.
const FLOWS = Array.isArray( config.flows ) ? config.flows : [];
const STATUSES = [ 'new', 'in_progress', 'replied', 'closed', 'spam', 'archived' ];
const STATUS_LABELS = {
	new: __( 'New', 'corex' ),
	in_progress: __( 'In progress', 'corex' ),
	replied: __( 'Replied', 'corex' ),
	closed: __( 'Closed', 'corex' ),
	spam: __( 'Spam', 'corex' ),
	archived: __( 'Archived', 'corex' ),
};
const STATUS_OPTIONS = STATUSES.map( ( status ) => ( {
	value: status,
	label: STATUS_LABELS[ status ],
} ) );
const BULK_ACTIONS = [
	{ value: 'mark_read', label: __( 'Mark read', 'corex' ) },
	{ value: 'assign', label: __( 'Assign', 'corex' ) },
	{ value: 'mark_spam', label: __( 'Mark spam', 'corex' ) },
	{ value: 'archive', label: __( 'Archive', 'corex' ) },
];
const OWNER_TYPES = [
	{ value: 'user', label: __( 'User', 'corex' ) },
	{ value: 'team', label: __( 'Team', 'corex' ) },
	{ value: 'role', label: __( 'Role', 'corex' ) },
	{ value: 'none', label: __( 'Unassigned', 'corex' ) },
];
// The drawer leads with Unassigned because that is the state most rows start in.
const ASSIGNMENT_OWNER_TYPES = [
	OWNER_TYPES[ 3 ],
	OWNER_TYPES[ 0 ],
	OWNER_TYPES[ 1 ],
	OWNER_TYPES[ 2 ],
];

/**
 * Export scopes, minus any that cannot apply right now.
 *
 * The native control kept "Selected rows" in the list and disabled it when nothing was selected.
 * An option that can never be chosen is better left out than shown greyed: the list then only
 * ever offers things that work.
 *
 * @param {number} selectedCount How many rows are currently ticked.
 * @return {Array} Option list for the scope control.
 */
function exportScopes( selectedCount ) {
	return [
		...( selectedCount
			? [ { value: 'selected', label: __( 'Selected rows', 'corex' ) } ]
			: [] ),
		{ value: 'filtered', label: __( 'Current filter', 'corex' ) },
		{ value: 'accessible', label: __( 'All accessible', 'corex' ) },
	];
}

function App() {
	const [ filters, setFilters ] = useState( {
		search: '', flow: '', status: '', owner: '', dateFrom: '', dateTo: '',
		includeTest: false, page: 1, perPage: 25,
	} );
	const inbox = useInbox( config, filters );
	const [ bulkAction, setBulkAction ] = useState( 'mark_read' );
	const [ bulkOwner, setBulkOwner ] = useState( { owner_type: 'user', owner_key: '' } );
	const [ preview, setPreview ] = useState( null );
	const [ exportOpen, setExportOpen ] = useState( false );
	const updateFilter = ( key, value ) => setFilters( ( current ) => ( {
		...current, [ key ]: value, page: key === 'page' ? value : 1,
	} ) );

	return (
		<div className="corex-inbox" data-status={ inbox.state.status }>
			<InboxHeader total={ inbox.state.total } onExport={ () => setExportOpen( true ) } />
			{ inbox.state.message && <div className="corex-inbox__notice is-success" role="status">{ inbox.state.message }</div> }
			{ inbox.state.error && <div className="corex-inbox__notice is-error" role="alert">{ inbox.state.error }</div> }
			<Filters filters={ filters } update={ updateFilter } flows={ FLOWS } />
			<BulkToolbar
				count={ inbox.state.selectedIds.length }
				action={ bulkAction }
				setAction={ setBulkAction }
				owner={ bulkOwner }
				setOwner={ setBulkOwner }
				onClear={ () => inbox.dispatch( { type: 'selectionChanged', ids: [] } ) }
				onPreview={ async () => {
					const data = await inbox.previewBulk( bulkAction, inbox.state.selectedIds, bulkAction === 'assign' ? bulkOwner : {} );
					if ( data ) setPreview( data.preview );
				} }
			/>
			<InboxTable state={ inbox.state } dispatch={ inbox.dispatch } open={ inbox.open } />
			<Pagination state={ inbox.state } filters={ filters } update={ updateFilter } />
			{ inbox.state.drawer.open && <DetailDrawer drawer={ inbox.state.drawer } inbox={ inbox } /> }
			{ preview && <ConfirmBulk preview={ preview } close={ () => setPreview( null ) } apply={ async () => {
				await inbox.applyBulk( preview.token ); setPreview( null );
			} } /> }
			{ exportOpen && <ExportModal close={ () => setExportOpen( false ) } inbox={ inbox } filters={ filters } selectedIds={ inbox.state.selectedIds } /> }
		</div>
	);
}

function InboxHeader( { total, onExport } ) {
	return <header className="corex-inbox__header">
		<div><p className="corex-inbox__eyebrow">{ __( 'Team workspace', 'corex' ) }</p>
		<h2>{ __( 'Submission Inbox', 'corex' ) }</h2>
		<p>{ sprintf( _n( '%d accessible submission', '%d accessible submissions', total, 'corex' ), total ) }</p></div>
		<Button variant="primary" onClick={ onExport }>{ __( 'Export', 'corex' ) }</Button>
	</header>;
}

function FormFilter( { flows, value, update } ) {
	// No forms yet — say so rather than present an empty control that looks broken. The filter is
	// also absent entirely when the forms add-on is not installed (flows arrives empty).
	if ( ! flows.length ) {
		return null;
	}

	return <div className="corex-field">
		<span>{ __( 'Form', 'corex' ) }</span>
		{ /* The value is the flow ID because that is what the inbox stores (meta corex_flow_id) —
		     the data explorer keys the same list by slug instead. The owner picks a name either
		     way; they used to have to know the number.

		     aria-label because a <label> wrapping a control names it from the label's whole
		     subtree, and an embedded control contributes its VALUE — so this would announce as
		     "Form All forms" and rename itself every time the selection changed. */ }
		<CorexSelect label={ __( 'Form', 'corex' ) } value={ value }
			options={ [ { value: '', label: __( 'All forms', 'corex' ) },
				...flows.map( ( flow ) => ( { value: String( flow.id ), label: flow.name } ) ) ] }
			onChange={ ( flow ) => update( 'flow', flow ) } block />
	</div>;
}

function Filters( { filters, update, flows } ) {
	return <section className="corex-inbox__filters" aria-label={ __( 'Submission filters', 'corex' ) }>
		<label className="is-wide"><span>{ __( 'Search', 'corex' ) }</span><input type="search" value={ filters.search } onChange={ ( event ) => update( 'search', event.target.value ) } placeholder={ __( 'Name, email, or flow', 'corex' ) } /></label>
		<FormFilter flows={ flows } value={ filters.flow } update={ update } />
		<div className="corex-field"><span>{ __( 'Status', 'corex' ) }</span><CorexSelect label={ __( 'Status', 'corex' ) } value={ filters.status } options={ [ { value: '', label: __( 'All statuses', 'corex' ) }, ...STATUSES.map( ( status ) => ( { value: status, label: STATUS_LABELS[ status ] } ) ) ] } onChange={ ( status ) => update( 'status', status ) } block /></div>
		<label><span>{ __( 'Owner', 'corex' ) }</span><input value={ filters.owner } onChange={ ( event ) => update( 'owner', event.target.value ) } placeholder="team:sales" /></label>
		<label><span>{ __( 'From', 'corex' ) }</span><input type="date" value={ filters.dateFrom } onChange={ ( event ) => update( 'dateFrom', event.target.value ) } /></label>
		<label><span>{ __( 'To', 'corex' ) }</span><input type="date" value={ filters.dateTo } onChange={ ( event ) => update( 'dateTo', event.target.value ) } /></label>
		<label className="is-check"><input type="checkbox" checked={ filters.includeTest } onChange={ ( event ) => update( 'includeTest', event.target.checked ) } /> <span>{ __( 'Include marked tests', 'corex' ) }</span></label>
	</section>;
}

function BulkToolbar( props ) {
	if ( props.count === 0 ) return null;
	return <div className="corex-inbox__bulk" role="region" aria-label={ __( 'Bulk actions', 'corex' ) }>
		<strong>{ sprintf( __( '%d selected', 'corex' ), props.count ) }</strong>
		<CorexSelect label={ __( 'Bulk action', 'corex' ) } value={ props.action } options={ BULK_ACTIONS } onChange={ props.setAction } />
		{ props.action === 'assign' && <><CorexSelect label={ __( 'Owner type', 'corex' ) } value={ props.owner.owner_type } options={ OWNER_TYPES } onChange={ ( ownerType ) => props.setOwner( { ...props.owner, owner_type: ownerType } ) } /><input value={ props.owner.owner_key } disabled={ props.owner.owner_type === 'none' } onChange={ ( e ) => props.setOwner( { ...props.owner, owner_key: e.target.value } ) } placeholder={ __( 'Owner key', 'corex' ) } /></> }
		<Button variant="primary" onClick={ props.onPreview }>{ __( 'Preview action', 'corex' ) }</Button><Button variant="tertiary" onClick={ props.onClear }>{ __( 'Clear selection', 'corex' ) }</Button>
	</div>;
}

function InboxTable( { state, dispatch, open } ) {
	const all = state.items.length > 0 && state.items.every( ( item ) => state.selectedIds.includes( item.id ) );
	if ( state.status === 'loading' && state.items.length === 0 ) return <div className="corex-inbox__state"><Spinner />{ __( 'Loading submissions…', 'corex' ) }</div>;
	if ( state.status !== 'loading' && state.items.length === 0 ) return <div className="corex-inbox__state"><h3>{ __( 'No matching submissions', 'corex' ) }</h3><p>{ __( 'Change the filters or wait for a published flow to receive a response.', 'corex' ) }</p></div>;
	return <div className="corex-inbox__table-wrap"><table className="corex-inbox__table"><thead><tr>
		<th><input type="checkbox" checked={ all } onChange={ () => dispatch( { type: 'selectionChanged', ids: all ? [] : state.items.map( ( item ) => item.id ) } ) } aria-label={ __( 'Select page', 'corex' ) } /></th><th>{ __( 'Submitter', 'corex' ) }</th><th>{ __( 'Flow', 'corex' ) }</th><th>{ __( 'Status', 'corex' ) }</th><th>{ __( 'Notification', 'corex' ) }</th><th>{ __( 'Owner', 'corex' ) }</th><th>{ __( 'Received', 'corex' ) }</th>
	</tr></thead><tbody>{ state.items.map( ( item ) => <tr key={ item.id } className={ item.read_at ? '' : 'is-unread' }>
		<td><input type="checkbox" checked={ state.selectedIds.includes( item.id ) } onChange={ () => dispatch( { type: 'selectionChanged', ids: toggleSubmission( state.selectedIds, item.id ) } ) } aria-label={ sprintf( __( 'Select submission %d', 'corex' ), item.id ) } /></td>
		<td><button className="corex-inbox__row-button" onClick={ () => open( item.id ) }><span className="corex-inbox__unread" aria-hidden="true" /><strong>{ item.submitter_name || __( 'Anonymous', 'corex' ) }</strong><small>{ item.submitter_email || `#${ item.id }` }</small>{ item.is_test && <em>{ __( 'Test', 'corex' ) }</em> }</button></td>
		<td>{ item.flow || item.form }</td><td><StatusBadge status={ item.status } /></td><td><DeliveryBadge delivery={ item.delivery } /></td><td>{ item.owner_type === 'none' ? __( 'Unassigned', 'corex' ) : `${ item.owner_type }:${ item.owner_key }` }</td><td>{ item.created_at || '' }</td>
	</tr> ) }</tbody></table></div>;
}

function StatusBadge( { status } ) { return <span className={ `corex-inbox__status is-${ status }` }>{ STATUS_LABELS[ status ] || status }</span>; }

// The notification-delivery states, each conveyed by text + icon + accessible name — never colour
// alone (WCAG 2.2 AA 1.4.1). "accepted" reads as accepted-for-delivery: a transport taking a message
// is not proof it reached an inbox (spec 071 FR-015). Written in this file's dense single-line style.
const DELIVERY_META = { accepted: { label: __( 'Notification accepted', 'corex' ), tone: 'success', icon: 'yes-alt' }, captured: { label: __( 'Notification captured', 'corex' ), tone: 'info', icon: 'download' }, queued: { label: __( 'Notification queued', 'corex' ), tone: 'info', icon: 'clock' }, sending: { label: __( 'Notification sending', 'corex' ), tone: 'info', icon: 'update' }, sent: { label: __( 'Notification sent', 'corex' ), tone: 'success', icon: 'yes-alt' }, opened: { label: __( 'Notification opened', 'corex' ), tone: 'success', icon: 'visibility' }, failed: { label: __( 'Notification failed', 'corex' ), tone: 'warning', icon: 'warning' }, rejected: { label: __( 'Notification rejected', 'corex' ), tone: 'warning', icon: 'dismiss' }, bounced: { label: __( 'Notification bounced', 'corex' ), tone: 'warning', icon: 'undo' }, not_attempted: { label: __( 'No notification', 'corex' ), tone: 'neutral', icon: 'minus' }, unavailable: { label: __( 'Delivery unavailable', 'corex' ), tone: 'neutral', icon: 'backup' } };

function DeliveryBadge( { delivery } ) { const meta = DELIVERY_META[ delivery?.status ] || DELIVERY_META.unavailable; return <span className={ `corex-inbox__delivery is-${ meta.tone }` } title={ delivery?.safe_reason || meta.label }><span className={ `dashicons dashicons-${ meta.icon }` } aria-hidden="true" /><span>{ meta.label }</span></span>; }

function Pagination( { state, filters, update } ) {
	const pages = Math.max( 1, Math.ceil( state.total / filters.perPage ) );
	return <nav className="corex-inbox__pagination" aria-label={ __( 'Inbox pages', 'corex' ) }><span>{ sprintf( __( 'Page %1$d of %2$d', 'corex' ), filters.page, pages ) }</span><Button disabled={ filters.page <= 1 } onClick={ () => update( 'page', filters.page - 1 ) }>{ __( 'Previous', 'corex' ) }</Button><Button disabled={ filters.page >= pages } onClick={ () => update( 'page', filters.page + 1 ) }>{ __( 'Next', 'corex' ) }</Button></nav>;
}

function DetailDrawer( { drawer, inbox } ) {
	const record = drawer.record;
	const [ note, setNote ] = useState( '' );
	const [ reply, setReply ] = useState( { subject: '', body: '' } );
	const [ owner, setOwner ] = useState( {
		owner_type: record?.owner_type || 'none',
		owner_key: record?.owner_key || '',
	} );
	const [ log, setLog ] = useState( null );
	useEffect( () => {
		if ( record ) {
			setOwner( {
				owner_type: record.owner_type || 'none',
				owner_key: record.owner_key || '',
			} );
		}
	}, [ record ] );
	if ( drawer.status === 'loading' ) return <aside className="corex-inbox__drawer" aria-label={ __( 'Submission detail', 'corex' ) }><Button onClick={ inbox.close }>{ __( 'Close', 'corex' ) }</Button><Spinner /></aside>;
	if ( ! record ) return <aside className="corex-inbox__drawer"><Button onClick={ inbox.close }>{ __( 'Close', 'corex' ) }</Button><p>{ drawer.error }</p></aside>;
	const emails = Object.values( record.related_emails?.bindings || {} ).filter( ( item ) => item?.attempt_id );
	return <aside className="corex-inbox__drawer" aria-labelledby="corex-submission-title"><header><div><p>{ record.flow }</p><h2 id="corex-submission-title">{ record.submitter_name || __( 'Anonymous submission', 'corex' ) }</h2><span>{ record.submitter_email }</span></div><Button icon="no-alt" label={ __( 'Close detail', 'corex' ) } onClick={ inbox.close } /></header>
		<div className="corex-inbox__drawer-actions"><CorexSelect label={ __( 'Status', 'corex' ) } value={ record.status } options={ STATUS_OPTIONS } block onChange={ ( status ) => inbox.update( record.id, { status, expected_updated_at: record.updated_at } ) } />{ ! record.read_at && <Button onClick={ () => inbox.update( record.id, { mark_read: true, expected_updated_at: record.updated_at } ) }>{ __( 'Mark read', 'corex' ) }</Button> }</div>
		<section><h3>{ __( 'Assignment', 'corex' ) }</h3><div className="corex-inbox__assignment"><CorexSelect label={ __( 'Owner type', 'corex' ) } value={ owner.owner_type } options={ ASSIGNMENT_OWNER_TYPES } onChange={ ( ownerType ) => setOwner( { ...owner, owner_type: ownerType, owner_key: ownerType === 'none' ? '' : owner.owner_key } ) } /><input value={ owner.owner_key } disabled={ owner.owner_type === 'none' } onChange={ ( e ) => setOwner( { ...owner, owner_key: e.target.value } ) } placeholder={ __( 'Eligible owner key', 'corex' ) } /><Button variant="secondary" onClick={ () => inbox.update( record.id, { ...owner, expected_updated_at: record.updated_at } ) }>{ __( 'Assign', 'corex' ) }</Button></div></section>
		<section className="corex-inbox__delivery-detail"><h3>{ __( 'Notification delivery', 'corex' ) }</h3><p><DeliveryBadge delivery={ record.delivery } /></p>{ record.delivery?.safe_reason && <p className="corex-inbox__muted">{ record.delivery.safe_reason }</p> }{ record.delivery?.attempted_at && <p className="corex-inbox__muted">{ sprintf( __( 'Attempted %s', 'corex' ), record.delivery.attempted_at ) }</p> }</section>
		<DetailSection title={ __( 'Submitted fields', 'corex' ) } value={ record.values } />
		<DetailSection title={ __( 'Hidden metadata', 'corex' ) } value={ record.hidden_metadata } />
		<DetailSection title={ __( 'UTM attribution', 'corex' ) } value={ record.utm } />
		<DetailSection title={ __( 'Consent snapshot', 'corex' ) } value={ record.consent_snapshot } />
		<section><h3>{ __( 'Internal notes', 'corex' ) }</h3><ul className="corex-inbox__timeline">{ ( record.notes || [] ).map( ( item ) => <li key={ item.id }><strong>#{ item.author_id }</strong> { item.body }<small>{ item.created_at }</small></li> ) }</ul><textarea value={ note } onChange={ ( e ) => setNote( e.target.value ) } placeholder={ __( 'Add a team note', 'corex' ) } /><Button variant="secondary" disabled={ ! note.trim() } onClick={ async () => { if ( await inbox.addNote( record.id, { body: note, visibility: 'corex-team' } ) ) setNote( '' ); } }>{ __( 'Add note', 'corex' ) }</Button></section>
		<section><h3>{ __( 'Email', 'corex' ) }</h3><input value={ reply.subject } onChange={ ( e ) => setReply( { ...reply, subject: e.target.value } ) } placeholder={ __( 'Reply subject', 'corex' ) } /><textarea value={ reply.body } onChange={ ( e ) => setReply( { ...reply, body: e.target.value } ) } placeholder={ __( 'Reply message', 'corex' ) } /><Button variant="secondary" disabled={ ! reply.subject || ! reply.body || ! record.submitter_email } onClick={ () => inbox.reply( record.id, reply ) }>{ __( 'Send reply', 'corex' ) }</Button><ul>{ emails.map( ( email ) => <li key={ email.attempt_id }><code>{ email.state }</code> <Button variant="link" onClick={ () => inbox.resend( record.id, email.attempt_id ) } disabled={ ! email.retryable }>{ __( 'Resend', 'corex' ) }</Button> <Button variant="link" onClick={ async () => { const result = await inbox.log( record.id, email.attempt_id ); if ( result.envelope.ok ) setLog( result.envelope.data.log ); } }>{ __( 'Open log', 'corex' ) }</Button></li> ) }</ul>{ log && <pre>{ JSON.stringify( log, null, 2 ) }</pre> }</section>
		<section><h3>{ __( 'Timeline', 'corex' ) }</h3><ul className="corex-inbox__timeline">{ ( record.timeline || [] ).map( ( event, index ) => <li key={ event.id || index }><strong>{ event.stage || event.kind }</strong><span>{ event.outcome || event.state }</span><small>{ event.created_at || event.occurred_at }</small></li> ) }</ul></section>
		<footer><span>{ sprintf( __( 'Version %d', 'corex' ), record.flow_version_id || 0 ) }</span><span>{ __( 'Retention:', 'corex' ) } { record.retention_state }</span><span>{ record.exported_at ? __( 'Exported', 'corex' ) : __( 'Not exported', 'corex' ) }</span></footer>
	</aside>;
}

function DetailSection( { title, value } ) {
	const entries = Object.entries( value || {} );
	return <section><h3>{ title }</h3>{ entries.length === 0 ? <p className="corex-inbox__muted">{ __( 'No data recorded.', 'corex' ) }</p> : <dl className="corex-inbox__fields">{ entries.map( ( [ key, item ] ) => <div key={ key }><dt>{ key }</dt><dd>{ typeof item === 'object' ? JSON.stringify( item ) : String( item ) }</dd></div> ) }</dl> }</section>;
}

function ConfirmBulk( { preview, close, apply } ) {
	return <Modal title={ __( 'Confirm bulk action', 'corex' ) } onRequestClose={ close }><p>{ sprintf( __( '%1$s will affect exactly %2$d submissions.', 'corex' ), preview.action, preview.count ) }</p><div className="corex-inbox__modal-actions"><Button variant="tertiary" onClick={ close }>{ __( 'Cancel', 'corex' ) }</Button><Button variant="primary" onClick={ apply }>{ __( 'Confirm and apply', 'corex' ) }</Button></div></Modal>;
}

function ExportModal( { close, inbox, filters, selectedIds } ) {
	const [ scope, setScope ] = useState( selectedIds.length ? 'selected' : 'filtered' );
	const [ columns, setColumns ] = useState( [ 'identity', 'workflow', 'submitted_fields' ] );
	const [ includeTest, setIncludeTest ] = useState( false );
	const [ acknowledged, setAcknowledged ] = useState( false );
	const [ history, setHistory ] = useState( [] );
	const personal = columns.some( ( item ) => [ 'submitted_fields', 'hidden_metadata', 'utm', 'consent_snapshot', 'notes' ].includes( item ) );
	const choices = useMemo( () => [ 'identity', 'workflow', 'submitted_fields', 'hidden_metadata', 'utm', 'consent_snapshot', 'notes' ], [] );
	const refresh = async () => { const result = await inbox.loadExports(); if ( result.envelope.ok ) setHistory( result.envelope.data.exports ); };
	return <Modal title={ __( 'Export submissions', 'corex' ) } onRequestClose={ close } className="corex-inbox__export-modal"><label>{ __( 'Scope', 'corex' ) }<CorexSelect label={ __( 'Scope', 'corex' ) } value={ scope } options={ exportScopes( selectedIds.length ) } onChange={ setScope } /></label><fieldset><legend>{ __( 'Columns', 'corex' ) }</legend>{ choices.map( ( choice ) => <label key={ choice }><input type="checkbox" checked={ columns.includes( choice ) } onChange={ () => setColumns( columns.includes( choice ) ? columns.filter( ( item ) => item !== choice ) : [ ...columns, choice ] ) } /> { choice.replaceAll( '_', ' ' ) }</label> ) }</fieldset><label><input type="checkbox" checked={ includeTest } onChange={ ( e ) => setIncludeTest( e.target.checked ) } /> { __( 'Include marked tests', 'corex' ) }</label>{ personal && <label className="corex-inbox__warning"><input type="checkbox" checked={ acknowledged } onChange={ ( e ) => setAcknowledged( e.target.checked ) } /> { __( 'I understand this export contains personal data and will handle it according to policy.', 'corex' ) }</label> }<div className="corex-inbox__modal-actions"><Button onClick={ refresh }>{ __( 'Refresh history', 'corex' ) }</Button><Button variant="primary" disabled={ ! columns.length || ( personal && ! acknowledged ) } onClick={ async () => { const data = await inbox.createExport( buildExportPayload( { scope, selectedIds, columns, includeTest, acknowledged, filters: { search: filters.search, flow: filters.flow, status: filters.status, owner: filters.owner, date_from: filters.dateFrom, date_to: filters.dateTo } } ) ); if ( data ) refresh(); } }>{ __( 'Create export', 'corex' ) }</Button></div><ul className="corex-inbox__export-history">{ history.map( ( item ) => <li key={ item.id }><span>#{ item.id } · { item.scope } · { item.record_count }</span><Button variant="link" onClick={ async () => { const result = await inbox.downloadExport( item.id ); if ( result.envelope.ok ) downloadCsv( result.envelope.data.artifact ); } }>{ __( 'Download', 'corex' ) }</Button></li> ) }</ul></Modal>;
}

function downloadCsv( artifact ) {
	const url = URL.createObjectURL( new Blob( [ artifact.csv ], { type: 'text/csv;charset=utf-8' } ) );
	const link = document.createElement( 'a' ); link.href = url; link.download = artifact.filename; link.click(); URL.revokeObjectURL( url );
}

const root = document.getElementById( 'corex-submissions-app' );
if ( root ) {
	if ( typeof createRoot === 'function' ) createRoot( root ).render( <App /> );
	else render( <App />, root );
}
