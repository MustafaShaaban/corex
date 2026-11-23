/**
 * The glyphs of the share bar, the same paths SocialShareRenderer::icon() prints, so the editor
 * draws the bar the page shows (spec 136, FR-018). Decorative: the name a screen reader hears is
 * the text beside each glyph.
 */

/** Each glyph's path, by name: a network, `copy` or `share`. A test holds them to the renderer's. */
export const ICON_PATHS = {
	x: 'M4 3l6.5 8.5L4.3 21H6l5.4-6.6L16 21h4l-6.8-9L19.6 3H18l-5 6.1L8.5 3H4z',
	facebook: 'M15 8h2V5h-2a3 3 0 00-3 3v2H9v3h3v6h3v-6h2.2l.4-3H15V8z',
	linkedin:
		'M6 9v9H3V9h3zM4.5 4a1.6 1.6 0 100 3.2A1.6 1.6 0 004.5 4zM21 18h-3v-4.5c0-1.2-.5-1.9-1.5-1.9s-1.5.7-1.5 1.9V18h-3V9h3v1.2c.5-.8 1.4-1.4 2.6-1.4 2 0 3.4 1.3 3.4 4V18z',
	whatsapp:
		'M12 3a9 9 0 00-7.7 13.6L3 21l4.5-1.2A9 9 0 1012 3zm0 2a7 7 0 016 10.6l-.3.5.7 2.4-2.5-.7-.5.3A7 7 0 1112 5z',
	email: 'M3 5h18v14H3V5zm2 2v.4l7 4.6 7-4.6V7H5zm14 3.3l-6.4 4.2a1 1 0 01-1.2 0L5 10.3V17h14v-6.7z',
	copy: 'M9 3h9v13H9V3zm-2 4H5v14h11v-2H7V7z',
	share: 'M14 9V5l7 7-7 7v-4C9 12 6 14 4 18c0-6 4-9 10-9z',
};

/**
 * @param {Object} props
 * @param {string} props.name The glyph: a network, `copy` or `share`.
 * @return {Element} The glyph, as the renderer draws it.
 */
export function ShareIcon( { name } ) {
	return (
		<svg
			className="corex-social-share__icon"
			viewBox="0 0 24 24"
			width="18"
			height="18"
			fill="currentColor"
			aria-hidden="true"
			focusable="false"
		>
			<path d={ ICON_PATHS[ name ] } />
		</svg>
	);
}
