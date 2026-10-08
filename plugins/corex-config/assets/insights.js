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

	/*
	 * The admin's loading states (spec 108), written as markup: this screen is not React, and
	 * the class names are the contract. A bar where a line of text will be, inside a wrapper
	 * that is hidden from assistive technology; the screen's state on its root, as on every
	 * other surface; a load that lasts a second announced at its start and its end.
	 */
	function bar( width ) {
		return (
			'<span class="corex-admin-skeleton__bar corex-admin-skeleton__bar--' +
			width +
			'"></span>'
		);
	}

	function placeholder( inner ) {
		return (
			'<span class="corex-admin-skeleton" aria-hidden="true">' +
			inner +
			'</span>'
		);
	}

	// Which of the screen's two requests have been answered: the last results, the widgets.
	const answered = { results: false, widgets: false };

	const announcement = document.createElement( 'p' );
	announcement.className = 'screen-reader-text';
	announcement.setAttribute( 'role', 'status' );
	root.before( announcement );
	let saidLoading = false;
	const sayLoading = setTimeout( () => {
		saidLoading = true;
		announcement.textContent = t( 'Loading insights…' );
	}, 1000 );

	function settle() {
		const done = answered.results && answered.widgets;
		root.setAttribute( 'data-corex-state', done ? 'ready' : 'loading' );
		if ( ! done ) {
			root.setAttribute( 'aria-busy', 'true' );
			return;
		}
		root.removeAttribute( 'aria-busy' );
		clearTimeout( sayLoading );
		if ( saidLoading ) {
			saidLoading = false;
			announcement.textContent = t( 'Loaded.' );
		}
	}

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

		// Until the last results have arrived a card does not know whether it has ever been
		// run. It drew "Not run yet" and a dash at once, which read as a result.
		const waiting = ! answered.results;
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
			scoreLabel( waiting, score, grade ) +
			'"><span class="corex-insight-card__grade">' +
			( waiting ? placeholder( bar( 'full' ) ) : escape( grade ) ) +
			'</span>' +
			'<span class="corex-insight-card__num">' +
			( waiting ? placeholder( bar( 'full' ) ) : scoreText( score ) ) +
			'</span></div>' +
			'</header>' +
			( error
				? '<p class="corex-insight-card__error" role="alert">' +
					escape( error ) +
					'</p>'
				: '' ) +
			( waiting ? waitingBody() : '' ) +
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
			// The admin's one working state (spec 108): the label stays, so the button keeps
			// its width, and the styles draw the loader over it from these attributes.
			runAttributes( loading, waiting ) +
			'>' +
			t( 'Run check' ) +
			'</button>' +
			'<span class="corex-insight-card__time">' +
			( waiting ? placeholder( bar( 'full' ) ) : escape( checkedAt ) ) +
			'</span>' +
			'</footer>';

		const button = el.querySelector( 'button' );
		if ( button ) {
			button.addEventListener( 'click', () => run( id ) );
		}
	}

	/**
	 * The body of a card whose last result has not arrived: a line where its summary will
	 * be, and three where its measures will. A card that has been run has both; one that has
	 * not is shorter than this, and one with a long list of recommendations is longer. A
	 * card with only a heading and a held button did not read as loading at all.
	 *
	 * @return {string} The placeholder's markup.
	 */
	function waitingBody() {
		return (
			'<div class="corex-admin-skeleton" aria-hidden="true">' +
			'<p class="corex-insight-card__summary">' +
			bar( 'long' ) +
			'</p>' +
			'<ul class="corex-insight-card__metrics">' +
			[ 'medium', 'long', 'medium' ]
				.map(
					( width ) =>
						'<li class="corex-insight-card__metric">' +
						bar( width ) +
						'</li>'
				)
				.join( '' ) +
			'</ul></div>'
		);
	}

	function scoreLabel( waiting, score, grade ) {
		if ( waiting ) {
			return t( 'Loading the last result' );
		}
		return score === null
			? t( 'No score yet' )
			: escape( t( 'Score' ) + ' ' + score + ' / 100, grade ' + grade );
	}

	function scoreText( score ) {
		return score === null ? '—' : escape( score );
	}

	/**
	 * What the card's button is: working while its check runs, held until the last results
	 * are in (a check run before them would be overwritten by them), and otherwise itself.
	 *
	 * @param {boolean} loading Whether this card's check is running.
	 * @param {boolean} waiting Whether the last results have not arrived.
	 * @return {string} The button's attributes.
	 */
	function runAttributes( loading, waiting ) {
		if ( loading ) {
			return 'disabled aria-busy="true" data-corex-working="true"';
		}
		return waiting ? 'disabled' : '';
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
		answered.results = true;
		settle();

		// It failed in silence, and every card went on saying "Not run yet" about checks
		// that may have been run an hour before.
		if ( ! result.envelope.ok ) {
			const failure =
				result.envelope.message ||
				t(
					'The last results could not be loaded. Run a check to get new ones.'
				);
			providers.forEach( ( p ) => render( p.id, null, false, failure ) );
			return;
		}

		( result.envelope.data.results || [] ).forEach( ( r ) => {
			results[ r.provider ] = r;
		} );
		providers.forEach( ( p ) => render( p.id, lastResult( p.id ), false ) );
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

		// Straight into the screen grid, alongside the provider cards. This used to append into
		// a nested `.corex-insights__widgets` container, which the outer grid then treated as a
		// single cell -- so all five widgets collapsed into one narrow column beside the cards.
		root.appendChild( el );
	}

	const WIDGET_PLACEHOLDERS = 3;

	/**
	 * A widget that has not arrived: its heading, its line, three rows. Drawn in the widget's
	 * own markup, so it is the widget's size in the grid.
	 *
	 * @return {HTMLElement} The placeholder.
	 */
	function widgetPlaceholder() {
		const el = document.createElement( 'section' );
		el.className = 'corex-insight-widget corex-admin-skeleton';
		el.setAttribute( 'aria-hidden', 'true' );
		el.innerHTML =
			'<header class="corex-insight-widget__head"><div>' +
			'<h2>' +
			bar( 'medium' ) +
			'</h2>' +
			'<p class="corex-insight-widget__sub">' +
			bar( 'long' ) +
			'</p></div></header>' +
			'<ul class="corex-insight-widget__rows">' +
			[ 'long', 'medium', 'long' ]
				.map(
					( width ) =>
						'<li class="corex-insight-widget__row">' +
						bar( width ) +
						'</li>'
				)
				.join( '' ) +
			'</ul>';
		root.appendChild( el );
		return el;
	}

	/**
	 * The widgets could not be loaded: said where they would have been, with a way to ask
	 * again. The shared error state's markup, for a screen that cannot import it.
	 *
	 * @return {HTMLElement} The failure.
	 */
	function widgetsFailure() {
		const el = document.createElement( 'section' );
		el.className = 'corex-insight-widget';
		el.innerHTML =
			'<div class="corex-error-state corex-error-state--panel" role="status" aria-live="polite">' +
			'<p class="corex-error-state__message">' +
			escape( t( 'The rest of the insights could not be loaded.' ) ) +
			'</p>' +
			'<div class="corex-error-state__actions">' +
			'<button type="button" class="button">' +
			escape( t( 'Try again' ) ) +
			'</button></div></div>';
		el.querySelector( 'button' ).addEventListener( 'click', () => {
			el.remove();
			loadWidgets();
		} );
		root.appendChild( el );
		return el;
	}

	function loadWidgets() {
		answered.widgets = false;
		settle();
		// Nothing was drawn until they arrived: the screen was two cards, and then seven.
		const placeholders = Array.from(
			{ length: WIDGET_PLACEHOLDERS },
			widgetPlaceholder
		);

		api.get( restUrl + '/widgets', { nonce } ).then( ( result ) => {
			placeholders.forEach( ( el ) => el.remove() );
			answered.widgets = true;
			settle();

			if ( ! result.envelope.ok ) {
				widgetsFailure();
				return;
			}

			( result.envelope.data.widgets || [] )
				.filter( ( w ) => ! w.mount )
				.forEach( renderWidget );
		} );
	}

	loadWidgets();
} )( window.wp, window.corexInsights );
