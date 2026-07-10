import { __ } from '@wordpress/i18n';

const STAGES = [
	[ 'form', __( 'Form', 'corex' ) ],
	[ 'validation', __( 'Validation', 'corex' ) ],
	[ 'routing', __( 'Routing', 'corex' ) ],
	[ 'emails', __( 'Emails', 'corex' ) ],
	[ 'success', __( 'Success', 'corex' ) ],
	[ 'preview', __( 'Preview', 'corex' ) ],
	[ 'test', __( 'Test', 'corex' ) ],
];

export function StageRail( { active, statuses, onChange } ) {
	return (
		<nav className="corex-flow-editor__stages" aria-label={ __( 'Flow stages', 'corex' ) }>
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
					<small className={ `is-${ statuses[ key ] || 'ready' }` }>
						{ statuses[ key ] === 'incomplete'
							? __( 'Incomplete', 'corex' )
							: __( 'Ready', 'corex' ) }
					</small>
				</button>
			) ) }
		</nav>
	);
}
