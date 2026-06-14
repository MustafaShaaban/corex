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
import { createRoot, render, useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { buildListUrl, buildExportUrl, toggleSort, mergeForms, viewState } from './dataClient.js';

const config = window.corexData || {
	restUrl: '',
	nonce: '',
	sources: [],
	exportUrl: '',
	exportNonce: '',
};

const PER_PAGE = 20;

function useSource() {
	const [ sourceKey, setSourceKey ] = useState( config.sources[ 0 ]?.key || '' );
	const [ search, setSearch ] = useState( '' );
	const [ form, setForm ] = useState( '' );
	const [ sort, setSort ] = useState( '' );
	const [ dir, setDir ] = useState( 'asc' );
	const [ page, setPage ] = useState( 1 );
	const [ rows, setRows ] = useState( [] );
	const [ columns, setColumns ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ knownForms, setKnownForms ] = useState( [] );
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
			.get( buildListUrl( config.restUrl, sourceKey, { search, form, sort, dir, page, perPage: PER_PAGE } ), {
				nonce: config.nonce,
			} )
			.then( ( result ) => {
				if ( ! result.envelope.ok ) {
					setStatus( 'error' );
					setError( result.envelope.message || __( 'Could not load this data.', 'corex' ) );
					return;
				}
				const payload = result.envelope.data;
				const next = payload.rows.map( ( r, i ) => ( { id: r.id ?? i, ...r } ) );
				setRows( next );
				setColumns( payload.columns );
				setTotal( payload.total );
				setKnownForms( ( prev ) => mergeForms( prev, next ) );
				setStatus( 'ready' );
			} )
			.catch( () => {
				setStatus( 'error' );
				setError( __( 'The request failed. Check your connection and try again.', 'corex' ) );
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
			.delete( `${ config.restUrl }/${ sourceKey }/${ id }`, { nonce: config.nonce } )
			.then( load );

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
		status,
		error,
		query,
		retry: load,
		remove,
	};
}

function Toolbar( { s } ) {
	const exportHref = buildExportUrl( config.exportUrl, s.sourceKey, s.query, config.exportNonce );
	return (
		<div className="corex-data__toolbar">
			{ config.sources.length > 1 && (
				<select
					className="corex-data__source"
					aria-label={ __( 'Data source', 'corex' ) }
					value={ s.sourceKey }
					onChange={ ( e ) => s.setSourceKey( e.target.value ) }
				>
					{ config.sources.map( ( src ) => (
						<option key={ src.key } value={ src.key }>
							{ src.label }
						</option>
					) ) }
				</select>
			) }

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

			<a
				className="button corex-data__export"
				href={ s.total > 0 ? exportHref : undefined }
				aria-disabled={ s.total === 0 }
				onClick={ ( e ) => s.total === 0 && e.preventDefault() }
			>
				{ __( 'Export CSV', 'corex' ) }
			</a>

			{ /* DataExportController::MAX_ROWS — the server bounds the export to 5000 rows. */ }
			{ s.total > 5000 && (
				<span className="corex-data__export-note">
					{ __( 'Export is limited to the first 5000 rows.', 'corex' ) }
				</span>
			) }
		</div>
	);
}

function SortableHeader( { column, s } ) {
	const active = s.sort === column.id;
	const ariaSort = active ? ( s.dir === 'asc' ? 'ascending' : 'descending' ) : 'none';
	return (
		<th aria-sort={ ariaSort }>
			<button type="button" className="corex-data__sort" onClick={ () => s.onSort( column.id ) }>
				{ column.label }
				<span aria-hidden="true" className="corex-data__sort-ind">
					{ active ? ( s.dir === 'asc' ? ' ▲' : ' ▼' ) : '' }
				</span>
			</button>
		</th>
	);
}

function Table( { s, onOpen } ) {
	return (
		<table className="widefat striped corex-data__table">
			<thead>
				<tr>
					{ s.columns.map( ( c ) => (
						<SortableHeader key={ c.id } column={ c } s={ s } />
					) ) }
					<th>{ __( 'Actions', 'corex' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ s.rows.map( ( row ) => (
					<tr key={ row.id }>
						{ s.columns.map( ( c ) => (
							<td key={ c.id }>{ row[ c.id ] || '—' }</td>
						) ) }
						<td className="corex-data__row-actions">
							<Button variant="link" onClick={ () => onOpen( row.id ) }>
								{ __( 'View', 'corex' ) }
							</Button>
							<Button isDestructive variant="link" onClick={ () => s.remove( row.id ) }>
								{ __( 'Delete', 'corex' ) }
							</Button>
						</td>
					</tr>
				) ) }
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
			<Button variant="secondary" disabled={ s.page <= 1 } onClick={ () => s.setPage( s.page - 1 ) }>
				{ __( 'Previous', 'corex' ) }
			</Button>
			<span className="corex-data__page-of">
				{ sprintf( /* translators: 1: current page, 2: total pages. */ __( 'Page %1$d of %2$d', 'corex' ), s.page, totalPages ) }
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

function DetailDrawer( { sourceKey, id, onClose } ) {
	const [ fields, setFields ] = useState( null );
	const [ failed, setFailed ] = useState( false );
	const closeRef = useRef( null );

	useEffect( () => {
		window.Corex.api
			.get( `${ config.restUrl }/${ sourceKey }/${ id }`, { nonce: config.nonce } )
			.then( ( result ) => {
				if ( result.envelope.ok ) {
					setFields( result.envelope.data.fields || [] );
				} else {
					setFailed( true );
				}
			} )
			.catch( () => setFailed( true ) );
	}, [ sourceKey, id ] );

	useEffect( () => {
		closeRef.current?.focus();
		const onKey = ( e ) => e.key === 'Escape' && onClose();
		document.addEventListener( 'keydown', onKey );
		return () => document.removeEventListener( 'keydown', onKey );
	}, [ onClose ] );

	return (
		<div className="corex-data__drawer-overlay" onClick={ onClose }>
			<aside
				className="corex-data__drawer"
				role="dialog"
				aria-modal="true"
				aria-label={ __( 'Record detail', 'corex' ) }
				onClick={ ( e ) => e.stopPropagation() }
			>
				<div className="corex-data__drawer-head">
					<h2>{ __( 'Record detail', 'corex' ) }</h2>
					<Button ref={ closeRef } variant="tertiary" onClick={ onClose }>
						{ __( 'Close', 'corex' ) }
					</Button>
				</div>
				{ failed && <p>{ __( 'That record could not be loaded.', 'corex' ) }</p> }
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
								<dd>{ f.value === '' || f.value === null ? '—' : f.value }</dd>
							</div>
						) ) }
					</dl>
				) }
			</aside>
		</div>
	);
}

