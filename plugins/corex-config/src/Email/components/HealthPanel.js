import { __, sprintf } from '@wordpress/i18n';
import { Notice } from './shared.js';

function HealthResults( { health } ) {
	if ( ! health ) {
		return null;
	}
	if ( Object.keys( health ).length === 0 ) {
		return (
			<Notice tone="success">
				{ __( 'All available checks passed.', 'corex' ) }
			</Notice>
		);
	}

	return (
		<ul className="corex-email-app__health">
			{ Object.entries( health ).map( ( [ key, message ] ) => (
				<li key={ key }>
					<strong>{ key }</strong>
					<span>{ message }</span>
				</li>
			) ) }
		</ul>
	);
}

export function HealthPanel( { detail, health, busy, onRun } ) {
	return (
		<section className="corex-surface corex-email-app__editor">
			<h2>{ __( 'Template health', 'corex' ) }</h2>
			{ ! detail ? (
				<p>{ __( 'Select a template first.', 'corex' ) }</p>
			) : (
				<>
					<p>
						{ sprintf(
							/* translators: %s: Email template name. */
							__( 'Checking %s', 'corex' ),
							detail.template.name
						) }
					</p>
					<button
						type="button"
						className="button button-primary"
						disabled={ busy || detail.template.draft_version < 1 }
						onClick={ onRun }
					>
						{ __( 'Run health checks', 'corex' ) }
					</button>
					<HealthResults health={ health } />
				</>
			) }
		</section>
	);
}
