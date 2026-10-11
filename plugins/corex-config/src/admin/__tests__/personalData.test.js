/**
 * What a field's personal-data class means, in words (spec 109, slice 1).
 */
import {
	holdsPersonalData,
	listed,
	personalDataLabel,
} from '../personalData.js';

describe( 'a personal-data class, in words', () => {
	it.each( [
		[ 'contact', 'contact details' ],
		[ 'identity', 'names and identities' ],
		[ 'content', 'what people wrote' ],
		[ 'security', 'sign-in and security records' ],
		[ 'behavioral', 'interests and behaviour' ],
		[ 'sensitive', 'sensitive details' ],
		[ 'undeclared', 'data nobody has classified yet' ],
	] )( 'is worded for %s', ( className, words ) => {
		expect( personalDataLabel( className ) ).toBe( words );
	} );

	it( 'is nothing for a field that holds none', () => {
		expect( personalDataLabel( 'none' ) ).toBe( '' );
	} );

	it( 'is worded as unclassified for a class this screen was never told of, not printed as its key', () => {
		expect( personalDataLabel( 'biometric' ) ).toBe(
			'data nobody has classified yet'
		);
	} );
} );

describe( 'several kinds, listed in a sentence', () => {
	afterEach( () => {
		document.documentElement.lang = '';
	} );

	it( 'are parted by commas in English, with no "and"', () => {
		expect( listed( [ 'contact details', 'what people wrote' ] ) ).toBe(
			'contact details, what people wrote'
		);
	} );

	it( 'are listed by the rules of the screen’s language, not by English’s', () => {
		document.documentElement.lang = 'ar';
		const arabic = listed( [ 'أ', 'ب', 'ج' ] );

		// The exact form is the browser's to know. What is held here is that it is asked.
		expect( arabic ).not.toBe( 'أ, ب, ج' );
		expect( arabic ).toMatch( /^أ.+ب.+ج$/ );
	} );

	it( 'are the one kind when there is one', () => {
		expect( listed( [ 'contact details' ] ) ).toBe( 'contact details' );
	} );
} );

describe( 'whether a field holds personal data', () => {
	it.each( [
		[ 'none', false ],
		[ 'contact', true ],
		[ 'undeclared', true ],
	] )( 'for the class %s is %s', ( className, expected ) => {
		expect( holdsPersonalData( { personal_data_class: className } ) ).toBe(
			expected
		);
	} );

	it( 'is yes for a field that says nothing at all', () => {
		// Silence is read the safe way here as it is on the server.
		expect( holdsPersonalData( { key: 'notes' } ) ).toBe( true );
	} );
} );
