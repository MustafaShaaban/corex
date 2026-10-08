import { useContext } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	PendingControl,
	workingProps,
} from '../../admin/components/working.js';

export function TestTab( { busy, result, onRun } ) {
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
		</section>
	);
}
