/**
 * Corex → Data — a React admin screen that lists a Corex data source (form submissions, or
 * any registered custom-table source) with server-driven search, source/form filter,
 * sortable columns, pagination, CSV export of the current view, and a per-record detail
 * drawer (specs 030 + 045 backends; the UI is spec 053 US2). Data comes from the cap-gated
 * corex/v1/data REST routes; export targets the corex_data_export admin-post handler.
 *
 * It supersedes the earlier minimal DataViews table (spec 030): server-driven search/sort/
 * export/detail need direct control of the request, so the screen owns its own accessible
 * table + toolbar rather than delegating to the core DataViews view-state. The thin shell
 * sits over the pure, unit-tested helpers in dataClient.js.
 */
import {
	createRoot,
	render,
	useState,
	useEffect,
	useCallback,
	useRef,
} from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	buildListUrl,
	buildExportUrl,
	toggleSort,
	mergeForms,
	viewState,
	toggleSelection,
	allRowsSelected,
} from './dataClient.js';

const config = window.corexData || {
	restUrl: '',
	nonce: '',
	sources: [],
	exportUrl: '',
	exportNonce: '',
};

const PER_PAGE = 20;

function useSource() {
	const [ sourceKey, setSourceKey ] = useState(
		config.sources[ 0 ]?.key || ''
	);
	const [ search, setSearch ] = useState( '' );
	const [ form, setForm ] = useState( '' );
	const [ sort, setSort ] = useState( '' );
	const [ dir, setDir ] = useState( 'asc' );
	const [ page, setPage ] = useState( 1 );
	const [ rows, setRows ] = useState( [] );
	const [ columns, setColumns ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ knownForms, setKnownForms ] = useState( [] );
	const [ schema, setSchema ] = useState( [] );
	const [ trend, setTrend ] = useState( [] );
	const [ status, setStatus ] = useState( 'idle' );
	const [ error, setError ] = useState( '' );

	const query = { search, form, sort, dir, page, perPage: PER_PAGE };

	const load = useCallback( () => {
		if ( ! sourceKey ) {
			return;
		}
		setStatus( 'loading' );
		setError( '' );
		window.Corex.api
			.get(
				buildListUrl( config.restUrl, sourceKey, {
					search,
					form,
					sort,
					dir,
					page,
					perPage: PER_PAGE,
				} ),
				{
					nonce: config.nonce,
				}
			)
			.then( ( result ) => {
				if ( ! result.envelope.ok ) {
					setStatus( 'error' );
					setError(
						result.envelope.message ||
							__( 'Could not load this data.', 'corex' )
					);
					return;
				}
				const payload = result.envelope.data;
				const next = payload.rows.map( ( r, i ) => ( {
					id: r.id ?? i,
					...r,
				} ) );
				setRows( next );
				setColumns( payload.columns );
				setTotal( payload.total );
				setKnownForms( ( prev ) => mergeForms( prev, next ) );
				setSchema( payload.schema || [] );
				setTrend( payload.trend || [] );
				setStatus( 'ready' );
			} )
			.catch( () => {
				setStatus( 'error' );
				setError(
					__(
						'The request failed. Check your connection and try again.',
						'corex'
					)
				);
			} );
	}, [ sourceKey, search, form, sort, dir, page ] );

	useEffect( load, [ load ] );

	// Any filter/search/sort change resets to page 1 so pagination stays truthful.
	const resetTo = ( fn ) => ( value ) => {
		fn( value );
		setPage( 1 );
	};

	const onSort = ( column ) => {
		const nextSort = toggleSort( { sort, dir }, column );
		setSort( nextSort.sort );
		setDir( nextSort.dir );
		setPage( 1 );
	};

	const remove = ( id ) =>
		window.Corex.api
			.delete( `${ config.restUrl }/${ sourceKey }/${ id }`, {
				nonce: config.nonce,
			} )
			.then( load );

	// Row selection for bulk actions. Selection is cleared whenever the visible rows change
	// (source/search/filter/page), so a stale id can never be acted on.
	const [ selected, setSelected ] = useState( [] );
	useEffect( () => setSelected( [] ), [ sourceKey, search, form, page ] );

	const toggleSelect = ( id ) =>
		setSelected( ( prev ) => toggleSelection( prev, id ) );

	const toggleSelectAll = () =>
		setSelected( ( prev ) =>
			allRowsSelected( prev, rows ) ? [] : rows.map( ( r ) => r.id )
		);

	// Bulk delete = the per-row delete (already cap-gated server-side) applied to each
	// selected id; deletion is the only bulk action the backend truthfully supports.
	const removeMany = ( ids ) =>
		Promise.all(
			ids.map( ( id ) =>
				window.Corex.api.delete(
					`${ config.restUrl }/${ sourceKey }/${ id }`,
					{ nonce: config.nonce }
				)
			)
		).then( () => {
			setSelected( [] );
			load();
		} );

	const resetFilters = () => {
		setSearch( '' );
		setForm( '' );
		setPage( 1 );
	};

	return {
		sourceKey,
		setSourceKey: resetTo( setSourceKey ),
		search,
		setSearch: resetTo( setSearch ),
		form,
		setForm: resetTo( setForm ),
		sort,
		dir,
		onSort,
		page,
		setPage,
		rows,
		columns,
		total,
		knownForms,
		schema,
		trend,
		status,
		error,
		query,
		retry: load,
		remove,
		selected,
		toggleSelect,
		toggleSelectAll,
		removeMany,
		resetFilters,
		hasFilters: Boolean( search || form ),
	};
}

