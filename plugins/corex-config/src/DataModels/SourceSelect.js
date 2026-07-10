import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function SourceSelect( { sources, value, onChange, label = __( 'Model', 'corex' ) } ) {
	return <SelectControl label={ label } value={ value } onChange={ onChange }
		options={ sources.map( ( source ) => ( { label: source.label, value: source.key } ) ) } />;
}
