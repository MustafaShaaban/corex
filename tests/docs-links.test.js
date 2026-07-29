/**
 * Every internal link in the built docs site resolves under the configured base (spec 092).
 *
 * This exists because v0.40.0 published a site where 85 links 404'd. Adding `base: '/corex'` fixed
 * assets, the sidebar and search — Astro rewrites those — and silently left every hand-written
 * markdown link alone, so `[Getting Started](/getting-started/overview/)` shipped verbatim and
 * resolved off the base path entirely.
 *
 * Nothing caught it. Spec 090's own verification fetched six URLs and every one happened to be a
 * base-prefixed form, which is exactly why the body links survived.
 *
 * **Asserted against `dist/`, not against the source.** The source is not where the bug lives: the
 * bug is the difference between what Astro rewrites and what it does not, and only the build knows
 * that. A test that read the markdown would have to reimplement Astro's rules to be wrong in the
 * same way.
 */
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const repositoryRoot = path.resolve( __dirname, '..' );
const distDirectory = path.join( repositoryRoot, 'docs-app', 'dist' );

/** The same default `docs-app/astro.config.mjs` uses, and the same env override. */
const base = ( process.env.COREX_DOCS_BASE || '/corex' ).replace( /\/$/, '' );

/**
 * Every `.html` file under the built site.
 *
 * @param {string} directory Where to start.
 * @return {Array<string>} Absolute paths.
 */
function htmlFiles( directory ) {
	const found = [];

	for ( const entry of fs.readdirSync( directory, {
		withFileTypes: true,
	} ) ) {
		const full = path.join( directory, entry.name );

		if ( entry.isDirectory() ) {
			found.push( ...htmlFiles( full ) );
		} else if ( entry.isFile() && entry.name.endsWith( '.html' ) ) {
			found.push( full );
		}
	}

	return found;
}

/**
 * Root-absolute internal URLs that do not start with the base.
 *
 * Only `/…` is a problem. A scheme (`https:`), a protocol-relative `//host`, a fragment, a `mailto:`
 * and a relative path all resolve correctly whatever the base is — including `data:` URIs, which are
 * long enough to bury a real finding if they were reported.
 *
 * @param {string} html One built page.
 * @return {Array<string>} The offending URLs, deduplicated.
 */
function offendingUrls( html ) {
	const urls = new Set();

	for ( const [ , url ] of html.matchAll( /(?:href|src)="([^"]*)"/g ) ) {
		if ( ! url.startsWith( '/' ) || url.startsWith( '//' ) ) {
			continue;
		}
		if ( url === base || url.startsWith( `${ base }/` ) ) {
			continue;
		}
		urls.add( url );
	}

	return [ ...urls ];
}

describe( 'built documentation site', () => {
	// Guarded rather than assumed: this suite runs in the same Jest job as everything else, and a
	// developer who has never built the docs should get a clear skip, not a confusing failure about
	// a directory. CI builds the site, so it runs there.
	const built = fs.existsSync( distDirectory );
	const describeBuilt = built ? describe : describe.skip;

	if ( ! built ) {
		it( 'is not built here, so link checking is skipped', () => {
			expect( built ).toBe( false );
		} );
	}

	describeBuilt( 'internal links', () => {
		it( 'all resolve under the configured base', () => {
			const failures = [];

			for ( const file of htmlFiles( distDirectory ) ) {
				const offenders = offendingUrls(
					fs.readFileSync( file, 'utf8' )
				);

				if ( offenders.length > 0 ) {
					failures.push(
						`${ path.relative(
							distDirectory,
							file
						) }: ${ offenders.join( ', ' ) }`
					);
				}
			}

			// Compared as one joined string rather than as an array: Jest prints a diff, and a
			// diff of 29 array entries is unreadable where 29 lines are not. The whole list is
			// reported on purpose — 85 links across 29 files was the real number, and a message
			// naming only the first would have sent somebody to fix a single page.
			expect( failures.join( '\n' ) ).toBe( '' );
		} );

		/**
		 * The sibling of the rule above: a link that carries the base twice is equally broken, and a
		 * naive "just prefix everything" fix produces exactly that.
		 */
		it( 'never carries the base twice', () => {
			const doubled = [];

			for ( const file of htmlFiles( distDirectory ) ) {
				const html = fs.readFileSync( file, 'utf8' );

				if ( html.includes( `${ base }${ base }/` ) ) {
					doubled.push( path.relative( distDirectory, file ) );
				}
			}

			expect( doubled ).toEqual( [] );
		} );
	} );
} );
