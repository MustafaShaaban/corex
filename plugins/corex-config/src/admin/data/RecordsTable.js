import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { allRowsSelected, toggleSort } from '../dataClient.js';
import FieldValue from '../components/FieldValue.js';

function recordSelectionLabel( recordId ) {
	/* translators: %s: record identifier. */
	return sprintf( __( 'Select record %s', 'corex' ), recordId );
}

/**
 * The `aria-sort` a header cell should carry.
 *
 * @param {Object}  query    The current query, with its `sort` and `dir`.
 * @param {string}  columnId The column this header renders.
 * @param {boolean} sortable Whether the field can be sorted on at all.
 * @return {string|undefined} The ARIA value, or undefined when the column is not sortable.
 */
function ariaSortFor( query, columnId, sortable ) {
	if ( ! sortable ) {
		return undefined;
	}
	if ( query.sort !== columnId ) {
		return 'none';
	}
	return query.dir === 'asc' ? 'ascending' : 'descending';
}

export default function RecordsTable( { explorer, open } ) {
	const { state } = explorer;
	const all = allRowsSelected( state.selected, state.rows );
	const fields = new Map(
		( explorer.source?.fields || [] ).map( ( field ) => [
			field.key,
			field,
		] )
	);
	return (
		<div className="corex-data__table-scroll" tabIndex={ 0 }>
			<table className="widefat corex-data__table">
				<thead>
					<tr>
						<th className="corex-data__check">
							<input
								type="checkbox"
								checked={ all }
								onChange={ () =>
									explorer.dispatch( { type: 'select-all' } )
								}
								aria-label={ __(
									'Select visible records',
									'corex'
								) }
							/>
						</th>
						{ state.columns.map( ( column ) => {
							const sortable = Boolean(
								fields.get( column.id )?.sortable
							);
							const ariaSort = ariaSortFor(
								state.query,
								column.id,
								sortable
							);
							return (
								<th key={ column.id } aria-sort={ ariaSort }>
									{ sortable ? (
										<button
											type="button"
											className="corex-data__sort"
											onClick={ () =>
												explorer.dispatch( {
													type: 'query',
													patch: toggleSort(
														state.query,
														column.id
													),
												} )
											}
										>
											{ column.label }
										</button>
									) : (
										column.label
									) }
								</th>
							);
						} ) }
						<th>{ __( 'Actions', 'corex' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ state.rows.map( ( row ) => (
						<tr
							key={ row.id }
							className={
								state.selected.includes( row.id )
									? 'is-selected'
									: undefined
							}
						>
							<td className="corex-data__check">
								<input
									type="checkbox"
									checked={ state.selected.includes(
										row.id
									) }
									onChange={ () =>
										explorer.dispatch( {
											type: 'select',
											id: row.id,
										} )
									}
									aria-label={ recordSelectionLabel(
										row.id
									) }
								/>
							</td>
							{ state.columns.map( ( column ) => (
								<td key={ column.id }>
									<FieldValue value={ row[ column.id ] } />
								</td>
							) ) }
							<td className="corex-data__row-actions">
								<Button
									size="small"
									variant="secondary"
									onClick={ () => open( row ) }
								>
									{ __( 'View', 'corex' ) }
								</Button>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
