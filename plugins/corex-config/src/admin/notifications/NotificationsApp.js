/**
 * NotificationsApp — the full CoreX → Notifications screen (spec 072 US1, FR-018).
 *
 * The bounded, filtered center: named views (tabs) over the actor's notifications, each mapping to a
 * server-side filter, plus a severity refine, per-item mark-read, and a bulk mark-all. It consumes
 * the same live REST boundary as the header drawer, with honest loading / error / empty states.
 */
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import PreferencesPanel from './PreferencesPanel.js';

const SEVERITIES = [ 'critical', 'error', 'warning', 'action', 'information', 'success' ];

/** Named views map to the bounded query the REST boundary supports (category / unread_only). */
const VIEWS = [
	{ id: 'inbox', label: __( 'Inbox', 'corex' ), params: {} },
	{ id: 'attention', label: __( 'Requires attention', 'corex' ), params: { unread_only: '1' } },
	{ id: 'submissions', label: __( 'Submissions', 'corex' ), params: { category: 'submissions' } },
	{ id: 'security', label: __( 'Security', 'corex' ), params: { category: 'security' } },
	{ id: 'system', label: __( 'System', 'corex' ), params: { category: 'system' } },
];

export default function NotificationsApp() {
	const [ status, setStatus ] = useState( 'loading' );
	const [ items, setItems ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ perPage ] = useState( 20 );
	const [ view, setView ] = useState( 'inbox' );
	const [ severity, setSeverity ] = useState( '' );

	const activeView = useMemo(
		() => VIEWS.find( ( candidate ) => candidate.id === view ) ?? VIEWS[ 0 ],
		[ view ]
	);

	const load = useCallback( () => {
		if ( view === 'preferences' ) {
			return; // the preferences panel owns its own data
		}
		setStatus( 'loading' );
		const query = new URLSearchParams( {
			page: String( page ),
			per_page: String( perPage ),
			...activeView.params,
		} );
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
	}, [ view, page, perPage, activeView, severity ] );

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

	const chooseView = useCallback( ( id ) => {
		setPage( 1 );
		setView( id );
	}, [] );

	const pages = Math.max( 1, Math.ceil( total / perPage ) );

	return (
		<div className="corex-notifications-screen">
			<nav
				className="corex-notifications-screen__views"
				aria-label={ __( 'Notification views', 'corex' ) }
			>
				{ [ ...VIEWS, { id: 'preferences', label: __( 'Preferences', 'corex' ) } ].map(
					( candidate ) => (
						<button
							key={ candidate.id }
							type="button"
							className={
								'corex-notifications-screen__view' +
								( candidate.id === view ? ' is-active' : '' )
							}
							aria-current={ candidate.id === view ? 'true' : undefined }
							onClick={ () => chooseView( candidate.id ) }
						>
							{ candidate.label }
						</button>
					)
				) }
			</nav>

			{ view === 'preferences' && <PreferencesPanel /> }

			{ view !== 'preferences' && ( <>
			<div className="corex-notifications-screen__filters">
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
			</> ) }
		</div>
	);
}
