/**
 * Pending access requests, with decisions that actually decide (spec 079).
 *
 * The panel this replaces mapped `config.requests` to a bare list of reasons and explained that the
 * "pending request workflow is backed by the Access REST routes". Both the route and the screen
 * handed it a hardcoded empty array, so it never rendered a row — and there was nothing to press.
 * A person asking for access was told an administrator would review it, and no administrator could
 * see it.
 *
 * `AccessService::decideRequest()` was already complete: it transitions the row, grants the ability
 * on approval, records an audit event and notifies. Only the surface was missing.
 */
import { useState } from '@wordpress/element';
import CorexErrorState from '../admin/components/CorexErrorState.js';
import { __, sprintf } from '@wordpress/i18n';
import CorexTime from '../admin/components/CorexTime.js';

/**
 * Approve or deny a request through the CoreX access API.
 *
 * @param {Object}  config   The localized screen config.
 * @param {number}  id       The request ID.
 * @param {boolean} approved Whether to approve.
 * @return {Promise<void>} Resolves when the decision is recorded; rejects with a readable message.
 */
async function decide( config, id, approved ) {
	const response = await window.fetch(
		`${ config.restUrl }/requests/${ id }/decision`,
		{
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify( { approved, note: '' } ),
		}
	);

	if ( ! response.ok ) {
		// Deliberately not the response body: it may carry a WordPress error payload, and this
		// message is rendered on the page.
		throw new Error(
			__(
				'CoreX could not record that decision. Nothing changed — please try again.',
				'corex'
			)
		);
	}
}

export default function AccessRequestsPanel( { config } ) {
	const [ requests, setRequests ] = useState( config?.requests || [] );
	const [ busyId, setBusyId ] = useState( 0 );
	const [ error, setError ] = useState( '' );

	const onDecide = async ( id, approved ) => {
		setBusyId( id );
		setError( '' );

		try {
			await decide( config, id, approved );
			// Removed only after the server confirmed it. Removing first would show the
			// administrator a decision that may not have been recorded.
			setRequests( ( current ) =>
				current.filter( ( request ) => request.id !== id )
			);
		} catch ( failure ) {
			setError( failure.message );
		} finally {
			setBusyId( 0 );
		}
	};

	return (
		<section>
			<h3>{ __( 'Access requests', 'corex' ) }</h3>

			{ error && <CorexErrorState scale="action" message={ error } /> }

			{ requests.length === 0 ? (
				<p className="corex-access__muted">
					{ __(
						'No one is waiting for access. Requests made from a denied CoreX screen appear here.',
						'corex'
					) }
				</p>
			) : (
				<ul className="corex-access__requests">
					{ requests.map( ( request ) => (
						<li
							className="corex-access__request"
							key={ request.id }
						>
							<p className="corex-access__request-who">
								{ sprintf(
									/* translators: 1: person's name, 2: the ability they asked for. */
									__( '%1$s asked for %2$s', 'corex' ),
									request.requester,
									request.ability
								) }
							</p>
							<p className="corex-access__request-when">
								<CorexTime
									value={ request.requested_at }
									kind="full"
									absent={ __( 'Not recorded', 'corex' ) }
								/>
							</p>
							<p className="corex-access__request-why">
								{ request.reason }
							</p>
							<div className="corex-access__request-actions">
								<button
									type="button"
									className="button button-primary"
									disabled={ busyId === request.id }
									onClick={ () =>
										onDecide( request.id, true )
									}
								>
									{ __( 'Approve', 'corex' ) }
								</button>
								<button
									type="button"
									className="button"
									disabled={ busyId === request.id }
									onClick={ () =>
										onDecide( request.id, false )
									}
								>
									{ __( 'Deny', 'corex' ) }
								</button>
							</div>
						</li>
					) ) }
				</ul>
			) }
		</section>
	);
}
