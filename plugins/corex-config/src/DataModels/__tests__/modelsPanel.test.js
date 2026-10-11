/**
 * What the Models tab says a field holds (spec 109, slice 1).
 *
 * It printed the personal-data class as its key, untranslated: "contact", and for a column
 * nobody had classed, nothing at all.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import ModelsPanel from '../ModelsPanel.js';

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

/**
 * The "Behavior" cell of each field of the one model drawn, by field key.
 *
 * @param {Array<Object>} fields The model's fields.
 * @return {Object<string,string>} What each field's row says it does and holds.
 */
function behaviours( fields ) {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	act( () => {
		root.render(
			<ModelsPanel
				sources={ [
					{
						key: 'subscribers',
						label: 'Subscribers',
						access: 'allowed',
						actions: {},
						capabilities: {},
						fields,
					},
				] }
			/>
		);
	} );
	const said = Object.fromEntries(
		[ ...container.querySelectorAll( 'tbody tr' ) ].map( ( row ) => [
			row.querySelector( 'code' ).textContent,
			row.cells[ 2 ].textContent,
		] )
	);
	act( () => {
		root.unmount();
		container.remove();
	} );

	return said;
}

it.each( [
	[
		'a field that holds contact details',
		'contact',
		false,
		'Writable · Personal data: contact details',
	],
	[ 'a field that holds nothing about a person', 'none', true, 'Read only' ],
	[
		'a field nobody classed, which is treated as personal data',
		'undeclared',
		true,
		'Read only · Personal data: data nobody has classified yet',
	],
] )( 'says in words what %s holds', ( _, className, readOnly, said ) => {
	expect(
		behaviours( [
			{
				key: 'email',
				label: 'Email',
				type: 'text',
				read_only: readOnly,
				personal_data_class: className,
			},
		] )
	).toEqual( { email: said } );
} );
