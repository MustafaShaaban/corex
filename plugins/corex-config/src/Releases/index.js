/**
 * The Releases screen's mount (spec 107). `ReleasesScreen` prints the node and the two things
 * the installer's client needs: where the Releases routes are, and the nonce that signs a
 * request to them.
 */
import { createRoot } from '@wordpress/element';
import ReleasesApp from './ReleasesApp.js';
import { createReleaseClient } from './releaseClient.js';

const mount = document.getElementById( 'corex-releases-app' );

if ( mount && window.corexReleases ) {
	createRoot( mount ).render(
		<ReleasesApp client={ createReleaseClient( window.corexReleases ) } />
	);
}
