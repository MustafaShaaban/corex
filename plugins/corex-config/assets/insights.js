/**
 * Corex Insights — the admin dashboard cards (spec 037). Vanilla JS over wp.apiFetch: it loads
 * the cached results on mount and renders one card per provider; "Run check" POSTs to the
 * cap+nonce-gated run endpoint and re-renders that card. No build step — plain DOM, accessible.
 *
 * @param {Object} wp   The global `window.wp` (apiFetch + i18n).
 * @param {Object} data The localized `window.corexInsights` config.
 */
( function ( wp, data ) {
	if ( ! window.Corex || ! window.Corex.api || ! data ) {
		return;
	}

	const api = window.Corex.api;
	const { restUrl, nonce, providers } = data;
	const root = document.getElementById( 'corex-insights-app' );
	if ( ! root ) {
		return;
	}

	const byProvider = {};
	// eslint-disable-next-line @wordpress/i18n-no-variables -- runtime translate helper; string literals are passed at every call site so extraction still works.
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
		d.textContent = s === null || s === undefined ? '' : String( s );
		return d.innerHTML;
	}

	function render( id, result, loading, error ) {
		const el = byProvider[ id ];
		const provider = providers.find( ( p ) => p.id === id ) || {
			id,
			label: id,
		};
		if ( ! el ) {
			return;
		}

		const score = result ? result.score : null;
		const grade = result ? result.grade : '—';
		const metrics = result && result.metrics ? result.metrics : [];
		const recs =
			result && result.recommendations ? result.recommendations : [];
		const checkedAt =
			result && result.checkedAt
				? new Date( result.checkedAt * 1000 ).toLocaleString()
				: t( 'Not run yet' );

		el.className =
			'corex-insight-card ' +
			( result ? statusClass( result.status ) : '' );
		el.innerHTML =
			'<header class="corex-insight-card__head">' +
			'<h2 id="corex-insight-' +
			escape( id ) +
			'">' +
			escape( provider.label ) +
			'</h2>' +
			'<div class="corex-insight-card__score" role="img" aria-label="' +
			( score === null
				? t( 'No score yet' )
				: escape(
						t( 'Score' ) + ' ' + score + ' / 100, grade ' + grade
				  ) ) +
			'"><span class="corex-insight-card__grade">' +
			escape( grade ) +
			'</span>' +
			'<span class="corex-insight-card__num">' +
			( score === null ? '—' : escape( score ) ) +
			'</span></div>' +
			'</header>' +
			( error
				? '<p class="corex-insight-card__error" role="alert">' +
				  escape( error ) +
				  '</p>'
				: '' ) +
			( result
				? '<p class="corex-insight-card__summary">' +
				  escape( result.summary ) +
				  '</p>'
				: '' ) +
			( metrics.length
				? '<ul class="corex-insight-card__metrics">' +
				  metrics.map( metricRow ).join( '' ) +
				  '</ul>'
				: '' ) +
			( recs.length
				? '<ul class="corex-insight-card__recs">' +
				  recs
						.map( ( r ) => '<li>' + escape( r ) + '</li>' )
						.join( '' ) +
				  '</ul>'
				: '' ) +
			'<footer class="corex-insight-card__foot">' +
			'<button type="button" class="button button-primary" ' +
			( loading ? 'disabled' : '' ) +
			'>' +
			( loading ? t( 'Running…' ) : t( 'Run check' ) ) +
			'</button>' +
			'<span class="corex-insight-card__time">' +
			escape( checkedAt ) +
			'</span>' +
			'</footer>';

		const button = el.querySelector( 'button' );
		if ( button ) {
			button.addEventListener( 'click', () => run( id ) );
		}
	}

	function run( id ) {
		render( id, lastResult( id ), true );
		// Corex.api always resolves (never throws). A failed run now surfaces the envelope
		// message inline (role=alert) instead of silently reverting to the last result.
		api.post( restUrl + '/run', { provider: id }, { nonce } ).then(
			( result ) => {
				if ( ! result.envelope.ok ) {
					render(
						id,
						lastResult( id ),
						false,
						result.envelope.message ||
							t( 'The check could not be completed. Try again.' )
					);
					return;
				}
				const payload = result.envelope.data;
				results[ id ] =
					payload && payload.result
						? payload.result
						: lastResult( id );
				render( id, results[ id ], false );
			}
		);
	}

	const results = {};
	const lastResult = ( id ) => results[ id ] || null;

	providers.forEach( card );

	api.get( restUrl, { nonce } ).then( ( result ) => {
		const payload = result.envelope.ok ? result.envelope.data : null;
		( payload && payload.results ? payload.results : [] ).forEach(
			( r ) => {
				results[ r.provider ] = r;
				render( r.provider, r, false );
			}
		);
	} );

	// The designed informational widget set (Cloudflare, Security events, SEO, Operations health,
	// Forms & Flows analytics) rendered from real gathered facts. The two runnable widgets
	// (Performance, Readiness) already render as run-cards above, so they are skipped here.
	const SECTION_URLS = {
		settings: 'admin.php?page=corex-settings-config',
		operations: 'admin.php?page=corex-operations-security',
		submissions: 'admin.php?page=corex-submissions',
	};

	function widgetRow( r ) {
		return (
			'<li class="corex-insight-widget__row is-' +
			escape( r.tone || 'subtle' ) +
			'">' +
			'<span>' +
			escape( r.label ) +
			'</span><strong>' +
			escape( r.value ) +
			'</strong></li>'
		);
	}

	function widgetEvent( e ) {
		return (
			'<li class="corex-insight-widget__event is-' +
			escape( e.tone || 'info' ) +
			'">' +
			'<span>' +
			escape( e.text ) +
			'</span><time>' +
			escape( e.meta ) +
			'</time></li>'
		);
	}

	function widgetAlt( alt ) {
		const href =
			SECTION_URLS[ alt.ctaHref ] || 'admin.php?page=corex-settings';
		return (
			'<div class="corex-insight-widget__alt">' +
			( alt.title
				? '<p class="corex-insight-widget__alt-title">' +
				  escape( alt.title ) +
				  '</p>'
				: '' ) +
			( alt.message ? '<p>' + escape( alt.message ) + '</p>' : '' ) +
			( alt.ctaLabel
				? '<a class="button" href="' +
				  escape( href ) +
				  '">' +
				  escape( alt.ctaLabel ) +
				  '</a>'
				: '' ) +
			'</div>'
		);
	}

	function renderWidget( widget ) {
		const el = document.createElement( 'section' );
		el.className =
			'corex-insight-widget is-' + escape( widget.state || 'empty' );
		el.innerHTML =
			'<header class="corex-insight-widget__head"><div>' +
			'<h2>' +
			escape( widget.title ) +
			'</h2>' +
			'<p class="corex-insight-widget__sub">' +
			escape( widget.sub ) +
			'</p></div>' +
			'<span class="corex-badge corex-badge--' +
			escape( widget.chipTone || 'subtle' ) +
			'">' +
			escape( widget.chip ) +
			'</span>' +
			'</header>' +
			( widget.note
				? '<p class="corex-insight-widget__note">' +
				  escape( widget.note ) +
				  '</p>'
				: '' ) +
			( widget.rows && widget.rows.length
				? '<ul class="corex-insight-widget__rows">' +
				  widget.rows.map( widgetRow ).join( '' ) +
				  '</ul>'
				: '' ) +
			( widget.events && widget.events.length
				? '<ul class="corex-insight-widget__events">' +
				  widget.events.map( widgetEvent ).join( '' ) +
				  '</ul>'
				: '' ) +
			( widget.alt ? widgetAlt( widget.alt ) : '' );
		widgetsRoot.appendChild( el );
	}

	const widgetsRoot = document.createElement( 'div' );
	widgetsRoot.className = 'corex-insights__widgets';
	root.appendChild( widgetsRoot );

	api.get( restUrl + '/widgets', { nonce } ).then( ( result ) => {
		const payload = result.envelope.ok ? result.envelope.data : null;
		( payload && payload.widgets ? payload.widgets : [] )
			.filter( ( w ) => ! w.mount )
			.forEach( renderWidget );
	} );
} )( window.wp, window.corexInsights );
