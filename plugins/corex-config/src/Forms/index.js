import { createRoot, render, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { FlowEditorPanel } from './FlowEditorPanel.js';
import { FlowList } from './FlowList.js';
import { TeamsPanel } from './TeamsPanel.js';
import { FlowEditorSkeleton } from './FlowSkeletons.js';
import { useFlows } from './useFlows.js';
import { PendingControl } from '../admin/components/working.js';

const config = window.corexFlows || {
	restUrl: '',
	nonce: '',
	ownerId: 0,
	catalog: [],
	submissionsUrl: '',
	timezone: '',
	upgrade: null,
};

/**
 * Flows or Teams: the two things this screen manages (spec 122).
 *
 * @param {Object}   props
 * @param {string}   props.view     The view on screen.
 * @param {Function} props.onChange Given the view chosen.
 * @return {import('react').ReactElement} The switch, as two toggle buttons.
 */
function ViewSwitch( { view, onChange } ) {
	return (
		<nav
			className="corex-flows-app__views"
			aria-label={ __( 'Forms & Flows', 'corex' ) }
		>
			{ [
				[ 'flows', __( 'Flows', 'corex' ) ],
				[ 'teams', __( 'Teams', 'corex' ) ],
			].map( ( [ key, label ] ) => (
				<button
					key={ key }
					type="button"
					className={ view === key ? 'button is-active' : 'button' }
					aria-pressed={ view === key }
					onClick={ () => onChange( key ) }
				>
					{ label }
				</button>
			) ) }
		</nav>
	);
}

/**
 * The catalog, the editor, or the editor that is on its way.
 *
 * @param {Object} props        Component props.
 * @param {Object} props.studio The flows hook's value.
 * @return {Element} One of the three.
 */
function Workspace( { studio } ) {
	const { state } = studio;
	const [ view, setView ] = useState( 'flows' );

	// A flow that is being opened is the editor, not yet filled in. The catalog used to
	// stay, disabled, until the flow arrived.
	if ( studio.pending.startsWith( 'open:' ) ) {
		return <FlowEditorSkeleton />;
	}

	if ( state.draft && state.extensions ) {
		return (
			<FlowEditorPanel
				studio={ studio }
				timezone={ config.timezone }
				onBack={ () => studio.dispatch( { type: 'cleared' } ) }
			/>
		);
	}

	return (
		<>
			<ViewSwitch view={ view } onChange={ setView } />
			{ view === 'teams' ? (
				<TeamsPanel
					client={ studio.teams }
					people={ state.extensions?.people || [] }
					onChange={ ( teams ) =>
						studio.dispatch( {
							type: 'teams_changed',
							payload: teams,
						} )
					}
				/>
			) : (
				<FlowList
					flows={ state.flows }
					listed={ state.listed }
					catalog={ config.catalog }
					submissionsUrl={ config.submissionsUrl }
					status={ state.status }
					ownerId={ Number( config.ownerId ) }
					upgrade={ config.upgrade }
					onLoad={ studio.load }
					onCreate={ studio.create }
					onSelect={ studio.select }
				/>
			) }
		</>
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
