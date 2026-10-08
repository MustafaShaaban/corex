import { useState } from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import FieldControl, { writableFields } from './FieldControl.js';
import CorexSelect from '../components/CorexSelect.js';
import { usePending, workingProps } from '../components/working.js';

export default function BulkEditDialog( { source, count, close, preview } ) {
	const fields = writableFields( source );
	const [ fieldKey, setFieldKey ] = useState( fields[ 0 ]?.key || '' );
	const [ fieldValue, setFieldValue ] = useState( '' );
	const [ pending, during ] = usePending();
	const field = fields.find( ( candidate ) => candidate.key === fieldKey );

	return (
		<Modal
			title={ __( 'Bulk edit records', 'corex' ) }
			onRequestClose={ close }
		>
			<p>
				{ sprintf(
					/* translators: %d: selected record count. */
					__(
						'Change one field for %d selected record(s).',
						'corex'
					),
					count
				) }
			</p>
			<CorexSelect
				label={ __( 'Field', 'corex' ) }
				value={ fieldKey }
				onChange={ setFieldKey }
				emptyLabel={ __( 'No editable fields', 'corex' ) }
				options={ fields.map( ( candidate ) => ( {
					label: candidate.label,
					value: candidate.key,
				} ) ) }
			/>
			{ field && (
				<FieldControl
					field={ field }
					value={ fieldValue }
					onChange={ setFieldValue }
				/>
			) }
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
					disabled={ ! fieldKey }
					onClick={ () =>
						during( 'preview', async () => {
							await preview( { [ fieldKey ]: fieldValue } );
							close();
						} )
					}
					{ ...workingProps( pending === 'preview' ) }
				>
					{ __( 'Preview bulk edit', 'corex' ) }
				</Button>
			</div>
		</Modal>
	);
}
