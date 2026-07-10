import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function RecordDetail( { explorer, record, close, edit } ) {
	return <Modal title={ __( 'Record detail', 'corex' ) } onRequestClose={ close }>
		<dl className="corex-data__fields">{ ( explorer.source?.fields || [] ).map( ( field ) => <div key={ field.key } className="corex-data__field">
			<dt>{ field.label }</dt><dd>{ record[ field.key ] == null || record[ field.key ] === '' ? '—' : String( record[ field.key ] ) }</dd>
		</div> ) }</dl>
		<div className="corex-data__dialog-actions"><Button variant="tertiary" onClick={ close }>{ __( 'Close', 'corex' ) }</Button>
			{ explorer.can( 'update' ) && <Button variant="secondary" onClick={ edit }>{ __( 'Edit', 'corex' ) }</Button> }
			{ explorer.can( 'delete' ) && <Button isDestructive variant="secondary" onClick={ () => {
				explorer.previewMutation( 'delete', [ record.id ] ); close();
			} }>{ __( 'Delete', 'corex' ) }</Button> }
		</div>
	</Modal>;
}
