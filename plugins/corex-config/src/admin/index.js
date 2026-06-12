/**
 * Corex → Data — a React admin screen that lists a Corex data source (form submissions, or
 * any registered custom-table source) in a table with a delete action (spec 030). Data comes
 * from the cap-gated corex/v1/data REST routes.
 *
 * It uses the core @wordpress/dataviews component when present (accessed from the runtime
 * `wp.dataviews` global so the bundle stays lean), and falls back to a simple table
 * otherwise — so the screen works across WordPress versions.
 */
import { createRoot, render, useState, useEffect, useCallback } from '@wordpress/element';
import { Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const config = window.corexData || { restUrl: '', nonce: '', sources: [] };
const DataViews = ( window.wp && window.wp.dataviews && window.wp.dataviews.DataViews ) || null;

function useSource() {
	const [ sourceKey, setSourceKey ] = useState( config.sources[ 0 ]?.key || '' );
	const [ data, setData ] = useState( [] );
	const [ columns, setColumns ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const perPage = 20;

	const load = useCallback( () => {
		if ( ! sourceKey ) {
			return;
		}
		apiFetch( {
			url: `${ config.restUrl }/${ sourceKey }?page=${ page }&per_page=${ perPage }`,
			headers: { 'X-WP-Nonce': config.nonce },
		} )
			.then( ( res ) => {
				setData( res.rows.map( ( r, i ) => ( { id: r.id ?? i, ...r } ) ) );
				setColumns( res.columns );
				setTotal( res.total );
			} )
			.catch( () => {
				setData( [] );
				setTotal( 0 );
			} );
	}, [ sourceKey, page ] );

	useEffect( load, [ load ] );

	const remove = ( id ) =>
		apiFetch( {
			url: `${ config.restUrl }/${ sourceKey }/${ id }`,
			method: 'DELETE',
			headers: { 'X-WP-Nonce': config.nonce },
		} ).then( load );

	return { sourceKey, setSourceKey, data, columns, total, page, setPage, perPage, remove };
}

function SourceSwitcher( { sourceKey, setSourceKey } ) {
	if ( config.sources.length <= 1 ) {
		return null;
	}
	return (
		<select
			className="corex-data__source"
			value={ sourceKey }
			onChange={ ( e ) => setSourceKey( e.target.value ) }
		>
			{ config.sources.map( ( s ) => (
				<option key={ s.key } value={ s.key }>
					{ s.label }
				</option>
			) ) }
		</select>
	);
}

function FallbackTable( { data, columns, remove } ) {
	if ( data.length === 0 ) {
		return <p>{ __( 'No data yet.', 'corex' ) }</p>;
	}
	return (
		<table className="widefat striped corex-data__table">
			<thead>
				<tr>
					{ columns.map( ( c ) => (
						<th key={ c.id }>{ c.label }</th>
					) ) }
					<th />
				</tr>
			</thead>
			<tbody>
				{ data.map( ( row ) => (
					<tr key={ row.id }>
						{ columns.map( ( c ) => (
							<td key={ c.id }>{ row[ c.id ] }</td>
						) ) }
						<td>
							<Button isDestructive variant="link" onClick={ () => remove( row.id ) }>
								{ __( 'Delete', 'corex' ) }
							</Button>
						</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}

function App() {
	const s = useSource();

	const body = DataViews ? (
		<DataViews
			data={ s.data }
			fields={ s.columns.map( ( c ) => ( {
				id: c.id,
				label: c.label,
				render: ( { item } ) => item[ c.id ],
			} ) ) }
			view={ { type: 'table', page: s.page, perPage: s.perPage, fields: s.columns.map( ( c ) => c.id ) } }
			onChangeView={ ( v ) => s.setPage( v.page || 1 ) }
			getItemId={ ( item ) => String( item.id ) }
			actions={ [
				{
					id: 'delete',
					label: __( 'Delete', 'corex' ),
					isDestructive: true,
					callback: ( items ) => items.forEach( ( it ) => s.remove( it.id ) ),
				},
			] }
			paginationInfo={ {
				totalItems: s.total,
				totalPages: Math.max( 1, Math.ceil( s.total / s.perPage ) ),
			} }
		/>
	) : (
		<FallbackTable data={ s.data } columns={ s.columns } remove={ s.remove } />
	);

	return (
		<div className="corex-data">
			<SourceSwitcher sourceKey={ s.sourceKey } setSourceKey={ s.setSourceKey } />
			{ body }
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
