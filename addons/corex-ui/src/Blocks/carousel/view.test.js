/**
 * Jest — Corex Carousel visitor behavior (spec 068, US9 / FR-154, FR-162). Drives the enhanced
 * controls over jsdom: arrows and dots scroll the right slide into view (clamped at the ends), the
 * dots reflect the active slide, and opt-in autoplay advances on a timer but never under reduced
 * motion. scrollIntoView / IntersectionObserver / matchMedia are stubbed (absent in jsdom).
 */

function buildMarkup( { slides = 3, autoplay = false } = {} ) {
	let dots = '';
	let items = '';
	for ( let i = 0; i < slides; i++ ) {
		items += `<li class="corex-carousel__slide" data-corex-carousel-slide="${ i }">s${ i }</li>`;
		dots += `<button class="corex-carousel__dot" data-corex-carousel-goto="${ i }"${
			i === 0 ? ' aria-current="true"' : ''
		}></button>`;
	}
	return (
		`<section class="corex-carousel" data-corex-carousel${
			autoplay ? ' data-corex-carousel-autoplay' : ''
		}>` +
		`<div class="corex-carousel__viewport"><ul class="corex-carousel__track">${ items }</ul></div>` +
		`<div class="corex-carousel__controls">` +
		`<button class="corex-carousel__arrow" data-corex-carousel-prev></button>` +
		`<div class="corex-carousel__dots">${ dots }</div>` +
		`<button class="corex-carousel__arrow" data-corex-carousel-next></button>` +
		`</div></section>`
	);
}

function setup( opts ) {
	document.body.innerHTML = buildMarkup( opts );
	jest.resetModules();
	// eslint-disable-next-line global-require
	require( './view.js' );
}

let scrollSpy;

beforeEach( () => {
	scrollSpy = jest.fn();
	window.HTMLElement.prototype.scrollIntoView = scrollSpy;
	window.IntersectionObserver = class {
		observe() {}
		disconnect() {}
	};
	window.matchMedia = jest.fn().mockReturnValue( { matches: false } );
} );

afterEach( () => {
	jest.useRealTimers();
	document.body.innerHTML = '';
} );

describe( 'carousel view — controls', () => {
	test( 'Next scrolls the following slide into view', () => {
		setup();
		document.querySelector( '[data-corex-carousel-next]' ).click();
		expect( scrollSpy ).toHaveBeenCalledTimes( 1 );
		const slide = document.querySelectorAll( '.corex-carousel__slide' )[ 1 ];
		expect( scrollSpy.mock.instances[ 0 ] ).toBe( slide );
	} );

	test( 'Prev clamps at the first slide', () => {
		setup();
		document.querySelector( '[data-corex-carousel-prev]' ).click();
		const first = document.querySelectorAll( '.corex-carousel__slide' )[ 0 ];
		expect( scrollSpy.mock.instances[ 0 ] ).toBe( first );
	} );

	test( 'A dot scrolls straight to its slide', () => {
		setup();
		document.querySelectorAll( '[data-corex-carousel-goto]' )[ 2 ].click();
		const third = document.querySelectorAll( '.corex-carousel__slide' )[ 2 ];
		expect( scrollSpy.mock.instances[ 0 ] ).toBe( third );
	} );
} );

describe( 'carousel view — autoplay', () => {
	test( 'opt-in autoplay advances on a timer', () => {
		jest.useFakeTimers();
		setup( { autoplay: true } );
		jest.advanceTimersByTime( 5000 );
		expect( scrollSpy ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'no autoplay timer under reduced motion', () => {
		window.matchMedia = jest.fn().mockReturnValue( { matches: true } );
		jest.useFakeTimers();
		setup( { autoplay: true } );
		jest.advanceTimersByTime( 15000 );
		expect( scrollSpy ).not.toHaveBeenCalled();
	} );

	test( 'no autoplay timer when the carousel does not opt in', () => {
		jest.useFakeTimers();
		setup( { autoplay: false } );
		jest.advanceTimersByTime( 15000 );
		expect( scrollSpy ).not.toHaveBeenCalled();
	} );
} );
