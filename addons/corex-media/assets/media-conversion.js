/**
 * "Convert existing library" on Settings > Media (spec 119, US-SET-06). Vanilla, no build.
 *
 * Starts a conversion, follows it while the page is open (each request takes one step of the
 * bounded job; WP-Cron carries on when the page is closed), and cancels it. The run is read from
 * the server when the page opens, so a page reopened mid-run shows where it is.
 */
( function () {
	const STEP_EVERY_MS = 1200;

	/**
	 * Whether a run is still going.
	 *
	 * @param {Object|null} run the run the server answered
	 * @return {boolean} true while queued or running
	 */
	function isActive( run ) {
		return Boolean( run && run.active );
	}

	/**
	 * Draws a run into the panel.
	 *
	 * @param {HTMLElement} panel the panel
	 * @param {Object|null} run   the run the server answered
	 */
	function show( panel, run ) {
		const start = panel.querySelector( '[data-corex-conversion-start]' );
		const cancel = panel.querySelector( '[data-corex-conversion-cancel]' );
		const progress = panel.querySelector( '[data-corex-conversion-progress]' );
		const summary = panel.querySelector( '[data-corex-conversion-summary]' );
		const failures = panel.querySelector( '[data-corex-conversion-failures]' );
		const active = isActive( run );
		const available = panel.getAttribute( 'data-available' ) === '1';

		start.hidden = active;
		start.disabled = ! available;
		cancel.hidden = ! active;
		progress.hidden = ! active;

		if ( run ) {
			progress.max = Math.max( 1, run.total );
			progress.value = run.processed;
			if ( run.summary && summary.textContent !== run.summary ) {
				summary.textContent = run.summary;
			}

			const list = failures.querySelector( 'ul' );
			list.textContent = '';
			( run.failures || [] ).forEach( function ( failure ) {
				const item = document.createElement( 'li' );
				const file = document.createElement( 'bdi' );
				file.textContent = failure.file;
				item.appendChild( file );
				item.appendChild(
					document.createTextNode( ': ' + failure.reason )
				);
				list.appendChild( item );
			} );
			failures.hidden = ( run.failures || [] ).length === 0;
		}
	}

	function init() {
		const config = window.corexMediaConversion;
		const panel = document.querySelector( '[data-corex-media-conversion]' );
		if ( ! config || ! panel || ! window.Corex || ! window.Corex.api ) {
			return;
		}

		const api = window.Corex.api;
		const summary = panel.querySelector( '[data-corex-conversion-summary]' );
		let timer = null;

		function answer( res ) {
			const envelope = ( res && res.envelope ) || {};
			if ( ! envelope.ok ) {
				summary.textContent =
					envelope.message || config.i18n.failedRequest;

				return null;
			}

			const run = ( envelope.data && envelope.data.run ) || null;
			show( panel, run );

			return run;
		}

		function follow( run ) {
			window.clearTimeout( timer );
			if ( ! isActive( run ) ) {
				return;
			}
			timer = window.setTimeout( function () {
				api.post( config.restUrl + '/advance', {}, { nonce: config.nonce } )
					.then( answer )
					.then( follow );
			}, STEP_EVERY_MS );
		}

		panel
			.querySelector( '[data-corex-conversion-start]' )
			.addEventListener( 'click', function () {
				api.post( config.restUrl, {}, { nonce: config.nonce } )
					.then( answer )
					.then( follow );
			} );

		panel
			.querySelector( '[data-corex-conversion-cancel]' )
			.addEventListener( 'click', function () {
				window.clearTimeout( timer );
				api.post( config.restUrl + '/cancel', {}, { nonce: config.nonce } ).then(
					answer
				);
			} );

		api.get( config.restUrl, { nonce: config.nonce } )
			.then( answer )
			.then( follow );
	}

	if ( typeof document !== 'undefined' ) {
		if ( document.readyState !== 'loading' ) {
			init();
		} else {
			document.addEventListener( 'DOMContentLoaded', init );
		}
	}

	// Exported for unit tests (no effect in the browser, where there is no CommonJS module).
	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { isActive, show };
	}
} )();
