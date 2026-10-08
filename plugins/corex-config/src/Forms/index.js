import { createRoot, render } from '@wordpress/element';
import { FlowEditorPanel } from './FlowEditorPanel.js';
import { FlowList } from './FlowList.js';
import { FlowEditorSkeleton } from './FlowSkeletons.js';
import { useFlows } from './useFlows.js';
import { PendingControl } from '../admin/components/working.js';

const config = window.corexFlows || {
	restUrl: '',
	nonce: '',
	ownerId: 0,
	catalog: [],
	submissionsUrl: '',
};

/**
 * The catalog, the editor, or the editor that is on its way.
 *
 * @param {Object} props        Component props.
 * @param {Object} props.studio The flows hook's value.
 * @return {Element} One of the three.
 */
function Workspace( { studio } ) {
	const { state } = studio;

	// A flow that is being opened is the editor, not yet filled in. The catalog used to
	// stay, disabled, until the flow arrived.
	if ( studio.pending.startsWith( 'open:' ) ) {
		return <FlowEditorSkeleton />;
	}

	if ( state.draft && state.extensions ) {
		return (
			<FlowEditorPanel
				studio={ studio }
				onBack={ () => studio.dispatch( { type: 'cleared' } ) }
			/>
		);
	}

	return (
		<FlowList
			flows={ state.flows }
			listed={ state.listed }
			catalog={ config.catalog }
			submissionsUrl={ config.submissionsUrl }
			status={ state.status }
			ownerId={ Number( config.ownerId ) }
			onLoad={ studio.load }
			onCreate={ studio.create }
			onSelect={ studio.select }
		/>
	);
}

function App() {
	const studio = useFlows( config );
	const { state } = studio;

	return (
		<PendingControl.Provider value={ studio.pending }>
			<div className="corex-flows-app">
				{ state.message ? (
					<div
						className={ `corex-flows-app__notice is-${ state.status }` }
						role={ state.status === 'error' ? 'alert' : 'status' }
					>
						{ state.message }
					</div>
				) : null }
				<Workspace studio={ studio } />
			</div>
		</PendingControl.Provider>
	);
}

const mount = document.getElementById( 'corex-forms-flows-app' );
if ( mount ) {
	if ( typeof createRoot === 'function' ) {
		createRoot( mount ).render( <App /> );
	} else {
		render( <App />, mount );
	}
}