function activeLabel( sourceKey ) {
	return (
		config.sources.find( ( src ) => src.key === sourceKey )?.label ||
		__( 'Source', 'corex' )
	);
}

/*
 * The Sources / models rail (design: Add-ons & Data capture). Lists only the real
 * registered CoreX data sources — never invented models. The active source shows its
 * real row count; other sources show no count until selected (we have no count for them).
 */
function SourcesRail( { s } ) {
	return (
		<nav
			className="corex-data__sources"
			aria-label={ __( 'Data sources', 'corex' ) }
		>
			<p className="corex-data__rail-kicker">
				{ __( 'Sources / models', 'corex' ) }
			</p>
			{ config.sources.length === 0 && (
				<p className="corex-data__rail-empty">
					{ __( 'No data sources are registered yet.', 'corex' ) }
				</p>
			) }
			{ config.sources.map( ( src, i ) => {
				const isActive = src.key === s.sourceKey;
				return (
					<button
						key={ src.key }
						type="button"
						className={ `corex-data__source-row${
							isActive ? ' is-active' : ''
						}` }
						aria-current={ isActive ? 'true' : undefined }
						onClick={ () => s.setSourceKey( src.key ) }
					>
						<span
							className={ `corex-data__source-dot is-c${
								i % 4
							}` }
							aria-hidden="true"
						/>
						<span className="corex-data__source-label">
							{ src.label }
						</span>
						{ isActive && (
							<span className="corex-data__source-count">
								{ s.total }
							</span>
						) }
					</button>
				);
			} ) }
		</nav>
	);
}

/*
 * The schema panel (design: capture). Field rows are derived only from the active source's
 * real columns; the mono token on the right is the real field key. No invented fields.
 */
function SchemaPanel( { s } ) {
	// Prefer the source's real derived field schema (name + meaningful type); fall back to the
	// table columns for sources that don't expose one.
	const fields =
		s.schema && s.schema.length > 0
			? s.schema
			: s.columns.map( ( c ) => ( { name: c.label, type: c.id } ) );
	const derived = Boolean( s.schema && s.schema.length > 0 );

	return (
		<div className="corex-data__schema">
			<p className="corex-data__rail-kicker">
				{ sprintf(
					/* translators: %s: active source label. */ __(
						'Schema — %s',
						'corex'
					),
					activeLabel( s.sourceKey )
				) }
			</p>
			{ fields.length === 0 ? (
				<p className="corex-data__rail-empty">
					{ __(
						'Schema metadata is not available for this source yet.',
						'corex'
					) }
				</p>
			) : (
				<>
					<ul className="corex-data__schema-list">
						{ fields.map( ( f, i ) => (
							<li key={ i } className="corex-data__schema-field">
								<span className="corex-data__schema-name">
									{ f.name }
								</span>
								<span className="corex-data__schema-type">
									{ f.type }
								</span>
							</li>
						) ) }
					</ul>
					{ derived && (
						<p className="corex-data__schema-note">
							{ __(
								'Derived from captured submissions.',
								'corex'
							) }
						</p>
					) }
				</>
			) }
		</div>
	);
}

