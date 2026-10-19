/**
 * Spec 111: a CoreX site says it runs CoreX, in the page head a technology scanner reads.
 *
 * Read signed out and from the raw response, the way a scanner reads a page: the markup the
 * server sent, before any script ran.
 */
const { test, expect, request: apiRequest } = require( '@playwright/test' );

const NO_SESSION = { cookies: [], origins: [] };
const GENERATOR = /<meta[^>]+name=["']generator["'][^>]*>/g;

test( 'a signed-out visitor gets one CoreX mark, with no version, on every page template', async ( {
	baseURL,
} ) => {
	const client = await apiRequest.newContext( {
		baseURL,
		storageState: NO_SESSION,
	} );

	// The front page and the not-found page are drawn from different templates.
	for ( const address of [
		'/',
		`/corex-no-such-address-${ Date.now() }/`,
	] ) {
		const html = await ( await client.get( address ) ).text();
		const marks = ( html.match( GENERATOR ) || [] ).filter( ( tag ) =>
			/content=["']CoreX/.test( tag )
		);

		// SC-001: exactly one, and it names CoreX and nothing else.
		expect( marks, address ).toHaveLength( 1 );
		expect( marks[ 0 ], address ).toMatch( /content=["']CoreX["']/ );
	}

	await client.dispose();
} );
