import { __ } from '@wordpress/i18n';

export function ValidationTab( { draft, rules, onChange } ) {
	const validation = draft.configuration.validation || {};
	const update = ( key, source ) => {
		onChange( {
			...validation,
			[ key ]: source.split( ',' ).map( ( rule ) => rule.trim() ).filter( Boolean ),
		} );
	};

	return (
		<section className="corex-flow-editor__panel">
			<h2>{ __( 'Validation', 'corex' ) }</h2>
			<p>{ __( 'Rules run in the listed order and stop at the first failure for each field.', 'corex' ) }</p>
			{ draft.configuration.schema.map( ( field ) => (
				<label key={ field.uuid } htmlFor={ `corex-validation-${ field.uuid }` }>
					{ field.label }
					<input
						id={ `corex-validation-${ field.uuid }` }
						value={ ( validation[ field.key ] || [] ).join( ', ' ) }
						list="corex-flow-validation-rules"
						onChange={ ( event ) => update( field.key, event.target.value ) }
					/>
				</label>
			) ) }
			<datalist id="corex-flow-validation-rules">
				{ rules.map( ( rule ) => <option key={ rule } value={ rule } /> ) }
			</datalist>
		</section>
	);
}
