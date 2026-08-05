/**
 * Renders `docs-app/src/content/docs/project-status.md` from the canonical `PROJECT-STATUS.md`.
 *
 * The docs site used to carry a **hand-written second copy** of the status page — 62 lines against
 * the root file's 123, its own opening paragraph, and its own version sentence for `wp corex
 * version` to stamp. Two hand-kept copies of "what works and what does not" is the exact shape of
 * the defect this project keeps finding in itself: the one nobody is editing quietly stops being
 * true, and a reader has no way to tell which one they are looking at.
 *
 * So there is one source now, and this is the mechanism. Run by `docs-app`'s `prebuild`, and held to
 * its output by `tests/repo-hygiene.test.js` — the same arrangement `generate-token-inventory.mjs`
 * has, for the same reason: the artifact is committed so the site builds and the diff is reviewable,
 * and a test proves nobody edited the copy instead of the source.
 *
 * ## What it changes, and what it deliberately does not
 *
 * Only the frame. Starlight needs YAML front matter and renders its own `<h1>` from the title, so
 * the front matter is added and the source's single `#` heading is dropped. Links to files that
 * exist in the repository but not on the site resolve to GitHub, because a relative link to
 * `ROADMAP.md` from a rendered page is a 404.
 *
 * The prose is copied verbatim. Rewriting it here would put a second author between the status file
 * and its reader, which is the problem rather than the fix.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const BLOB = 'https://github.com/MustafaShaaban/corex/blob/main/';

/** Repository documents that have no page on the docs site and must resolve to GitHub. */
const LINKS_TO_GITHUB =
	/\]\((?!https?:|#|\/)((?:[A-Za-z0-9._-]+\/)*[A-Za-z0-9._-]+\.(?:md|json|php|js|mjs|yml|yaml|xml|dist))(#[^)]*)?\)/g;

/**
 * The docs-site page, as text.
 *
 * @param {string} repositoryRoot Absolute path to the repository root.
 * @return {string} The full contents of the generated Markdown page.
 */
export function renderProjectStatusPage( repositoryRoot ) {
	const source = readFileSync(
		path.join( repositoryRoot, 'PROJECT-STATUS.md' ),
		'utf8'
	);

	const body = source
		// Starlight renders the `title` front matter as the page's only `<h1>`; leaving the
		// source's would give the page two, which fails accessibility review and looks like a bug.
		.replace( /^#\s.*\r?\n+/, '' )
		.replace(
			LINKS_TO_GITHUB,
			( _match, file, fragment ) =>
				`](${ BLOB }${ file }${ fragment || '' })`
		)
		.replace( /\r\n/g, '\n' )
		.trimEnd();

	return `---
title: Project status
description: What works, what is partial, and what is not built — with the file that records each one.
---

:::note[One source]
This page is generated from [\`PROJECT-STATUS.md\`](${ BLOB }PROJECT-STATUS.md) in the repository
root, which is the canonical copy. Edit that file; this one is rebuilt from it.
:::

${ body }
`;
}

export const PAGE_PATH = 'docs-app/src/content/docs/project-status.md';

/**
 * Write the page. Separate from rendering so a test can compare without touching the tree.
 *
 * @param {string} repositoryRoot Absolute path to the repository root.
 * @return {string} The path written, repository-relative.
 */
export function writeProjectStatusPage( repositoryRoot ) {
	writeFileSync(
		path.join( repositoryRoot, PAGE_PATH ),
		renderProjectStatusPage( repositoryRoot ),
		'utf8'
	);

	return PAGE_PATH;
}

// The same CLI detection `generate-token-inventory.mjs` uses, and for the same reason: this module
// is imported by `tests/repo-hygiene.test.js`, where `import.meta` does not survive Jest's transform
// and writing to the working tree from a test would be a side effect nobody asked for.
const isCli =
	path.basename( process.argv[ 1 ] ?? '' ) === 'sync-project-status.mjs';
if ( isCli ) {
	const root = path.resolve( path.dirname( process.argv[ 1 ] ), '..' );
	process.stdout.write( `${ writeProjectStatusPage( root ) }\n` );
}
