import { __ } from '@wordpress/i18n';

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
				<label htmlFor="corex-flow-field-width">
					{ __( 'Width', 'corex' ) }
					<select id="corex-flow-field-width" name="width" value={ field.width || 'full' } onChange={ change }>
						<option value="full">{ __( 'Full', 'corex' ) }</option>
						<option value="half">{ __( 'Half', 'corex' ) }</option>
						<option value="third">{ __( 'Third', 'corex' ) }</option>
						<option value="two-thirds">{ __( 'Two thirds', 'corex' ) }</option>
					</select>
				</label>
				<label htmlFor="corex-flow-field-personal-data">
					{ __( 'Personal data class', 'corex' ) }
					<select id="corex-flow-field-personal-data" name="personal_data_class" value={ field.personal_data_class || 'none' } onChange={ change }>
						<option value="none">{ __( 'None', 'corex' ) }</option>
						<option value="contact">{ __( 'Contact', 'corex' ) }</option>
						<option value="identity">{ __( 'Identity', 'corex' ) }</option>
						<option value="consent">{ __( 'Consent', 'corex' ) }</option>
						<option value="sensitive">{ __( 'Sensitive', 'corex' ) }</option>
						<option value="custom">{ __( 'Custom', 'corex' ) }</option>
					</select>
				</label>
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
