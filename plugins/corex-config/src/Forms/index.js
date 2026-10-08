import { createRoot, render } from '@wordpress/element';
import { FlowEditorPanel } from './FlowEditorPanel.js';
import { FlowList } from './FlowList.js';
import { useFlows } from './useFlows.js';
import { PendingControl } from '../admin/components/working.js';

const config = window.corexFlows || {
	restUrl: '',
	nonce: '',
	ownerId: 0,
	catalog: [],
	submissionsUrl: '',
};

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
				{ state.draft && state.extensions ? (
					<FlowEditorPanel
						studio={ studio }
						onBack={ () => studio.dispatch( { type: 'cleared' } ) }
					/>
				) : (
					<FlowList
						flows={ state.flows }
						catalog={ config.catalog }
						submissionsUrl={ config.submissionsUrl }
						status={ state.status }
						ownerId={ Number( config.ownerId ) }
						onLoad={ studio.load }
						onCreate={ studio.create }
						onSelect={ studio.select }
					/>
				) }
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
