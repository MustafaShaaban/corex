import { createRoot, render, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TABS } from './emailStudioClient.js';
import { Notice, StudioTabs } from './components/index.js';
import { StudioPanel } from './StudioPanel.js';
import { useEmailStudio } from './useEmailStudio.js';

const config = window.corexEmailStudio || {
	restUrl: '',
	nonce: '',
	settingsUrl: '',
};

function translatedTabs() {
	const labels = {
		overview: __( 'Overview', 'corex' ),
		templates: __( 'Templates', 'corex' ),
		layouts: __( 'Layouts', 'corex' ),
		partials: __( 'Partials', 'corex' ),
		variables: __( 'Variables', 'corex' ),
		routing: __( 'Routing', 'corex' ),
		preview: __( 'Preview', 'corex' ),
		plain: __( 'Plain text', 'corex' ),
		test: __( 'Test send', 'corex' ),
		logs: __( 'Delivery logs', 'corex' ),
		health: __( 'Health', 'corex' ),
	};
	return TABS.map( ( tab ) => ( { ...tab, label: labels[ tab.key ] } ) );
}

function StudioContent( { tab, studio } ) {
	const isInitialLoad =
		studio.state.status === 'loading' &&
		studio.state.data.templates.length === 0;
	return isInitialLoad ? (
		<p role="status">{ __( 'Loading Email Studio…', 'corex' ) }</p>
	) : (
		<StudioPanel tab={ tab } studio={ studio } config={ config } />
	);
}

function App() {
	const [ tab, setTab ] = useState( 'overview' );
	const studio = useEmailStudio( config, setTab );

	return (
		<div className="corex-email-app">
			<StudioTabs
				tabs={ translatedTabs() }
				active={ tab }
				onChange={ setTab }
			/>
			{ studio.state.message && (
				<Notice
					tone={
						studio.state.status === 'error' ? 'error' : 'success'
					}
				>
					{ studio.state.message }
				</Notice>
			) }
			<StudioContent tab={ tab } studio={ studio } />
		</div>
	);
}

const mount = document.getElementById( 'corex-email-studio-app' );
if ( mount ) {
	if ( typeof createRoot === 'function' ) {
		createRoot( mount ).render( <App /> );
	} else {
		render( <App />, mount );
	}
}
