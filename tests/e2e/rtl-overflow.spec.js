/**
 * No CoreX admin screen scrolls sideways (spec 091).
 *
 * This exists because "corex-access overflows 1px in RTL at 375px" sat on the open list for several
 * releases, attributed to a screen that had nothing to do with it. Bisecting the document found the
 * source in WordPress's own admin-bar menu toggle — `margin-left: -1px` beside a 1px left border, a
 * border-overlap trick written for LTR, on an element whose parent floats left. In RTL that lands it
 * at `left: -1`, and it does so on *every* screen. Access was simply the one somebody measured.
 *
 * So the test is written the way the finding says it should be: every screen, both directions,
 * measured rather than looked at. A single-screen test would have kept pointing at the wrong place.
 */
const { test, expect } = require( '@playwright/test' );

/** The narrowest supported viewport — where a one-pixel overflow actually shows. */
const PHONE = { width: 375, height: 900 };

const SCREENS = [
	'corex-settings',
	'corex-access',
	'corex-submissions',
	'corex-notifications',
	'corex-forms',
	'corex-guides',
];

for ( const direction of [ 'ltr', 'rtl' ] ) {
	for ( const slug of SCREENS ) {
		test( `${ slug } does not scroll sideways at 375px in ${ direction }`, async ( {
			page,
		} ) => {
			await page.setViewportSize( PHONE );
			await page.goto( `/wp-admin/admin.php?page=${ slug }` );

			// The shell is server-rendered, so its presence is the readiness signal that does not
			// depend on which screens happen to mount a React island.
			await page
				.locator( '.corex-admin' )
				.first()
				.waitFor( { state: 'attached', timeout: 15000 } );

			await page.evaluate( ( dir ) => {
				document.documentElement.setAttribute( 'dir', dir );
				document.body.setAttribute( 'dir', dir );
			}, direction );

			const overflow = await page.evaluate( () => {
				const root = document.documentElement;

				return {
					by: root.scrollWidth - root.clientWidth,
					// Reported so a failure names a width rather than only a delta.
					scrollWidth: root.scrollWidth,
					clientWidth: root.clientWidth,
				};
			} );

			expect(
				overflow.by,
				`${ slug } in ${ direction }: scrollWidth ${ overflow.scrollWidth } vs clientWidth ${ overflow.clientWidth }`
			).toBeLessThanOrEqual( 0 );
		} );
	}
}
