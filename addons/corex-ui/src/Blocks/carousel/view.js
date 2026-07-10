/**
 * corex/carousel front-end behavior. Progressive enhancement over a native scroll-snap row:
 * without JS the viewport is already swipeable and keyboard-scrollable and every slide is in the
 * DOM. This wires the prev/next arrows and dot buttons (direction-agnostic via scrollIntoView, so
 * RTL is correct for free), tracks the active slide to update the dots, and runs opt-in autoplay
 * that pauses on hover / focus / tab-blur and never runs under reduced motion. Loaded only where
 * the block renders (viewScript).
 */
( function () {
	const reduceMotion = window.matchMedia
		? window.matchMedia( '(prefers-reduced-motion: reduce)' )
		: { matches: false };

	function wire( root ) {
		const viewport = root.querySelector( '.corex-carousel__viewport' );
		const slides = root.querySelectorAll( '.corex-carousel__slide' );
		if ( ! viewport || ! slides.length ) {
			return;
		}

		const dots = root.querySelectorAll( '.corex-carousel__dot' );
		let current = 0;

		function behavior() {
			return reduceMotion.matches ? 'auto' : 'smooth';
		}

		function go( index ) {
			current = Math.max( 0, Math.min( slides.length - 1, index ) );
			slides[ current ].scrollIntoView( {
				behavior: behavior(),
				block: 'nearest',
				inline: 'start',
			} );
		}

		root.querySelectorAll( '[data-corex-carousel-prev]' ).forEach(
			function ( btn ) {
				btn.addEventListener( 'click', function () {
					pause();
					go( current - 1 );
				} );
			}
		);
		root.querySelectorAll( '[data-corex-carousel-next]' ).forEach(
			function ( btn ) {
				btn.addEventListener( 'click', function () {
					pause();
					go( current + 1 );
				} );
			}
		);
		dots.forEach( function ( dot ) {
			dot.addEventListener( 'click', function () {
				pause();
				go(
					parseInt(
						dot.getAttribute( 'data-corex-carousel-goto' ),
						10
					) || 0
				);
			} );
		} );

		// Reflect the most-visible slide on the dots.
		if ( 'IntersectionObserver' in window ) {
			const observer = new window.IntersectionObserver(
				function ( entries ) {
					entries.forEach( function ( entry ) {
						if ( ! entry.isIntersecting ) {
							return;
						}
						const index = parseInt(
							entry.target.getAttribute(
								'data-corex-carousel-slide'
							),
							10
						);
						if ( isNaN( index ) ) {
							return;
						}
						current = index;
						dots.forEach( function ( dot, i ) {
							if ( i === index ) {
								dot.setAttribute( 'aria-current', 'true' );
							} else {
								dot.removeAttribute( 'aria-current' );
							}
						} );
					} );
				},
				{ root: viewport, threshold: 0.6 }
			);
			slides.forEach( function ( slide ) {
				observer.observe( slide );
			} );
		}

		// Opt-in autoplay — never under reduced motion; pauses on interaction and tab-blur.
		let timer = null;
		const autoplay =
			root.hasAttribute( 'data-corex-carousel-autoplay' ) &&
			! reduceMotion.matches;

		function tick() {
			go( current >= slides.length - 1 ? 0 : current + 1 );
		}
		function start() {
			if ( autoplay && ! timer ) {
				timer = window.setInterval( tick, 5000 );
			}
		}
		function pause() {
			if ( timer ) {
				window.clearInterval( timer );
				timer = null;
			}
		}

		if ( autoplay ) {
			root.addEventListener( 'mouseenter', pause );
			root.addEventListener( 'mouseleave', start );
			root.addEventListener( 'focusin', pause );
			root.addEventListener( 'focusout', start );
			document.addEventListener( 'visibilitychange', function () {
				if ( document.hidden ) {
					pause();
				} else {
					start();
				}
			} );
			start();
		}
	}

	function init() {
		document
			.querySelectorAll( '[data-corex-carousel]' )
			.forEach( function ( root ) {
				wire( root );
			} );
	}

	if ( document.readyState !== 'loading' ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
} )();
