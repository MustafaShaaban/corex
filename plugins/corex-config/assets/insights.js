/**
 * Corex Insights — the admin dashboard cards (spec 037). Vanilla JS over wp.apiFetch: it loads
 * the cached results on mount and renders one card per provider; "Run check" POSTs to the
 * cap+nonce-gated run endpoint and re-renders that card. No build step — plain DOM, accessible.
 */
( function ( wp, data ) {
	if ( ! wp || ! wp.apiFetch || ! data ) {
		return;
	}

	const apiFetch = wp.apiFetch;
	const { restUrl, nonce, providers } = data;
	const root = document.getElementById( 'corex-insights-app' );
	if ( ! root ) {
		return;
	}

	const byProvider = {};
	const t = ( s ) => ( wp.i18n ? wp.i18n.__( s, 'corex' ) : s );

	function statusClass( status ) {
		return 'is-' + ( status || 'recommended' );
	}

	function card( provider ) {
		const el = document.createElement( 'section' );
		el.className = 'corex-insight-card';
		el.setAttribute( 'aria-labelledby', 'corex-insight-' + provider.id );
		root.appendChild( el );
		byProvider[ provider.id ] = el;
		render( provider.id, null, false );
		return el;
	}

	function metricRow( m ) {
		return (
			'<li class="corex-insight-card__metric"><span>' +
			escape( m.label ) +
			'</span><strong>' +
			escape( m.value ) +
			( m.unit ? ' ' + escape( m.unit ) : '' ) +
			'</strong></li>'
		);
	}

	function escape( s ) {
		const d = document.createElement( 'div' );
		d.textContent = s == null ? '' : String( s );
		return d.innerHTML;
	}

	function render( id, result, loading ) {
		const el = byProvider[ id ];
		const provider = providers.find( ( p ) => p.id === id ) || { id: id, label: id };
		if ( ! el ) {
			return;
		}

		const score = result ? result.score : null;
		const grade = result ? result.grade : '—';
		const metrics = result && result.metrics ? result.metrics : [];
		const recs = result && result.recommendations ? result.recommendations : [];
		const checkedAt = result && result.checkedAt
			? new Date( result.checkedAt * 1000 ).toLocaleString()
			: t( 'Not run yet' );

		el.className = 'corex-insight-card ' + ( result ? statusClass( result.status ) : '' );
		el.innerHTML =
			'<header class="corex-insight-card__head">' +
			'<h2 id="corex-insight-' + escape( id ) + '">' + escape( provider.label ) + '</h2>' +
			'<div class="corex-insight-card__score" role="img" aria-label="' +
			( score === null ? t( 'No score yet' ) : escape( t( 'Score' ) + ' ' + score + ' / 100, grade ' + grade ) ) +
			'"><span class="corex-insight-card__grade">' + escape( grade ) + '</span>' +
			'<span class="corex-insight-card__num">' + ( score === null ? '—' : escape( score ) ) + '</span></div>' +
			'</header>' +
			( result ? '<p class="corex-insight-card__summary">' + escape( result.summary ) + '</p>' : '' ) +
			( metrics.length ? '<ul class="corex-insight-card__metrics">' + metrics.map( metricRow ).join( '' ) + '</ul>' : '' ) +
			( recs.length
				? '<ul class="corex-insight-card__recs">' + recs.map( ( r ) => '<li>' + escape( r ) + '</li>' ).join( '' ) + '</ul>'
				: '' ) +
			'<footer class="corex-insight-card__foot">' +
			'<button type="button" class="button button-primary" ' + ( loading ? 'disabled' : '' ) + '>' +
			( loading ? t( 'Running…' ) : t( 'Run check' ) ) +
			'</button>' +
			'<span class="corex-insight-card__time">' + escape( checkedAt ) + '</span>' +
			'</footer>';

		const button = el.querySelector( 'button' );
		if ( button ) {
			button.addEventListener( 'click', () => run( id ) );
		}
	}

	function run( id ) {
		render( id, lastResult( id ), true );
		apiFetch( {
			url: restUrl + '/run',
			method: 'POST',
			headers: { 'X-WP-Nonce': nonce },
			data: { provider: id },
		} )
			.then( ( res ) => {
				results[ id ] = res && res.result ? res.result : null;
				render( id, results[ id ], false );
			} )
			.catch( () => {
				render( id, lastResult( id ), false );
			} );
	}

	const results = {};
	const lastResult = ( id ) => results[ id ] || null;

	providers.forEach( card );

	apiFetch( { url: restUrl, headers: { 'X-WP-Nonce': nonce } } )
		.then( ( res ) => {
			( res && res.results ? res.results : [] ).forEach( ( r ) => {
				results[ r.provider ] = r;
				render( r.provider, r, false );
			} );
		} )
		.catch( () => {} );
} )( window.wp, window.corexInsights );
