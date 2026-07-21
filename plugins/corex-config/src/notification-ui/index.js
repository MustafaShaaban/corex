/**
 * Notification-center entry — enhances the server-rendered header bell with the drawer (spec 072
 * FR-016). Enqueued on every CoreX admin screen (CorexAdminAssets), independently of the per-screen
 * product bundles, so the bell opens everywhere and the drawer mounts exactly once. A no-op when the
 * shell header (and its bell) is absent.
 */
import { createRoot, render } from '@wordpress/element';
import NotificationCenter from '../admin/components/NotificationCenter.js';

const bell = document.querySelector( '[data-corex-notification-bell]' );
if ( bell && bell.parentNode ) {
	const mount = document.createElement( 'div' );
	bell.parentNode.appendChild( mount );
	const app = <NotificationCenter bell={ bell } />;
	if ( typeof createRoot === 'function' ) {
		createRoot( mount ).render( app );
	} else {
		render( app, mount );
	}
}
