/**
 * Replying to the person who sent a submission (spec 106).
 *
 * In the order somebody needs it: what was already answered, whether an answer sent now will
 * reach anybody, the answer, and what happened to it. The middle two were unsaid: on a site with
 * live delivery off a reply was refused and the pane said it had been processed.
 */
import { useId, useRef, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import CorexErrorState from '../../admin/components/CorexErrorState.js';
import CorexTime from '../../admin/components/CorexTime.js';
import { workingProps } from '../../admin/components/working.js';
import Section from './Section.js';
import { contactOf } from './detailState.js';
import { cleanReplyHtml } from './replyHtml.js';
import { canSendReply, readinessNotice, replyStateOf } from './replyState.js';

const NOTHING_WRITTEN = { subject: '', body: '' };

/**
 * @param {Object} props
 * @param {Object} props.record            The open submission.
 * @param {Object} props.inbox             The inbox's requests.
 * @param {Object} props.work              The pane's `{ pending, during }`: which control's request is out.
 * @param {string} [props.mailSettingsUrl] The Mail settings, for a person who may change them.
 * @return {import('react').ReactElement} The section.
 */
export default function Reply( { record, inbox, work, mailSettingsUrl = '' } ) {
	const fieldId = useId();
	const [ reply, setReply ] = useState( NOTHING_WRITTEN );
	const [ outcome, setOutcome ] = useState( null );
	const contact = contactOf( record );
	const delivery = record.reply_delivery;
	const notice = readinessNotice( delivery, mailSettingsUrl );
	const noticeId = `${ fieldId }-delivery`;
	const subject = useRef( null );

	const send = () =>
		work.during( 'reply', async () => {
			setOutcome( null );
			const said = await inbox.reply( record.id, reply, contact.email );
			// Refused before it was tried: the pane's own failure line says why.
			if ( ! said ) {
				return;
			}
			setOutcome( said );
			if ( ! said.keep ) {
				setReply( NOTHING_WRITTEN );
				// The button that was pressed is off again; focus must not be left on it.
				subject.current?.focus();
			}
		} );

	return (
		<Section title={ __( 'Reply', 'corex' ) }>
			<Replies replies={ record.replies || [] } />
			{ ! contact.email && (
				<p className="corex-pane__muted">
					{ __(
						'This submission has no email address to reply to.',
						'corex'
					) }
				</p>
			) }
			{ contact.email && notice && (
				<DeliveryNotice id={ noticeId } notice={ notice } />
			) }
			{ contact.email && delivery?.mode !== 'unavailable' && (
				<>
					<div className="corex-field">
						<label htmlFor={ `${ fieldId }-subject` }>
							{ __( 'Subject', 'corex' ) }
						</label>
						<input
							id={ `${ fieldId }-subject` }
							ref={ subject }
							type="text"
							aria-describedby={ notice ? noticeId : undefined }
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
							aria-describedby={ notice ? noticeId : undefined }
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
							disabled={
								! canSendReply( delivery ) ||
								! reply.subject.trim() ||
								! reply.body.trim() ||
								work.pending !== ''
							}
							onClick={ send }
							{ ...workingProps( work.pending === 'reply' ) }
						>
							{ __( 'Send reply', 'corex' ) }
						</Button>
					</div>
					{ /* Always there, so that what it comes to hold is announced: a region
					     that arrives already holding its words is often not read out. */ }
					<p className="screen-reader-text" role="status">
						{ outcome && outcome.tone !== 'failure'
							? [ outcome.text, outcome.detail ]
									.filter( Boolean )
									.join( ' ' )
							: '' }
					</p>
					{ outcome && <Outcome outcome={ outcome } /> }
				</>
			) }
			<RelatedEmails record={ record } inbox={ inbox } work={ work } />
		</Section>
	);
}

/**
 * What will happen to a reply sent from this site, said before one is written.
 *
 * A note and not an alert: it is true when the pane opens, nothing the person did caused it.
 * The two fields are described by it, because a button that is off is not reached by keyboard
 * and its description would never be read.
 *
 * @param {Object} props
 * @param {string} props.id     The id the fields are described by.
 * @param {Object} props.notice `{ tone, text, link, hint }`.
 * @return {import('react').ReactElement} The notice.
 */
function DeliveryNotice( { id, notice } ) {
	return (
		<div
			id={ id }
			className={ `corex-pane__reply-notice is-${ notice.tone }` }
			role="note"
		>
			<p>{ notice.text }</p>
			{ notice.hint && <p>{ notice.hint }</p> }
			{ notice.link && (
				<a href={ notice.link.href }>{ notice.link.label }</a>
			) }
		</div>
	);
}

/**
 * What happened to the reply that was just sent.
 *
 * @param {Object} props
 * @param {Object} props.outcome `{ tone, text, detail }`.
 * @return {import('react').ReactElement} The outcome, announced.
 */
function Outcome( { outcome } ) {
	if ( outcome.tone === 'failure' ) {
		return (
			<CorexErrorState
				scale="action"
				message={ outcome.text }
				detail={ outcome.detail }
			/>
		);
	}

	// Announced by the section's status region; this is the same words to read.
	return (
		<div className={ `corex-pane__reply-outcome is-${ outcome.tone }` }>
			<p>{ outcome.text }</p>
			{ outcome.detail && (
				<p className="corex-pane__muted">{ outcome.detail }</p>
			) }
		</div>
	);
}

/**
 * What the team already wrote back, oldest first.
 *
 * @param {Object}        props
 * @param {Array<Object>} props.replies The submission's `replies`.
 * @return {import('react').ReactElement|null} The list, or nothing when nobody has answered.
 */
function Replies( { replies } ) {
	if ( replies.length === 0 ) {
		return null;
	}

	return (
		<ul
			className="corex-pane__replies"
			aria-label={ __( 'Replies sent', 'corex' ) }
		>
			{ replies.map( ( reply ) => {
				const state = replyStateOf( reply.state );

				return (
					<li key={ reply.id } className="corex-pane__reply">
						<div className="corex-pane__reply-head">
							<strong>
								<bdi>{ reply.subject }</bdi>
							</strong>
							<span
								className={ `corex-inbox__delivery is-${ state.tone }` }
							>
								<span
									className={ `dashicons dashicons-${ state.icon }` }
									aria-hidden="true"
								/>
								<span>{ state.label }</span>
							</span>
						</div>
						<p className="corex-pane__reply-meta">
							<span>
								{ reply.actor_name ||
									__(
										'Somebody no longer on this site',
										'corex'
									) }
							</span>
							<CorexTime
								value={ reply.created_at }
								absent={ __( 'Not recorded', 'corex' ) }
							/>
						</p>
						{ /* A reply is written in the lead's language, not the admin's. What is
						     drawn is what a reply may hold and nothing else, whatever is stored. */ }
						<div
							className="corex-pane__reply-body"
							dir="auto"

							dangerouslySetInnerHTML={ {
								__html: cleanReplyHtml( reply.body_html, {
									forReading: true,
								} ),
							} }
						/>
						{ /* The mail system's own sentence, for a reply that did not go. */ }
						{ state.tone === 'warning' && reply.message && (
							<p className="corex-pane__muted">
								{ reply.message }
							</p>
						) }
					</li>
				);
			} ) }
		</ul>
	);
}

/**
 * The emails the form itself sent for this submission: each can be sent again and its record
 * opened.
 *
 * @param {Object} props
 * @param {Object} props.record The open submission.
 * @param {Object} props.inbox  The inbox's requests.
 * @param {Object} props.work   The pane's `{ pending, during }`.
 * @return {import('react').ReactElement|null} The list, or nothing when the form sent none.
 */
function RelatedEmails( { record, inbox, work } ) {
	const [ log, setLog ] = useState( null );
	const emails = Object.values(
		record.related_emails?.bindings || {}
	).filter( ( item ) => item?.attempt_id );

	if ( emails.length === 0 ) {
		return null;
	}

	return (
		<>
			<ul className="corex-pane__emails">
				{ emails.map( ( email ) => (
					<li key={ email.attempt_id }>
						<span>{ email.state }</span>
						<span className="corex-pane__actions">
							<Button
								variant="link"
								onClick={ () =>
									work.during(
										`resend:${ email.attempt_id }`,
										() =>
											inbox.resend(
												record.id,
												email.attempt_id
											)
									)
								}
								disabled={
									! email.retryable || work.pending !== ''
								}
								{ ...workingProps(
									work.pending ===
										`resend:${ email.attempt_id }`
								) }
							>
								{ __( 'Resend', 'corex' ) }
							</Button>
							<Button
								variant="link"
								disabled={ work.pending !== '' }
								onClick={ () =>
									work.during(
										`log:${ email.attempt_id }`,
										async () => {
											const result = await inbox.log(
												record.id,
												email.attempt_id
											);
											if ( result.envelope.ok ) {
												setLog(
													result.envelope.data.log
												);
											}
										}
									)
								}
								{ ...workingProps(
									work.pending === `log:${ email.attempt_id }`
								) }
							>
								{ __( 'Open log', 'corex' ) }
							</Button>
						</span>
					</li>
				) ) }
			</ul>
			{ log && (
				<pre className="corex-pane__log">
					{ JSON.stringify( log, null, 2 ) }
				</pre>
			) }
		</>
	);
}