function App() {
	const s = useSource();
	const [ openId, setOpenId ] = useState( null );

	const hasQuery = Boolean( s.search || s.form );
	const state = viewState( { status: s.status, rowCount: s.rows.length, hasQuery } );

	return (
		<div className="corex-data">
			<Toolbar s={ s } />

			{ state === 'loading' && (
				<p className="corex-data__loading" role="status" aria-busy="true">
					<Spinner /> { __( 'Loading…', 'corex' ) }
				</p>
			) }

			{ state === 'error' && (
				<div className="corex-data__error notice notice-error" role="alert">
					<p>{ s.error }</p>
					<Button variant="secondary" onClick={ s.retry }>
						{ __( 'Retry', 'corex' ) }
					</Button>
				</div>
			) }

			{ state === 'empty' && <p className="corex-data__empty">{ __( 'No data yet.', 'corex' ) }</p> }

			{ state === 'empty-filtered' && (
				<p className="corex-data__empty">{ __( 'No records match your search or filter.', 'corex' ) }</p>
			) }

			{ state === 'ready' && (
				<>
					<Table s={ s } onOpen={ setOpenId } />
					<Pagination s={ s } />
				</>
			) }

			{ openId !== null && (
				<DetailDrawer sourceKey={ s.sourceKey } id={ openId } onClose={ () => setOpenId( null ) } />
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
