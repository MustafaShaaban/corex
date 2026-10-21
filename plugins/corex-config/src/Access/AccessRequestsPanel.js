/**
 * Pending access requests, with decisions that actually decide (spec 079).
 *
 * The panel this replaces mapped `config.requests` to a bare list of reasons and explained that the
 * "pending request workflow is backed by the Access REST routes". Both the route and the screen
 * handed it a hardcoded empty array, so it never rendered a row — and there was nothing to press.
 *
 * Approving opens the request's preview first (spec 112, FR-002): what it grants, with a note, an
 * expiry and, for a dangerous ability, its name typed. A decision is taken from the server's answer,
 * not from the request having been sent: a refused one is answered 200 with the state `blocked`.
 */
import { useState } from '@wordpress/element';
import CorexErrorState from '../admin/components/CorexErrorState.js';
import { __, sprintf } from '@wordpress/i18n';
import CorexTime from '../admin/components/CorexTime.js';
import { usePending, workingProps } from '../admin/components/working.js';
import AccessApprovalDialog from './AccessApprovalDialog.js';
import { denyRequest, previewApproval, refusalOf } from './accessClient.js';

export default function AccessRequestsPanel( {
	config,
	onApproved = () => {},
} ) {
	const [ requests, setRequests ] = useState( config?.requests || [] );
	// One decision at a time. It was one id: deciding a second request while the first was
	// out handed the first one's buttons back before its answer.
	const [ pending, during ] = usePending();
	const [ error, setError ] = useState( '' );
	const [ approving, setApproving ] = useState( null );

	// Removed only after the server confirmed it. Removing first would show the administrator a
	// decision that may not have been recorded.
	const settled = ( id ) =>
		setRequests( ( current ) =>
			current.filter( ( request ) => request.id !== id )
		);

	const onApprove = async ( id ) => {
		setError( '' );
		try {
			const preview = await previewApproval( config, id );
			if ( preview === null ) {
				settled( id );
				return;
			}
			setApproving( preview );
		} catch ( failure ) {
			setError( failure.message );
		}
	};

	const onDeny = async ( id ) => {
		setError( '' );
		try {
			const result = await denyRequest( config, id );
			if ( result.state === 'completed' ) {
				settled( id );
				return;
			}
			setError( refusalOf( result ) );
		} catch ( failure ) {
			setError( failure.message );
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
								{ request.ability
									? sprintf(
											/* translators: 1: person's name, 2: the ability they asked for. */
											__(
												'%1$s asked for %2$s',
												'corex'
											),
											request.requester,
											request.ability
										)
									: sprintf(
											/* translators: 1: person's name, 2: a CoreX area's key, e.g. "data". */
											__(
												'%1$s asked for the %2$s area',
												'corex'
											),
											request.requester,
											request.area
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
									disabled={ pending !== '' }
									onClick={ () =>
										during( `approve:${ request.id }`, () =>
											onApprove( request.id )
										)
									}
									{ ...workingProps(
										pending === `approve:${ request.id }`
									) }
								>
									{ __( 'Approve', 'corex' ) }
								</button>
								<button
									type="button"
									className="button"
									disabled={ pending !== '' }
									onClick={ () =>
										during( `deny:${ request.id }`, () =>
											onDeny( request.id )
										)
									}
									{ ...workingProps(
										pending === `deny:${ request.id }`
									) }
								>
									{ __( 'Deny', 'corex' ) }
								</button>
							</div>
						</li>
					) ) }
				</ul>
			) }

			{ approving && (
				<AccessApprovalDialog
					config={ config }
					preview={ approving }
					onClose={ () => setApproving( null ) }
					onApproved={ () => {
						settled( approving.request_id );
						setApproving( null );
						onApproved();
					} }
				/>
			) }
		</section>
	);
}
