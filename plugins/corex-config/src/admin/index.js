/** CoreX Data and adjacent product clients share one wp-scripts entry. */
import { createRoot, render } from '@wordpress/element';
import '../Email/index.js';
import '../Forms/index.js';
import '../Submissions/index.js';
import '../DataModels/index.js';
import AccessWorkspace from '../access/AccessWorkspace.js';
import BlogProApp from '../blog/BlogProApp.js';
import SecurityCenter from '../Security/SecurityCenter.js';

// The `corex-data-app` mount is gone: the standalone Data screen rendered the same DataExplorer as
// the Data Models Records tab, so the screen was retired and its address redirects there. The
// explorer itself lives on, mounted by DataModelsApp.

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
