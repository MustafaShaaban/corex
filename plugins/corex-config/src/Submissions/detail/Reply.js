/**
 * Replying to the person who sent a submission (spec 106).
 *
 * In the order somebody needs it: what was already answered, whether an answer sent now will
 * reach anybody, the answer, and what happened to it. The middle two were unsaid: on a site with
 * live delivery off a reply was refused and the pane said it had been processed.
 */
import { useEffect, useId, useRef, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import CorexErrorState from '../../admin/components/CorexErrorState.js';
import CorexRichText from '../../admin/components/CorexRichText.js';
import CorexTime from '../../admin/components/CorexTime.js';
import { workingProps } from '../../admin/components/working.js';
import ReplyPreview from './ReplyPreview.js';
import Section from './Section.js';
import { contactOf } from './detailState.js';
import { cleanReplyHtml, hasWords } from '../../admin/components/replyHtml.js';
import { canSendReply, readinessNotice, replyStateOf } from './replyState.js';
import { draftOf, untouchedReply, useReplyDraft } from './useReplyDraft.js';

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
	// The box before anybody writes in it: a subject from the form the person filled in,
	// to keep or to change (FR-035). Read once: the pane reads the submission again after
	// every change to it, and what somebody is writing does not start over for that.
	const [ untouched ] = useState( () => untouchedReply( record ) );
	// What they began on another visit and did not send is where they left it (FR-006).
	const [ reply, setReply ] = useState( () =>
		draftOf( record.reply_draft, untouched )
	);
	const draft = useReplyDraft( {
		id: record.id,
		stored: record.reply_draft,
		reply,
		inbox,
		held: work.pending === 'reply',
		untouched,
	} );
	const [ outcome, setOutcome ] = useState( null );
	const [ preview, setPreview ] = useState( null );
	const contact = contactOf( record );
	const delivery = record.reply_delivery;
	const notice = readinessNotice( delivery, mailSettingsUrl );
	const noticeId = `${ fieldId }-delivery`;
	const subject = useRef( null );
	const discardButton = useRef( null );
	const draftId = `${ fieldId }-draft`;
	// What was done with the draft that nothing on the page now shows: it was discarded.
	const [ draftSaid, setDraftSaid ] = useState( '' );
	const [ focusOwed, setFocusOwed ] = useState( '' );
	const previewButton = useRef( null );
	const previewing = useRef( false );

	// Leaving the preview puts the keyboard back on the button that opened it. The browser
	// cannot: the button was switched off while its request was out, so nothing had focus
	// when the dialog opened, and it would be left on the page itself.
	useEffect( () => {
		if ( previewing.current && ! preview ) {
			previewButton.current?.focus();
		}
		previewing.current = Boolean( preview );
	}, [ preview ] );

	// A reply is a subject and a message that says something.
	const written = reply.subject.trim() !== '' && hasWords( reply.body );

	// The email as the server would build it now, from what is written now.
	const look = () =>
		work.during( 'preview', async () => {
			const email = await inbox.previewReply( record.id, reply );
			if ( email ) {
				setPreview( email );
			}
		} );

	const send = () => {
		draft.hold();

		return work.during( 'reply', async () => {
			setOutcome( null );
			const said = await inbox.reply( record.id, reply, contact.email );
			// Refused before it was tried: the pane's own failure line says why.
			if ( ! said ) {
				return;
			}
			setOutcome( said );
			if ( ! said.keep ) {
				draft.forget();
				setReply( untouched );
				// The button that was pressed is off again; focus must not be left on it.
				subject.current?.focus();
			}
		} );
	};

	const discard = async () => {
		const discarded = await work.during( 'draft', async () => {
			const done = await draft.discard();
			if ( done ) {
				setReply( untouched );
				setOutcome( null );
				setDraftSaid( __( 'Draft discarded.', 'corex' ) );
			}

			return done;
		} );
		// The button was off while its request was out, and a button that is off holds no
		// focus. It is gone with the draft, or it is there again to be pressed again: which
		// is so only once the pane has drawn itself with no request out.
		setFocusOwed( discarded ? 'subject' : 'discard' );
	};

	useEffect( () => {
		if ( focusOwed && work.pending === '' ) {
			( focusOwed === 'subject'
				? subject
				: discardButton
			).current?.focus();
			setFocusOwed( '' );
		}
	}, [ focusOwed, work.pending ] );

	// Said once: when something is written again it is about a draft that is gone.
	useEffect( () => {
		if ( draft.state !== 'none' ) {
			setDraftSaid( '' );
		}
	}, [ draft.state ] );

	// What the fields are described by: what will happen to a reply, and that what is in
	// them was written on an earlier visit. Said on the fields, since both come before them.
	const described =
		[ notice ? noticeId : '', draft.state === 'restored' ? draftId : '' ]
			.filter( Boolean )
			.join( ' ' ) || undefined;

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
							aria-describedby={ described }
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
						<span id={ `${ fieldId }-body-label` }>
							{ __( 'Message', 'corex' ) }
						</span>
						<CorexRichText
							value={ reply.body }
							onChange={ ( body ) =>
								setReply( ( before ) => ( {
									...before,
									body,
								} ) )
							}
							labelledBy={ `${ fieldId }-body-label` }
							describedBy={ described }
							// What is typed while a reply is on its way would be emptied
							// with it when it has gone.
							disabled={ work.pending === 'reply' }
						/>
					</div>
					<div className="corex-pane__actions">
						<Button
							ref={ previewButton }
							variant="secondary"
							disabled={ ! written || work.pending !== '' }
							onClick={ look }
							{ ...workingProps( work.pending === 'preview' ) }
						>
							{ __( 'Preview', 'corex' ) }
						</Button>
						<Button
							variant="primary"
							disabled={
								! canSendReply( delivery ) ||
								! written ||
								work.pending !== ''
							}
							onClick={ send }
							{ ...workingProps( work.pending === 'reply' ) }
						>
							{ __( 'Send reply', 'corex' ) }
						</Button>
					</div>
					{ draft.state !== 'none' && (
						<DraftLine
							id={ draftId }
							state={ draft.state }
							work={ work }
							button={ discardButton }
							onDiscard={ discard }
						/>
					) }
					{ preview && (
						<ReplyPreview
							email={ preview }
							subject={ preview.subject ?? reply.subject }
							note={ notice ? notice.text : '' }
							onClose={ () => setPreview( null ) }
						/>
					) }
					{ /* Always there, so that what it comes to hold is announced: a region
					     that arrives already holding its words is often not read out. */ }
					<p className="screen-reader-text" role="status">
						{ [
							...( outcome && outcome.tone !== 'failure'
								? [ outcome.text, outcome.detail ]
								: [] ),
							DRAFT_ANNOUNCED[ draft.state ] ||
								( draft.state === 'none' ? draftSaid : '' ),
						]
							.filter( Boolean )
							.join( ' ' ) }
					</p>
					{ outcome && <Outcome outcome={ outcome } /> }
				</>
			) }
			<RelatedEmails record={ record } inbox={ inbox } work={ work } />
		</Section>
	);
}

