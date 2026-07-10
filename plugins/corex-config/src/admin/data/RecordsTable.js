import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { allRowsSelected, toggleSort } from '../dataClient.js';

function recordSelectionLabel( recordId ) {
	/* translators: %s: record identifier. */
	return sprintf( __( 'Select record %s', 'corex' ), recordId );
}

export default function RecordsTable( { explorer, open } ) {
	const { state } = explorer;
	const all = allRowsSelected( state.selected, state.rows );
	const fields = new Map( ( explorer.source?.fields || [] ).map( ( field ) => [ field.key, field ] ) );
	return <div className="corex-data__table-scroll" tabIndex={ 0 }><table className="widefat corex-data__table">
		<thead><tr><th className="corex-data__check"><input type="checkbox" checked={ all }
			onChange={ () => explorer.dispatch( { type: 'select-all' } ) } aria-label={ __( 'Select visible records', 'corex' ) } /></th>
		{ state.columns.map( ( column ) => {
			const sortable = Boolean( fields.get( column.id )?.sortable );
			const ariaSort = sortable ? ( state.query.sort === column.id ? ( state.query.dir === 'asc' ? 'ascending' : 'descending' ) : 'none' ) : undefined;
			return <th key={ column.id } aria-sort={ ariaSort }>{ sortable
				? <button type="button" className="corex-data__sort" onClick={ () => explorer.dispatch( { type: 'query', patch: toggleSort( state.query, column.id ) } ) }>{ column.label }</button>
				: column.label }</th>;
		} ) }
		<th>{ __( 'Actions', 'corex' ) }</th></tr></thead>
		<tbody>{ state.rows.map( ( row ) => <tr key={ row.id } className={ state.selected.includes( row.id ) ? 'is-selected' : undefined }>
			<td className="corex-data__check"><input type="checkbox" checked={ state.selected.includes( row.id ) }
				onChange={ () => explorer.dispatch( { type: 'select', id: row.id } ) } aria-label={ recordSelectionLabel( row.id ) } /></td>
			{ state.columns.map( ( column ) => <td key={ column.id }>{ row[ column.id ] === '' || row[ column.id ] == null ? '—' : String( row[ column.id ] ) }</td> ) }
			<td className="corex-data__row-actions"><Button size="small" variant="secondary" onClick={ () => open( row ) }>{ __( 'View', 'corex' ) }</Button></td>
		</tr> ) }</tbody>
	</table></div>;
}