/*
 * The insight/metric cards (design: capture). Total rows and field count are real; the
 * 14-day trend chart has no backing source yet, so its card shows an honest empty state
 * rather than a fabricated sparkline.
 */
/*
 * The 14-day records chart (design: capture). Bars are real per-day counts from the active
 * source's timestamps; a day with no records is a real zero (a minimal stub bar). Sources
 * with no trend capability show a designed empty state instead of a fabricated chart.
 */
function TrendChart( { trend } ) {
	if ( ! trend || trend.length === 0 ) {
		return (
			<p className="corex-data__metric-empty">
				{ __(
					'Trend data is not available for this source yet.',
					'corex'
				) }
			</p>
		);
	}

	const max = trend.reduce( ( m, d ) => Math.max( m, d.count ), 0 );
	const total = trend.reduce( ( sum, d ) => sum + d.count, 0 );

	return (
		<div
			className="corex-data__chart"
			role="img"
			aria-label={ sprintf(
				/* translators: %d: number of records in the last 14 days. */
				_n(
					'%d record in the last 14 days',
					'%d records in the last 14 days',
					total,
					'corex'
				),
				total
			) }
		>
			{ trend.map( ( d ) => (
				<span
					key={ d.date }
					className="corex-data__chart-bar"
					style={ {
						height:
							max > 0
								? Math.max(
										4,
										Math.round( ( d.count / max ) * 100 )
								  ) + '%'
								: '2px',
					} }
					title={ `${ d.date }: ${ d.count }` }
				/>
			) ) }
		</div>
	);
}

function Metrics( { s } ) {
	const ready = s.status === 'ready' || s.status === 'idle';
	const fieldCount =
		s.schema && s.schema.length > 0 ? s.schema.length : s.columns.length;
	const total = ( s.trend || [] ).reduce( ( sum, d ) => sum + d.count, 0 );

	return (
		<div className="corex-data__metrics">
			<div className="corex-data__metric corex-data__metric--chart">
				<div className="corex-data__chart-head">
					<p className="corex-data__metric-label">
						{ __( 'Records / 14 days', 'corex' ) }
					</p>
					{ s.trend && s.trend.length > 0 && (
						<span className="corex-data__chart-total">
							{ sprintf(
								/* translators: %d: total records in the window. */
								__( '%d total', 'corex' ),
								total
							) }
						</span>
					) }
				</div>
				<TrendChart trend={ s.trend } />
			</div>
			<div className="corex-data__metric">
				<p className="corex-data__metric-label">
					{ __( 'Total rows', 'corex' ) }
				</p>
				<p className="corex-data__metric-value">
					{ ready ? s.total : '—' }
				</p>
			</div>
			<div className="corex-data__metric">
				<p className="corex-data__metric-label">
					{ __( 'Fields', 'corex' ) }
				</p>
				<p className="corex-data__metric-value">
					{ fieldCount || '—' }
				</p>
			</div>
		</div>
	);
}

/*
 * The data panel header: the active model, a QueryBuilder marker, and the real
 * search / form filter / export controls (design: capture topbar of the table card).
 */
