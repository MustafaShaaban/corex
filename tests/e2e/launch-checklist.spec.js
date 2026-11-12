/**
 * Corex E2E — one launch checklist and an honest Overview (spec 118, R3.2).
 *
 * The unit and integration suites hold each rule. This holds what a person reads: the Overview,
 * Operations & Security and the Setup Wizard draw the same checks in the same states, with the
 * counts agreeing; each state is a word beside an icon, readable in both themes and either
 * direction; the readiness card ends at its last row; nothing is wider than a phone; and the
 * Insights chips tell their states apart by more than colour.
 *
 * ENVIRONMENT-GATED: needs a running site at COREX_BASE_URL and `npx playwright install`.
 */
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

const OVERVIEW = '/wp-admin/admin.php?page=corex-settings';
const ENVIRONMENT =
	'/wp-admin/admin.php?page=corex-operations-security&tab=environment';
const HARDENING =
	'/wp-admin/admin.php?page=corex-operations-security&tab=hardening';

/**
 * Every check on the page, by key, with the chip it is drawn with.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @return {Promise<Object<string,string>>} key => chip.
 */
function checkStates( page ) {
	return page
		.locator( '.corex-checklist__row' )
		.evaluateAll( ( rows ) =>
			Object.fromEntries(
				rows.map( ( row ) => [
					row.dataset.check,
					[ ...row.classList ]
						.find( ( name ) => name.startsWith( 'is-' ) )
						.slice( 3 ),
				] )
			)
		);
}

test( 'the Overview and Operations show the same checks in the same states, and the same count', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );

	await page.goto( OVERVIEW );
	const overview = await checkStates( page );
	const count = (
		await page
			.locator( '.corex-overview__readiness .corex-overview__count' )
			.innerText()
	).trim();

	await page.goto( ENVIRONMENT );
	await expect(
		page.locator( '.corex-checklist__row' ).first()
	).toBeVisible();
	const operations = await checkStates( page );
	const ready = (
		await page.locator( '.corex-security__count' ).innerText()
	).trim();

	expect( Object.keys( overview ).length ).toBeGreaterThan( 6 );
	expect( operations ).toEqual( overview );
	expect( ready ).toBe( `${ count } ready` );
	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'each check says its state in words, and an open one links to its fix', async ( {
	page,
} ) => {
	await page.goto( HARDENING );

	const rows = page.locator( '.corex-checklist__row' );
	await expect( rows ).toHaveCount( 6 );
	for ( const row of await rows.all() ) {
		const chip = (
			await row.locator( '.corex-state-chip' ).innerText()
		).trim();
		expect( [ 'Ready', 'Blocks going live', 'Could not check' ] ).toContain(
			chip
		);
		// A ready row is not a link; one that is not ready is.
		await expect( row.locator( 'a' ) ).toHaveCount(
			chip === 'Ready' ? 0 : 1
		);
	}
	await expect( page.locator( '.corex-opsec__count' ) ).toHaveText(
		/^\d+ of 6 ready$/
	);
	await expect( page.getByRole( 'progressbar' ) ).toHaveAttribute(
		'aria-valuemax',
		'6'
	);
} );

test( 'the readiness card ends at its last row', async ( { page } ) => {
	await page.setViewportSize( { width: 1280, height: 900 } );
	await page.goto( OVERVIEW );

	const band = await page
		.locator( '.corex-overview__readiness' )
		.evaluate( ( card ) => {
			const rows = card.querySelectorAll( '.corex-checklist__row' );
			const last = rows[ rows.length - 1 ].getBoundingClientRect();
			const padding = parseFloat(
				window.getComputedStyle( card ).paddingBlockEnd
			);
			return card.getBoundingClientRect().bottom - last.bottom - padding;
		} );

	expect( band ).toBeLessThan( 2 );
} );

test( 'the Overview opens with four state tiles, each with a word and a link', async ( {
	page,
} ) => {
	await page.goto( OVERVIEW );

	const tiles = page.locator( '.corex-overview__tile' );
	await expect( tiles ).toHaveCount( 4 );
	await expect( tiles.locator( '.corex-overview__tile-label' ) ).toHaveText( [
		/Site health/,
		/Environment/,
		/Login protection/,
		/Add-ons/,
	] );
	for ( const tile of await tiles.all() ) {
		await expect(
			tile.locator( '.corex-overview__tile-value' )
		).not.toBeEmpty();
		await expect( tile.locator( 'a' ) ).toHaveCount( 1 );
	}
	await expect( page.locator( '.corex-overview' ) ).not.toContainText(
		'Published'
	);
} );

