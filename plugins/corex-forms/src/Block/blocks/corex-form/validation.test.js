/**
 * Jest tests for the shared client validator. These assert the SAME semantics as the
 * PHP Validator/Rules (bail-per-field, empty passes non-required rules, string-length
 * vs numeric bounds) so front and back stay in lockstep.
 */
import { validateField, validateForm } from './validation';

describe( 'validateField', () => {
	test( 'required fails on empty, passes on a value', () => {
		const field = { rules: [ { rule: 'required', params: [] } ] };
		expect( validateField( field, '', {} ) ).toBe( 'required' );
		expect( validateField( field, '   ', {} ) ).toBe( 'required' );
		expect( validateField( field, 'Sam', {} ) ).toBeNull();
	} );

	test( 'email checks only non-empty values', () => {
		const field = { rules: [ { rule: 'email', params: [] } ] };
		expect( validateField( field, '', {} ) ).toBeNull();
		expect( validateField( field, 'nope', {} ) ).toBe( 'email' );
		expect( validateField( field, 'a@b.co', {} ) ).toBeNull();
	} );

	test( 'max/min apply to string length and to numbers', () => {
		expect(
			validateField( { rules: [ { rule: 'max', params: [ '3' ] } ] }, 'abcd', {} )
		).toBe( 'max' );
		expect(
			validateField( { rules: [ { rule: 'max', params: [ '3' ] } ] }, 'abc', {} )
		).toBeNull();
		expect(
			validateField( { rules: [ { rule: 'min', params: [ '2' ] } ] }, 'a', {} )
		).toBe( 'min' );
		expect(
			validateField( { rules: [ { rule: 'max', params: [ '10' ] } ] }, '20', {} )
		).toBe( 'max' );
		expect(
			validateField( { rules: [ { rule: 'min', params: [ '5' ] } ] }, '3', {} )
		).toBe( 'min' );
	} );

	test( 'numeric checks only non-empty values', () => {
		const field = { rules: [ { rule: 'numeric', params: [] } ] };
		expect( validateField( field, 'x', {} ) ).toBe( 'numeric' );
		expect( validateField( field, '42', {} ) ).toBeNull();
		expect( validateField( field, '', {} ) ).toBeNull();
	} );

	test( 'bails per field at the first failing rule', () => {
		const field = {
			rules: [
				{ rule: 'required', params: [] },
				{ rule: 'email', params: [] },
			],
		};
		expect( validateField( field, '', {} ) ).toBe( 'required' );
	} );
} );

describe( 'validateForm', () => {
	const schema = [
		{ name: 'name', required: true, rules: [ { rule: 'required', params: [] } ] },
		{ name: 'note', required: false, rules: [ { rule: 'max', params: [ '5' ] } ] },
	];

	test( 'skips absent optional fields but flags required ones', () => {
		expect( validateForm( schema, {} ) ).toEqual( { name: 'required' } );
	} );

	test( 'passes a valid payload', () => {
		expect( validateForm( schema, { name: 'Sam' } ) ).toEqual( {} );
	} );

	test( 'flags a present optional field that breaks a rule', () => {
		expect( validateForm( schema, { name: 'Sam', note: 'toolong' } ) ).toEqual( {
			note: 'max',
		} );
	} );
} );
