import { useMemo, useState } from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import FieldControl, { writableFields } from './FieldControl.js';

export default function RecordDialog( { source, record, close, preview } ) {
	const fields = useMemo( () => writableFields( source ), [ source ] );
	const [ values, setValues ] = useState( () => Object.fromEntries(
		fields.map( ( field ) => [ field.key, record?.[ field.key ] ?? '' ] )
	) );
	const submit = ( event ) => {
		event.preventDefault();
		preview( record ? 'update' : 'create', record ? [ record.id ] : [], values );
		close();
	};

	return <Modal title={ record ? __( 'Edit record', 'corex' ) : __( 'New record', 'corex' ) } onRequestClose={ close }>
		<form className="corex-data__record-form" onSubmit={ submit }>
			{ fields.map( ( field ) => <FieldControl key={ field.key } field={ field } value={ values[ field.key ] }
				onChange={ ( fieldValue ) => setValues( ( current ) => ( { ...current, [ field.key ]: fieldValue } ) ) } /> ) }
			<div className="corex-data__dialog-actions">
				<Button variant="tertiary" onClick={ close }>{ __( 'Cancel', 'corex' ) }</Button>
				<Button variant="primary" type="submit">{ __( 'Preview changes', 'corex' ) }</Button>
			</div>
		</form>
	</Modal>;
}
