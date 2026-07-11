import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

export default function BulkBar( { explorer, edit, exportRows } ) {
	const count = explorer.state.selected.length;
	if ( ! count ) return null;

	return <div className="corex-data__bulkbar" role="region" aria-label={ __( 'Bulk actions', 'corex' ) }>
		{ /* translators: %d: selected record count. */ }
		<strong>{ sprintf( _n( '%d selected', '%d selected', count, 'corex' ), count ) }</strong>
		{ explorer.can( 'bulk_update' ) && <Button variant="secondary" onClick={ edit }>{ __( 'Bulk edit', 'corex' ) }</Button> }
		{ explorer.can( 'bulk_delete' ) && <Button isDestructive variant="secondary"
			onClick={ () => explorer.previewMutation( 'bulk_delete', explorer.state.selected ) }>{ __( 'Delete', 'corex' ) }</Button> }
		{ explorer.can( 'export_csv' ) && <Button variant="secondary" onClick={ exportRows }>{ __( 'Export selected', 'corex' ) }</Button> }
	</div>;
}
