/**
 * Check the published docs site as a reader reaches it (spec 130, SC-001 and SC-002).
 *
 * `tests/docs-links.test.js` proves the build's links agree with the base it was built for. This
 * checks the other half, which only the live site knows: that the address the site is served
 * from is the one it was built for, so its pages, styles and search load there, that plain HTTP
 * is sent to HTTPS, and, after a move, that every old address lands on the same page.
 *
 *   node scripts/check-docs-host.mjs https://corex-docs.mustafashaaban.com/ \
 *     [--moved-from https://mustafashaaban.github.io/corex/] [--retries 5]
 *
 * Exit 0 when nothing is wrong, 1 with one line per problem, 2 when the check could not run.
 * `.github/workflows/docs.yml` runs it after every deploy; spec 130's switch steps run it by hand.
 */
import path from 'node:path';

const STYLESHEET = /<link\b[^>]*\brel="stylesheet"[^>]*\bhref="([^"]+)"/g;
const ANCHOR = /<a\b[^>]*\bhref="([^"]+)"/g;

/**
 * Path-absolute links in an HTML page (`/x/`, not `//host/x` and not `https://…`), without their
 * fragment or query, in the order they appear.
 *
 * @param {string} html    The page.
 * @param {RegExp} pattern Which attribute to read.
 * @return {string[]} Unique paths.
 */
