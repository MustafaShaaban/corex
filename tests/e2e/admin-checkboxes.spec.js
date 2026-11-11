/**
 * Corex E2E: checkboxes and radios on a CoreX screen are drawn in CoreX's colours.
 *
 * wp-admin gives both inputs `appearance: none` and paints them itself, checked in the admin
 * theme's blue (rgb(56, 88, 233) on WordPress 7.0) with a white SVG tick, so a CoreX
 * `accent-color` changed nothing. The shell now draws them (DECISIONS #515). What is measured
 * here is the drawn result, which only a browser with wp-admin's stylesheets and the shell's
 * together can answer.
 *
 * The checkboxes are the Notifications screen's own Preferences toggles, where a mandatory
 * category is checked and disabled. No CoreX screen shows a radio on a fresh site, so two radios
 * are placed in that same panel by the test, under the same stylesheets.
 *
 * ENVIRONMENT-GATED: needs a site (COREX_BASE_URL), a built corex-config and
 * `npx playwright install`. Uses the saved admin session.
 */
const { test, expect } = require( '@playwright/test' );

test.use( { storageState: require( './global-setup' ).STORAGE_STATE } );

const NOTIFICATIONS = '/wp-admin/admin.php?page=corex-notifications';
const WORDPRESS_BLUE = 'rgb(56, 88, 233)';

async function openPreferences( page, theme, direction ) {
	await page.goto( NOTIFICATIONS );
	await page
		.locator( '.corex-notifications-screen__view', {
			hasText: 'Preferences',
		} )
		.click();
	const prefs = page.locator( '.corex-notifications-prefs' );
	await expect(
		prefs.locator( 'input[type="checkbox"]' ).first()
	).toBeVisible();

	await page.evaluate(
		( [ appearance, dir ] ) => {
			document.documentElement.setAttribute( 'dir', dir );
			document
				.querySelector( '.corex-admin' )
				.setAttribute( 'data-corex-theme', appearance );

			const radios = document.createElement( 'fieldset' );
			radios.className = 'corex-e2e-radios';
			radios.innerHTML =
				'<label><input type="radio" name="corex-e2e" value="a" checked> A</label>' +
				'<label><input type="radio" name="corex-e2e" value="b"> B</label>';
			document
				.querySelector( '.corex-notifications-prefs' )
				.append( radios );
		},
		[ theme, direction ]
	);

	// A colour change runs a short transition; measure where it ends.
	await page.evaluate( () =>
		Promise.all(
			document.getAnimations().map( ( animation ) => animation.finished )
		)
	);

	return prefs;
}

/**
 * How an input is drawn, with the shell tokens it should match resolved to rgb.
 *
 * @param {import('@playwright/test').Locator} input The input.
 * @return {Promise<Object>} The drawn colours and the token colours.
 */
const drawn = ( input ) =>
	input.evaluate( async ( element ) => {
		// A state change runs a short colour transition; read where it ends.
		await Promise.all(
			element.getAnimations().map( ( animation ) => animation.finished )
		);
		const view = element.ownerDocument.defaultView;
		const shell = element.closest( '.corex-admin' );
		const token = ( name ) => {
			const probe = element.ownerDocument.createElement( 'span' );
			probe.style.color = `var(${ name })`;
			shell.append( probe );
			const value = view.getComputedStyle( probe ).color;
			probe.remove();
			return value;
		};
		const box = view.getComputedStyle( element );
		const mark = view.getComputedStyle( element, '::before' );

		return {
			background: box.backgroundColor,
			border: box.borderTopColor,
			outline: `${ box.outlineStyle } ${ box.outlineColor }`,
			markContent: mark.content,
			markBackground: mark.backgroundColor,
			markShape: mark.clipPath,
			markWidth: parseFloat( mark.width ),
			width: parseFloat( box.width ),
			action: token( '--corex-admin-action' ),
			ink: token( '--corex-admin-action-ink' ),
			edge: token( '--corex-admin-text-muted' ),
			surface: token( '--corex-admin-surface' ),
			focus: token( '--corex-admin-focus' ),
		};
	} );

// WCAG relative luminance contrast of two `rgb(...)` strings.
function contrast( first, second ) {
	const luminance = ( rgb ) => {
		const [ r, g, b ] = rgb
			.match( /\d+(\.\d+)?/g )
			.slice( 0, 3 )
			.map( ( channel ) => {
				const value = Number( channel ) / 255;
				return value <= 0.03928
					? value / 12.92
					: ( ( value + 0.055 ) / 1.055 ) ** 2.4;
			} );
		return 0.2126 * r + 0.7152 * g + 0.0722 * b;
	};
	const [ light, dark ] = [ luminance( first ), luminance( second ) ].sort(
		( a, b ) => b - a
	);
	return ( light + 0.05 ) / ( dark + 0.05 );
}

