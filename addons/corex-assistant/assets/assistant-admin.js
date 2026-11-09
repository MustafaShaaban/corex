/**
 * CoreX → Assistant (spec 129, FR-014). Vanilla, no build, like the captcha's test button.
 *
 * - Moves a service up or down in place; without this script the same buttons save the page with the
 *   move, so the order can be changed either way.
 * - Opens the media library for the button image.
 * - "Check services": posts to corex/v1/assistant/check and lists which services answered, and how
 *   fast. The server sends each service's name, model and outcome; no key reaches the page.
 */
( function () {
	const { __, sprintf } = window.wp?.i18n ?? {
		__: ( text ) => text,
		sprintf: ( text, ...args ) =>
			args.reduce( ( out, arg ) => out.replace( /%\d?\$?s/, arg ), text ),
	};

	function line( result ) {
		return result.answered
			? /* translators: 1: a service, 2: its model, 3: seconds */ sprintf(
					__( '%1$s (%2$s): answered in %3$s s', 'corex' ),
					result.service,
					result.model,
					String( result.seconds )
				)
			: /* translators: 1: a service, 2: its model, 3: seconds, 4: why it did not answer */ sprintf(
					__(
						'%1$s (%2$s): did not answer after %3$s s, %4$s',
						'corex'
					),
					result.service,
					result.model,
					String( result.seconds ),
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

	function bindCheck( panel, config ) {
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
				if ( ! response.ok ) {
					throw new Error( String( response.status ) );
				}
				const body = await response.json();
				const results = body?.data?.results ?? [];
				show(
					region,
					results.length
						? results.map( line )
						: [ __( 'No service is on with a key yet.', 'corex' ) ]
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

	function bindOrder( body ) {
		body.addEventListener( 'click', ( event ) => {
			const button = event.target.closest(
				'[data-corex-assistant-move]'
			);
			if ( ! button ) {
				return;
			}
			event.preventDefault();
			const row = button.closest( 'tr' );
			const up = button.dataset.corexAssistantMove === 'up';
			const other = up
				? row.previousElementSibling
				: row.nextElementSibling;
			if ( ! other ) {
				return;
			}
			body.insertBefore( row, up ? other : other.nextElementSibling );
			button.focus();
		} );
	}

	function bindMedia( button ) {
		const field = document.getElementById( 'corex-assistant-icon' );
		if ( ! field || ! window.wp?.media ) {
			return;
		}
		button.addEventListener( 'click', () => {
			const frame = window.wp.media( {
				title: __( 'Choose the button image', 'corex' ),
				button: { text: __( 'Use this image', 'corex' ) },
				library: { type: 'image' },
				multiple: false,
			} );
			frame.on( 'select', () => {
				const chosen = frame
					.state()
					.get( 'selection' )
					.first()
					?.toJSON();
				if ( chosen?.url ) {
					field.value = chosen.url;
				}
			} );
			frame.open();
		} );
	}

	function init() {
		const config = window.corexAssistantAdmin;
		document
			.querySelectorAll( '[data-corex-assistant-order]' )
			.forEach( bindOrder );
		document
			.querySelectorAll( '[data-corex-assistant-media]' )
			.forEach( bindMedia );
		if ( config ) {
			document
				.querySelectorAll( '[data-corex-assistant-panel]' )
				.forEach( ( panel ) => bindCheck( panel, config ) );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
