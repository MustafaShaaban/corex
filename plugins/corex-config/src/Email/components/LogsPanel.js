import { useContext, useEffect, useState } from '@wordpress/element';
import {
	PendingControl,
	workingProps,
} from '../../admin/components/working.js';
import { __, sprintf } from '@wordpress/i18n';
import CorexTime from '../../admin/components/CorexTime.js';
import { Field } from './shared.js';

function RetryEditor( { retry, busy, onResend, onCancel } ) {
	const pending = useContext( PendingControl );
	const submit = async ( event ) => {
		event.preventDefault();
		const form = new FormData( event.currentTarget );
		const result = await onResend( retry, {
			to: [ form.get( 'to' ) ],
			subject: form.get( 'subject' ),
			body: form.get( 'body' ),
		} );
		if ( result ) {
			onCancel();
		}
	};

	return (
		<section className="corex-surface corex-email-app__editor">
			<h2>{ __( 'Retry failed attempt', 'corex' ) }</h2>
			<p>
				{ __(
					'Enter the intended full recipient and message body; logs retain only delivery evidence.',
					'corex'
				) }
			</p>
			<form className="corex-email-app__form-grid" onSubmit={ submit }>
				<Field
					label={ __( 'Recipient', 'corex' ) }
					name="to"
					type="email"
					required
				/>
				<Field
					label={ __( 'Subject', 'corex' ) }
					name="subject"
					defaultValue={ retry.subject }
					required
				/>
				<Field
					label={ __( 'HTML body', 'corex' ) }
					name="body"
					textarea
					wide
					required
				/>
				<div className="corex-email-app__actions is-wide">
					<button
						className="button button-primary"
						disabled={ busy }
						{ ...workingProps( pending === 'resend' ) }
					>
						{ __( 'Create retry attempt', 'corex' ) }
					</button>
					<button
						type="button"
						className="button"
						onClick={ onCancel }
					>
						{ __( 'Cancel', 'corex' ) }
					</button>
				</div>
			</form>
		</section>
	);
}

/**
 * Follows a link to one row (`#corex-attempt-…` from a test send, `#corex-capture-…` from a
 * flow test) once the rows are drawn: the browser looked for it before it was there.
 *
 * @param {string} prefix The rows' id prefix.
 * @param {Array}  rows   The rows drawn.
 */
function useFollowedRow( prefix, rows ) {
	useEffect( () => {
		const hash = window.location.hash.slice( 1 );
		if ( hash.startsWith( prefix ) ) {
			document
				.getElementById( hash )
				?.scrollIntoView( { block: 'center' } );
		}
	}, [ prefix, rows ] );
}

function AttemptRow( { attempt, busy, onRetry } ) {
	const missing = attempt.missing_variables || [];
	return (
		<tr id={ `corex-attempt-${ attempt.attempt_id }` }>
			<td>{ attempt.recipient }</td>
			<td>
				<bdi>{ attempt.subject }</bdi>
				{ missing.length > 0 && (
					<span className="corex-email-app__missing">
						{ sprintf(
							/* translators: %s: variable keys, comma separated. */
							__( 'Sent without: %s', 'corex' ),
							missing.join( ', ' )
						) }
					</span>
				) }
			</td>
			<td>{ attempt.template_slug || __( 'Ad hoc', 'corex' ) }</td>
			<td>
				<span
					className={ `corex-email-app__badge is-${ attempt.state }` }
				>
					{ attempt.state }
				</span>
			</td>
			<td>
				{ attempt.provider_event || __( 'No provider event', 'corex' ) }
			</td>
			<td>{ attempt.provider }</td>
			<td>
				<CorexTime value={ attempt.occurred_at } />
			</td>
			<td>
				{ attempt.retryable ? (
					<button
						type="button"
						className="button button-small"
						disabled={ busy }
						onClick={ () => onRetry( attempt ) }
					>
						{ __( 'Resend', 'corex' ) }
					</button>
				) : (
					'—'
				) }
			</td>
		</tr>
	);
}

