/**
 * Corex Assistant: "Check services" on Settings → Assistant (spec 129, FR-014). Vanilla, no build, like
 * the captcha's test button. It posts to corex/v1/assistant/check and lists which keyed services
 * answered. The server sends each service's name, model and outcome; no key reaches the page.
 */
( function () {
	const { __, sprintf } = window.wp?.i18n ?? {
		__: ( text ) => text,
		sprintf: ( text, ...args ) =>
			args.reduce( ( out, arg ) => out.replace( /%s/, arg ), text ),
	};

	/**
	 * One line per service.
	 *
	 * @param {{service: string, model: string, answered: boolean, detail: string}} result
	 * @return {string} what the line says
	 */
	function line( result ) {
		return result.answered
			? /* translators: 1: a service, 2: its model */ sprintf(
					__( '%1$s (%2$s): answered', 'corex' ),
					result.service,
					result.model
				)
			: /* translators: 1: a service, 2: its model, 3: why it did not answer */ sprintf(
					__( '%1$s (%2$s): did not answer, %3$s', 'corex' ),
					result.service,
					result.model,
					result.detail
				);
	}

	function show( region, lines ) {
		const list = document.createElement( 'ul' );
		lines.forEach( ( text ) => {
			const item = document.createElement( 'li' );
			item.textContent = text;
			list.append( item );
		} );
		region.replaceChildren( list );
	}

	function bind( panel, config ) {
		const button = panel.querySelector( '[data-corex-assistant-check]' );
		const region = panel.querySelector( '[data-corex-assistant-results]' );
		if ( ! button || ! region ) {
			return;
		}
		button.addEventListener( 'click', async () => {
			button.disabled = true;
			region.textContent = __( 'Checking…', 'corex' );
			try {
				const response = await window.fetch( config.endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': config.nonce },
				} );
				const body = await response.json();
				const results = body?.data?.results ?? [];
				show(
					region,
					results.length
						? results.map( line )
						: [ __( 'No service has a key yet.', 'corex' ) ]
				);
			} catch {
				show( region, [
					__(
						'The check could not run. Reload the page and try again.',
						'corex'
					),
				] );
			} finally {
				button.disabled = false;
			}
		} );
	}

	function init() {
		const config = window.corexAssistantAdmin;
		if ( ! config ) {
			return;
		}
		document
			.querySelectorAll( '[data-corex-assistant-panel]' )
			.forEach( ( panel ) => bind( panel, config ) );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
