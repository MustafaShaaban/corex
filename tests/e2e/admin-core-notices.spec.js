/**
 * What WordPress prints before the page is drawn inside the CoreX shell, under the page header.
 *
 * Core's update nag used to sit in a band of its own above the shell and push every CoreX screen
 * 54px down the page. It is printed only while a core update is pending, and CI installs the
 * latest WordPress, so the defect showed on a development install and on client sites and never in
 * CI. `admin-help-tab.spec.js` measures the same offset and passed there throughout.
 *
 * So these tests ask for the condition. `fixtures/corex-e2e-core-notices.php` answers the update
 * check for one request: `pending` makes core print its own nag, with one notice on
 * `admin_notices` and a dismissible one on `all_admin_notices`; `none` makes core print no nag on
 * an install that is behind.
 *
 * The spacing is read off the rendered page. A screenshot does not show a gap that is 5px out.
 */

const { test, expect } = require( '@playwright/test' );
const { COREX_ROUTES, contrast, pinAppearance } = require( './helpers' );

const NAG = '.corex-admin__notices > .update-nag';
const INFO = '#corex-e2e-notice-info';
const DISMISSIBLE = '#corex-e2e-notice-error';

/** What the fixture makes WordPress print, in the order it is printed. */
const FIXTURE_NOTICES = [
	'notice notice-warning update-nag inline',
	'corex-e2e-notice-info',
	'corex-e2e-notice-error',
];

/**
 * The fixture's notices among everything in the region.
 *
 * An install's own plugins print on the same hooks. CI activates every plugin a fresh WordPress
 * ships with, and Hello Dolly prints a line of a song on `admin_notices`: the first run of this
 * spec in CI met four things in the region where it expected three. What somebody else printed is
 * measured for its spacing like the rest, and is not expected to look like a notice.
 *
 * @param {Array} printed What `measure()` found in the region.
 * @return {string[]} The names of the fixture's notices, in the order they are drawn.
 */
const fixtureNoticesIn = ( printed ) =>
	printed
		.map( ( item ) => item.name )
		.filter( ( name ) => FIXTURE_NOTICES.includes( name ) );

/**
 * The space above each thing in the region when it is spaced as the shell spaces it: one gutter
 * under the page header, then 12px between one and the next.
 *
 * @param {number} gutter The shell's gutter at this width.
 * @param {number} count  How many things are in the region.
 * @return {number[]} The expected space above each.
 */
const stackedUnder = ( gutter, count ) => [
	gutter,
	...Array( Math.max( count - 1, 0 ) ).fill( 12 ),
];

/** The shell's one-column layout starts here, and its gutters go from 32px to 24px. */
const NARROW = 782;

const gutterAt = ( width ) => ( width > NARROW ? 32 : 24 );

/**
 * Open a CoreX screen with the update check answered by the fixture.
 *
 * @param {import('@playwright/test').Page} page    The page.
 * @param {string}                          slug    The CoreX page slug.
 * @param {string}                          notices 'pending' or 'none'.
 */
async function openWith( page, slug, notices ) {
	await page.goto(
		`/wp-admin/admin.php?page=${ slug }&corex_e2e_core_notices=${ notices }`
	);
	await expect( page.locator( '.corex-admin__shell' ) ).toBeVisible();

	if ( notices !== 'pending' ) {
		return;
	}

	// Not skipped when the fixture is missing: without it an up-to-date install prints no nag,
	// and every measurement below would pass on a page that has nothing on it.
	await expect(
		page.locator( INFO ),
		`${ slug }: core-notices fixture missing — run scripts/setup-wordpress.ps1, or copy tests/e2e/fixtures/corex-e2e-core-notices.php into wp/wp-content/mu-plugins/`
	).toHaveCount( 1 );

	// WordPress's own script adds this button, in the pass that moves notices to its marker. Its
	// presence says the page is in the state a person sees, not the state the server sent.
	await page
		.locator( `${ DISMISSIBLE } .notice-dismiss` )
		.waitFor( { state: 'attached' } );
}

/**
 * Where the shell and the notices are, in CSS pixels.
 *
 * Inline distances are measured from the start and the end of the reading direction, so the same
 * numbers are expected left-to-right and right-to-left.
 *
 * @param {import('@playwright/test').Page} page A page showing a CoreX screen.
 * @return {Promise<Object>} The measurements.
 */
