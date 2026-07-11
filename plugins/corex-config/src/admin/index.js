/** CoreX Data and adjacent product clients share one wp-scripts entry. */
import { createRoot, render } from '@wordpress/element';
import '../Email/index.js';
import '../Forms/index.js';
import '../Submissions/index.js';
import '../DataModels/index.js';
import AccessWorkspace from '../access/AccessWorkspace.js';
import BlogProApp from '../blog/BlogProApp.js';
import SecurityCenter from '../Security/SecurityCenter.js';
import DataExplorer from './data/DataExplorer.js';

const mount = document.getElementById( 'corex-data-app' );
if ( mount ) {
	const config = window.corexData || { restUrl: '', nonce: '', sources: [] };
	const app = <DataExplorer config={ config } />;
	if ( typeof createRoot === 'function' ) {
		createRoot( mount ).render( app );
	} else {
		render( app, mount );
	}
}

const accessMount = document.getElementById( 'corex-access-app' );
if ( accessMount ) {
	const config = window.corexAccess || { matrix: {}, requests: [], audit: [] };
	const app = <AccessWorkspace config={ config } />;
	if ( typeof createRoot === 'function' ) {
		createRoot( accessMount ).render( app );
	} else {
		render( app, accessMount );
	}
}

const securityMount = document.getElementById( 'corex-security-app' );
if ( securityMount ) {
	const config = window.corexSecurity || { mode: 'staging', readiness: {}, loginPolicy: {}, lockouts: [], activity: [] };
	const app = <SecurityCenter config={ config } />;
	if ( typeof createRoot === 'function' ) {
		createRoot( securityMount ).render( app );
	} else {
		render( app, securityMount );
	}
}

const blogMount = document.getElementById( 'corex-blog-pro-app' );
if ( blogMount ) {
	const config = window.corexBlogPro || { posts: [], analytics: {}, comments: [], authors: [], shareControls: [] };
	const app = <BlogProApp config={ config } />;
	if ( typeof createRoot === 'function' ) {
		createRoot( blogMount ).render( app );
	} else {
		render( app, blogMount );
	}
}
