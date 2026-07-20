import { useState } from '@wordpress/element';
import { Button, CheckboxControl, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import CorexSelect from '../components/CorexSelect.js';

export default function ExportDialog( { source, state, close, create } ) {
	const fields = source?.fields || [];
	const [ scope, setScope ] = useState( state.selected.length ? 'selected' : 'filtered' );
	const [ columns, setColumns ] = useState( fields.map( ( field ) => field.key ) );
	const [ format, setFormat ] = useState( 'csv' );
	const [ acknowledged, setAcknowledged ] = useState( false );
	const personal = fields.some( ( field ) => columns.includes( field.key ) && field.personal_data_class !== 'none' );
	const submit = async () => {
		const exportRun = await create( {
			scope, selected_ids: scope === 'selected' ? state.selected : [],
			query: scope === 'filtered' ? state.query : {}, columns, format,
			personal_data_acknowledged: acknowledged,
		} );
		if ( exportRun ) close();
	};

	return <Modal title={ __( 'Create export', 'corex' ) } onRequestClose={ close }>
		{ /* Scopes that cannot apply are left out rather than shown greyed: the list only ever
		     offers things that work. */ }
		<CorexSelect label={ __( 'Scope', 'corex' ) } value={ scope } onChange={ setScope } block options={ [
			{ label: __( 'Current filters', 'corex' ), value: 'filtered' },
			...( state.selected.length ? [ { label: __( 'Selected rows', 'corex' ), value: 'selected' } ] : [] ),
			{ label: __( 'All accessible rows', 'corex' ), value: 'all' },
		] } />
		<fieldset className="corex-data__column-picker"><legend>{ __( 'Columns', 'corex' ) }</legend>
			{ fields.map( ( field ) => <CheckboxControl key={ field.key } label={ field.label } checked={ columns.includes( field.key ) }
				onChange={ ( checked ) => setColumns( ( current ) => checked ? [ ...current, field.key ] : current.filter( ( key ) => key !== field.key ) ) } /> ) }
		</fieldset>
		<CorexSelect label={ __( 'Format', 'corex' ) } value={ format } onChange={ setFormat } block options={ [
			{ label: __( 'CSV', 'corex' ), value: 'csv' },
			...( source?.actions?.export_xlsx?.visible ? [ { label: __( 'XLSX', 'corex' ), value: 'xlsx' } ] : [] ),
		] } />
		{ personal && <CheckboxControl label={ __( 'I understand this export contains personal data and must be handled securely.', 'corex' ) }
			checked={ acknowledged } onChange={ setAcknowledged } /> }
		<div className="corex-data__dialog-actions">
			<Button variant="tertiary" onClick={ close }>{ __( 'Cancel', 'corex' ) }</Button>
			<Button variant="primary" onClick={ submit } disabled={ columns.length === 0 || ( personal && ! acknowledged ) }>{ __( 'Queue export', 'corex' ) }</Button>
		</div>
	</Modal>;
}