function measure( page ) {
	return page.evaluate( () => {
		const box = ( element ) => element.getBoundingClientRect();
		const rtl = document.documentElement.dir === 'rtl';
		const px = ( value ) => Math.round( parseFloat( value ) * 100 ) / 100;

		const shell = document.querySelector( '.corex-admin__shell' );
		const bar = document.querySelector( '#wpadminbar' );
		const main = document.querySelector( '.corex-admin__main' );
		const header = document.querySelector( '.corex-admin__header' );
		const region = document.querySelector( '.corex-admin__notices' );
		const content = document.querySelector( '.corex-admin__content' );
		// A page without the region is measured too, so that it fails on what is above the shell
		// and not on a missing element.
		const drawn = region
			? [ ...region.children ].filter(
					( child ) => box( child ).height > 0
			  )
			: [];
		const firstBlock = [ ...content.children ].find(
			( child ) => box( child ).height > 0
		);
		const last = drawn.at( -1 );

		return {
			belowBar: px( box( shell ).top - box( bar ).bottom ),
			// Anything WordPress printed that is still where WordPress printed it.
			aboveShell: document.querySelectorAll(
				'#wpbody-content > :is(.notice, .updated, .error, .update-nag)'
			).length,
			regionHeight: region ? px( box( region ).height ) : null,
			headerToContent: px( box( content ).top - box( header ).bottom ),
			contentPaddingTop: px(
				window.getComputedStyle( content ).paddingTop
			),
			headerToFirstBlock: firstBlock
				? px( box( firstBlock ).top - box( header ).bottom )
				: null,
			lastNoticeToContent: last
				? px( box( content ).top - box( last ).bottom )
				: null,
			lastNoticeToFirstBlock:
				last && firstBlock
					? px( box( firstBlock ).top - box( last ).bottom )
					: null,
			printed: drawn.map( ( notice, index ) => {
				const style = window.getComputedStyle( notice );
				const fromLeft = box( notice ).left - box( main ).left;
				const fromRight = box( main ).right - box( notice ).right;

				return {
					name: notice.id || notice.className,
					isNotice: notice.matches(
						'.notice, .updated, .error, .update-nag'
					),
					gapAbove: px(
						box( notice ).top -
							( index === 0
								? box( header ).bottom
								: box( drawn[ index - 1 ] ).bottom )
					),
					fromStart: px( rtl ? fromRight : fromLeft ),
					fromEnd: px( rtl ? fromLeft : fromRight ),
					startEdge: px(
						rtl ? style.borderRightWidth : style.borderLeftWidth
					),
					endEdge: px(
						rtl ? style.borderLeftWidth : style.borderRightWidth
					),
					background: style.backgroundColor,
					ink: style.color,
				};
			} ),
		};
	} );
}

