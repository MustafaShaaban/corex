import { generateUuid } from '../uuid.js';

const UUID_V4 =
	/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

function setCrypto( value ) {
	Object.defineProperty( globalThis, 'crypto', {
		configurable: true,
		value,
	} );
}

describe( 'generateUuid', () => {
	afterEach( () => {
		setCrypto( {
			randomUUID: () => '00000000-0000-4000-8000-000000000000',
		} );
	} );

	it( 'uses the native randomUUID when the context is secure', () => {
		setCrypto( { randomUUID: () => 'native-uuid' } );
		expect( generateUuid() ).toBe( 'native-uuid' );
	} );

	it( 'derives a valid v4 UUID from getRandomValues in an insecure context', () => {
		// http:// non-localhost admin: randomUUID is undefined, getRandomValues remains.
		setCrypto( {
			getRandomValues: ( array ) => {
				for ( let index = 0; index < array.length; index += 1 ) {
					array[ index ] = ( index * 37 + 11 ) % 256;
				}
				return array;
			},
		} );
		const value = generateUuid();
		expect( value ).toMatch( UUID_V4 );
		expect( generateUuid() ).toMatch( UUID_V4 );
	} );

	it( 'throws only when no secure randomness source exists at all', () => {
		setCrypto( undefined );
		expect( () => generateUuid() ).toThrow( /Secure UUID/ );
	} );
} );