function PanelHead( { s } ) {
	const exportHref = buildExportUrl(
		config.exportUrl,
		s.sourceKey,
		s.query,
		config.exportNonce
	);
	return (
		<div className="corex-data__panel-head">
			<div className="corex-data__panel-title">
				<h2>{ activeLabel( s.sourceKey ) }</h2>
				<span className="corex-data__qb">
					{ __( 'QueryBuilder', 'corex' ) }
				</span>
			</div>
			<div className="corex-data__toolbar">
				<input
					type="search"
					className="corex-data__search"
					placeholder={ __( 'Search…', 'corex' ) }
					aria-label={ __( 'Search records', 'corex' ) }
					value={ s.search }
					onChange={ ( e ) => s.setSearch( e.target.value ) }
				/>

				{ s.knownForms.length > 0 && (
					<select
						className="corex-data__form"
						aria-label={ __( 'Filter by form', 'corex' ) }
						value={ s.form }
						onChange={ ( e ) => s.setForm( e.target.value ) }
					>
						<option value="">{ __( 'All forms', 'corex' ) }</option>
						{ s.knownForms.map( ( f ) => (
							<option key={ f } value={ f }>
								{ f }
							</option>
						) ) }
					</select>
				) }

				{ s.hasFilters && (
					<button
						type="button"
						className="button corex-data__reset"
						onClick={ s.resetFilters }
					>
						{ __( 'Reset', 'corex' ) }
					</button>
				) }

				<a
					className="button corex-data__export"
					href={ s.total > 0 ? exportHref : undefined }
					aria-disabled={ s.total === 0 }
					onClick={ ( e ) => s.total === 0 && e.preventDefault() }
				>
					{ __( 'Export CSV', 'corex' ) }
				</a>

				{ /* Current CoreX sources (form submissions) are captured from the
				     front end and are read-only — New stays visible but honestly disabled. */ }
				<button
					type="button"
					className="button corex-data__new"
					disabled
					aria-disabled="true"
					title={ __(
						'Form submissions are captured from frontend forms and cannot be created manually yet.',
						'corex'
					) }
				>
					{ __( 'New record', 'corex' ) }
				</button>
			</div>
			{ /* DataExportController::MAX_ROWS — the server bounds the export to 5000 rows. */ }
			{ s.total > 5000 && (
				<span className="corex-data__export-note">
					{ __(
						'Export is limited to the first 5000 rows.',
						'corex'
					) }
				</span>
			) }
		</div>
	);
}

/*
 * The bulk-action toolbar (design: explorer). Appears once rows are selected. Delete is the
 * only action the backend truthfully supports in bulk; it confirms before the destructive call.
 */
function BulkBar( { s } ) {
	if ( s.selected.length === 0 ) {
		return null;
	}
	const onDelete = () => {
		// eslint-disable-next-line no-alert -- intentional confirm before a destructive bulk delete.
		const ok = window.confirm(
			__( 'Delete the selected records? This cannot be undone.', 'corex' )
		);
		if ( ok ) {
			s.removeMany( s.selected );
		}
	};
	return (
		<div
			className="corex-data__bulkbar"
			role="region"
			aria-label={ __( 'Bulk actions', 'corex' ) }
		>
			<span className="corex-data__bulk-count">
				{ sprintf(
					/* translators: %d: number of selected records. */
					_n(
						'%d record selected',
						'%d records selected',
						s.selected.length,
						'corex'
					),
					s.selected.length
				) }
			</span>
			<Button isDestructive variant="secondary" onClick={ onDelete }>
				{ __( 'Delete selected', 'corex' ) }
			</Button>
		</div>
	);
}

function SortableHeader( { column, s } ) {
	const active = s.sort === column.id;
	const ariaSort = active
		? s.dir === 'asc'
			? 'ascending'
			: 'descending'
		: 'none';
	return (
		<th aria-sort={ ariaSort }>
			<button
				type="button"
				className="corex-data__sort"
				onClick={ () => s.onSort( column.id ) }
			>
				{ column.label }
				<span aria-hidden="true" className="corex-data__sort-ind">
					{ active ? ( s.dir === 'asc' ? ' ▲' : ' ▼' ) : '' }
				</span>
			</button>
		</th>
	);
}

function Table( { s, onOpen } ) {
	const allSelected = allRowsSelected( s.selected, s.rows );
	return (
		<table className="widefat corex-data__table">
			<thead>
				<tr>
					<th className="corex-data__check">
						<input
							type="checkbox"
							checked={ allSelected }
							onChange={ s.toggleSelectAll }
							aria-label={ __( 'Select all records', 'corex' ) }
						/>
					</th>
					{ s.columns.map( ( c ) => (
						<SortableHeader key={ c.id } column={ c } s={ s } />
					) ) }
					<th className="corex-data__actions-head">
						{ __( 'Actions', 'corex' ) }
					</th>
				</tr>
			</thead>
			<tbody>
				{ s.rows.map( ( row ) => {
					const isSelected = s.selected.includes( row.id );
					return (
						<tr
							key={ row.id }
							className={ isSelected ? 'is-selected' : undefined }
						>
							<td className="corex-data__check">
								<input
									type="checkbox"
									checked={ isSelected }
									onChange={ () => s.toggleSelect( row.id ) }
									aria-label={ sprintf(
										/* translators: %s: record id. */
										__( 'Select record %s', 'corex' ),
										String( row.id )
									) }
								/>
							</td>
							{ s.columns.map( ( c ) => (
								<td key={ c.id }>{ row[ c.id ] || '—' }</td>
							) ) }
							<td className="corex-data__row-actions">
								<Button
									variant="secondary"
									size="small"
									onClick={ ( e ) =>
										onOpen( row.id, e.currentTarget )
									}
								>
									{ __( 'View', 'corex' ) }
								</Button>
								<Button
									isDestructive
									variant="tertiary"
									size="small"
									onClick={ () => s.remove( row.id ) }
								>
									{ __( 'Delete', 'corex' ) }
								</Button>
							</td>
						</tr>
					);
				} ) }
			</tbody>
		</table>
	);
}

