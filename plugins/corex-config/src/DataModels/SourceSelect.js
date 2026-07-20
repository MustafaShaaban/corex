import { __ } from '@wordpress/i18n';
import CorexSelect from '../admin/components/CorexSelect.js';

export default function SourceSelect( { sources, value, onChange, label = __( 'Model', 'corex' ) } ) {
	return <CorexSelect label={ label } value={ value } onChange={ onChange }
		emptyLabel={ __( 'No models available', 'corex' ) }
		options={ sources.map( ( source ) => ( { label: source.label, value: source.key } ) ) } />;
}
