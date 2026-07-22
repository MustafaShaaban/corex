/**
 * NotificationDrawer — the panel the header bell opens (spec 072 FR-016).
 *
 * A modal dialog listing the actor's notifications, fetched from the live REST boundary. It traps
 * focus while open, closes on Escape or backdrop click, and returns focus to the bell on close — the
 * accessibility contract the design inventory approves the component against. The server-rendered
 * bell already shows the unread count with no JavaScript; this enhances it with the openable list.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const FOCUSABLE =
	'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';

export default function NotificationDrawer( { open, onClose } ) {
	const panelRef = useRef( null );
	const [ status, setStatus ] = useState( 'idle' );
	const [ items, setItems ] = useState( [] );

	const load = useCallback( () => {
		setStatus( 'loading' );
		// `status=unread` is the derived per-user status. Not `unread_only`, which means
		// "unresolved" and would list items this actor already read — the drawer would then
		// contradict itself, since marking one read removes it locally but a reopen fetched it
		// straight back, and it would disagree with the bell beside it, which counts unread.
		apiFetch( {
			path: '/corex/v1/notifications?status=unread&per_page=15',
		} )
			.then( ( response ) => {
				setItems( response?.data?.items ?? [] );
				setStatus( 'ready' );
			} )
			.catch( () => setStatus( 'error' ) );
	}, [] );

	// Fetch fresh each time the drawer opens — a notification list is only useful current.
	useEffect( () => {
		if ( open ) {
			load();
		}
	}, [ open, load ] );

	// Focus management: move focus into the panel on open, trap Tab within it, close on Escape.
	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const panel = panelRef.current;
		const focusables = () => Array.from( panel.querySelectorAll( FOCUSABLE ) );
		( focusables()[ 0 ] || panel ).focus();

		const onKeyDown = ( event ) => {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				onClose();
				return;
			}
			if ( event.key !== 'Tab' ) {
				return;
			}
			const nodes = focusables();
			if ( nodes.length === 0 ) {
				event.preventDefault();
				return;
			}
			const first = nodes[ 0 ];
			const last = nodes[ nodes.length - 1 ];
			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		};
		panel.addEventListener( 'keydown', onKeyDown );
		return () => panel.removeEventListener( 'keydown', onKeyDown );
	}, [ open, onClose ] );

	const markRead = useCallback( ( id ) => {
		apiFetch( { path: `/corex/v1/notifications/${ id }/read`, method: 'POST' } )
			.then( () =>
				setItems( ( current ) =>
					current.filter( ( item ) => item.id !== id )
				)
			)
			.catch( () => {} );
	}, [] );

	const markAllRead = useCallback( () => {
		apiFetch( { path: '/corex/v1/notifications/read-all', method: 'POST' } )
			.then( () => setItems( [] ) )
			.catch( () => {} );
	}, [] );

	if ( ! open ) {
		return null;
	}

	return (
		<div className="corex-notification-drawer">
			<div
				className="corex-notification-drawer__backdrop"
				onClick={ onClose }
				aria-hidden="true"
			/>
			<div
				className="corex-notification-drawer__panel"
				ref={ panelRef }
				role="dialog"
				aria-modal="true"
				aria-label={ __( 'Notifications', 'corex' ) }
				tabIndex={ -1 }
			>
				<header className="corex-notification-drawer__header">
					<h2 className="corex-notification-drawer__title">
						{ __( 'Notifications', 'corex' ) }
					</h2>
					<button
						type="button"
						className="corex-notification-drawer__close"
						onClick={ onClose }
						aria-label={ __( 'Close notifications', 'corex' ) }
					>
						×
					</button>
				</header>
				{ status === 'loading' && (
					<p className="corex-notification-drawer__state">
						{ __( 'Loading notifications…', 'corex' ) }
					</p>
				) }
				{ status === 'error' && (
					<p className="corex-notification-drawer__state" role="alert">
						{ __(
							'Notifications could not be loaded. Try again shortly.',
							'corex'
						) }
					</p>
				) }
				{ status === 'ready' && items.length === 0 && (
					<p className="corex-notification-drawer__state">
						{ __( 'You’re all caught up.', 'corex' ) }
					</p>
				) }
				{ status === 'ready' && items.length > 0 && (
					<>
						<ul className="corex-notification-drawer__list">
							{ items.map( ( item ) => (
								<li
									key={ item.id }
									className="corex-notification-drawer__item"
									data-severity={ item.severity }
								>
									<p className="corex-notification-drawer__item-title">
										{ item.rendered?.title ?? '' }
									</p>
									<p className="corex-notification-drawer__item-body">
										{ item.rendered?.body ?? '' }
									</p>
									<button
										type="button"
										className="corex-notification-drawer__mark"
										onClick={ () => markRead( item.id ) }
									>
										{ __( 'Mark read', 'corex' ) }
									</button>
								</li>
							) ) }
						</ul>
						<button
							type="button"
							className="corex-notification-drawer__mark-all"
							onClick={ markAllRead }
						>
							{ __( 'Mark all as read', 'corex' ) }
						</button>
					</>
				) }
			</div>
		</div>
	);
}
