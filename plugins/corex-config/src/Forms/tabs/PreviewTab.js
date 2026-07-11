import { __ } from '@wordpress/i18n';

export function PreviewTab( { draft } ) {
	return (
		<section className="corex-flow-editor__panel">
			<h2>{ __( 'Visitor preview', 'corex' ) }</h2>
			<form className="corex-flow-preview" onSubmit={ ( event ) => event.preventDefault() }>
				{ draft.configuration.schema.map( ( field ) => <PreviewField key={ field.uuid } field={ field } /> ) }
				<button type="submit" className="button button-primary">{ __( 'Submit preview', 'corex' ) }</button>
			</form>
		</section>
	);
}

function PreviewField( { field } ) {
	if ( field.type === 'textarea' ) {
		return <label>{ field.label }<textarea placeholder={ field.placeholder } required={ field.required } /></label>;
	}
	if ( [ 'select', 'multi-select', 'radio', 'checkbox' ].includes( field.type ) ) {
		return <label>{ field.label }<select multiple={ field.type === 'multi-select' } required={ field.required }>{ ( field.options || [] ).map( ( option ) => <option key={ option.value } value={ option.value }>{ option.label }</option> ) }</select></label>;
	}

	return <label>{ field.label }<input type={ inputType( field.type ) } placeholder={ field.placeholder } required={ field.required } /></label>;
}

function inputType( type ) {
	return [ 'email', 'number', 'date', 'time', 'url', 'hidden' ].includes( type ) ? type : 'text';
}
