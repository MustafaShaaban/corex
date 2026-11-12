/**
 * Reduced motion (spec 116, US-NFR-03 and US-NFR-07; Definition of Done items 7 and 10).
 *
 * Every admin screen and theme template is opened with reduced motion requested. From the first
 * frame, every CSS animation and transition that starts is recorded, and every script animation
 * still running at the end. Only a change of opacity may move. Anything else fails, with the
 * element and what it animated. A motion of 10ms or less is a motion switched off, and is not
 * counted.
 *
 * The other half of the rule, nothing repeating at rest without reduced motion, is measured by
 * the screen matrix on its first cell.
 */
const { test, expect } = require( '@playwright/test' );

const { installQualityHelpers } = require( './in-page' );
const { assertNoNewFindings } = require( './findings' );
const { site, DESKTOP } = require( './site' );

/** Runs in the page before its scripts: records what starts moving, and what it moves. */
function recordMotion() {
	window.__corexMotion = [];

	const seconds = ( value ) =>
		Math.max(
			...value
				.split( ',' )
				.map( ( part ) =>
					part.trim().endsWith( 'ms' )
						? parseFloat( part ) / 1000
						: parseFloat( part )
				)
		);

	const note = ( element, what, properties ) => {
		window.__corexMotion.push( { element, what, properties } );
	};

	document.addEventListener(
		'animationstart',
		( event ) => {
			const style = window.getComputedStyle( event.target );

			if ( seconds( style.animationDuration ) <= 0.01 ) {
				return;
			}

			const animation = event.target
				.getAnimations()
				.find( ( item ) => item.animationName === event.animationName );
			const keyframes = animation ? animation.effect.getKeyframes() : [];
			const properties = [
				...new Set(
					keyframes.flatMap( ( frame ) => Object.keys( frame ) )
				),
			].filter(
				( name ) =>
					! [
						'offset',
						'computedOffset',
						'easing',
						'composite',
					].includes( name )
			);

			note(
				event.target,
				`animation ${ event.animationName }`,
				properties
			);
		},
		true
	);

	document.addEventListener(
		'transitionstart',
		( event ) => {
			if (
				seconds(
					window.getComputedStyle( event.target ).transitionDuration
				) <= 0.01
			) {
				return;
			}

			note( event.target, `transition of ${ event.propertyName }`, [
				event.propertyName,
			] );
		},
		true
	);
}

async function motionFindings( page, id, path ) {
	await page.addInitScript( installQualityHelpers );
	await page.addInitScript( recordMotion );
	await page.emulateMedia( {
		reducedMotion: 'reduce',
		colorScheme: 'light',
	} );
	await page.setViewportSize( DESKTOP );
	await page.goto( path, { waitUntil: 'load' } );
	await page
		.waitForLoadState( 'networkidle', { timeout: 10_000 } )
		.catch( () => {} );

	const moved = await page.evaluate( () => {
		const scripted = document
			.getAnimations()
			.filter(
				( animation ) =>
					! ( animation instanceof window.CSSAnimation ) &&
					! ( animation instanceof window.CSSTransition )
			)
			.filter(
				( animation ) =>
					animation.playState === 'running' &&
					animation.effect?.target
			)
			.map( ( animation ) => ( {
				element: animation.effect.target,
				what: 'script animation',
				properties: [
					...new Set(
						animation.effect
							.getKeyframes()
							.flatMap( ( frame ) => Object.keys( frame ) )
					),
				].filter(
					( name ) =>
						! [
							'offset',
							'computedOffset',
							'easing',
							'composite',
						].includes( name )
				),
			} ) );

		return [ ...window.__corexMotion, ...scripted ]
			.filter( ( item ) =>
				item.properties.some( ( name ) => name !== 'opacity' )
			)
			.map( ( item ) => ( {
				signature: window.__corexQuality.signature( item.element ),
				what: `${ item.what } (${ item.properties.join( ', ' ) })`,
			} ) );
	} );

	return moved.map( ( item ) => ( {
		screen: id,
		cell: '1280-light-ltr-reduced-motion',
		check: 'motion',
		rule: 'moves-with-reduced-motion',
		signature: item.signature,
		detail: item.what,
	} ) );
}

test.describe( 'admin screens', () => {
	for ( const screen of site.screens ) {
		test( `${ screen.id }: nothing but opacity moves with reduced motion`, async ( {
			page,
		} ) => {
			assertNoNewFindings(
				expect,
				await motionFindings( page, screen.id, screen.path )
			);
		} );
	}
} );

test.describe( 'theme templates, as a visitor sees them', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	for ( const template of site.templates.filter( ( entry ) => entry.path ) ) {
		const id = `template:${ template.template }`;

		test( `${ id }: nothing but opacity moves with reduced motion`, async ( {
			page,
		} ) => {
			assertNoNewFindings(
				expect,
				await motionFindings( page, id, template.path )
			);
		} );
	}
} );