test( 'no screen of the checklist is wider than a phone', async ( {
	page,
} ) => {
	await page.setViewportSize( { width: 375, height: 812 } );

	for ( const address of [ OVERVIEW, ENVIRONMENT, HARDENING ] ) {
		await page.goto( address );
		const overflow = await page.evaluate(
			() =>
				document.documentElement.scrollWidth -
				document.documentElement.clientWidth
		);
		expect( overflow, address ).toBeLessThanOrEqual( 0 );
	}
} );

test( 'a chip sits at the end of its row in either direction', async ( {
	page,
} ) => {
	await page.goto( HARDENING );
	const row = page.locator( '.corex-checklist__row' ).first();
	const ends = async () => {
		const label = await row
			.locator( '.corex-checklist__label' )
			.boundingBox();
		const chip = await row.locator( '.corex-state-chip' ).boundingBox();
		return { label: label.x, chip: chip.x };
	};

	const ltr = await ends();
	await page.evaluate( () => ( document.documentElement.dir = 'rtl' ) );
	const rtl = await ends();

	expect( ltr.chip ).toBeGreaterThan( ltr.label );
	expect( rtl.chip ).toBeLessThan( rtl.label );
} );

for ( const colorScheme of [ 'light', 'dark' ] ) {
	test( `Insights chips differ by icon and word, and read at 4.5:1 (${ colorScheme })`, async ( {
		page,
	} ) => {
		await page.emulateMedia( { colorScheme } );
		await page.goto( '/wp-admin/admin.php?page=corex-insights' );
		await expect(
			page.locator( '.corex-insight-widget .corex-state-chip' ).first()
		).toBeVisible();

		const chips = await page
			.locator( '.corex-insight-widget .corex-state-chip' )
			.evaluateAll( ( elements ) => {
				const rgb = ( value ) =>
					value
						.match( /[\d.]+/g )
						.slice( 0, 3 )
						.map( Number );
				const luminance = ( [ r, g, b ] ) => {
					const channel = ( c ) => {
						const s = c / 255;
						return s <= 0.03928
							? s / 12.92
							: ( ( s + 0.055 ) / 1.055 ) ** 2.4;
					};
					return (
						0.2126 * channel( r ) +
						0.7152 * channel( g ) +
						0.0722 * channel( b )
					);
				};
				const contrast = ( a, b ) => {
					const [ hi, lo ] = [ luminance( a ), luminance( b ) ].sort(
						( x, y ) => y - x
					);
					return ( hi + 0.05 ) / ( lo + 0.05 );
				};
				return elements.map( ( chip ) => {
					const style = window.getComputedStyle( chip );
					const icon = window.getComputedStyle(
						chip.querySelector( '.corex-state-chip__icon' )
					);
					return {
						word: chip.textContent.trim(),
						icon: icon.maskImage || icon.webkitMaskImage,
						text: contrast(
							rgb( style.color ),
							rgb( style.backgroundColor )
						),
						edge: contrast(
							rgb( style.borderTopColor ),
							rgb( style.backgroundColor )
						),
					};
				} );
			} );

		const byWord = Object.fromEntries(
			chips.map( ( chip ) => [ chip.word, chip ] )
		);
		expect( Object.keys( byWord ).length ).toBeGreaterThan( 1 );
		expect(
			new Set( Object.values( byWord ).map( ( chip ) => chip.icon ) ).size
		).toBe( Object.keys( byWord ).length );
		for ( const chip of chips ) {
			expect( chip.text, chip.word ).toBeGreaterThanOrEqual( 4.5 );
			expect( chip.edge, chip.word ).toBeGreaterThanOrEqual( 3 );
		}
		await expect(
			page.getByRole( 'heading', { name: 'Insights', exact: true } )
		).toBeVisible();
	} );
}

test( 'the Setup Wizard ends on the same launch checklist as Operations', async ( {
	page,
} ) => {
	await page.goto( '/wp-admin/admin.php?page=corex-setup' );
	const next = page.locator( '#corex-setup-next' );
	for ( let step = 0; step < 7; step++ ) {
		await next.click();
	}

	await expect(
		page.locator( '#corex-setup-app .corex-checklist__row' ).first()
	).toBeVisible();
	const wizard = await checkStates( page );

	await page.goto( ENVIRONMENT );
	await expect(
		page.locator( '.corex-checklist__row' ).first()
	).toBeVisible();
	expect( await checkStates( page ) ).toEqual( wizard );
} );
