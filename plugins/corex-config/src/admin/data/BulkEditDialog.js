import { useState } from '@wordpress/element';
import { Button, Modal, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import FieldControl, { writableFields } from './FieldControl.js';

export default function BulkEditDialog( { source, count, close, preview } ) {
	const fields = writableFields( source );
	const [ fieldKey, setFieldKey ] = useState( fields[ 0 ]?.key || '' );
	const [ fieldValue, setFieldValue ] = useState( '' );
	const field = fields.find( ( candidate ) => candidate.key === fieldKey );

	return <Modal title={ __( 'Bulk edit records', 'corex' ) } onRequestClose={ close }>
		{ /* translators: %d: selected record count. */ }
		<p>{ sprintf( __( 'Change one field for %d selected record(s).', 'corex' ), count ) }</p>
		<SelectControl label={ __( 'Field', 'corex' ) } value={ fieldKey } onChange={ setFieldKey }
			options={ fields.map( ( candidate ) => ( { label: candidate.label, value: candidate.key } ) ) } />
		{ field && <FieldControl field={ field } value={ fieldValue } onChange={ setFieldValue } /> }
		<div className="corex-data__dialog-actions">
			<Button variant="tertiary" onClick={ close }>{ __( 'Cancel', 'corex' ) }</Button>
			<Button variant="primary" disabled={ ! fieldKey } onClick={ () => {
				preview( { [ fieldKey ]: fieldValue } ); close();
			} }>{ __( 'Preview bulk edit', 'corex' ) }</Button>
		</div>
	</Modal>;
}
