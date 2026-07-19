import {
	createRoot,
	render,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { Button, Modal, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
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

	return <label>
		<span>{ __( 'Form', 'corex' ) }</span>
		{ /* The value is the flow ID because that is what the inbox stores (meta corex_flow_id) —
		     the data explorer keys the same list by slug instead. The owner picks a name either
		     way; they used to have to know the number.

		     aria-label because a <label> wrapping a control names it from the label's whole
		     subtree, and an embedded control contributes its VALUE — so this would announce as
		     "Form All forms" and rename itself every time the selection changed. */ }
		<select aria-label={ __( 'Form', 'corex' ) } value={ value }
			onChange={ ( event ) => update( 'flow', event.target.value ) }>
			<option value="">{ __( 'All forms', 'corex' ) }</option>
			{ flows.map( ( flow ) => <option key={ flow.id } value={ flow.id }>{ flow.name }</option> ) }
		</select>
	</label>;
}

function Filters( { filters, update, flows } ) {
	return <section className="corex-inbox__filters" aria-label={ __( 'Submission filters', 'corex' ) }>
		<label className="is-wide"><span>{ __( 'Search', 'corex' ) }</span><input type="search" value={ filters.search } onChange={ ( event ) => update( 'search', event.target.value ) } placeholder={ __( 'Name, email, or flow', 'corex' ) } /></label>
		<FormFilter flows={ flows } value={ filters.flow } update={ update } />
		<label><span>{ __( 'Status', 'corex' ) }</span><select value={ filters.status } onChange={ ( event ) => update( 'status', event.target.value ) }><option value="">{ __( 'All statuses', 'corex' ) }</option>{ STATUSES.map( ( status ) => <option key={ status } value={ status }>{ STATUS_LABELS[ status ] }</option> ) }</select></label>
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
		<select value={ props.action } onChange={ ( event ) => props.setAction( event.target.value ) } aria-label={ __( 'Bulk action', 'corex' ) }>
			<option value="mark_read">{ __( 'Mark read', 'corex' ) }</option><option value="assign">{ __( 'Assign', 'corex' ) }</option><option value="mark_spam">{ __( 'Mark spam', 'corex' ) }</option><option value="archive">{ __( 'Archive', 'corex' ) }</option>
		</select>
		{ props.action === 'assign' && <><select value={ props.owner.owner_type } onChange={ ( e ) => props.setOwner( { ...props.owner, owner_type: e.target.value } ) }><option value="user">{ __( 'User', 'corex' ) }</option><option value="team">{ __( 'Team', 'corex' ) }</option><option value="role">{ __( 'Role', 'corex' ) }</option><option value="none">{ __( 'Unassigned', 'corex' ) }</option></select><input value={ props.owner.owner_key } disabled={ props.owner.owner_type === 'none' } onChange={ ( e ) => props.setOwner( { ...props.owner, owner_key: e.target.value } ) } placeholder={ __( 'Owner key', 'corex' ) } /></> }
		<Button variant="primary" onClick={ props.onPreview }>{ __( 'Preview action', 'corex' ) }</Button><Button variant="tertiary" onClick={ props.onClear }>{ __( 'Clear selection', 'corex' ) }</Button>
	</div>;
}

function InboxTable( { state, dispatch, open } ) {
	const all = state.items.length > 0 && state.items.every( ( item ) => state.selectedIds.includes( item.id ) );
	if ( state.status === 'loading' && state.items.length === 0 ) return <div className="corex-inbox__state"><Spinner />{ __( 'Loading submissions…', 'corex' ) }</div>;
	if ( state.status !== 'loading' && state.items.length === 0 ) return <div className="corex-inbox__state"><h3>{ __( 'No matching submissions', 'corex' ) }</h3><p>{ __( 'Change the filters or wait for a published flow to receive a response.', 'corex' ) }</p></div>;
	return <div className="corex-inbox__table-wrap"><table className="corex-inbox__table"><thead><tr>
		<th><input type="checkbox" checked={ all } onChange={ () => dispatch( { type: 'selectionChanged', ids: all ? [] : state.items.map( ( item ) => item.id ) } ) } aria-label={ __( 'Select page', 'corex' ) } /></th><th>{ __( 'Submitter', 'corex' ) }</th><th>{ __( 'Flow', 'corex' ) }</th><th>{ __( 'Status', 'corex' ) }</th><th>{ __( 'Owner', 'corex' ) }</th><th>{ __( 'Received', 'corex' ) }</th>
	</tr></thead><tbody>{ state.items.map( ( item ) => <tr key={ item.id } className={ item.read_at ? '' : 'is-unread' }>
		<td><input type="checkbox" checked={ state.selectedIds.includes( item.id ) } onChange={ () => dispatch( { type: 'selectionChanged', ids: toggleSubmission( state.selectedIds, item.id ) } ) } aria-label={ sprintf( __( 'Select submission %d', 'corex' ), item.id ) } /></td>
		<td><button className="corex-inbox__row-button" onClick={ () => open( item.id ) }><span className="corex-inbox__unread" aria-hidden="true" /><strong>{ item.submitter_name || __( 'Anonymous', 'corex' ) }</strong><small>{ item.submitter_email || `#${ item.id }` }</small>{ item.is_test && <em>{ __( 'Test', 'corex' ) }</em> }</button></td>
		<td>{ item.flow || item.form }</td><td><StatusBadge status={ item.status } /></td><td>{ item.owner_type === 'none' ? __( 'Unassigned', 'corex' ) : `${ item.owner_type }:${ item.owner_key }` }</td><td>{ item.created_at || '' }</td>
	</tr> ) }</tbody></table></div>;
}

function StatusBadge( { status } ) { return <span className={ `corex-inbox__status is-${ status }` }>{ STATUS_LABELS[ status ] || status }</span>; }

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
		<div className="corex-inbox__drawer-actions"><select value={ record.status } onChange={ ( e ) => inbox.update( record.id, { status: e.target.value, expected_updated_at: record.updated_at } ) }>{ STATUSES.map( ( status ) => <option key={ status } value={ status }>{ STATUS_LABELS[ status ] }</option> ) }</select>{ ! record.read_at && <Button onClick={ () => inbox.update( record.id, { mark_read: true, expected_updated_at: record.updated_at } ) }>{ __( 'Mark read', 'corex' ) }</Button> }</div>
		<section><h3>{ __( 'Assignment', 'corex' ) }</h3><div className="corex-inbox__assignment"><select value={ owner.owner_type } onChange={ ( e ) => setOwner( { ...owner, owner_type: e.target.value, owner_key: e.target.value === 'none' ? '' : owner.owner_key } ) }><option value="none">{ __( 'Unassigned', 'corex' ) }</option><option value="user">{ __( 'User', 'corex' ) }</option><option value="team">{ __( 'Team', 'corex' ) }</option><option value="role">{ __( 'Role', 'corex' ) }</option></select><input value={ owner.owner_key } disabled={ owner.owner_type === 'none' } onChange={ ( e ) => setOwner( { ...owner, owner_key: e.target.value } ) } placeholder={ __( 'Eligible owner key', 'corex' ) } /><Button variant="secondary" onClick={ () => inbox.update( record.id, { ...owner, expected_updated_at: record.updated_at } ) }>{ __( 'Assign', 'corex' ) }</Button></div></section>
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
	return <Modal title={ __( 'Export submissions', 'corex' ) } onRequestClose={ close } className="corex-inbox__export-modal"><label>{ __( 'Scope', 'corex' ) }<select value={ scope } onChange={ ( e ) => setScope( e.target.value ) }><option value="selected" disabled={ ! selectedIds.length }>{ __( 'Selected rows', 'corex' ) }</option><option value="filtered">{ __( 'Current filter', 'corex' ) }</option><option value="accessible">{ __( 'All accessible', 'corex' ) }</option></select></label><fieldset><legend>{ __( 'Columns', 'corex' ) }</legend>{ choices.map( ( choice ) => <label key={ choice }><input type="checkbox" checked={ columns.includes( choice ) } onChange={ () => setColumns( columns.includes( choice ) ? columns.filter( ( item ) => item !== choice ) : [ ...columns, choice ] ) } /> { choice.replaceAll( '_', ' ' ) }</label> ) }</fieldset><label><input type="checkbox" checked={ includeTest } onChange={ ( e ) => setIncludeTest( e.target.checked ) } /> { __( 'Include marked tests', 'corex' ) }</label>{ personal && <label className="corex-inbox__warning"><input type="checkbox" checked={ acknowledged } onChange={ ( e ) => setAcknowledged( e.target.checked ) } /> { __( 'I understand this export contains personal data and will handle it according to policy.', 'corex' ) }</label> }<div className="corex-inbox__modal-actions"><Button onClick={ refresh }>{ __( 'Refresh history', 'corex' ) }</Button><Button variant="primary" disabled={ ! columns.length || ( personal && ! acknowledged ) } onClick={ async () => { const data = await inbox.createExport( buildExportPayload( { scope, selectedIds, columns, includeTest, acknowledged, filters: { search: filters.search, flow: filters.flow, status: filters.status, owner: filters.owner, date_from: filters.dateFrom, date_to: filters.dateTo } } ) ); if ( data ) refresh(); } }>{ __( 'Create export', 'corex' ) }</Button></div><ul className="corex-inbox__export-history">{ history.map( ( item ) => <li key={ item.id }><span>#{ item.id } · { item.scope } · { item.record_count }</span><Button variant="link" onClick={ async () => { const result = await inbox.downloadExport( item.id ); if ( result.envelope.ok ) downloadCsv( result.envelope.data.artifact ); } }>{ __( 'Download', 'corex' ) }</Button></li> ) }</ul></Modal>;
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
