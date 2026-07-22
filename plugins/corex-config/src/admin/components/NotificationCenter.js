/**
 * NotificationCenter — binds the server-rendered header bell to the React drawer (spec 072 FR-016).
 *
 * The bell already exists in the DOM and shows the unread count without JavaScript; this enhances it,
 * toggling the drawer on click, mirroring the open state onto the bell's `aria-expanded`, and handing
 * focus back to the bell whenever the drawer closes.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import NotificationDrawer from './NotificationDrawer.js';

export default function NotificationCenter( { bell } ) {
	const [ open, setOpen ] = useState( false );

	const close = useCallback( () => {
		setOpen( false );
		bell?.focus();
	}, [ bell ] );

	useEffect( () => {
		if ( ! bell ) {
			return undefined;
		}
		const toggle = () => setOpen( ( current ) => ! current );
		bell.addEventListener( 'click', toggle );
		return () => bell.removeEventListener( 'click', toggle );
	}, [ bell ] );

	useEffect( () => {
		bell?.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	}, [ bell, open ] );

	return <NotificationDrawer open={ open } onClose={ close } />;
}
