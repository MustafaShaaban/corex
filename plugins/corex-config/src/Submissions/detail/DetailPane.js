/**
 * The detail pane: one submission, in the order it is read (spec 103, US5).
 *
 * Who sent it and how to reach them, then what they said, under the questions the form asked.
 * Then what somebody does about it: reply, note, triage. Then what happened to its notification,
 * and last the details almost nobody opens.
 *
 * It is a drawer form of `CorexDialog`, so the browser moves focus in, keeps it there, closes on
 * Escape and hands focus back to the row it was opened from.
 */
import { useId, useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import CorexDialog from '../../admin/components/CorexDialog.js';
import CorexErrorState from '../../admin/components/CorexErrorState.js';
import CorexSelect from '../../admin/components/CorexSelect.js';
import CorexTime from '../../admin/components/CorexTime.js';
import FieldValue from '../../admin/components/FieldValue.js';
import { DeliveryBadge, STATUS_OPTIONS, StatusBadge } from '../badges.js';
import { cannotDeleteReason } from '../inbox.js';
import {
	answersOf,
	assignmentOf,
	contactOf,
	historyLine,
	identityOf,
	ownerOptions,
	ownerValue,
	technicalGroups,
	untrackedReason,
} from './detailState.js';

/**
 * What `FieldValue` should be handed for one stored value.
 *
 * A described attachment goes through untouched so it renders as a link; anything else that is an
 * object is still JSON, which is unlovely but honest — better than "[object Object]" and better
 * than dropping a value nobody anticipated (#138 item 6).
 *
 * @param {*} item One stored value.
 * @return {*} The value, or its JSON.
 */
function displayable( item ) {
	if ( item !== null && typeof item === 'object' ) {
		if (
			Array.isArray( item ) &&
			item.every( ( part ) => typeof part !== 'object' )
		) {
			return item.join( ', ' );
		}
		return typeof item.id === 'number' && 'missing' in item
			? item
			: JSON.stringify( item );
	}

	return item;
}

function Fields( { entries } ) {
	return (
		<dl className="corex-pane__fields">
			{ entries.map( ( { key, label, value } ) => (
				<div key={ key }>
					<dt>{ label }</dt>
					<dd>
						{ /* A visitor writes in their language, not the admin's: an
						     English answer in an Arabic admin had its full stop on the
						     wrong side. */ }
						<bdi>
							<FieldValue value={ displayable( value ) } />
						</bdi>
					</dd>
				</div>
			) ) }
		</dl>
	);
}

/**
 * @param {Object}   props
 * @param {Object}   props.drawer      The pane's state: which submission, loading or not, and the record.
 * @param {Object}   props.inbox       The inbox's data and requests.
 * @param {string}   props.id          The id the row's button says it controls.
 * @param {Function} props.onTrash     Called when somebody wants the open submission in the trash.
 * @param {boolean}  [props.mayDelete] Whether this person may delete a submission for good.
 * @param {Function} props.onDelete    Called when somebody wants the open submission deleted for good.
 * @return {import('react').ReactElement} The pane.
 */
export default function DetailPane( {
	drawer,
	inbox,
	id,
	onTrash,
	mayDelete = false,
	onDelete,
} ) {
	const record = drawer.record;

	if ( ! record ) {
		return (
			<CorexDialog
				id={ id }
				variant="drawer"
				className="corex-inbox__drawer corex-pane"
				title={ __( 'Submission', 'corex' ) }
				onClose={ inbox.close }
			>
				{ drawer.status === 'loading' ? (
					<div className="corex-pane__waiting" role="status">
						<Spinner />
						{ __( 'Loading the submission…', 'corex' ) }
					</div>
				) : (
					<CorexErrorState
						scale="panel"
						message={
							drawer.error ||
							__( 'The submission could not be loaded.', 'corex' )
						}
					/>
				) }
			</CorexDialog>
		);
	}

	return (
		<CorexDialog
			id={ id }
			variant="drawer"
			className="corex-inbox__drawer corex-pane"
			title={
				record.submitter_name || __( 'Anonymous submission', 'corex' )
			}
			subtitle={ <PaneHeader record={ record } inbox={ inbox } /> }
			onClose={ inbox.close }
		>
			{ /* A trashed submission is read and nothing else, until it is restored (spec 105,
			     FR-006): no reply, no new note, no status or owner to change. */ }
			{ record.trashed && (
				<TrashLine
					record={ record }
					inbox={ inbox }
					mayDelete={ mayDelete }
					onDelete={ onDelete }
				/>
			) }
			<Answers record={ record } />
			{ ! record.trashed && <Reply record={ record } inbox={ inbox } /> }
			<Notes
				record={ record }
				inbox={ inbox }
				readOnly={ Boolean( record.trashed ) }
			/>
			{ ! record.trashed && (
				<Triage record={ record } inbox={ inbox } onTrash={ onTrash } />
			) }
			<Delivery record={ record } />
			<TechnicalDetails record={ record } />
			<History record={ record } />
			<footer className="corex-pane__facts">
				<span>
					{ sprintf(
						/* translators: %s: how a submission is being retained, e.g. "active". */
						__( 'Retention: %s', 'corex' ),
						record.retention_state
					) }
				</span>
				<span>
					{ record.exported_at
						? __( 'Exported', 'corex' )
						: __( 'Not exported', 'corex' ) }
				</span>
			</footer>
		</CorexDialog>
	);
}

/**
 * Says a submission is in the trash, since when and by whom, and offers the one thing that can
 * be done with it there.
 *
 * @param {Object}   props
 * @param {Object}   props.record    The trashed submission.
 * @param {Object}   props.inbox     The inbox's requests.
 * @param {boolean}  props.mayDelete Whether this person may delete it for good.
 * @param {Function} props.onDelete  Called when they ask to.
 * @return {import('react').ReactElement} The line.
 */
function TrashLine( { record, inbox, mayDelete, onDelete } ) {
	return (
		<div className="corex-pane__trash" role="note">
			<p>
				<strong>{ __( 'In the trash.', 'corex' ) }</strong>{ ' ' }
				{ record.trashed_by_name &&
					sprintf(
						/* translators: %s: the name of the person who moved the submission to the trash. */
						__( 'Moved there by %s.', 'corex' ),
						record.trashed_by_name
					) }{ ' ' }
				<CorexTime
					value={ record.trashed_at }
					absent={ __( 'When was not recorded.', 'corex' ) }
				/>
			</p>
			<p className="corex-pane__muted">
				{ __(
					'Restore it to reply, add a note, or change its status or owner.',
					'corex'
				) }
			</p>
			<div className="corex-pane__trash-actions">
				<Button
					variant="primary"
					onClick={ () => inbox.restore( [ record.id ] ) }
				>
					{ __( 'Restore', 'corex' ) }
				</Button>
				{ mayDelete && (
					<Button
						variant="secondary"
						isDestructive
						onClick={ onDelete }
					>
						{ __( 'Delete permanently', 'corex' ) }
					</Button>
				) }
			</div>
			{ ! mayDelete && (
				<p className="corex-pane__muted">{ cannotDeleteReason() }</p>
			) }
		</div>
	);
}

function PaneHeader( { record, inbox } ) {
	const contact = contactOf( record );
	const read = Boolean( record.read_at );

	return (
		<div className="corex-pane__header">
			{ ( contact.email || contact.phone ) && (
				<p className="corex-pane__contact">
					{ contact.email && (
						<a href={ `mailto:${ contact.email }` }>
							{ contact.email }
						</a>
					) }
					{ contact.phone && (
						<a href={ `tel:${ contact.phoneHref }` }>
							{ contact.phone }
						</a>
					) }
				</p>
			) }
			<p className="corex-pane__identity">
				<span>{ identityOf( record ) }</span>
				<CorexTime
					value={ record.created_at }
					absent={ __( 'Date not recorded', 'corex' ) }
				/>
			</p>
			<div className="corex-pane__state">
				<StatusBadge status={ record.status } />
				<span className="corex-pane__read">
					{ read ? __( 'Read', 'corex' ) : __( 'Unread', 'corex' ) }
				</span>
				{ ! record.trashed && (
					<Button
						variant="link"
						onClick={ () =>
							inbox.update( record.id, {
								[ read ? 'mark_unread' : 'mark_read' ]: true,
								expected_updated_at: record.updated_at,
							} )
						}
					>
						{ read
							? __( 'Mark unread', 'corex' )
							: __( 'Mark read', 'corex' ) }
					</Button>
				) }
			</div>
		</div>
	);
}

function Section( { title, children, className = '' } ) {
	const headingId = useId();

	return (
		<section
			className={ [ 'corex-pane__section', className ]
				.filter( Boolean )
				.join( ' ' ) }
			aria-labelledby={ headingId }
		>
			<h3 id={ headingId }>{ title }</h3>
			{ children }
		</section>
	);
}

function Answers( { record } ) {
	const answers = answersOf( record );

	return (
		<Section title={ __( 'Answers', 'corex' ) }>
			{ answers.length === 0 ? (
				<p className="corex-pane__muted">
					{ __( 'This submission holds no answers.', 'corex' ) }
				</p>
			) : (
				<Fields entries={ answers } />
			) }
		</Section>
	);
}

function Reply( { record, inbox } ) {
	const fieldId = useId();
	const [ reply, setReply ] = useState( { subject: '', body: '' } );
	const [ log, setLog ] = useState( null );
	const contact = contactOf( record );
	const emails = Object.values(
		record.related_emails?.bindings || {}
	).filter( ( item ) => item?.attempt_id );

	return (
		<Section title={ __( 'Reply', 'corex' ) }>
			{ ! contact.email ? (
				<p className="corex-pane__muted">
					{ __(
						'This submission has no email address to reply to.',
						'corex'
					) }
				</p>
			) : (
				<>
					<div className="corex-field">
						<label htmlFor={ `${ fieldId }-subject` }>
							{ __( 'Subject', 'corex' ) }
						</label>
						<input
							id={ `${ fieldId }-subject` }
							type="text"
							value={ reply.subject }
							onChange={ ( event ) =>
								setReply( {
									...reply,
									subject: event.target.value,
								} )
							}
						/>
					</div>
					<div className="corex-field">
						<label htmlFor={ `${ fieldId }-body` }>
							{ __( 'Message', 'corex' ) }
						</label>
						<textarea
							id={ `${ fieldId }-body` }
							value={ reply.body }
							onChange={ ( event ) =>
								setReply( {
									...reply,
									body: event.target.value,
								} )
							}
						/>
					</div>
					<div className="corex-pane__actions">
						<Button
							variant="secondary"
							disabled={ ! reply.subject || ! reply.body }
							onClick={ () => inbox.reply( record.id, reply ) }
						>
							{ __( 'Send reply', 'corex' ) }
						</Button>
					</div>
				</>
			) }
			{ emails.length > 0 && (
				<ul className="corex-pane__emails">
					{ emails.map( ( email ) => (
						<li key={ email.attempt_id }>
							<span>{ email.state }</span>
							<span className="corex-pane__actions">
								<Button
									variant="link"
									onClick={ () =>
										inbox.resend(
											record.id,
											email.attempt_id
										)
									}
									disabled={ ! email.retryable }
								>
									{ __( 'Resend', 'corex' ) }
								</Button>
								<Button
									variant="link"
									onClick={ async () => {
										const result = await inbox.log(
											record.id,
											email.attempt_id
										);
										if ( result.envelope.ok ) {
											setLog( result.envelope.data.log );
										}
									} }
								>
									{ __( 'Open log', 'corex' ) }
								</Button>
							</span>
						</li>
					) ) }
				</ul>
			) }
			{ log && (
				<pre className="corex-pane__log">
					{ JSON.stringify( log, null, 2 ) }
				</pre>
			) }
		</Section>
	);
}

function Notes( { record, inbox, readOnly = false } ) {
	const fieldId = useId();
	const [ note, setNote ] = useState( '' );
	const notes = record.notes || [];

	return (
		<Section title={ __( 'Team notes', 'corex' ) }>
			{ notes.length > 0 && (
				<ul className="corex-pane__timeline">
					{ notes.map( ( item ) => (
						<li key={ item.id }>
							<span>{ item.body }</span>
							<small>
								<CorexTime
									value={ item.created_at }
									absent={ __( 'Not recorded', 'corex' ) }
								/>
							</small>
						</li>
					) ) }
				</ul>
			) }
			{ readOnly && notes.length === 0 && (
				<p className="corex-pane__muted">
					{ __( 'No notes were added.', 'corex' ) }
				</p>
			) }
			{ ! readOnly && (
				<>
					<div className="corex-field">
						<label htmlFor={ `${ fieldId }-note` }>
							{ __( 'Add a note for the team', 'corex' ) }
						</label>
						<textarea
							id={ `${ fieldId }-note` }
							value={ note }
							onChange={ ( event ) =>
								setNote( event.target.value )
							}
						/>
					</div>
					<div className="corex-pane__actions">
						<Button
							variant="secondary"
							disabled={ ! note.trim() }
							onClick={ async () => {
								if (
									await inbox.addNote( record.id, {
										body: note,
										visibility: 'corex-team',
									} )
								) {
									setNote( '' );
								}
							} }
						>
							{ __( 'Add note', 'corex' ) }
						</Button>
					</div>
				</>
			) }
		</Section>
	);
}

function Triage( { record, inbox, onTrash } ) {
	return (
		<Section title={ __( 'Triage', 'corex' ) }>
			<div className="corex-pane__triage">
				<div className="corex-field">
					<span>{ __( 'Status', 'corex' ) }</span>
					<CorexSelect
						label={ __( 'Status', 'corex' ) }
						value={ record.status }
						options={ STATUS_OPTIONS }
						block
						onChange={ ( status ) =>
							inbox.update( record.id, {
								status,
								expected_updated_at: record.updated_at,
							} )
						}
					/>
				</div>
				<div className="corex-field">
					<span>{ __( 'Assigned to', 'corex' ) }</span>
					<CorexSelect
						label={ __( 'Assigned to', 'corex' ) }
						value={ ownerValue( record ) }
						options={ ownerOptions( record ) }
						block
						onChange={ ( value ) =>
							inbox.update( record.id, {
								...assignmentOf( value ),
								expected_updated_at: record.updated_at,
							} )
						}
					/>
				</div>
			</div>
			<div className="corex-pane__actions">
				<Button variant="secondary" isDestructive onClick={ onTrash }>
					{ __( 'Move to trash', 'corex' ) }
				</Button>
			</div>
		</Section>
	);
}

function Delivery( { record } ) {
	const untracked = untrackedReason( record );

	return (
		<Section title={ __( 'Notification', 'corex' ) }>
			<p className="corex-pane__delivery">
				<DeliveryBadge delivery={ record.delivery } />
				{ record.delivery?.attempted_at && (
					<CorexTime value={ record.delivery.attempted_at } />
				) }
			</p>
			{ ( untracked || record.delivery?.safe_reason ) && (
				<p className="corex-pane__muted">
					{ untracked || record.delivery.safe_reason }
				</p>
			) }
		</Section>
	);
}

function TechnicalDetails( { record } ) {
	const groups = technicalGroups( record );

	if ( groups.length === 0 ) {
		return (
			<p className="corex-pane__muted corex-pane__nothing">
				{ __(
					'No hidden fields, campaign data or consent record were stored with this submission.',
					'corex'
				) }
			</p>
		);
	}

	return (
		<details className="corex-pane__more">
			<summary>{ __( 'Technical details', 'corex' ) }</summary>
			{ groups.map( ( group ) => (
				<div key={ group.id } className="corex-pane__group">
					<h4>{ group.title }</h4>
					<Fields
						entries={ group.entries.map( ( [ key, value ] ) => ( {
							key,
							label: key,
							value,
						} ) ) }
					/>
				</div>
			) ) }
		</details>
	);
}

function History( { record } ) {
	const events = record.timeline || [];

	if ( events.length === 0 ) {
		return null;
	}

	return (
		<details className="corex-pane__more">
			<summary>{ __( 'History', 'corex' ) }</summary>
			<ul className="corex-pane__timeline">
				{ events.map( ( event, index ) => (
					<li key={ event.id || index }>
						<span>{ historyLine( event, record ) }</span>
						<small>
							<CorexTime
								value={ event.created_at || event.occurred_at }
								absent={ __( 'Not recorded', 'corex' ) }
							/>
						</small>
					</li>
				) ) }
			</ul>
		</details>
	);
}