const DRAFT_SAYS = {
	restored: __(
		'This is the reply you began earlier and did not send.',
		'corex'
	),
	saved: __( 'Draft saved.', 'corex' ),
	unsaved: __(
		'The draft could not be saved. What you have written is still here.',
		'corex'
	),
	undiscarded: __( 'The draft could not be discarded.', 'corex' ),
};

/** What became of the draft that has to be heard as well as seen: only what went wrong. */
const DRAFT_ANNOUNCED = {
	unsaved: DRAFT_SAYS.unsaved,
	undiscarded: DRAFT_SAYS.undiscarded,
};

/**
 * What became of the reply being written, as a draft, and the way to be rid of it.
 *
 * Said in the page and not announced: a draft is saved every time writing pauses, and a
 * screen reader that said so each time would talk over the writing. What went wrong is
 * announced, from the section's own status region, which is there before it has words.
 *
 * @param {Object}   props
 * @param {string}   props.id        The id the fields are described by while the draft is one that was restored.
 * @param {string}   props.state     `restored`, `saved`, `unsaved` or `undiscarded`.
 * @param {Object}   props.work      The pane's `{ pending }`: which control's request is out.
 * @param {Object}   props.button    A ref for the discard button.
 * @param {Function} props.onDiscard Called to discard the draft and empty the box.
 * @return {import('react').ReactElement} The line.
 */
function DraftLine( { id, state, work, button, onDiscard } ) {
	return (
		<p className="corex-pane__muted corex-pane__reply-draft">
			<span id={ id }>{ DRAFT_SAYS[ state ] }</span>
			<Button
				ref={ button }
				variant="link"
				disabled={ work.pending !== '' }
				onClick={ onDiscard }
				{ ...workingProps( work.pending === 'draft' ) }
			>
				{ __( 'Discard draft', 'corex' ) }
			</Button>
		</p>
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
