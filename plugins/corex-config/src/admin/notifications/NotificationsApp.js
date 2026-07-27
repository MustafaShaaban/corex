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
import CorexSelect from '../components/CorexSelect.js';
import NotificationItem from './NotificationItem.js';
import PreferencesPanel from './PreferencesPanel.js';

/**
 * The severity refine, as CorexSelect takes them (DECISIONS #141 — a native <select> draws its open
 * menu through the OS, which no CSS can reach). Labels are translated rather than printing the raw
 * severity key, which showed an English identifier on an otherwise translated screen.
 */
const SEVERITY_OPTIONS = [
	{ value: '', label: __( 'All severities', 'corex' ) },
	{ value: 'critical', label: __( 'Critical', 'corex' ) },
	{ value: 'error', label: __( 'Error', 'corex' ) },
	{ value: 'warning', label: __( 'Warning', 'corex' ) },
	{ value: 'action', label: __( 'Action', 'corex' ) },
	{ value: 'information', label: __( 'Information', 'corex' ) },
	{ value: 'success', label: __( 'Success', 'corex' ) },
];

/**
 * Three views, each a bounded server-side filter — no client-side slicing.
 *
 * This replaces the eight saved views v0.35.0 shipped (DECISIONS: spec 074). Two problems with
 * those: "Requires attention" filtered on the actor's *unread* state, so reading a production
 * readiness blocker took it off the attention list while the blocker was still true; and
 * Submissions / Security / System / Assigned-to-me were tabs competing with the real question —
 * does this still need me? Those four are filters now, which is what they always were.
 */
const VIEWS = [
	{
		id: 'action_needed',
		label: __( 'Action needed', 'corex' ),
		params: { view: 'action_needed' },
		empty: __( 'Nothing needs you right now.', 'corex' ),
	},
	{
		id: 'updates',
		label: __( 'Updates', 'corex' ),
		params: { view: 'updates' },
		empty: __( 'No updates to report.', 'corex' ),
	},
	{
		id: 'history',
		label: __( 'History', 'corex' ),
		params: { view: 'history' },
		empty: __( 'Nothing has been resolved or dismissed yet.', 'corex' ),
	},
];

/** The old tabs, as the filters they always were. */
const CATEGORY_OPTIONS = [
	{ value: '', label: __( 'All categories', 'corex' ) },
	{ value: 'submissions', label: __( 'Submissions', 'corex' ) },
	{ value: 'email', label: __( 'Email', 'corex' ) },
	{ value: 'jobs', label: __( 'Jobs', 'corex' ) },
	{ value: 'security', label: __( 'Security', 'corex' ) },
	{ value: 'access', label: __( 'Access', 'corex' ) },
	{ value: 'operations', label: __( 'Operations', 'corex' ) },
	{ value: 'readiness', label: __( 'Readiness', 'corex' ) },
	{ value: 'imports_exports', label: __( 'Imports and exports', 'corex' ) },
	{ value: 'editorial', label: __( 'Editorial', 'corex' ) },
	{ value: 'setup', label: __( 'Setup', 'corex' ) },
	{ value: 'system', label: __( 'System', 'corex' ) },
];