function Pagination( { s } ) {
	const totalPages = Math.max( 1, Math.ceil( s.total / PER_PAGE ) );
	if ( totalPages <= 1 ) {
		return null;
	}
	return (
		<div className="corex-data__pagination">
			<Button
				variant="secondary"
				disabled={ s.page <= 1 }
				onClick={ () => s.setPage( s.page - 1 ) }
			>
				{ __( 'Previous', 'corex' ) }
			</Button>
			<span className="corex-data__page-of">
				{ sprintf(
					/* translators: 1: current page, 2: total pages. */ __(
						'Page %1$d of %2$d',
						'corex'
					),
					s.page,
					totalPages
				) }
			</span>
			<Button
				variant="secondary"
				disabled={ s.page >= totalPages }
				onClick={ () => s.setPage( s.page + 1 ) }
			>
				{ __( 'Next', 'corex' ) }
			</Button>
		</div>
	);
}

function DetailDrawer( { sourceKey, id, sourceLabel, onClose, onDelete } ) {
	const [ fields, setFields ] = useState( null );
	const [ failed, setFailed ] = useState( false );
	const closeRef = useRef( null );
	const drawerRef = useRef( null );

	useEffect( () => {
		window.Corex.api
			.get( `${ config.restUrl }/${ sourceKey }/${ id }`, {
				nonce: config.nonce,
			} )
			.then( ( result ) => {
				if ( result.envelope.ok ) {
					setFields( result.envelope.data.fields || [] );
				} else {
					setFailed( true );
				}
			} )
			.catch( () => setFailed( true ) );
	}, [ sourceKey, id ] );

	// Move focus into the drawer on open; Escape closes; Tab is trapped inside the dialog so
	// focus cannot escape to the page behind the modal overlay.
	useEffect( () => {
		closeRef.current?.focus();
		const onKey = ( e ) => {
			if ( e.key === 'Escape' ) {
				onClose();
				return;
			}
			if ( e.key !== 'Tab' || ! drawerRef.current ) {
				return;
			}
			const focusable = drawerRef.current.querySelectorAll(
				'a[href], button:not([disabled]), input, [tabindex]:not([tabindex="-1"])'
			);
			if ( focusable.length === 0 ) {
				return;
			}
			const first = focusable[ 0 ];
			const last = focusable[ focusable.length - 1 ];
			const active = drawerRef.current.ownerDocument.activeElement;
			if ( e.shiftKey && active === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && active === last ) {
				e.preventDefault();
				first.focus();
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => document.removeEventListener( 'keydown', onKey );
	}, [ onClose ] );

	const onDeleteClick = () => {
		// eslint-disable-next-line no-alert -- intentional confirm before a destructive delete.
		const ok = window.confirm(
			__( 'Delete this record? This cannot be undone.', 'corex' )
		);
		if ( ok ) {
			onDelete( id );
			onClose();
		}
	};

	return (
		<div className="corex-data__drawer-overlay" onClick={ onClose }>
			<aside
				ref={ drawerRef }
				className="corex-data__drawer"
				role="dialog"
				aria-modal="true"
				aria-label={ __( 'Record detail', 'corex' ) }
				onClick={ ( e ) => e.stopPropagation() }
			>
				<div className="corex-data__drawer-head">
					<div>
						<h2>{ __( 'Record detail', 'corex' ) }</h2>
						<p className="corex-data__drawer-meta">
							<span>{ sourceLabel }</span>
							<span className="corex-data__drawer-id">
								{ sprintf(
									/* translators: %s: record id. */
									__( 'ID %s', 'corex' ),
									String( id )
								) }
							</span>
						</p>
					</div>
					<Button
						ref={ closeRef }
						variant="tertiary"
						label={ __( 'Close', 'corex' ) }
						showTooltip
						icon="no-alt"
						onClick={ onClose }
						className="corex-data__drawer-close"
					/>
				</div>

				<div className="corex-data__drawer-body">
					{ failed && (
						<p>
							{ __(
								'That record could not be loaded.',
								'corex'
							) }
						</p>
					) }
					{ ! failed && fields === null && (
						<p className="corex-data__loading" role="status">
							<Spinner /> { __( 'Loading…', 'corex' ) }
						</p>
					) }
					{ fields && (
						<dl className="corex-data__fields">
							{ fields.map( ( f, i ) => (
								<div key={ i } className="corex-data__field">
									<dt>{ f.label }</dt>
									<dd>
										{ f.value === '' || f.value === null
											? '—'
											: f.value }
									</dd>
								</div>
							) ) }
						</dl>
					) }
				</div>

				<div className="corex-data__drawer-foot">
					<Button variant="secondary" onClick={ onClose }>
						{ __( 'Close', 'corex' ) }
					</Button>
					<Button
						variant="secondary"
						disabled
						aria-disabled="true"
						label={ __(
							'Form submissions are read-only and cannot be edited yet.',
							'corex'
						) }
						showTooltip
					>
						{ __( 'Edit', 'corex' ) }
					</Button>
					<Button
						isDestructive
						variant="secondary"
						onClick={ onDeleteClick }
					>
						{ __( 'Delete', 'corex' ) }
					</Button>
				</div>
			</aside>
		</div>
	);
}

function App() {
	const s = useSource();
	const [ open, setOpen ] = useState( null );

	// Closing returns focus to the row control that opened the drawer.
	const closeDrawer = () => {
		const trigger = open?.trigger;
		setOpen( null );
		trigger?.focus?.();
	};

	const hasQuery = Boolean( s.search || s.form );
	const state = viewState( {
		status: s.status,
		rowCount: s.rows.length,
		hasQuery,
	} );

	return (
		<div className="corex-data corex-data--explorer">
			<aside className="corex-data__rail">
				<SourcesRail s={ s } />
				<SchemaPanel s={ s } />
			</aside>

			<div className="corex-data__explorer-main">
				<Metrics s={ s } />

				<div className="corex-data__panel">
					<PanelHead s={ s } />

					<BulkBar s={ s } />

					<div className="corex-data__panel-body">
						{ state === 'loading' && (
							<p
								className="corex-data__loading"
								role="status"
								aria-busy="true"
							>
								<Spinner /> { __( 'Loading…', 'corex' ) }
							</p>
						) }

						{ state === 'error' && (
							<div
								className="corex-data__error notice notice-error"
								role="alert"
							>
								<p>{ s.error }</p>
								<Button variant="secondary" onClick={ s.retry }>
									{ __( 'Retry', 'corex' ) }
								</Button>
							</div>
						) }

						{ state === 'empty' && (
							<p className="corex-data__empty">
								{ __( 'No data yet.', 'corex' ) }
							</p>
						) }

						{ state === 'empty-filtered' && (
							<p className="corex-data__empty">
								{ __(
									'No records match your search or filter.',
									'corex'
								) }
							</p>
						) }

						{ state === 'ready' && (
							<Table
								s={ s }
								onOpen={ ( id, trigger ) =>
									setOpen( { id, trigger } )
								}
							/>
						) }
					</div>

					{ state === 'ready' && <Pagination s={ s } /> }
				</div>
			</div>

			{ open !== null && (
				<DetailDrawer
					sourceKey={ s.sourceKey }
					id={ open.id }
					sourceLabel={ activeLabel( s.sourceKey ) }
					onDelete={ s.remove }
					onClose={ closeDrawer }
				/>
			) }
		</div>
	);
}

const mount = document.getElementById( 'corex-data-app' );
if ( mount ) {
	if ( typeof createRoot === 'function' ) {
		createRoot( mount ).render( <App /> );
	} else {
		render( <App />, mount );
	}
}
