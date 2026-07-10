/**
 * Generate an RFC-4122 version-4 UUID for client-authored identifiers.
 *
 * Prefers the native crypto.randomUUID(), but that API is restricted to secure
 * contexts (HTTPS or localhost). The WordPress admin is frequently served over
 * plain HTTP (for example http://corex.local), where crypto.randomUUID is
 * undefined and calling it throws. In that case we derive a v4 UUID from
 * crypto.getRandomValues(), which is available in insecure contexts too. We
 * never fall back to Math.random(), so identifiers stay unguessable; when no
 * cryptographic randomness source exists at all we fail loudly.
 *
 * @return {string} A lowercase 8-4-4-4-12 hyphenated v4 UUID.
 */
export function generateUuid() {
	const source = globalThis.crypto;
	if ( source?.randomUUID ) {
		return source.randomUUID();
	}
	if ( source?.getRandomValues ) {
		const bytes = source.getRandomValues( new Uint8Array( 16 ) );
		/* eslint-disable no-bitwise -- RFC-4122 v4 mandates masking these random bytes. */
		bytes[ 6 ] = ( bytes[ 6 ] & 0x0f ) | 0x40; // Version 4.
		bytes[ 8 ] = ( bytes[ 8 ] & 0x3f ) | 0x80; // Variant 10xx.
		/* eslint-enable no-bitwise */
		const hex = Array.from( bytes, ( byte ) =>
			byte.toString( 16 ).padStart( 2, '0' )
		);
		return [
			hex.slice( 0, 4 ).join( '' ),
			hex.slice( 4, 6 ).join( '' ),
			hex.slice( 6, 8 ).join( '' ),
			hex.slice( 8, 10 ).join( '' ),
			hex.slice( 10, 16 ).join( '' ),
		].join( '-' );
	}
	throw new Error( 'Secure UUID generation is unavailable.' );
}
