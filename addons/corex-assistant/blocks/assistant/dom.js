/**
 * Small DOM helpers for the assistant's chat (spec 129). Every visitor or model text goes in as text,
 * never as HTML: `html` is used only for the fixed icons below.
 */

const ICONS = {
	reset: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
	close: '<path d="M18 6 6 18M6 6l12 12"/>',
	send: '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
	link: '<path d="M7 17 17 7M8 7h9v9"/>',
	check: '<path d="M20 6 9 17l-5-5"/>',
	bubble: '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-5.1A8 8 0 1 1 21 12Z"/>',
};

export function icon( name ) {
	return `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${ ICONS[ name ] }</svg>`;
}

export function el( tag, attrs = {}, children = [] ) {
	const node = document.createElement( tag );
	for ( const [ key, value ] of Object.entries( attrs ) ) {
		if ( value === false || value === null || value === undefined ) {
			continue;
		}
		if ( key === 'text' ) {
			node.textContent = value;
		} else if ( key === 'html' ) {
			node.innerHTML = value;
		} else {
			node.setAttribute( key, value === true ? '' : value );
		}
	}
	node.append( ...children );
	return node;
}

/**
 * Paragraphs from plain text.
 *
 * @param {string} text
 * @return {HTMLElement[]} one paragraph per blank-line-separated part
 */
export function paragraphs( text ) {
	return String( text )
		.split( /\n{2,}/ )
		.map( ( part ) => part.trim() )
		.filter( Boolean )
		.map( ( part ) => el( 'p', { text: part } ) );
}

export function parseJson( value, fallback ) {
	try {
		return JSON.parse( value );
	} catch {
		return fallback;
	}
}

/**
 * Whether an address is on this site (with or without `www.`): the only links a reply may show.
 *
 * @param {string} url
 * @return {boolean} same site
 */
export function sameSite( url ) {
	try {
		const bare = ( host ) => host.replace( /^www\./, '' );
		return (
			bare( new URL( url, window.location.href ).host ) ===
			bare( window.location.host )
		);
	} catch {
		return false;
	}
}
