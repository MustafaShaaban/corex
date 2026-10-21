/**
 * What the pane says about replying to a submission, as plain functions (spec 106).
 *
 * Before anything is typed: whether a reply from this site is delivered at all. After one is
 * sent: what happened to it. Both were unsaid. A reply on a site with live delivery off was
 * refused and the pane said "Reply processed by Email Studio."
 */
import { __, sprintf } from '@wordpress/i18n';

/** States in which the mail provider took the message. */
const SENT = [ 'sent', 'accepted', 'opened' ];

/** Outcomes the site's own settings would change, so the notice offers the way there. */
const CHANGED_IN_SETTINGS = [ 'no_provider', 'live_off', 'staging_capture' ];

/**
 * Whether a reply can be sent from this site now.
 *
 * @param {Object} [delivery] The submission's `reply_delivery`.
 * @return {boolean} False only where the server said a reply would be refused.
 */
export function canSendReply( delivery ) {
	return ! delivery || [ 'deliver', 'capture' ].includes( delivery.mode );
}

/**
 * What the reply box says before anything is typed (FR-028).
 *
 * @param {Object} [delivery]  The submission's `reply_delivery`: `{ mode, code, reason }`.
 * @param {string} settingsUrl The Mail settings, for a person who may change them; otherwise ''.
 * @return {{tone:string,text:string,link:?{href:string,label:string},hint:string}|null} Null where a reply is simply delivered.
 */
export function readinessNotice( delivery, settingsUrl ) {
	if ( ! delivery || delivery.mode === 'deliver' || ! delivery.reason ) {
		return null;
	}
	const changeable = CHANGED_IN_SETTINGS.includes( delivery.code );

	return {
		tone: delivery.mode === 'capture' ? 'info' : 'warning',
		text: delivery.reason,
		link:
			changeable && settingsUrl
				? {
						href: settingsUrl,
						label: __( 'Open Mail settings', 'corex' ),
					}
				: null,
		hint:
			changeable && ! settingsUrl && delivery.mode === 'refuse'
				? __(
						'Somebody who manages CoreX settings can change this.',
						'corex'
					)
				: '',
	};
}

/**
 * What the pane says after a reply is sent, from what the mail system answered (FR-030).
 *
 * @param {Object}  [result]   The route's `result`: `{ state, message }`.
 * @param {string}  address    Who the reply was for.
 * @param {boolean} [recorded] False when the reply went and could not then be kept on the submission.
 * @param {boolean} [again]    True for a reply that was listed and sent again (FR-022): it is on the submission already, and what could not be kept is this sending of it.
 * @return {{tone:string,text:string,detail:string,keep:boolean}} `keep` is whether what was written stays in the box (FR-031).
 */
export function replyOutcome(
	result,
	address,
	recorded = true,
	again = false
) {
	const state = result?.state || '';
	const detail = result?.message || '';
	// Said with what happened, never in its place: the reply has gone and is not to be sent again.
	const unsaid = again
		? __(
				'That could not be written down on this submission, which may go on listing the reply as it was. Do not send it again.',
				'corex'
			)
		: __(
				'It could not be kept on this submission, so it is not listed here. Do not send it again.',
				'corex'
			);
	const unrecorded = recorded ? '' : unsaid;

	if ( state === 'queued' ) {
		return {
			tone: 'info',
			text: sprintf(
				/* translators: %s: the email address a reply is going to. */
				__( 'Reply is waiting to be sent to %s.', 'corex' ),
				address
			),
			detail: unrecorded,
			keep: false,
		};
	}
	if ( SENT.includes( state ) ) {
		return {
			tone: 'success',
			text: sprintf(
				/* translators: %s: the email address a reply was sent to. */
				__( 'Reply sent to %s.', 'corex' ),
				address
			),
			detail: unrecorded,
			keep: false,
		};
	}
	if ( state === 'captured' ) {
		return {
			tone: 'info',
			text: sprintf(
				/* translators: %s: the email address a reply was written to. */
				__( 'Reply kept on this site, not delivered to %s.', 'corex' ),
				address
			),
			// Why is already said above the box, before anything was typed.
			detail: unrecorded,
			keep: false,
		};
	}

	return {
		tone: 'failure',
		text: __( 'The reply was not sent.', 'corex' ),
		detail,
		keep: true,
	};
}

/**
 * A reply's state as a word, an icon and a tone: never colour alone.
 *
 * @param {string} state The state of the reply's last attempt.
 * @return {{label:string,tone:string,icon:string}} How to draw it.
 */
export function replyStateOf( state ) {
	if ( SENT.includes( state ) ) {
		return {
			label: __( 'Sent', 'corex' ),
			tone: 'success',
			icon: 'yes-alt',
		};
	}
	if ( state === 'queued' || state === 'sending' ) {
		return {
			label: __( 'Waiting to be sent', 'corex' ),
			tone: 'info',
			icon: 'clock',
		};
	}
	if ( state === 'captured' ) {
		return {
			label: __( 'Kept, not delivered', 'corex' ),
			tone: 'info',
			icon: 'download',
		};
	}
	if ( state === 'bounced' ) {
		return {
			label: __( 'Returned undelivered', 'corex' ),
			tone: 'warning',
			icon: 'undo',
		};
	}

	return {
		label: __( 'Not sent', 'corex' ),
		tone: 'warning',
		icon: 'warning',
	};
}

/**
 * The history's line for an email sent from the pane.
 *
 * @param {Object} event One history entry of the `email` stage.
 * @return {string} What happened.
 */
export function emailHistoryLine( event ) {
	const summary = event.summary || {};
	const succeeded = event.outcome === 'success';

	if ( summary.action === 'resend' ) {
		const to = ( summary.recipients || [] ).join( ', ' );
		if ( succeeded && to !== '' ) {
			return sprintf(
				/* translators: %s: the addresses an email was sent again to, comma-separated. */
				__( 'Email sent again to %s', 'corex' ),
				to
			);
		}
		return succeeded
			? __( 'Email sent again', 'corex' )
			: __( 'Email could not be sent again', 'corex' );
	}
	if ( summary.action === 'reply_again' && summary.state !== 'captured' ) {
		return succeeded
			? __( 'Reply sent again', 'corex' )
			: __( 'Reply could not be sent again', 'corex' );
	}
	if ( summary.state === 'captured' ) {
		return __( 'Reply kept on this site, not delivered', 'corex' );
	}

	return succeeded
		? __( 'Reply sent', 'corex' )
		: __( 'Reply could not be sent', 'corex' );
}

/**
 * What one of the emails a flow sent for a submission is (spec 112, FR-033).
 *
 * @param {Object} email A related email, as the submission's detail describes it.
 * @return {string} Its name.
 */
export function relatedEmailName( email ) {
	if ( email.kind === 'confirmation' ) {
		return __( 'Confirmation to the sender', 'corex' );
	}
	if ( email.kind === 'failure_notice' ) {
		return __( 'Failure notice', 'corex' );
	}

	return __( 'Team notification', 'corex' );
}

/**
 * Why a related email is not offered to be sent again, or nothing when it is.
 *
 * @param {Object} email A related email, as the submission's detail describes it.
 * @return {string} The reason, or an empty string.
 */
export function whyNotResent( email ) {
	if ( email.not_resendable_reason === 'recipients_unknown' ) {
		return __(
			'Who it was sent to is not known, so it is not sent again.',
			'corex'
		);
	}
	if ( email.not_resendable_reason === 'not_retryable' ) {
		return __( 'This email cannot be sent again.', 'corex' );
	}

	return '';
}
