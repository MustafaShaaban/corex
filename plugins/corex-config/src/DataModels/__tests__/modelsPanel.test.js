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

it( 'says in words what a field holds about a person, and nothing of one that holds none', () => {
	expect(
		behaviours( [
			{
				key: 'email',
				label: 'Email',
				type: 'email',
				read_only: false,
				personal_data_class: 'contact',
			},
			{
				key: 'status',
				label: 'Status',
				type: 'text',
				read_only: true,
				personal_data_class: 'none',
			},
		] )
	).toEqual( {
		email: 'Writable · Personal data: contact details',
		status: 'Read only',
	} );
} );

it( 'says that a field nobody classed is treated as personal data', () => {
	expect(
		behaviours( [
			{
				key: 'notes',
				label: 'Notes',
				type: 'text',
				read_only: true,
				personal_data_class: 'undeclared',
			},
		] )
	).toEqual( {
		notes: 'Read only · Personal data: data nobody has classified yet',
	} );
} );
