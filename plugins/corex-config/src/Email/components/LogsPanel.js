import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Field } from './shared.js';

function RetryEditor( { retry, busy, onResend, onCancel } ) {
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
					<button className="button button-primary" disabled={ busy }>
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

function AttemptRow( { attempt, busy, onRetry } ) {
	return (
		<tr>
			<td>{ attempt.recipient }</td>
			<td>{ attempt.subject }</td>
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
			<td>{ new Date( attempt.occurred_at ).toLocaleString() }</td>
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

function CapturesList( { captures } ) {
	return (
		<section className="corex-surface corex-email-app__table-card">
			<h2>{ __( 'Development captures', 'corex' ) }</h2>
			{ captures.length === 0 ? (
				<p>{ __( 'No captured messages yet.', 'corex' ) }</p>
			) : (
				<ul className="corex-email-app__captures">
					{ captures.map( ( capture ) => (
						<li key={ capture.capture_id }>
							<strong>{ capture.subject }</strong>
							<span>{ capture.to.join( ', ' ) }</span>
							<time>
								{ new Date(
									capture.captured_at
								).toLocaleString() }
							</time>
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
