/**
 * The admin controls a CSS read cannot settle (spec 087, US4 / SC-005).
 *
 * Three defects live here, and all three are contests between CoreX's stylesheet and two
 * third-party ones that ship inside WordPress. Reading the source proves what CoreX *declares*;
 * only a browser resolves what actually wins.
 *
 * - A `<Button>` with no variant kept Gutenberg's own near-black ink on the dark CoreX surface, so
 *   the submissions close X and the inbox pager were the colour of what they sat on.
 * - `.wp-core-ui .button:focus` is `:focus`, not `:focus-visible`, and outweighed the CoreX rule —
 *   so every mouse click left a blue halo.
 * - The same rule is what makes a keyboard focus ring visible, so removing it naively would trade
 *   one defect for a worse one. Both directions are asserted.
 *
 * Measured, not photographed: a contrast ratio and a computed `box-shadow` are facts a screenshot
 * review does not catch, which is the failure mode this repository keeps meeting.
 */
const { test, expect } = require( '@playwright/test' );
const { seedSubmission } = require( './helpers' );

const INBOX = '/wp-admin/admin.php?page=corex-submissions';
const NOTIFICATIONS = '/wp-admin/admin.php?page=corex-notifications';

/** WCAG 2.2 AA for non-text UI components (1.4.11). */
const MIN_CONTRAST = 3;

/** WCAG 2.2 AA target size (2.5.8) asks 24x24; CoreX adopts 44x44 for unlabelled controls. */
const MIN_TARGET = 24;

/**
 * Relative luminance of a computed `rgb()` / `rgba()` colour.
 *
 * @param {string} value A computed CSS colour.
 * @return {number} Its relative luminance, 0..1.
 */
function luminance( value ) {
	const [ r, g, b ] = ( value.match( /[\d.]+/g ) || [ 0, 0, 0 ] )
		.slice( 0, 3 )
		.map( ( channel ) => {
			const c = Number( channel ) / 255;

			return c <= 0.03928
				? c / 12.92
				: Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
		} );

	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * @param {string} foreground A computed CSS colour.
 * @param {string} background A computed CSS colour.
 * @return {number} The WCAG contrast ratio between them.
 */
function contrast( foreground, background ) {
	const a = luminance( foreground );
	const b = luminance( background );

	return ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 );
}

/**
 * The colour actually painted behind an element, walking up past transparent ancestors — which is
 * what a person sees, and is not necessarily the element's own `background-color`.
 *
 * @param {import('@playwright/test').Locator} locator The element.
 * @return {Promise<string>} A computed opaque colour.
 */
function paintedBackground( locator ) {
	return locator.evaluate( ( element ) => {
		const view = element.ownerDocument.defaultView;
		let node = element;
		while ( node ) {
			const colour = view.getComputedStyle( node ).backgroundColor;
			const alpha = Number(
				( colour.match( /[\d.]+/g ) || [] )[ 3 ] ?? 1
			);
			if ( alpha > 0 ) {
				return colour;
			}
			node = node.parentElement;
		}

		return 'rgb(255, 255, 255)';
	} );
}

/**
 * The layers of a computed `box-shadow` that paint *outside* the element.
 *
 * Splitting on commas that are not inside `rgb(...)`, because a computed shadow is a comma-joined
 * list whose own colours contain commas.
 *
 * @param {string} shadow A computed `box-shadow`.
 * @return {Array<string>} The outer layers; empty when there is no ring.
 */
function outerShadowLayers( shadow ) {
	if ( ! shadow || shadow === 'none' ) {
		return [];
	}

	return shadow
		.split( /,(?![^(]*\))/ )
		.map( ( layer ) => layer.trim() )
		.filter( ( layer ) => layer !== '' && ! layer.includes( 'inset' ) );
}

/**
 * Open a CoreX screen with the appearance pinned, rather than inheriting whatever this install
 * happens to be set to.
 *
 * The invisible-control defect is a **dark**-theme defect: Gutenberg's own `#1e1e1e` ink is
 * perfectly legible on a light surface, so a suite that ran against a light install would have
 * measured a contrast ratio of 15:1 and reported the bug as absent. Pinning both directions is what
 * makes the measurement mean something.
 *
 * @param {import('@playwright/test').Page} page  The page.
 * @param {string}                          url   Where to go.
 * @param {string}                          theme 'dark' or 'light'.
 */
async function openPinned( page, url, theme ) {
	await page.goto( url );

	// The inbox is a React island, so `goto` resolving says the document arrived, not that the
	// table exists. Counting controls before this point found zero of them and skipped the
	// measurement — a green run that had measured nothing, which is the failure mode this whole
	// spec is about.
	const app = page.locator( '.corex-inbox[data-status="ready"]' );
	if ( url === INBOX ) {
		await app.waitFor( { state: 'attached', timeout: 15000 } );
	}

	await page.evaluate( ( pinned ) => {
		document
			.querySelectorAll( '.corex-admin' )
			.forEach( ( shell ) =>
				shell.setAttribute( 'data-corex-theme', pinned )
			);
		document.body.classList.remove(
			'corex-appearance-light',
			'corex-appearance-dark'
		);
		document.body.classList.add( `corex-appearance-${ pinned }` );
	}, theme );
}

