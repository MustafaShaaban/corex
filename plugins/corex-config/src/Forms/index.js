import { createRoot, render } from '@wordpress/element';
import { FlowEditorPanel } from './FlowEditorPanel.js';
import { FlowList } from './FlowList.js';
import { useFlows } from './useFlows.js';

const config = window.corexFlows || { restUrl: '', nonce: '', ownerId: 0 };

function App() {
	const studio = useFlows( config );
	const { state } = studio;

	return (
		<div className="corex-flows-app">
			{ state.message ? (
				<div className={ `corex-flows-app__notice is-${ state.status }` } role={ state.status === 'error' ? 'alert' : 'status' }>
					{ state.message }
				</div>
			) : null }
			{ state.draft && state.extensions ? (
				<FlowEditorPanel studio={ studio } onBack={ () => studio.dispatch( { type: 'cleared' } ) } />
			) : (
				<FlowList
					flows={ state.flows }
					status={ state.status }
					ownerId={ Number( config.ownerId ) }
					onLoad={ studio.load }
					onCreate={ studio.create }
					onSelect={ studio.select }
				/>
			) }
		</div>
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
