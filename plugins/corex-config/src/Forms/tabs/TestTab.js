import { useContext } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	PendingControl,
	workingProps,
} from '../../admin/components/working.js';

/**
 * Where Email Studio shows one held email.
 *
 * @param {string} captureId The held email's capture id.
 * @return {string} A link inside wp-admin.
 */
export function heldEmailLink( captureId ) {
	return `admin.php?page=corex-email-studio&tab=logs#corex-capture-${ encodeURIComponent(
		captureId
	) }`;
}

function HeldEmails( { emails } ) {
	if ( ! Array.isArray( emails ) ) {
		return null;
	}

	return (
		<section className="corex-flow-test-held">
			<h3>{ __( 'Emails held by this test', 'corex' ) }</h3>
			{ emails.length === 0 ? (
				<p>{ __( 'This run had no email to send.', 'corex' ) }</p>
			) : (
				<ul>
					{ emails.map( ( email ) => (
						<li key={ email.capture_id }>
							<span>
								{ sprintf(
									/* translators: 1: email subject, 2: recipients. */
									__( '%1$s, to %2$s', 'corex' ),
									email.subject,
									( email.to || [] ).join( ', ' )
								) }
							</span>{ ' ' }
							<a href={ heldEmailLink( email.capture_id ) }>
								{ __( 'View in Email Studio', 'corex' ) }
							</a>
						</li>
					) ) }
				</ul>
			) }
		</section>
	);
}

export function TestTab( { busy, result, version, onRun } ) {
	const pending = useContext( PendingControl );
	return (
		<section className="corex-flow-editor__panel">
			<h2>{ __( 'Marked test submission', 'corex' ) }</h2>
			<p>
				{ __(
					'Runs validation, protection, storage, routing, email, inbox, and timeline stages without entering ordinary metrics.',
					'corex'
				) }
			</p>
			<p>
				{ sprintf(
					/* translators: %d: the number of the saved draft version the test runs on. */
					__( 'Tests the saved draft, version %d', 'corex' ),
					version
				) }
			</p>
			<p className="corex-flow-editor__note">
				{ __(
					'Emails are held, not sent: each one this test would send is kept in the Email Studio log, on every site, and nobody receives it.',
					'corex'
				) }
			</p>
			<button
				type="button"
				className="button button-primary"
				disabled={ busy }
				onClick={ onRun }
				{ ...workingProps( pending === 'test' ) }
			>
				{ __( 'Run marked test', 'corex' ) }
			</button>
			{ result?.stages ? (
				<ol className="corex-flow-test-results">
					{ result.stages.map( ( stage ) => (
						<li key={ stage.key }>
							<strong>{ stage.label }</strong>
							<span>{ stage.state }</span>
							<p>{ stage.message }</p>
						</li>
					) ) }
				</ol>
			) : null }
			{ result?.stages ? (
				<HeldEmails emails={ result.held_emails } />
			) : null }
		</section>
	);
}
