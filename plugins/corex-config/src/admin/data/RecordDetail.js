import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import recordRows from './recordRows.js';

export default function RecordDetail( { explorer, record, close, edit } ) {
	const rows = recordRows( record, explorer.source?.fields );

	return (
		<Modal
			title={ __( 'Record detail', 'corex' ) }
			onRequestClose={ close }
		>
			{ rows.length === 0 ? (
				<p className="corex-data__empty">
					{ __( 'This record has no readable fields.', 'corex' ) }
				</p>
			) : (
				<dl className="corex-data__fields">
					{ rows.map( ( row ) => (
						<div key={ row.key } className="corex-data__field">
							<dt>{ row.label }</dt>
							<dd>{ row.value }</dd>
						</div>
					) ) }
				</dl>
			) }
			<div className="corex-data__dialog-actions">
				<Button variant="tertiary" onClick={ close }>
					{ __( 'Close', 'corex' ) }
				</Button>
				{ explorer.can( 'update' ) && (
					<Button variant="secondary" onClick={ edit }>
						{ __( 'Edit', 'corex' ) }
					</Button>
				) }
				{ explorer.can( 'delete' ) && (
					<Button
						isDestructive
						variant="secondary"
						onClick={ () => {
							explorer.previewMutation( 'delete', [ record.id ] );
							close();
						} }
					>
						{ __( 'Delete', 'corex' ) }
					</Button>
				) }
			</div>
		</Modal>
	);
}