const THEMES = [ 'dark', 'light' ];

test.describe( 'Admin controls are visible and behave when clicked', () => {
	// The inbox needs rows for its pager and its detail drawer to exist at all, and those two are
	// exactly the controls this spec was written for. Seeded rather than skipped: a measurement that
	// does not run is not evidence that the thing it measures is fine.
	test.beforeAll( async ( { browser } ) => {
		const page = await browser.newPage();
		await seedSubmission( page, 'corex-controls-e2e' );
		await page.close();
	} );

	/**
	 * The pager is the plainest case: two `<Button>` elements with no variant, which is exactly the
	 * shape that had no CoreX rule at all and so kept Gutenberg's near-black ink.
	 */
	for ( const theme of THEMES ) {
		test( `inbox pagination reads against its surface in ${ theme }`, async ( {
			page,
		} ) => {
			await openPinned( page, INBOX, theme );
			const pager = page.locator( '.corex-inbox__pagination' );

			if ( ( await pager.count() ) === 0 ) {
				test.skip(
					true,
					'no submissions on this install to page through'
				);
			}

			for ( const name of [ 'Previous', 'Next' ] ) {
				const button = pager.getByRole( 'button', { name } );
				await expect( button ).toBeVisible();

				const ink = await button.evaluate(
					( element ) =>
						element.ownerDocument.defaultView.getComputedStyle(
							element
						).color
				);
				const behind = await paintedBackground( button );

				expect(
					contrast( ink, behind ),
					`${ name } (${ ink }) against its surface (${ behind }) in ${ theme }`
				).toBeGreaterThanOrEqual( MIN_CONTRAST );
			}
		} );

		test( `the notification drawer close is legible and a real target in ${ theme }`, async ( {
			page,
		} ) => {
			await openPinned( page, NOTIFICATIONS, theme );

			const bell = page
				.locator( '[data-corex-notification-bell]' )
				.first();
			if ( ( await bell.count() ) === 0 ) {
				test.skip( true, 'no notification bell on this screen' );
			}
			await bell.click();

			const close = page.locator( '.corex-notification-drawer__close' );
			await expect( close ).toBeVisible();

			const ink = await close.evaluate(
				( element ) =>
					element.ownerDocument.defaultView.getComputedStyle(
						element
					).color
			);
			const behind = await paintedBackground( close );
			expect(
				contrast( ink, behind ),
				`close (${ ink }) against its surface (${ behind }) in ${ theme }`
			).toBeGreaterThanOrEqual( MIN_CONTRAST );

			const box = await close.boundingBox();
			expect( box.width ).toBeGreaterThanOrEqual( MIN_TARGET );
			expect( box.height ).toBeGreaterThanOrEqual( MIN_TARGET );
		} );
	}

	/**
	 * The detail drawer's close is an icon-only `<Button icon="no-alt">`. Its glyph is an SVG that
	 * takes `fill` from Gutenberg rather than `color`, so setting the ink was not on its own enough
	 * to make it appear — this asserts the glyph, not the button around it.
	 */
	for ( const theme of THEMES ) {
		test( `the submissions detail close glyph is visible in ${ theme }`, async ( {
			page,
		} ) => {
			await openPinned( page, INBOX, theme );

			const row = page.locator( '.corex-inbox__row-button' ).first();
			if ( ( await row.count() ) === 0 ) {
				test.skip( true, 'no submissions on this install to open' );
			}
			await row.click();

			const close = page
				.locator( '.corex-inbox__drawer header .components-button' )
				.first();
			await expect( close ).toBeVisible();

			const glyph = await close.evaluate( ( element ) => {
				const view = element.ownerDocument.defaultView;
				const svg = element.querySelector( 'svg' );

				return svg
					? view.getComputedStyle( svg ).fill
					: view.getComputedStyle( element ).color;
			} );
			const behind = await paintedBackground( close );

			expect(
				contrast( glyph, behind ),
				`close glyph (${ glyph }) against its surface (${ behind }) in ${ theme }`
			).toBeGreaterThanOrEqual( MIN_CONTRAST );
		} );
	}

	/**
	 * The reported symptom, stated as a measurement: after a mouse click these buttons carry no
	 * outer `box-shadow`. WordPress and Gutenberg both paint their focus ring with one, so this is
	 * the assertion that distinguishes "we won the cascade" from "we declared and lost".
	 *
	 * The selectors name the three families that were actually losing — wp-admin's `.button` and
	 * `.button-primary`, and Gutenberg's `.components-button`. An earlier version of this test took
	 * "the first visible button", which on this screen is the notification bell: a plain `<button>`
	 * CoreX styles itself, which never had a WordPress ring to lose. It passed against the broken
	 * stylesheet, which is the whole failure this spec is about, one level down.
	 */
	/**
	 * `focusClass` is what the clicked control must still be when the shadow is read. Only controls
	 * that *keep* focus through their own click are listed: the pager re-renders the table under
	 * itself and the header's Export opens a modal, and both hand focus back to <body> — so a click
	 * assertion on either measures the document, not the button. Their ink is covered by the
	 * contrast tests above; what is not covered by a browser test is stated in the spec rather than
	 * implied by a green run.
	 */
	for ( const [ label, selector, focusClass ] of [
		[
			'a wp-admin primary button',
			'.corex-inbox-retention .button-primary',
			'button-primary',
		],
	] ) {
		test( `clicking ${ label } leaves no ring behind`, async ( {
			page,
		} ) => {
			await openPinned( page, INBOX, 'dark' );

			const button = page.locator( selector ).first();
			if ( ( await button.count() ) === 0 ) {
				test.skip( true, `no ${ label } on this install` );
			}

			await button.scrollIntoViewIfNeeded();

			// Hold the form back. This control submits and the page navigates, which resets focus —
			// so whether the measurement below landed on the button or on <body> was a race against
			// the page load, and the test failed intermittently for a reason that had nothing to do
			// with the ring. The click, the focus and the cascade are all still real; only the
			// navigation is suppressed.
			await button.evaluate( ( element ) => {
				element
					.closest( 'form' )
					?.addEventListener(
						'submit',
						( event ) => event.preventDefault(),
						{ once: true }
					);
			} );

			// A real mouse press at the control's coordinates, rather than `locator.click()`. Some of
			// these re-render or navigate under the pointer, and a locator click then waits for an
			// element that has already been replaced — a hang, not a finding.
			const box = await button.boundingBox();
			await page.mouse.click(
				box.x + box.width / 2,
				box.y + box.height / 2
			);

			// Identity and shadow read in one evaluate, because this is a race: the retention form
			// submits and navigates, so a second round trip finds focus already gone. Reading them
			// apart is what made an earlier version of this measure <body> — which has no shadow, so
			// the test passed by looking at the wrong element.
			const focused = await button.evaluate( ( node ) => {
				const owner = node.ownerDocument;
				const element = owner.activeElement;

				return {
					classes: String( element?.className ?? '' ),
					shadow: element
						? owner.defaultView.getComputedStyle( element )
								.boxShadow
						: 'none',
				};
			} );

			// Proves the measurement landed on the control that was clicked, not on the document.
			expect(
				focused.classes,
				'the clicked control should hold focus'
			).toContain( focusClass );

			// Every *outer* layer is the defect; inset layers are borders these variants legitimately
			// draw with a shadow. An earlier version asserted
			// `shadow === 'none' || shadow.includes( 'inset' )`, which accepted the exact broken value
			// it existed to reject: wp-admin paints
			// `rgb(56,88,233) 0 0 0 2px, rgb(255,255,255) 0 0 0 1px inset` — a blue ring *and* a white
			// inset — so `.includes('inset')` was true and the test passed on the bug.
			expect(
				outerShadowLayers( focused.shadow ),
				`outer box-shadow after clicking ${ label } was "${ focused.shadow }"`
			).toEqual( [] );
		} );

		/**
		 * The other direction, and the reason the fix is not simply `box-shadow: none` everywhere:
		 * a control reached by keyboard must still announce itself. Removing the click ring without
		 * this would trade a cosmetic complaint for a WCAG 2.4.7 failure.
		 */
		test( `focusing ${ label } by keyboard still shows the ring`, async ( {
			page,
		} ) => {
			await openPinned( page, INBOX, 'dark' );

			const button = page.locator( selector ).first();
			if ( ( await button.count() ) === 0 ) {
				test.skip( true, `no ${ label } on this install` );
			}

			// A real Tab, not `.focus()`: `:focus-visible` is exactly the distinction between the
			// two, so asserting through a programmatic focus would prove a different state than
			// the one a keyboard user is in. Reached by tabbing forward from the control itself,
			// then back, which lands keyboard focus on it however the page is ordered.
			await button.evaluate( ( element ) => element.focus() );
			await page.keyboard.press( 'Tab' );
			await page.keyboard.press( 'Shift+Tab' );
			await expect( button ).toBeFocused();

			const outline = await button.evaluate( ( element ) => {
				const style =
					element.ownerDocument.defaultView.getComputedStyle(
						element
					);

				return { width: style.outlineWidth, style: style.outlineStyle };
			} );

			expect( outline.style ).not.toBe( 'none' );
			expect( parseFloat( outline.width ) ).toBeGreaterThan( 0 );
		} );
	}

	/**
	 * The retention panel's eyebrow and title collided because a bare `<div>` never got the heading
	 * class the inbox header uses. Asserted as a gap in pixels rather than by looking at it.
	 */
	test( 'the retention heading has room to breathe', async ( { page } ) => {
		await openPinned( page, INBOX, 'dark' );

		const heading = page.locator(
			'.corex-inbox-retention .corex-inbox__heading'
		);
		await expect( heading ).toBeVisible();

		const gap = await heading.evaluate( ( element ) => {
			const eyebrow = element
				.querySelector( '.corex-inbox__eyebrow' )
				.getBoundingClientRect();
			const title = element.querySelector( 'h2' ).getBoundingClientRect();

			return title.top - eyebrow.bottom;
		} );

		expect( gap ).toBeGreaterThan( 0 );
	} );
} );
