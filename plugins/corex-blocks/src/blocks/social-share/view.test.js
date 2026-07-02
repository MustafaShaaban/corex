/**
 * Unit tests for the Social Share front-end enhancement (spec 063, Phase 7).
 * The server links already work without JS; this verifies the copy/native enhancements
 * are additive, revealed only when supported, and never fabricate behaviour.
 */
import { initSocialShare } from './view';

function buildBar( url = 'https://acme.test/post/' ) {
	document.body.innerHTML = `
		<div class="corex-social-share" role="group">
			<a class="corex-social-share__link" href="https://twitter.com/intent/tweet?url=x">X</a>
			<button class="corex-social-share__copy" data-corex-share-copy="${ url }" hidden>Copy link</button>
			<button class="corex-social-share__native" data-corex-share-native hidden>Share</button>
		</div>`;
	return document.querySelector( '.corex-social-share' );
}

describe( 'initSocialShare', () => {
	it( 'reveals and wires the copy button when the Clipboard API exists', async () => {
		const root = buildBar( 'https://acme.test/post/' );
		const writeText = jest.fn().mockResolvedValue( undefined );

		initSocialShare( root, { clipboard: { writeText } } );

		const copyBtn = root.querySelector( '[data-corex-share-copy]' );
		expect( copyBtn.hidden ).toBe( false );

		copyBtn.click();
		await Promise.resolve();
		expect( writeText ).toHaveBeenCalledWith( 'https://acme.test/post/' );
		expect( copyBtn.classList.contains( 'is-copied' ) ).toBe( true );
	} );

	it( 'leaves the copy button hidden when there is no Clipboard API (server links remain the fallback)', () => {
		const root = buildBar();

		initSocialShare( root, {} );

		expect( root.querySelector( '[data-corex-share-copy]' ).hidden ).toBe(
			true
		);
	} );

	it( 'reveals the native-share button only when the Web Share API exists and shares the real url', () => {
		const root = buildBar( 'https://acme.test/x/' );
		const share = jest.fn().mockResolvedValue( undefined );

		initSocialShare( root, { share } );

		const nativeBtn = root.querySelector( '[data-corex-share-native]' );
		expect( nativeBtn.hidden ).toBe( false );

		nativeBtn.click();
		expect( share ).toHaveBeenCalledWith( { url: 'https://acme.test/x/' } );
	} );

	it( 'keeps the native button hidden without the Web Share API', () => {
		const root = buildBar();

		initSocialShare( root, { clipboard: { writeText: jest.fn() } } );

		expect( root.querySelector( '[data-corex-share-native]' ).hidden ).toBe(
			true
		);
	} );

	it( 'is a safe no-op when given no root', () => {
		expect( () => initSocialShare( null, {} ) ).not.toThrow();
	} );
} );
