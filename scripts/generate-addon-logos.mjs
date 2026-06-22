/**
 * Generates the CoreX add-on logo tile system as real, static SVG files — the frozen
 * "Direction A — Module Tile" system from the approved design capture
 * (`Corex Addon Logos - Final.dc.html`): a rounded graphite tile, a brass stroke glyph,
 * and the five-square Core sigil in the bottom-trailing corner.
 *
 * Output: plugins/corex-config/assets/addon-logos/{slug}.svg + {slug}--disabled.svg, and
 * the standalone five-square mark corex-mark.svg. No data:/base64 — these are committed assets.
 *
 * Run: node scripts/generate-addon-logos.mjs
 */
import { mkdirSync, writeFileSync } from 'node:fs';

const OUT = 'plugins/corex-config/assets/addon-logos';
mkdirSync(OUT, { recursive: true });

const BRASS = '#c9a25e';
const MUTED = '#646b78';
const TILE = '#15171c';
const TILE_BD = '#2e3340';

// 24x24 stroke glyph library — copied verbatim from the frozen design capture.
const GLYPHS = {
	ui: ['M4 4h7v7H4z', 'M13 4h7v7h-7z', 'M4 13h7v7H4z', 'M13 13h7v7h-7z'],
	captcha: ['M12 3 19 5.4 V11 c0 4.3-3 6.9-7 8.3 C8 17.9 5 15.3 5 11 V5.4 Z', 'M8.7 11.6 l2.3 2.3 l4.3-4.6'],
	media: ['M4 5h16v14H4z', 'M4 16l4.5-4.5 3 3 4-4 4.5 4.5', 'M9 9.5a1.4 1.4 0 1 0-.001-.001z'],
	email: ['M3 6h18v12H3z', 'M3 7l9 6 9-6'],
	newsletter: ['M21 3 3 10.5 9.5 13 12 20 21 3z', 'M21 3 9.5 13'],
	careers: ['M3 8h18v11H3z', 'M8 8V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2', 'M3 13h18'],
	bookings: ['M4 6h16v15H4z', 'M4 10h16', 'M8 3v4', 'M16 3v4'],
	company: ['M5 21V4h8v17', 'M13 11h6v10', 'M8 8h2M8 12h2M8 16h2', 'M3 21h18'],
	portfolio: ['M4 5h7v6H4z', 'M13 5h7v6h-7z', 'M4 14h16v5H4z'],
	woo: ['M6 8h12l-1 11H7z', 'M9 8a3 3 0 0 1 6 0'],
	pro: ['M12 3l2.2 5.8L20 11l-5.8 2.2L12 19l-2.2-5.8L4 11l5.8-2.2z'],
};

const n = (x) => Number(x.toFixed(3));

function glyphGroup(key, size, frac, color, strokeW) {
	const span = size * frac;
	const s = span / 24;
	const off = (size - span) / 2;
	const paths = GLYPHS[key].map((d) => `<path d="${d}"/>`).join('');
	return `<g transform="translate(${n(off)} ${n(off)}) scale(${n(s)})">`
		+ `<g fill="none" stroke="${color}" stroke-width="${n(strokeW / s)}" stroke-linejoin="round" stroke-linecap="round">`
		+ `${paths}</g></g>`;
}

function coreGlyph(size, frac, color) {
	const span = size * frac;
	const off = (size - span) / 2;
	const u = span / 3;
	const d = u * 0.78;
	const pad = (u - d) / 2;
	const at = (cx, cy) =>
		`<rect x="${n(off + cx * u + pad)}" y="${n(off + cy * u + pad)}" width="${n(d)}" height="${n(d)}" rx="${n(d * 0.26)}" fill="${color}"/>`;
	return at(0, 0) + at(2, 0) + at(1, 1) + at(0, 2) + at(2, 2);
}

