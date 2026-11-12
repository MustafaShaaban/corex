/**
 * The form a visitor fills in (spec 116, US-NFR-03; Definition of Done item 7).
 *
 * On the Contact page the Company kit makes: every field has a programmatic label, and a submit
 * with fields in error moves focus to an error summary whose links each lead to a field in error.
 */
const { test, expect } = require( '@playwright/test' );

const { installQualityHelpers } = require( './in-page' );
const { assertNoNewFindings } = require( './findings' );

const CONTACT = '/contact/';

test.use( { storageState: { cookies: [], origins: [] } } );

test( 'the contact form labels each field and leads from an error summary to each field in error', async ( {
	page,
} ) => {
	await page.addInitScript( installQualityHelpers );
	await page.goto( CONTACT, { waitUntil: 'load' } );

	const form = page
		.locator( 'form.corex-form, .corex-form form, form[class*="corex"]' )
		.first();
	await expect( form, `no CoreX form on ${ CONTACT }` ).toBeVisible();

	const finding = ( rule, signature, detail ) => ( {
		screen: 'page:/contact/',
		cell: '1280-light-ltr',
		check: 'forms',
		rule,
		signature,
		detail,
	} );
	const findings = [];

	const unlabelled = await form.evaluate( ( element ) =>
		[
			...element.querySelectorAll(
				'input:not([type="hidden"]):not([type="submit"]):not([tabindex="-1"]), select, textarea'
			),
		]
			.filter( ( field ) => window.__corexQuality.isRendered( field ) )
			.filter(
				( field ) =>
					field.labels.length === 0 &&
					! field.getAttribute( 'aria-label' ) &&
					! field.getAttribute( 'aria-labelledby' )
			)
			.map(
				( field ) =>
					window.__corexQuality.signature( field ) +
					`[name=${ field.name }]`
			)
	);
	unlabelled.forEach( ( signature ) =>
		findings.push(
			finding(
				'field-without-label',
				signature,
				`${ signature } has no label`
			)
		)
	);

	await form.locator( '[type="submit"]' ).first().click();
	await page.waitForTimeout( 1000 );

	const summary = await page.evaluate( () => {
		const focused = document.body.ownerDocument.activeElement;
		const links = focused
			? [ ...focused.querySelectorAll( 'a[href^="#"]' ) ]
			: [];
		const invalid = [
			...document.querySelectorAll( '[aria-invalid="true"]' ),
		]
			.map( ( field ) => field.id )
			.filter( Boolean );

		return {
			focused: focused
				? window.__corexQuality.signature( focused )
				: 'nothing',
			links: links.map( ( link ) =>
				link.getAttribute( 'href' ).slice( 1 )
			),
			invalid,
		};
	} );

	if ( summary.links.length === 0 ) {
		findings.push(
			finding(
				'no-error-summary',
				'form.corex-form',
				`after a submit with errors, focus is on ${ summary.focused }, not on a summary of links to the fields`
			)
		);
	} else {
		summary.invalid
			.filter( ( id ) => ! summary.links.includes( id ) )
			.forEach( ( id ) =>
				findings.push(
					finding(
						'error-not-in-summary',
						`#${ id }`,
						`the field ${ id } is in error and the summary does not link to it`
					)
				)
			);
	}

	assertNoNewFindings( expect, findings );
} );
