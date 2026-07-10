import { createRoot, render } from '@wordpress/element';
import DataModelsApp from './DataModelsApp.js';

const mount = document.getElementById( 'corex-data-models-app' );
if ( mount ) {
	const config = window.corexDataModels || { restUrl: '', nonce: '', sources: [] };
	const app = <DataModelsApp config={ config } />;
	if ( typeof createRoot === 'function' ) createRoot( mount ).render( app );
	else render( app, mount );
}
