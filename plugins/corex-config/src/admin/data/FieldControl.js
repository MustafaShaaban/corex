import {
	CheckboxControl,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

export function writableFields( source ) {
	return ( source?.fields || [] ).filter( ( field ) => ! field.read_only );
}

export default function FieldControl( { field, value, onChange } ) {
	/* translators: %s: personal-data classification. */
	const help = field.personal_data_class !== 'none'
		? sprintf( __( 'Personal data: %s', 'corex' ), field.personal_data_class )
		: undefined;
	const props = { label: field.label, value: value ?? '', required: field.required, help, onChange };
	if ( field.type === 'textarea' || field.type === 'json' ) return <TextareaControl { ...props } />;
	if ( field.type === 'select' && Array.isArray( field.validation?.options ) ) {
		return <SelectControl { ...props } options={ field.validation.options.map( ( option ) => ( {
			label: String( option ), value: String( option ),
		} ) ) } />;
	}
	if ( field.type === 'boolean' ) {
		return <CheckboxControl label={ field.label } checked={ Boolean( value ) } onChange={ onChange } />;
	}

	return <TextControl { ...props } type={ field.type === 'integer' || field.type === 'decimal' ? 'number' : field.type } />;
}