export default function NotificationsApp() {
	const [ status, setStatus ] = useState( 'loading' );
	const [ items, setItems ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ perPage ] = useState( 20 );
	const [ view, setView ] = useState( 'action_needed' );
	const [ severity, setSeverity ] = useState( '' );
	const [ category, setCategory ] = useState( '' );
	const [ assignedToMe, setAssignedToMe ] = useState( false );
	const [ error, setError ] = useState( '' );

	const activeView = useMemo(
		() =>
			VIEWS.find( ( candidate ) => candidate.id === view ) ?? VIEWS[ 0 ],
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
		if ( category ) {
			query.set( 'category', category );
		}
		if ( assignedToMe ) {
			query.set( 'assigned_to_me', '1' );
		}
		apiFetch( { path: `/corex/v1/notifications?${ query.toString() }` } )
			.then( ( response ) => {
				setItems( response?.data?.items ?? [] );
				setTotal( response?.data?.total ?? 0 );
				setStatus( 'ready' );
			} )
			.catch( () => setStatus( 'error' ) );
	}, [ view, page, perPage, activeView, severity, category, assignedToMe ] );

	useEffect( () => {
		load();
	}, [ load ] );

	/**
	 * Every per-item control goes through one call, so a new one cannot arrive half-wired.
	 *
	 * `read` was the only action the screen used; `unread`, `dismiss`, `snooze`, and `resolve` have
	 * been registered on the REST controller since v0.35.0 with nothing calling them.
	 */
	const act = useCallback(
		( id, action, body ) => {
			setError( '' );
			apiFetch( {
				path: `/corex/v1/notifications/${ id }/${ action }`,
				method: 'POST',
				...( body ? { data: body } : {} ),
			} )
				.then( load )
				.catch( ( failure ) =>
					setError(
						failure?.message ||
							__( 'That action could not be completed.', 'corex' )
					)
				);
		},
		[ load ]
	);

	const itemActions = useMemo(
		() => ( {
			markRead: ( id ) => act( id, 'read' ),
			markUnread: ( id ) => act( id, 'unread' ),
			dismiss: ( id ) => act( id, 'dismiss' ),
			// A day is the snooze the store already understands; the control says so rather than
			// leaving the person to guess how long "snooze" lasts.
			snooze: ( id ) =>
				act( id, 'snooze', {
					snoozed_until: new Date(
						Date.now() + 86400000
					).toISOString(),
				} ),
			resolve: ( id ) => act( id, 'resolve', { reason: 'manual' } ),
		} ),
		[ act ]
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
				{ [
					...VIEWS,
					{ id: 'preferences', label: __( 'Preferences', 'corex' ) },
				].map( ( candidate ) => (
					<button
						key={ candidate.id }
						type="button"
						className={
							'corex-notifications-screen__view' +
							( candidate.id === view ? ' is-active' : '' )
						}
						aria-current={
							candidate.id === view ? 'true' : undefined
						}
						onClick={ () => chooseView( candidate.id ) }
					>
						{ candidate.label }
					</button>
				) ) }
			</nav>

			{ view === 'preferences' && <PreferencesPanel /> }

			{ view !== 'preferences' && (
				<>
					<div className="corex-notifications-screen__filters">
						<div className="corex-notifications-screen__filter">
							<CorexSelect
								label={ __( 'Severity', 'corex' ) }
								value={ severity }
								options={ SEVERITY_OPTIONS }
								onChange={ ( next ) => {
									setPage( 1 );
									setSeverity( next );
								} }
							/>
						</div>
						<div className="corex-notifications-screen__filter">
							<CorexSelect
								label={ __( 'Category', 'corex' ) }
								value={ category }
								options={ CATEGORY_OPTIONS }
								onChange={ ( next ) => {
									setPage( 1 );
									setCategory( next );
								} }
							/>
						</div>
						<label className="corex-notifications-screen__toggle">
							<input
								type="checkbox"
								checked={ assignedToMe }
								onChange={ ( event ) => {
									setPage( 1 );
									setAssignedToMe( event.target.checked );
								} }
							/>
							{ __( 'Assigned to me', 'corex' ) }
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
						<p
							className="corex-notifications-screen__state"
							role="alert"
						>
							{ __(
								'Notifications could not be loaded.',
								'corex'
							) }
						</p>
					) }
					{ status === 'ready' && items.length === 0 && (
						<p className="corex-notifications-screen__state">
							{ activeView.empty }
						</p>
					) }
					{ error && (
						<p
							className="corex-notifications-screen__state"
							role="alert"
						>
							{ error }
						</p>
					) }
					{ status === 'ready' && items.length > 0 && (
						<ul className="corex-notifications-screen__list">
							{ items.map( ( item ) => (
								<li
									key={ item.id }
									className="corex-notifications-screen__item"
								>
									<NotificationItem
										item={ item }
										actions={ itemActions }
									/>
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
								onClick={ () =>
									setPage( ( current ) => current - 1 )
								}
							>
								{ __( 'Previous', 'corex' ) }
							</button>
							<span>
								{ __( 'Page', 'corex' ) } { page } / { pages }
							</span>
							<button
								type="button"
								disabled={ page >= pages }
								onClick={ () =>
									setPage( ( current ) => current + 1 )
								}
							>
								{ __( 'Next', 'corex' ) }
							</button>
						</nav>
					) }
				</>
			) }
		</div>
	);
}
