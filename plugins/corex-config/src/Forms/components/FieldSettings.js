import { __ } from '@wordpress/i18n';
import CorexSelect from '../../admin/components/CorexSelect.js';

const FIELD_WIDTHS = [
	{ value: 'full', label: __( 'Full', 'corex' ) },
	{ value: 'half', label: __( 'Half', 'corex' ) },
	{ value: 'third', label: __( 'Third', 'corex' ) },
	{ value: 'two-thirds', label: __( 'Two thirds', 'corex' ) },
];

const PERSONAL_DATA_CLASSES = [
	{ value: 'none', label: __( 'None', 'corex' ) },
	{ value: 'contact', label: __( 'Contact', 'corex' ) },
	{ value: 'identity', label: __( 'Identity', 'corex' ) },
	{ value: 'consent', label: __( 'Consent', 'corex' ) },
	{ value: 'sensitive', label: __( 'Sensitive', 'corex' ) },
	{ value: 'custom', label: __( 'Custom', 'corex' ) },
];

function TextField( { label, name, value, onChange, ...props } ) {
	const id = `corex-flow-field-${ name }`;
	return (
		<label htmlFor={ id }>
			{ label }
			<input id={ id } name={ name } value={ value ?? '' } onChange={ onChange } { ...props } />
		</label>
	);
}

export function FieldSettings( { field, onChange } ) {
	const change = ( event ) => {
		const { name, type, checked, value } = event.target;
		onChange( { [ name ]: type === 'checkbox' ? checked : value } );
	};
	const options = Array.isArray( field.options )
		? field.options.map( ( option ) => `${ option.value ?? option }:${ option.label ?? option }` ).join( '\n' )
		: '';

	return (
		<section className="corex-flow-editor__field-settings" aria-labelledby="corex-field-settings-title">
			<h3 id="corex-field-settings-title">{ __( 'Field settings', 'corex' ) }</h3>
			<div className="corex-flow-editor__form-grid">
				<TextField label={ __( 'Label', 'corex' ) } name="label" value={ field.label } onChange={ change } required />
				<TextField label={ __( 'Key', 'corex' ) } name="key" value={ field.key } onChange={ change } pattern="[a-z][a-z0-9_]*" required />
				<TextField label={ __( 'Placeholder', 'corex' ) } name="placeholder" value={ field.placeholder } onChange={ change } />
				<TextField label={ __( 'Help text', 'corex' ) } name="help_text" value={ field.help_text } onChange={ change } />
				<TextField label={ __( 'Default value', 'corex' ) } name="default_value" value={ field.default_value } onChange={ change } />
				<div className="corex-field">
					<span>{ __( 'Width', 'corex' ) }</span>
					<CorexSelect id="corex-flow-field-width" label={ __( 'Width', 'corex' ) }
						value={ field.width || 'full' } options={ FIELD_WIDTHS }
						onChange={ ( value ) => change( { target: { name: 'width', value } } ) } block />
				</div>
				<div className="corex-field">
					<span>{ __( 'Personal data class', 'corex' ) }</span>
					<CorexSelect id="corex-flow-field-personal-data" label={ __( 'Personal data class', 'corex' ) }
						value={ field.personal_data_class || 'none' } options={ PERSONAL_DATA_CLASSES }
						onChange={ ( value ) => change( { target: { name: 'personal_data_class', value } } ) } block />
				</div>
				<label className="is-wide" htmlFor="corex-flow-field-options">
					{ __( 'Options (value:label, one per line)', 'corex' ) }
					<textarea
						id="corex-flow-field-options"
						value={ options }
						onChange={ ( event ) => onChange( { options: parseOptions( event.target.value ) } ) }
					/>
				</label>
				<label className="corex-flow-editor__check" htmlFor="corex-flow-field-required">
					<input id="corex-flow-field-required" type="checkbox" name="required" checked={ Boolean( field.required ) } onChange={ change } />
					{ __( 'Required field', 'corex' ) }
				</label>
			</div>
		</section>
	);
}

function parseOptions( source ) {
	return source.split( '\n' ).map( ( line ) => line.trim() ).filter( Boolean ).map( ( line ) => {
		const [ value, ...label ] = line.split( ':' );
		return { value: value.trim(), label: ( label.join( ':' ) || value ).trim() };
	} );
}