for ( const theme of [ 'dark', 'light' ] ) {
	for ( const direction of [ 'ltr', 'rtl' ] ) {
		test( `a checked box is brass with an ink tick, not WordPress blue (${ theme }, ${ direction })`, async ( {
			page,
		} ) => {
			const prefs = await openPreferences( page, theme, direction );
			const checked = prefs
				.locator( 'input[type="checkbox"]:checked:not(:disabled)' )
				.first();
			await expect( checked ).toBeVisible();

			const look = await drawn( checked );
			expect( look.background ).not.toBe( WORDPRESS_BLUE );
			expect( look.background ).toBe( look.action );
			expect( look.border ).toBe( look.action );
			// The tick is a shape filled with the ink colour, not wp-admin's SVG image.
			expect( look.markContent ).toBe( '""' );
			expect( look.markBackground ).toBe( look.ink );
			expect( look.markShape ).toMatch( /^polygon\(/ );
			expect(
				contrast( look.markBackground, look.background )
			).toBeGreaterThanOrEqual( 3 );
		} );
	}

	test( `an unchecked box has an edge a person can see, and no mark (${ theme })`, async ( {
		page,
	} ) => {
		const prefs = await openPreferences( page, theme, 'ltr' );
		const box = prefs
			.locator( 'input[type="checkbox"]:not(:disabled)' )
			.first();
		await box.evaluate( ( element ) => {
			element.checked = false;
		} );

		const look = await drawn( box );
		expect( look.background ).toBe( look.surface );
		expect( look.border ).toBe( look.edge );
		// WCAG 1.4.11: the edge is all that says a box is there.
		expect(
			contrast( look.border, look.background )
		).toBeGreaterThanOrEqual( 3 );
		expect( look.markContent ).toBe( 'none' );
	} );

	test( `an indeterminate box shows the dash in brass (${ theme })`, async ( {
		page,
	} ) => {
		const prefs = await openPreferences( page, theme, 'ltr' );
		const box = prefs
			.locator( 'input[type="checkbox"]:not(:disabled)' )
			.first();
		await box.evaluate( ( element ) => {
			element.indeterminate = true;
		} );

		const look = await drawn( box );
		expect( look.background ).toBe( look.action );
		expect( look.markBackground ).toBe( look.ink );
		expect( look.markShape ).toBe(
			'polygon(22% 43%, 78% 43%, 78% 57%, 22% 57%)'
		);
	} );

	test( `a disabled checked box keeps its colours and is dimmed (${ theme })`, async ( {
		page,
	} ) => {
		const prefs = await openPreferences( page, theme, 'ltr' );
		const box = prefs
			.locator( 'input[type="checkbox"]:checked:disabled' )
			.first();
		await expect( box ).toBeVisible();

		const look = await drawn( box );
		expect( look.background ).toBe( look.action );
		expect( look.markBackground ).toBe( look.ink );
		expect(
			Number(
				await box.evaluate(
					( element ) =>
						element.ownerDocument.defaultView.getComputedStyle(
							element
						).opacity
				)
			)
		).toBeLessThan( 1 );
	} );

	test( `a checked radio is brass with an ink dot (${ theme })`, async ( {
		page,
	} ) => {
		await openPreferences( page, theme, 'ltr' );
		const radio = page.locator( '.corex-e2e-radios input[value="a"]' );

		const look = await drawn( radio );
		expect( look.background ).toBe( look.action );
		expect( look.markBackground ).toBe( look.ink );
		// The dot sits inside the ring, smaller than the box.
		expect( look.markWidth ).toBeGreaterThan( 0 );
		expect( look.markWidth ).toBeLessThan( look.width );

		// Arrow keys still move the choice: the input is the browser's own.
		await radio.focus();
		await page.keyboard.press( 'ArrowDown' );
		await expect(
			page.locator( '.corex-e2e-radios input[value="b"]' )
		).toBeChecked();
		expect( ( await drawn( radio ) ).background ).toBe( look.surface );
	} );

	test( `the keyboard focus ring is CoreX's, and a click leaves no blue ring (${ theme })`, async ( {
		page,
	} ) => {
		const prefs = await openPreferences( page, theme, 'ltr' );
		const box = prefs
			.locator( 'input[type="checkbox"]:not(:disabled)' )
			.first();

		// A click focuses without a ring. The click is cancelled so that it does not change, and
		// save, the site's notification preference.
		await box.evaluate( ( element ) => {
			element.addEventListener( 'click', ( event ) =>
				event.preventDefault()
			);
		} );
		await box.click();
		const clicked = await box.evaluate( ( element ) => ( {
			shadow: element.ownerDocument.defaultView.getComputedStyle(
				element
			).boxShadow,
			visible: element.matches( ':focus-visible' ),
		} ) );
		expect( clicked.visible ).toBe( false );
		expect( clicked.shadow ).toBe( 'none' );

		// The keyboard gets the CoreX ring.
		await page.keyboard.press( 'Shift+Tab' );
		await page.keyboard.press( 'Tab' );
		await expect( box ).toBeFocused();
		const look = await drawn( box );
		expect( look.outline ).toBe( `solid ${ look.focus }` );
	} );
}
