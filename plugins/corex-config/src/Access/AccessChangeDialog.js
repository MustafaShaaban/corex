/**
 * What a staged change to a role would do, read before it is applied (#313).
 *
 * Each ability is named with what it is now and what it would become. Where the safety policy
 * refuses the change, the reason stands where the button to apply it would be pressed, and the
 * button is not offered: a change that cannot be applied is not something to confirm.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import CorexDialog from '../admin/components/CorexDialog.js';
import CorexErrorState from '../admin/components/CorexErrorState.js';
import { usePending, workingProps } from '../admin/components/working.js';
import { describeChanges } from './accessState.js';

/**
 * Why the safety policy refuses a change, by the code `AccessPolicy::preview()` gives it.
 *
 * @param {string} code The blocker's code.
 * @return {string} The reason, translated.
 */
function blockerReason( code ) {
	if ( code === 'ability_locked' ) {
		return __( 'This ability is locked and cannot be changed.', 'corex' );
	}
	if ( code === 'self_lockout' ) {
		return __(
			'Denying this would lock you out: you have this role.',
			'corex'
		);
	}
	if ( code === 'last_full_access_admin' ) {
		return __(
			'Denying this would leave no administrator with full access.',
			'corex'
		);
	}

	return __( 'The access safety policy refuses this change.', 'corex' );
}

export default function AccessChangeDialog( {
	preview,
	roleName,
	rows,
	effectLabel,
	onApply,
	onClose,
} ) {
	const [ pending, during ] = usePending();
	const [ failure, setFailure ] = useState( '' );
	const lines = describeChanges( rows, preview.role, preview.changes );
	const labelOf = ( ability ) =>
		rows.find( ( row ) => row.key === ability )?.label || ability;

	const apply = async () => {
		setFailure( '' );
		try {
			await onApply();
		} catch ( reason ) {
			setFailure( reason.message );
		}
	};

	return (
		<CorexDialog
			title={ sprintf(
				/* translators: %s: a role's name, e.g. "Editor". */
				__( 'Changes to %s', 'corex' ),
				roleName
			) }
			onClose={ onClose }
			busy={ pending !== '' }
			footer={
				<div className="corex-access__dialog-actions">
					<button
						type="button"
						className="button"
						disabled={ pending !== '' }
						onClick={ onClose }
					>
						{ __( 'Cancel', 'corex' ) }
					</button>
					{ preview.allowed && (
						<button
							type="button"
							className="button button-primary"
							onClick={ () => during( 'apply', apply ) }
							{ ...workingProps( pending === 'apply' ) }
						>
							{ __( 'Apply changes', 'corex' ) }
						</button>
					) }
				</div>
			}
		>
			<ul className="corex-access__changes">
				{ lines.map( ( line ) => (
					<li key={ line.key }>
						<strong>{ line.label }</strong>
						<span>
							{ sprintf(
								/* translators: 1: the state an ability has now, 2: the state it would have, e.g. "Inherit" and "Allow". */
								__( '%1$s → %2$s', 'corex' ),
								effectLabel( line.from ),
								effectLabel( line.to )
							) }
						</span>
					</li>
				) ) }
			</ul>
			{ ! preview.allowed && (
				<CorexErrorState
					scale="action"
					message={ __(
						'These changes cannot be applied.',
						'corex'
					) }
				>
					<ul>
						{ preview.blockers.map( ( blocker ) => (
							<li
								key={ `${ blocker.code }:${ blocker.ability }` }
							>
								{ sprintf(
									/* translators: 1: an ability's name, 2: why it cannot be changed. */
									__( '%1$s: %2$s', 'corex' ),
									labelOf( blocker.ability ),
									blockerReason( blocker.code )
								) }
							</li>
						) ) }
					</ul>
				</CorexErrorState>
			) }
			{ failure && (
				<CorexErrorState scale="action" message={ failure } />
			) }
		</CorexDialog>
	);
}
