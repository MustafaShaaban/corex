import { useMemo, useState } from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import FieldControl, { writableFields } from './FieldControl.js';
import { usePending, workingProps } from '../components/working.js';

export default function RecordDialog( { source, record, close, preview } ) {
	const fields = useMemo( () => writableFields( source ), [ source ] );
	const [ values, setValues ] = useState( () =>
		Object.fromEntries(
			fields.map( ( field ) => [
				field.key,
				record?.[ field.key ] ?? '',
			] )
		)
	);
	const [ pending, during ] = usePending();
	// The dialog used to close at once and leave nothing on screen until the confirmation
	// arrived. It stays, with its button working, until there is something to show.
	const submit = ( event ) => {
		event.preventDefault();
		during( 'preview', async () => {
			await preview(
				record ? 'update' : 'create',
				record ? [ record.id ] : [],
				values
			);
			close();
		} );
	};

	return (
		<Modal
			title={
				record
					? __( 'Edit record', 'corex' )
					: __( 'New record', 'corex' )
			}
			onRequestClose={ close }
		>
			<form className="corex-data__record-form" onSubmit={ submit }>
				{ fields.map( ( field ) => (
					<FieldControl
						key={ field.key }
						field={ field }
						value={ values[ field.key ] }
						onChange={ ( fieldValue ) =>
							setValues( ( current ) => ( {
								...current,
								[ field.key ]: fieldValue,
							} ) )
						}
					/>
				) ) }
				<div className="corex-data__dialog-actions">
					<Button
						variant="tertiary"
						onClick={ close }
						disabled={ pending !== '' }
					>
						{ __( 'Cancel', 'corex' ) }
					</Button>
					<Button
						variant="primary"
						type="submit"
						{ ...workingProps( pending === 'preview' ) }
					>
						{ __( 'Preview changes', 'corex' ) }
					</Button>
				</div>
			</form>
		</Modal>
	);
}
