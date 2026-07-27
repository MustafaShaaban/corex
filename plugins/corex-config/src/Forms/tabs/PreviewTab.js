import { __ } from '@wordpress/i18n';

export function PreviewTab( { draft } ) {
	return (
		<section className="corex-flow-editor__panel">
			<h2>{ __( 'Visitor preview', 'corex' ) }</h2>
			<form
				className="corex-flow-preview"
				onSubmit={ ( event ) => event.preventDefault() }
			>
				{ draft.configuration.schema.map( ( field ) => (
					<PreviewField key={ field.uuid } field={ field } />
				) ) }
				<button type="submit" className="button button-primary">
					{ __( 'Submit preview', 'corex' ) }
				</button>
			</form>
		</section>
	);
}

function PreviewField( { field } ) {
	// The field's own uuid names the control, so the label points at it by `for` rather than
	// by wrapping it — the same association the front end renders (FieldRenderer.php).
	const controlId = `corex-flow-preview-${ field.uuid }`;

	if ( field.type === 'textarea' ) {
		return (
			<label htmlFor={ controlId }>
				{ field.label }
				<textarea
					id={ controlId }
					placeholder={ field.placeholder }
					required={ field.required }
				/>
			</label>
		);
	}
	if (
		[ 'select', 'multi-select', 'radio', 'checkbox' ].includes( field.type )
	) {
		// Deliberately a native <select>, not CorexSelect. This is a preview of what a VISITOR
		// will see, and the front end renders a native control (FieldRenderer.php) — showing the
		// admin component here would preview something the site does not serve. It also has to
		// support `multiple`, which the single-value admin control does not.
		return (
			<label htmlFor={ controlId }>
				{ field.label }
				<select
					id={ controlId }
					multiple={ field.type === 'multi-select' }
					required={ field.required }
				>
					{ ( field.options || [] ).map( ( option ) => (
						<option key={ option.value } value={ option.value }>
							{ option.label }
						</option>
					) ) }
				</select>
			</label>
		);
	}

	return (
		<label htmlFor={ controlId }>
			{ field.label }
			<input
				id={ controlId }
				type={ inputType( field.type ) }
				placeholder={ field.placeholder }
				required={ field.required }
			/>
		</label>
	);
}

function inputType( type ) {
	return [ 'email', 'number', 'date', 'time', 'url', 'hidden' ].includes(
		type
	)
		? type
		: 'text';
}