function AttemptsTable( { attempts, busy, onRetry } ) {
	useFollowedRow( 'corex-attempt-', attempts );
	return (
		<section className="corex-surface corex-email-app__table-card">
			<h2>{ __( 'Delivery attempts', 'corex' ) }</h2>
			{ attempts.length === 0 ? (
				<p>{ __( 'No attempts yet.', 'corex' ) }</p>
			) : (
				<div className="corex-email-app__table-wrap">
					<table>
						<thead>
							<tr>
								<th>{ __( 'Recipient', 'corex' ) }</th>
								<th>{ __( 'Subject', 'corex' ) }</th>
								<th>{ __( 'Template', 'corex' ) }</th>
								<th>{ __( 'State', 'corex' ) }</th>
								<th>{ __( 'Provider event', 'corex' ) }</th>
								<th>{ __( 'Provider', 'corex' ) }</th>
								<th>{ __( 'Time', 'corex' ) }</th>
								<th>{ __( 'Action', 'corex' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ attempts.map( ( attempt ) => (
								<AttemptRow
									key={ attempt.attempt_id }
									attempt={ attempt }
									busy={ busy }
									onRetry={ onRetry }
								/>
							) ) }
						</tbody>
					</table>
				</div>
			) }
		</section>
	);
}

/**
 * What kept a captured email, in words: a marked test holds, a development or staging site
 * captures (spec 113).
 *
 * @param {string} state The capture's state.
 * @return {string} The state's label.
 */
export function captureStateLabel( state ) {
	return state === 'held_for_test'
		? __( 'Held for test', 'corex' )
		: __( 'Captured', 'corex' );
}

/**
 * One kept email, as it would have arrived: its HTML and its plain-text version (spec 120).
 *
 * @param {Object} props
 * @param {Object} props.capture The kept email.
 * @return {Element} The disclosure.
 */
function CapturedMessage( { capture } ) {
	return (
		<details className="corex-email-app__capture-body">
			<summary>{ __( 'Show the email', 'corex' ) }</summary>
			<h3>{ __( 'As HTML', 'corex' ) }</h3>
			<iframe
				title={ sprintf(
					/* translators: %s: an email's subject. */
					__( 'The email "%s" as it would have arrived', 'corex' ),
					capture.subject
				) }
				sandbox=""
				srcDoc={ capture.html_body }
			/>
			<h3>{ __( 'Plain-text version', 'corex' ) }</h3>
			<pre className="corex-email-app__plain" dir="auto">
				{ capture.plain_text ||
					__( 'This email has no plain-text version.', 'corex' ) }
			</pre>
		</details>
	);
}

function CapturesList( { captures } ) {
	useFollowedRow( 'corex-capture-', captures );

	return (
		<section className="corex-surface corex-email-app__table-card">
			<h2>{ __( 'Captured and held mail', 'corex' ) }</h2>
			<p className="corex-email-app__captures-note">
				{ __(
					'Kept here instead of sent, newest first. Each is removed 30 days after it was kept.',
					'corex'
				) }
			</p>
			{ captures.length === 0 ? (
				<p>{ __( 'No captured messages yet.', 'corex' ) }</p>
			) : (
				<ul className="corex-email-app__captures">
					{ captures.map( ( capture ) => (
						<li
							key={ capture.capture_id }
							id={ `corex-capture-${ capture.capture_id }` }
						>
							<span className="corex-email-app__capture-what">
								<strong dir="auto">{ capture.subject }</strong>
								<span>
									{ capture.path ||
										__( 'Email Studio', 'corex' ) }
								</span>
							</span>
							<span>{ capture.to.join( ', ' ) }</span>
							<span className="corex-email-app__capture-state">
								{ captureStateLabel( capture.state ) }
							</span>
							<CorexTime value={ capture.captured_at } />
							<CapturedMessage capture={ capture } />
						</li>
					) ) }
				</ul>
			) }
		</section>
	);
}

export function LogsPanel( { attempts, captures, busy, onResend } ) {
	const [ retry, setRetry ] = useState( null );
	return (
		<div className="corex-email-app__stack">
			{ retry && (
				<RetryEditor
					retry={ retry }
					busy={ busy }
					onResend={ onResend }
					onCancel={ () => setRetry( null ) }
				/>
			) }
			<AttemptsTable
				attempts={ attempts }
				busy={ busy }
				onRetry={ setRetry }
			/>
			<CapturesList captures={ captures } />
		</div>
	);
}
