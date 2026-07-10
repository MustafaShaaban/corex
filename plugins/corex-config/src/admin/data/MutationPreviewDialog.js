import { Button, Modal } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

function mutationLabel( operation ) {
	const labels = {
		create: __( 'Create', 'corex' ), update: __( 'Update', 'corex' ), delete: __( 'Delete', 'corex' ),
		bulk_update: __( 'Bulk update', 'corex' ), bulk_delete: __( 'Bulk delete', 'corex' ),
	};

	return labels[ operation ] || operation;
}

export default function MutationPreviewDialog( { preview, pending, close, apply } ) {
	const count = preview.record_ids?.length || ( preview.operation === 'create' ? 1 : 0 );
	return <Modal title={ __( 'Confirm data change', 'corex' ) } onRequestClose={ close }>
		{ /* translators: 1: operation name, 2: affected record count. */ }
		<p>{ sprintf( __( '%1$s will affect %2$d record(s). Review the exact scope before applying.', 'corex' ), mutationLabel( preview.operation ), count ) }</p>
		{ Object.keys( preview.values || {} ).length > 0 && <dl className="corex-data__preview-values">
			{ Object.entries( preview.values ).map( ( [ key, fieldValue ] ) =>
				<div key={ key }><dt>{ key }</dt><dd>{ String( fieldValue ) }</dd></div> ) }
		</dl> }
		<div className="corex-data__dialog-actions">
			<Button variant="tertiary" onClick={ close } disabled={ Boolean( pending ) }>{ __( 'Cancel', 'corex' ) }</Button>
			<Button variant="primary" isBusy={ Boolean( pending ) } disabled={ Boolean( pending ) } onClick={ apply }>{ __( 'Confirm and apply', 'corex' ) }</Button>
		</div>
	</Modal>;
}