test.describe( 'with a core update pending', () => {
	test( 'no route has anything above the shell, and every route shows the nag under its header', async ( {
		page,
	} ) => {
		for ( const [ slug ] of COREX_ROUTES ) {
			await openWith( page, slug, 'pending' );

			const layout = await measure( page );

			expect(
				layout.belowBar,
				`${ slug } shell offset below admin bar`
			).toBeLessThanOrEqual( 1 );
			expect(
				layout.aboveShell,
				`${ slug } notices left above the shell`
			).toBe( 0 );

			// Moved, not hidden: one nag, on screen, with the link an administrator updates by.
			const nag = page.locator( NAG );
			await expect( nag, `${ slug } update nag` ).toHaveCount( 1 );
			await expect( nag, `${ slug } update nag` ).toBeVisible();
			await expect( nag, `${ slug } update nag` ).toContainText(
				'WordPress 99.0'
			);
			await expect(
				nag.getByRole( 'link', {
					name: 'Please update WordPress now',
				} ),
				`${ slug } update link`
			).toHaveAttribute( 'href', /update-core\.php/ );

			// The notices the two hooks printed are with it, in the order they were printed.
			expect(
				fixtureNoticesIn( layout.printed ),
				`${ slug } notices in the region`
			).toEqual( FIXTURE_NOTICES );

			// Directly under the page header, and the content follows without a second gutter.
			expect(
				layout.printed.map( ( item ) => item.gapAbove ),
				`${ slug } space above each thing in the region`
			).toEqual( stackedUnder( 32, layout.printed.length ) );
			expect(
				layout.lastNoticeToContent,
				`${ slug } space between the notices and the content area`
			).toBeCloseTo( 0, 0 );
			expect(
				layout.contentPaddingTop,
				`${ slug } content padding under the notices`
			).toBeCloseTo( 24, 0 );
		}
	} );

	for ( const direction of [ 'ltr', 'rtl' ] ) {
		for ( const theme of [ 'dark', 'light' ] ) {
			test( `the notices keep the shell's spacing and colours in ${ theme } ${ direction }`, async ( {
				page,
			} ) => {
				for ( const width of [ 375, 768, 1024, 1440 ] ) {
					const where = `${ theme } ${ direction } ${ width }px`;
					const gutter = gutterAt( width );

					await page.setViewportSize( { width, height: 900 } );
					await openWith( page, 'corex-addons', 'pending' );
					await pinAppearance( page, theme, direction );

					const layout = await measure( page );

					expect(
						layout.belowBar,
						`shell offset below admin bar: ${ where }`
					).toBeLessThanOrEqual( 1 );
					expect(
						fixtureNoticesIn( layout.printed ),
						`notices: ${ where }`
					).toEqual( FIXTURE_NOTICES );

					// One gutter above, 12px between, and 24px to the first block: the distance
					// between any two blocks on a CoreX screen.
					expect(
						layout.printed.map( ( item ) => item.gapAbove ),
						`space above each thing in the region: ${ where }`
					).toEqual( stackedUnder( gutter, layout.printed.length ) );
					expect(
						layout.lastNoticeToFirstBlock,
						`space under the last notice: ${ where }`
					).toBeCloseTo( 24, 0 );

					for ( const notice of layout.printed ) {
						const which = `${ notice.name }: ${ where }`;

						// The same inline edges as the content under it.
						expect(
							notice.fromStart,
							`start edge of ${ which }`
						).toBe( gutter );
						expect( notice.fromEnd, `end edge of ${ which }` ).toBe(
							gutter
						);

						if ( ! notice.isNotice ) {
							continue;
						}

						// The tone is on the edge reading starts from, in both directions.
						expect(
							notice.startEdge,
							`tone edge of ${ which }`
						).toBe( 4 );
						expect( notice.endEdge, `far edge of ${ which }` ).toBe(
							1
						);
						expect(
							contrast( notice.ink, notice.background ),
							`text contrast of ${ which }`
						).toBeGreaterThanOrEqual( 4.5 );
					}

					// The shell's raised surface, not the white WordPress paints a notice with
					// in either appearance.
					const [ nagSurface, cardSurface ] = await page.evaluate(
						( selector ) => {
							const probe = document.createElement( 'div' );
							probe.style.background =
								'var(--corex-admin-surface-raised)';
							document
								.querySelector( '.corex-admin__main' )
								.append( probe );
							const surface =
								window.getComputedStyle(
									probe
								).backgroundColor;
							probe.remove();

							return [
								window.getComputedStyle(
									document.querySelector( selector )
								).backgroundColor,
								surface,
							];
						},
						NAG
					);
					expect( nagSurface, `nag surface: ${ where }` ).toBe(
						cardSurface
					);

					// The link is underlined and readable on that surface.
					const link = await page
						.locator( `${ NAG } a` )
						.first()
						.evaluate( ( anchor ) => ( {
							ink: window.getComputedStyle( anchor ).color,
							line: window.getComputedStyle( anchor )
								.textDecorationLine,
						} ) );
					expect( link.line, `nag link underline: ${ where }` ).toBe(
						'underline'
					);
					expect(
						contrast( link.ink, nagSurface ),
						`nag link contrast: ${ where }`
					).toBeGreaterThanOrEqual( 4.5 );

					// The dismiss button WordPress adds: the smallest allowed target, 12px inside
					// the far edge, level with the first line of text, and visible on the surface.
					const dismiss = await page
						.locator( DISMISSIBLE )
						.evaluate( ( notice ) => {
							const button =
								notice.querySelector( '.notice-dismiss' );
							const text = notice.querySelector( 'p' );
							const line = text.getBoundingClientRect();
							// The first line: at a phone's width the sentence takes two.
							const firstLine = parseFloat(
								window.getComputedStyle( text ).lineHeight
							);
							const at = button.getBoundingClientRect();
							const outer = notice.getBoundingClientRect();
							const rtl = document.documentElement.dir === 'rtl';
							const style = window.getComputedStyle( notice );

							return {
								width: at.width,
								height: at.height,
								insideEnd: rtl
									? at.left -
									  outer.left -
									  parseFloat( style.borderLeftWidth )
									: outer.right -
									  at.right -
									  parseFloat( style.borderRightWidth ),
								offLine:
									at.top +
									at.height / 2 -
									( line.top + firstLine / 2 ),
								// Between the text and the button, so one never runs under the other.
								clearOfText: rtl
									? line.left - at.right
									: at.left - line.right,
								glyph: window.getComputedStyle(
									button,
									'::before'
								).color,
							};
						} );
					expect(
						[ dismiss.width, dismiss.height ],
						`dismiss button size: ${ where }`
					).toEqual( [ 24, 24 ] );
					expect(
						dismiss.insideEnd,
						`dismiss button from the far edge: ${ where }`
					).toBeCloseTo( 12, 0 );
					expect(
						dismiss.offLine,
						`dismiss button against its first line of text: ${ where }`
					).toBeCloseTo( 0, 0 );
					expect(
						dismiss.clearOfText,
						`dismiss button clear of the text: ${ where }`
					).toBeGreaterThanOrEqual( 12 );
					expect(
						contrast( dismiss.glyph, nagSurface ),
						`dismiss glyph contrast: ${ where }`
					).toBeGreaterThanOrEqual( 3 );
				}
			} );
		}
	}

	test( 'the update link is the next stop after the page header, and shows where focus is', async ( {
		page,
	} ) => {
		await openWith( page, 'corex-addons', 'pending' );

		// From the last control in the page header, one Tab.
		await page
			.locator( '.corex-admin__header' )
			.locator( 'a, button' )
			.last()
			.focus();
		await page.keyboard.press( 'Tab' );

		const first = page.locator( `${ NAG } a` ).first();
		await expect( first ).toBeFocused();

		const ring = await first.evaluate( ( anchor ) => ( {
			style: window.getComputedStyle( anchor ).outlineStyle,
			width: window.getComputedStyle( anchor ).outlineWidth,
		} ) );
		expect( ring ).toEqual( { style: 'solid', width: '3px' } );
	} );

	test( 'dismissing a notice removes it and leaves the rest where they were', async ( {
		page,
	} ) => {
		await openWith( page, 'corex-addons', 'pending' );
		const before = await measure( page );

		await page.locator( `${ DISMISSIBLE } .notice-dismiss` ).click();
		// WordPress fades the notice and then takes it out of the page.
		await expect( page.locator( DISMISSIBLE ) ).toHaveCount( 0 );

		const layout = await measure( page );

		await expect( page.locator( NAG ) ).toBeVisible();
		expect( layout.printed ).toHaveLength( before.printed.length - 1 );
		expect( layout.printed.map( ( item ) => item.gapAbove ) ).toEqual(
			stackedUnder( 32, layout.printed.length )
		);
		expect( layout.lastNoticeToFirstBlock ).toBeCloseTo( 24, 0 );
	} );
} );

test.describe( 'with nothing for WordPress to say', () => {
	test( 'the notice region takes no space', async ( { page } ) => {
		await openWith( page, 'corex-addons', 'none' );
		await expect( page.locator( NAG ) ).toHaveCount( 0 );

		// The region with nothing in it is what is measured here. What another plugin of this
		// install printed on the hook is taken out of the page first, as dismissing it would.
		await page.evaluate( () =>
			document
				.querySelectorAll(
					'.corex-admin__notices > :not(.wp-header-end)'
				)
				.forEach( ( printed ) => printed.remove() )
		);

		const layout = await measure( page );

		// The page is laid out as it was before the region existed: the content area starts at
		// the page header and its first block is one gutter down.
		expect( layout.belowBar ).toBeLessThanOrEqual( 1 );
		expect( layout.regionHeight ).toBe( 0 );
		expect( layout.headerToContent ).toBe( 0 );
		expect( layout.headerToFirstBlock ).toBeCloseTo( 32, 0 );
	} );
} );
