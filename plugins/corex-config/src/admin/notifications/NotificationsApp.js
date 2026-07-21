/**
 * NotificationsApp — the full CoreX → Notifications screen (spec 072 US1, FR-018).
 *
 * The bounded, filtered center: a paginated list of the actor's notifications with an unread-only
 * filter and a severity filter, per-item mark-read, and a bulk mark-all. It consumes the same live
 * REST boundary as the header drawer, with honest loading / error / empty states. Advanced saved
 * views (assigned / system / history) build on this list.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const SEVERITIES = [ 'critical', 'error', 'warning', 'action', 'information', 'success' ];

export default function NotificationsApp() {
	const [ status, setStatus ] = useState( 'loading' );
	const [ items, setItems ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ perPage ] = useState( 20 );
	const [ unreadOnly, setUnreadOnly ] = useState( false );
	const [ severity, setSeverity ] = useState( '' );

	const load = useCallback( () => {
		setStatus( 'loading' );
		const query = new URLSearchParams( {
			page: String( page ),
			per_page: String( perPage ),
		} );
		if ( unreadOnly ) {
			query.set( 'unread_only', '1' );
		}
		if ( severity ) {
			query.set( 'severity', severity );
		}
		apiFetch( { path: `/corex/v1/notifications?${ query.toString() }` } )
			.then( ( response ) => {
				setItems( response?.data?.items ?? [] );
				setTotal( response?.data?.total ?? 0 );
				setStatus( 'ready' );
			} )
			.catch( () => setStatus( 'error' ) );
	}, [ page, perPage, unreadOnly, severity ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const markRead = useCallback(
		( id ) => {
			apiFetch( {
				path: `/corex/v1/notifications/${ id }/read`,
				method: 'POST',
			} )
				.then( load )
				.catch( () => {} );
		},
		[ load ]
	);

	const markAllRead = useCallback( () => {
		apiFetch( {
			path: '/corex/v1/notifications/read-all',
			method: 'POST',
		} )
			.then( load )
			.catch( () => {} );
	}, [ load ] );

	const pages = Math.max( 1, Math.ceil( total / perPage ) );

	return (
		<div className="corex-notifications-screen">
			<div className="corex-notifications-screen__filters">
				<label className="corex-notifications-screen__filter">
					<input
						type="checkbox"
						checked={ unreadOnly }
						onChange={ ( event ) => {
							setPage( 1 );
							setUnreadOnly( event.target.checked );
						} }
					/>
					{ __( 'Unread only', 'corex' ) }
				</label>
				<label className="corex-notifications-screen__filter">
					{ __( 'Severity', 'corex' ) }
					<select
						value={ severity }
						onChange={ ( event ) => {
							setPage( 1 );
							setSeverity( event.target.value );
						} }
					>
						<option value="">{ __( 'All', 'corex' ) }</option>
						{ SEVERITIES.map( ( level ) => (
							<option key={ level } value={ level }>
								{ level }
							</option>
						) ) }
					</select>
				</label>
				<button
					type="button"
					className="corex-notifications-screen__mark-all"
					onClick={ markAllRead }
				>
					{ __( 'Mark all as read', 'corex' ) }
				</button>
			</div>

			{ status === 'loading' && (
				<p className="corex-notifications-screen__state">
					{ __( 'Loading notifications…', 'corex' ) }
				</p>
			) }
			{ status === 'error' && (
				<p className="corex-notifications-screen__state" role="alert">
					{ __( 'Notifications could not be loaded.', 'corex' ) }
				</p>
			) }
			{ status === 'ready' && items.length === 0 && (
				<p className="corex-notifications-screen__state">
					{ __( 'Nothing here — you’re all caught up.', 'corex' ) }
				</p>
			) }
			{ status === 'ready' && items.length > 0 && (
				<ul className="corex-notifications-screen__list">
					{ items.map( ( item ) => (
						<li
							key={ item.id }
							className="corex-notifications-screen__item"
							data-severity={ item.severity }
						>
							<div className="corex-notifications-screen__item-main">
								<p className="corex-notifications-screen__item-title">
									{ item.rendered?.title ?? '' }
								</p>
								<p className="corex-notifications-screen__item-body">
									{ item.rendered?.body ?? '' }
								</p>
							</div>
							{ ! item.user_state?.read && (
								<button
									type="button"
									className="corex-notifications-screen__mark"
									onClick={ () => markRead( item.id ) }
								>
									{ __( 'Mark read', 'corex' ) }
								</button>
							) }
						</li>
					) ) }
				</ul>
			) }

			{ status === 'ready' && pages > 1 && (
				<nav
					className="corex-notifications-screen__pager"
					aria-label={ __( 'Notifications pages', 'corex' ) }
				>
					<button
						type="button"
						disabled={ page <= 1 }
						onClick={ () => setPage( ( current ) => current - 1 ) }
					>
						{ __( 'Previous', 'corex' ) }
					</button>
					<span>
						{ /* translators: 1: current page, 2: total pages. */ }
						{ __( 'Page', 'corex' ) } { page } / { pages }
					</span>
					<button
						type="button"
						disabled={ page >= pages }
						onClick={ () => setPage( ( current ) => current + 1 ) }
					>
						{ __( 'Next', 'corex' ) }
					</button>
				</nav>
			) }
		</div>
	);
}