function pathLinks( html, pattern ) {
	const found = [];

	for ( const [ , href ] of html.matchAll( pattern ) ) {
		const target = href.split( /[#?]/ )[ 0 ];

		if (
			target.startsWith( '/' ) &&
			! target.startsWith( '//' ) &&
			! found.includes( target )
		) {
			found.push( target );
		}
	}

	return found;
}

/**
 * A recorder of problems over one `fetch`: each request is made once and never followed, since a
 * redirect is an answer to check, not to take.
 */
class SiteProbe {
	/** @param {typeof fetch} fetch The `fetch` to read the site with. */
	constructor( fetch ) {
		this.fetch = fetch;
		this.problems = [];
	}

	/**
	 * One request. A request that gets no answer is a problem, not a crash, so the rest of the
	 * site is still checked.
	 *
	 * @param {string} url Where.
	 * @return {Promise<Response|null>} The response, or null when none came.
	 */
	async request( url ) {
		try {
			return await this.fetch( url, { redirect: 'manual' } );
		} catch ( error ) {
			// `fetch` rejects with a TypeError when no answer comes; anything else is a bug here.
			if ( ! ( error instanceof TypeError ) ) {
				throw error;
			}
			this.problems.push(
				`${ url } did not answer (${ error.cause?.code || error.message })`
			);
			return null;
		}
	}

	/**
	 * The body at `url`, which must answer 200.
	 *
	 * @param {string} url Where.
	 * @return {Promise<string|null>} The body, or null (and a problem) otherwise.
	 */
	async load( url ) {
		const response = await this.request( url );

		if ( response === null ) {
			return null;
		}
		if ( response.status !== 200 ) {
			this.problems.push(
				`${ url } answered ${ response.status }, expected 200`
			);
			return null;
		}

		return response.text();
	}

	/**
	 * `from` must redirect to exactly `to`.
	 *
	 * @param {string} from The address asked for.
	 * @param {string} to   The address it must send the reader to.
	 * @return {Promise<void>}
	 */
	async expectRedirect( from, to ) {
		const response = await this.request( from );

		if ( response === null ) {
			return;
		}

		const location = response.headers.get( 'location' );
		const redirected =
			response.status >= 300 &&
			response.status < 400 &&
			location !== null &&
			new URL( location, from ).href === to;

		if ( ! redirected ) {
			this.problems.push(
				`${ from } answered ${ response.status }, expected a redirect to ${ to }`
			);
		}
	}
}

/**
 * Read the home page, every page it links to, and the first page each of those links to that was
 * not already read: that reaches a class reference page from the reference index without reading
 * every class.
 *
 * @param {SiteProbe} probe The recorder.
 * @param {URL}       base  Where the site is served.
 * @return {Promise<Map<string, string|null>>} Each page read, by URL, with its body or null.
 */
async function readPages( probe, base ) {
	const pages = new Map();
	const readPage = async ( url ) => {
		pages.set( url, await probe.load( url ) );
		return pages.get( url );
	};
	const linksOf = ( html ) =>
		html === null
			? []
			: pathLinks( html, ANCHOR ).map(
					( link ) => new URL( link, base ).href
				);

	const firstLevel = linksOf( await readPage( base.href ) );

	for ( const url of firstLevel.filter( ( link ) => ! pages.has( link ) ) ) {
		await readPage( url );
	}
	for ( const url of firstLevel ) {
		const next = linksOf( pages.get( url ) ).find(
			( link ) => ! pages.has( link )
		);
		if ( next !== undefined ) {
			await readPage( next );
		}
	}

	return pages;
}

/**
 * Everything wrong with the docs site served at `baseUrl`, as one sentence per problem: the
 * pages {@link readPages} reaches, every stylesheet they use, the search index, plain HTTP sent
 * to HTTPS and, after a move, each page reached from its old address.
 *
 * @param {Object}       options
 * @param {string}       options.baseUrl     Where the site is served, e.g. `https://corex-docs.mustafashaaban.com/`.
 * @param {string|null}  [options.movedFrom] The address it moved from; each page read must redirect from there.
 * @param {typeof fetch} [options.fetch]     A `fetch`; the global one by default.
 * @return {Promise<string[]>} The problems; empty when there are none.
 */
export async function checkDocsSite( {
	baseUrl,
	movedFrom = null,
	fetch = globalThis.fetch,
} ) {
	const base = new URL( withSlash( baseUrl ) );
	const probe = new SiteProbe( fetch );
	const pages = await readPages( probe, base );
	const loaded = [ ...pages ].filter( ( [ , html ] ) => html !== null );
	const stylesheets = new Set(
		loaded.flatMap( ( [ , html ] ) =>
			pathLinks( html, STYLESHEET ).map(
				( sheet ) => new URL( sheet, base ).href
			)
		)
	);

	for ( const sheet of stylesheets ) {
		await probe.load( sheet );
	}
	await probe.load( new URL( 'pagefind/pagefind.js', base ).href );

	if ( base.protocol === 'https:' ) {
		await probe.expectRedirect(
			base.href.replace( /^https:/, 'http:' ),
			base.href
		);
	}
	if ( movedFrom !== null ) {
		for ( const [ url ] of loaded.filter( ( [ page ] ) =>
			page.startsWith( base.href )
		) ) {
			const rest = url.slice( base.href.length );
			await probe.expectRedirect(
				`${ withSlash( movedFrom ) }${ rest }`,
				url
			);
		}
	}

	return probe.problems;
}

/**
 * @param {string} address An address.
 * @return {string} The same address ending in `/`.
 */
function withSlash( address ) {
	return address.endsWith( '/' ) ? address : `${ address }/`;
}

/**
 * The command line: an address, `--moved-from <address>` and `--retries <n>`.
 *
 * @param {string[]} argv The arguments after the script.
 * @return {{baseUrl: string, movedFrom: string|null, retries: number}} Options.
 */
function parseArguments( argv ) {
	const options = { baseUrl: null, movedFrom: null, retries: 0 };

	for ( let index = 0; index < argv.length; index++ ) {
		if ( argv[ index ] === '--moved-from' ) {
			options.movedFrom = argv[ ++index ];
		} else if ( argv[ index ] === '--retries' ) {
			options.retries = Number.parseInt( argv[ ++index ], 10 );
		} else {
			options.baseUrl = argv[ index ];
		}
	}

	if ( ! options.baseUrl || ! Number.isInteger( options.retries ) ) {
		throw new Error(
			'Usage: node scripts/check-docs-host.mjs <address> [--moved-from <address>] [--retries <n>]'
		);
	}

	return options;
}

/**
 * Run the check from the command line and exit with its answer.
 *
 * @return {Promise<void>}
 */
async function main() {
	try {
		const { retries, ...options } = parseArguments(
			process.argv.slice( 2 )
		);
		let problems = await checkDocsSite( options );

		// A deploy reaches Pages' edge, and a new certificate is issued, some time after the
		// workflow step that asked for it finished; retry before calling either a failure.
		for (
			let attempt = 0;
			problems.length > 0 && attempt < retries;
			attempt++
		) {
			await new Promise( ( resolve ) => setTimeout( resolve, 30_000 ) );
			problems = await checkDocsSite( options );
		}

		for ( const problem of problems ) {
			process.stdout.write( `${ problem }
` );
		}

		process.stdout.write(
			problems.length === 0
				? `The docs site at ${ options.baseUrl } loads, with its styles and search.
`
				: `${ problems.length } problem(s).
`
		);
		process.exit( problems.length === 0 ? 0 : 1 );
	} catch ( error ) {
		process.stderr.write( `${ error.message }
` );
		process.exit( 2 );
	}
}

// Run as a command, not when a test imports it: `import.meta` does not survive Jest's transform.
if ( path.basename( process.argv[ 1 ] ?? '' ) === 'check-docs-host.mjs' ) {
	main();
}
