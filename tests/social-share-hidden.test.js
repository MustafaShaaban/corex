/**
 * The social share block's copy and device-share buttons are printed `hidden` and shown by its
 * view script: the copy button once the script runs, the device-share button only where the
 * browser can share. Their sheet gives them `display: inline-flex`, which beats the browser's own
 * `[hidden] { display: none }`, so the sheet must hide them again while they carry `hidden`.
 */
const path = require( 'path' );
// Sass comes with @wordpress/scripts, which compiles these sheets for the build.
/* eslint-disable import/no-extraneous-dependencies */
const sass = require( 'sass' );
/* eslint-enable import/no-extraneous-dependencies */

const SHEET = path.join(
	__dirname,
	'../plugins/corex-blocks/src/blocks/social-share/style.scss'
);

/**
 * The declarations of the compiled rule whose selector list holds `selector`.
 *
 * @param {string} css      The compiled sheet.
 * @param {string} selector One selector of the rule.
 * @return {string|null} The rule's body, or null when no rule names it.
 */
function bodyOf( css, selector ) {
	for ( const [ , selectors, body ] of css.matchAll(
		/([^{}]+)\{([^}]*)\}/g
	) ) {
		const list = selectors.split( ',' ).map( ( one ) => one.trim() );
		if ( list.includes( selector ) ) {
			return body;
		}
	}

	return null;
}

describe( 'social share buttons printed hidden', () => {
	const css = sass.compile( SHEET, { style: 'expanded' } ).css;

	test.each( [ 'copy', 'native' ] )(
		'the %s button is not drawn while it carries hidden',
		( part ) => {
			const body = bodyOf(
				css,
				`.corex-social-share__${ part }[hidden]`
			);

			expect( body ).not.toBeNull();
			expect( body ).toMatch( /display:\s*none/ );
		}
	);

	test( 'the buttons still draw as inline-flex once shown', () => {
		expect( bodyOf( css, '.corex-social-share__copy' ) ).toMatch(
			/display:\s*inline-flex/
		);
	} );
} );