function cornerSigil(size, color) {
	const dot = Math.max(1.4, size * 0.046);
	const g = dot * 2.4;
	const x = size * 0.64;
	const y = size * 0.64;
	const at = (cx, cy) =>
		`<rect x="${n(x + cx * g)}" y="${n(y + cy * g)}" width="${n(dot)}" height="${n(dot)}" rx="${n(dot * 0.28)}" fill="${color}"/>`;
	return `<g opacity="0.95">${at(0, 0) + at(2, 0) + at(1, 1) + at(0, 2) + at(2, 2)}</g>`;
}

function tile(key, size, { glyphColor = BRASS, sigilColor, proTick = false, label = '' } = {}) {
	sigilColor = sigilColor || glyphColor;
	const r = size * 0.24;
	const showSigil = size >= 48;
	const isCore = key === 'core';
	let frac = size <= 23 ? 0.56 : showSigil ? 0.46 : 0.52;
	let stroke = size <= 23 ? 2.5 : 2.1;
	if (isCore) frac = showSigil ? 0.52 : 0.58;

	const parts = [
		`<rect x="0.6" y="0.6" width="${n(size - 1.2)}" height="${n(size - 1.2)}" rx="${n(r)}" fill="${TILE}" stroke="${TILE_BD}" stroke-width="1"/>`,
		isCore ? coreGlyph(size, frac, glyphColor) : glyphGroup(key, size, frac, glyphColor, stroke),
	];
	if (showSigil && !isCore) parts.push(cornerSigil(size, sigilColor));
	if (proTick) parts.push(`<circle cx="${n(size * 0.78)}" cy="${n(size * 0.22)}" r="${n(size * 0.12)}" fill="${BRASS}"/>`);

	return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" fill="none" role="img" aria-label="${label}">`
		+ `<title>${label}</title>${parts.join('')}</svg>\n`;
}

// Standalone five-square CoreX mark (for shell/header identity).
function mark(size = 48) {
	const cell = (x, y, fill) => `<rect x="${x}" y="${y}" width="12" height="12" rx="2.5" fill="${fill}"/>`;
	return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 48 48" fill="none" role="img" aria-label="Corex">`
		+ `<title>Corex</title>`
		+ cell(3, 3, 'currentColor') + cell(33, 3, 'currentColor')
		+ cell(18, 18, BRASS)
		+ cell(3, 33, 'currentColor') + cell(33, 33, 'currentColor')
		+ `</svg>\n`;
}

// slug -> glyph key. Functional add-ons, site kits, and the core package.
const MAP = {
	'corex-ui': 'ui',
	'corex-captcha': 'captcha',
	'corex-media': 'media',
	'corex-email': 'email',
	'corex-newsletter': 'newsletter',
	'corex-careers': 'careers',
	'corex-bookings': 'bookings',
	'corex-kit-company': 'company',
	'corex-kit-portfolio': 'portfolio',
	'corex-kit-woo': 'woo',
	'corex-core': 'core',
};

let count = 0;
for (const [slug, key] of Object.entries(MAP)) {
	const isWoo = key === 'woo';
	writeFileSync(`${OUT}/${slug}.svg`, tile(key, 48, { label: slug, proTick: false }));
	writeFileSync(`${OUT}/${slug}--disabled.svg`, tile(key, 48, { glyphColor: MUTED, sigilColor: MUTED, label: `${slug} (disabled)` }));
	count += 2;
	void isWoo;
}
// Pro placeholder mark (non-actionable, truthful).
writeFileSync(`${OUT}/pro.svg`, tile('pro', 48, { proTick: true, label: 'pro' }));
writeFileSync(`${OUT}/pro--disabled.svg`, tile('pro', 48, { glyphColor: MUTED, sigilColor: MUTED, proTick: true, label: 'pro (disabled)' }));
// Generic fallback = core package mark.
writeFileSync(`${OUT}/fallback.svg`, tile('core', 48, { label: 'add-on' }));
writeFileSync(`${OUT}/fallback--disabled.svg`, tile('core', 48, { glyphColor: MUTED, label: 'add-on (disabled)' }));
// Standalone five-square mark for headers.
writeFileSync('plugins/corex-config/assets/brand/corex-mark.svg', mark(48));
count += 5;

console.log(`Generated ${count} add-on logo SVGs in ${OUT} + corex-mark.svg`);
