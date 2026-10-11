/**
 * What a record's form says a field holds (spec 109, slice 1). It printed the personal-data
 * class as its key, untranslated.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import FieldControl from '../FieldControl.js';

// `@wordpress/components` does not load under Jest here; a control is its label and its help.
jest.mock( '@wordpress/components', () => ( {
	TextControl: ( { label, help } ) => (
		<div>
			<span>{ label }</span>
			<span className="help">{ help }</span>
		</div>
	),
	TextareaControl: () => null,
	CheckboxControl: () => null,
} ) );

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

/**
 * The help line drawn under a field's control.
 *
 * @param {string} className The field's personal-data class.
 * @return {string} The help's text; empty when there is none.
 */
function helpFor( className ) {
	const container = document.createElement( 'div' );
	const root = createRoot( container );
	act( () => {
		root.render(
			<FieldControl
				field={ {
					key: 'email',
					label: 'Email',
					type: 'text',
					personal_data_class: className,
				} }
				value=""
				onChange={ () => {} }
			/>
		);
	} );
	const help = container.querySelector( '.help' ).textContent;
	act( () => root.unmount() );

	return help;
}

it.each( [
	[ 'contact', 'Personal data: contact details' ],
	[ 'undeclared', 'Personal data: data nobody has classified yet' ],
	[ 'none', '' ],
] )( 'for the class %s says "%s"', ( className, help ) => {
	expect( helpFor( className ) ).toBe( help );
} );
