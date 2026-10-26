import { __ } from '@wordpress/i18n';

const STAGES = [
	[ 'form', __( 'Form', 'corex' ) ],
	[ 'validation', __( 'Validation', 'corex' ) ],
	[ 'routing', __( 'Routing', 'corex' ) ],
	[ 'emails', __( 'Emails', 'corex' ) ],
	[ 'protection', __( 'Protection', 'corex' ) ],
	[ 'success', __( 'Success', 'corex' ) ],
	[ 'availability', __( 'Availability', 'corex' ) ],
	[ 'preview', __( 'Preview', 'corex' ) ],
	[ 'test', __( 'Test', 'corex' ) ],
	[ 'versions', __( 'Versions', 'corex' ) ],
];

/** Tabs that are not a step of building the flow, so they say nothing about being ready. */
const WITHOUT_STATUS = [ 'availability', 'versions' ];

export function StageRail( { active, statuses, onChange } ) {
	return (
		<nav
			className="corex-flow-editor__stages"
			aria-label={ __( 'Flow stages', 'corex' ) }
		>
			{ STAGES.map( ( [ key, label ], index ) => (
				<button
					key={ key }
					type="button"
					className={ active === key ? 'is-active' : '' }
					aria-current={ active === key ? 'step' : undefined }
					onClick={ () => onChange( key ) }
				>
					<span>{ index + 1 }</span>
					<strong>{ label }</strong>
					{ WITHOUT_STATUS.includes( key ) ? null : (
						<small
							className={ `is-${ statuses[ key ] || 'ready' }` }
						>
							{ statuses[ key ] === 'incomplete'
								? __( 'Incomplete', 'corex' )
								: __( 'Ready', 'corex' ) }
						</small>
					) }
				</button>
			) ) }
		</nav>
	);
}
