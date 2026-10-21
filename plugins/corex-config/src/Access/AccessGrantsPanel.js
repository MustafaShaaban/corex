/**
 * The abilities given by approving requests, while they stand (spec 112, FR-005 and FR-007).
 *
 * Each grant says who has it, who approved it and why, and when it ends; one approved before
 * expiries existed says it has none. Revoke takes the ability back. The list is read again after
 * an approval, through `refreshKey`.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import CorexErrorState from '../admin/components/CorexErrorState.js';
import CorexTime from '../admin/components/CorexTime.js';
import { usePending, workingProps } from '../admin/components/working.js';
import { fetchGrants, refusalOf, revokeGrant } from './accessClient.js';

export default function AccessGrantsPanel( { config, refreshKey = 0 } ) {
	const [ grants, setGrants ] = useState( config?.grants || [] );
	const [ error, setError ] = useState( '' );
	const [ pending, during ] = usePending();

	useEffect( () => {
		if ( refreshKey === 0 ) {
			return;
		}
		fetchGrants( config ).then( setGrants, ( failure ) =>
			setError( failure.message )
		);
	}, [ refreshKey, config ] );

	const revoke = async ( id ) => {
		setError( '' );
		try {
			const result = await revokeGrant( config, id );
			if ( result.state !== 'completed' ) {
				setError( refusalOf( result ) );
				return;
			}
			setGrants( ( current ) =>
				current.filter( ( grant ) => grant.id !== id )
			);
		} catch ( failure ) {
			setError( failure.message );
		}
	};

	return (
		<section>
			<h3>{ __( 'Access granted by approval', 'corex' ) }</h3>

			{ error && <CorexErrorState scale="action" message={ error } /> }

			{ grants.length === 0 ? (
				<p className="corex-access__muted">
					{ __(
						'No one holds access granted by approving a request.',
						'corex'
					) }
				</p>
			) : (
				<ul className="corex-access__grants">
					{ grants.map( ( grant ) => (
						<li className="corex-access__request" key={ grant.id }>
							<p className="corex-access__request-who">
								{ sprintf(
									/* translators: 1: person's name, 2: the abilities they were given, comma-separated. */
									__( '%1$s has %2$s', 'corex' ),
									grant.person.name,
									grant.abilities
										.map( ( ability ) => ability.label )
										.join( ', ' )
								) }
							</p>
							<p className="corex-access__request-when">
								{ sprintf(
									/* translators: %s: the name of the administrator who approved it. */
									__( 'Approved by %s', 'corex' ),
									grant.approver?.name ||
										__( 'an administrator', 'corex' )
								) }{ ' ' }
								<CorexTime
									value={ grant.approved_at }
									kind="full"
									absent={ __( 'Not recorded', 'corex' ) }
								/>
							</p>
							{ grant.note && (
								<p className="corex-access__request-why">
									{ grant.note }
								</p>
							) }
							<p className="corex-access__request-when">
								{ grant.expires_at ? (
									<>
										{ __( 'Ends', 'corex' ) }{ ' ' }
										<CorexTime
											value={ grant.expires_at }
											kind="full"
										/>
									</>
								) : (
									__(
										'No expiry (granted before expiries existed)',
										'corex'
									)
								) }
							</p>
							{ ! grant.in_effect && (
								<p className="corex-access__error">
									{ __(
										'No longer in effect: the ability was removed outside CoreX.',
										'corex'
									) }
								</p>
							) }
							<div className="corex-access__request-actions">
								<button
									type="button"
									className="button"
									disabled={ pending !== '' }
									onClick={ () =>
										during( `revoke:${ grant.id }`, () =>
											revoke( grant.id )
										)
									}
									{ ...workingProps(
										pending === `revoke:${ grant.id }`
									) }
								>
									{ __( 'Revoke', 'corex' ) }
								</button>
							</div>
						</li>
					) ) }
				</ul>
			) }
		</section>
	);
}
