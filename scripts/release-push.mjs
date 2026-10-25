/**
 * One command from a client's sources to a release installed on its site, with no browser
 * (spec 123, User Story 4; FR-013 to FR-015d; DECISIONS #512, #513).
 *
 *     npm run release:push -- --client=<name> [--with-plugin=<folder>]... [--pdf-fonts=all] [options]
 *     npm run release:push -- --package=<corex-release-….zip> [--client=<name>] [options]
 *
 * Options:
 *     --settings=<file>     where the site, the account and its Application Password are
 *     --accept-older        the package is older than the release the site runs, and that is meant
 *     --accept-downgrades   plugins or themes the package holds older versions of go back to them
 *     --resume              go on with an installation that a run of this command did not finish
 *     --upload-only         stop once the package is on the site (an agent installs it, spec 123 US5)
 *
 * It builds the package with `build:dist --zip` when given `--client` and no `--package`, sends it
 * through spec 107's resumable upload, and installs it through the Releases routes exactly as the
 * screen does: it drives them with the screen's own client (`createReleaseClient()`) and its own
 * loop of steps (`runInstallation()`), so the two cannot disagree about the protocol. Then it asks
 * the site which release it runs and whether its home page answers.
 *
 * The site must define `COREX_ALLOW_RELEASE_PUSH` as true in its `wp-config.php` (DECISIONS #512),
 * and the release running there must be one that knows it: the first release that carries this is
 * installed from the screen.
 *
 * The site's address, the account and its Application Password come from `--settings`, else the
 * file `COREX_PUSH_SETTINGS` names, else `COREX_PUSH_SITE`, `COREX_PUSH_USER` and
 * `COREX_PUSH_APP_PASSWORD`, else `~/.corex/release-push/<client>.json`. Never from an argument,
 * never from a file inside a git checkout, and the password is never printed.
 *
 * Exit codes: 0 done; 1 usage, settings, or something the command must be told is meant;
 * 2 the site refused, or an installation stands; 3 the site could not be reached; 4 the build failed;
 * 5 the command itself failed.
 */
import { spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import {
	chmodSync,
	createReadStream,
	existsSync,
	mkdirSync,
	openAsBlob,
	readFileSync,
	rmSync,
	statSync,
	writeFileSync,
} from 'node:fs';
import { homedir } from 'node:os';
import { basename, dirname, join, resolve } from 'node:path';

export const EXIT = Object.freeze( {
	DONE: 0,
	USAGE: 1,
	REFUSED: 2,
	UNREACHABLE: 3,
	BUILD: 4,
	FAULT: 5,
} );

/** The name spec 107 accepts a package under (`ReleaseUpload::A_PACKAGE_NAME`). */
const A_PACKAGE_NAME = /^corex-release-[A-Za-z0-9][A-Za-z0-9._-]*\.zip$/;

/** Hosts a password may be sent to over plain HTTP: this machine, and names that never leave it. */
const LOCAL_HOST =
	/^(?:localhost|127\.0\.0\.1|\[::1\]|.+\.localhost|.+\.test|.+\.local)$/i;

/** What a site in maintenance mode answers visitors, on purpose: not a sign the release broke it. */
const MAINTENANCE_STATUS = 503;

/** The step at which an installation awaits its confirmation and has started nothing. */
const AWAITING = 'awaiting';

/**
 * How many times a request is made, without the installation's key, while the site may hold
 * visitors off: with the client's waits (one second, doubled, at most fifteen) this outlasts the
 * ten minutes after which WordPress lets every request in by itself.
 */
const TRIES_WITHOUT_THE_KEY = 50;

/** The failures this command explains, and turns into an exit code; anything else is thrown on. */
const EXPLAINED = [
	'ReleaseRefusedError',
	'ReleaseUnreachableError',
	'BuildError',
];

/** How the command was asked to run, from its arguments. Settings and secrets are never among them. */
export class UsageError extends Error {}

/**
 * @param {string[]} argv The arguments after the script's name.
 * @return {Object} What was asked.
 */
export function parseArguments( argv ) {
	const asked = {
		client: null,
		withPlugins: [],
		pdfFonts: null,
		packagePath: null,
		settingsPath: null,
		acceptOlder: false,
		acceptDowngrades: false,
		resume: false,
		uploadOnly: false,
	};
	const valued = {
		'--client': ( v ) => ( asked.client = v ),
		'--with-plugin': ( v ) => asked.withPlugins.push( v ),
		'--pdf-fonts': ( v ) => ( asked.pdfFonts = v ),
		'--package': ( v ) => ( asked.packagePath = v ),
		'--settings': ( v ) => ( asked.settingsPath = v ),
	};
	const flags = {
		'--accept-older': 'acceptOlder',
		'--accept-downgrades': 'acceptDowngrades',
		'--resume': 'resume',
		'--upload-only': 'uploadOnly',
	};

	for ( const argument of argv ) {
		const [ name, value ] = argument.split( /=(.*)/s, 2 );
		if ( flags[ name ] && value === undefined ) {
			asked[ flags[ name ] ] = true;
		} else if ( valued[ name ] && value ) {
			valued[ name ]( value );
		} else {
			throw new UsageError(
				`Not an option of release:push: ${ argument }`
			);
		}
	}

	if ( ! asked.client && ! asked.packagePath && ! asked.resume ) {
		throw new UsageError(
			'Say which client to build (--client=<name>) or which package to send (--package=<file>).'
		);
	}

	return asked;
}

/**
 * Whether a path is inside a git checkout, where a settings file could be committed by mistake.
 *
 * @param {string}                    file   The file.
 * @param {(path: string) => boolean} exists Whether a path exists.
 * @return {boolean} Whether a folder above it holds `.git`.
 */
export function insideGitCheckout( file, exists ) {
	for (
		let folder = dirname( resolve( file ) );
		;
		folder = dirname( folder )
	) {
		if ( exists( join( folder, '.git' ) ) ) {
			return true;
		}
		// The root of a drive is its own folder above.
		if ( dirname( folder ) === folder ) {
			return false;
		}
	}
}

/**
 * The site's address, checked: HTTPS, or HTTP to a host that never leaves this machine.
 *
 * @param {string} address As the settings give it.
 * @return {string} The address without a trailing slash.
 */
export function checkedSite( address ) {
	let url;
	try {
		url = new URL( address );
	} catch {
		throw new UsageError(
			`The site's address is not an address: ${ address }`
		);
	}
	const local = LOCAL_HOST.test( url.hostname );
	if (
		url.protocol !== 'https:' &&
		! ( url.protocol === 'http:' && local )
	) {
		throw new UsageError(
			`The site's address must start with https:// (plain http:// only for a local site): ${ url.origin }`
		);
	}

	return `${ url.origin }${ url.pathname.replace( /\/+$/, '' ) }`;
}

/**
 * Where the site, the account and its Application Password come from, in the order the file's
 * header gives.
 *
 * @param {Object}                    context
 * @param {Object}                    context.asked    What the arguments asked.
 * @param {Object}                    context.env      The environment.
 * @param {string}                    context.home     The home folder.
 * @param {(path: string) => boolean} context.exists   Whether a path exists.
 * @param {(path: string) => string}  context.readFile Reads a file as text.
 * @return {{site: string, user: string, password: string, from: string}} The settings, and where they came from.
 */
export function resolveSettings( { asked, env, home, exists, readFile } ) {
	const file =
		asked.settingsPath ||
		env.COREX_PUSH_SETTINGS ||
		( ! env.COREX_PUSH_SITE && asked.client
			? join( home, '.corex', 'release-push', `${ asked.client }.json` )
			: null );

	if ( ! file ) {
		const { COREX_PUSH_SITE, COREX_PUSH_USER, COREX_PUSH_APP_PASSWORD } =
			env;
		if (
			! COREX_PUSH_SITE ||
			! COREX_PUSH_USER ||
			! COREX_PUSH_APP_PASSWORD
		) {
			throw new UsageError(
				'No settings: give --settings=<file>, or set COREX_PUSH_SETTINGS, or all three of COREX_PUSH_SITE, COREX_PUSH_USER and COREX_PUSH_APP_PASSWORD.'
			);
		}
		return {
			site: checkedSite( COREX_PUSH_SITE ),
			user: COREX_PUSH_USER,
			password: COREX_PUSH_APP_PASSWORD,
			from: 'the COREX_PUSH_* environment variables',
		};
	}

	if ( ! exists( file ) ) {
		throw new UsageError( `No settings file at ${ file }.` );
	}
	if ( insideGitCheckout( file, exists ) ) {
		throw new UsageError(
			`The settings file ${ file } is inside a git checkout, where it could be committed. Keep it outside, for example in ~/.corex/release-push/.`
		);
	}

	let settings;
	try {
		settings = JSON.parse( readFile( file ) );
	} catch {
		throw new UsageError( `The settings file ${ file } is not JSON.` );
	}
	if (
		! settings?.site ||
		! settings?.user ||
		! settings?.application_password
	) {
		throw new UsageError(
			`The settings file ${ file } needs "site", "user" and "application_password".`
		);
	}

	return {
		site: checkedSite( settings.site ),
		user: String( settings.user ),
		password: String( settings.application_password ),
		from: file,
	};
}

/**
 * `fetch`, signed with the Application Password, which goes into this header and nowhere else.
 * It notes WordPress's own refusal (wrong password, no right to the route), which is not the
 * installer's answer and which the screen's client reads as a session that expired.
 *
 * @param {typeof fetch} fetchImpl The real `fetch`.
 * @param {string}       user      The account.
 * @param {string}       password  Its Application Password.
 * @return {{fetch: typeof fetch, turnedAway: () => (Object|null)}} The signed `fetch`, and the last refusal of WordPress's.
 */
export function signedFetch( fetchImpl, user, password ) {
	const authorization = `Basic ${ Buffer.from(
		`${ user }:${ password }`
	).toString( 'base64' ) }`;
	let lastTurnedAway = null;

	const signed = async ( url, init = {} ) => {
		const response = await fetchImpl( url, {
			...init,
			headers: { ...init.headers, Authorization: authorization },
		} );
		if ( response.status === 401 || response.status === 403 ) {
			const text = await response.clone().text();
			try {
				const body = JSON.parse( text );
				if ( ! body?.corex_release && body?.code ) {
					lastTurnedAway = {
						status: response.status,
						code: body.code,
						message: body.message,
					};
				}
			} catch {
				// Not JSON: a host's own page. The client asks again, as it does for any.
			}
		}
		return response;
	};

	return { fetch: signed, turnedAway: () => lastTurnedAway };
}

/**
 * The file that holds an installation's key and recovery link while it stands (FR-015b).
 *
 * @param {string} folder Where such files are kept.
 * @param {string} site   The site's address.
 * @return {Object} `{ path, read, write, clear }`.
 */
export function stateFile( folder, site ) {
	const path = join(
		folder,
		`${ new URL( site ).host.replace( /[^A-Za-z0-9.-]/g, '_' ) }.json`
	);

	return {
		path,
		read() {
			try {
				return JSON.parse( readFileSync( path, 'utf8' ) );
			} catch {
				return null;
			}
		},
		write( state ) {
			mkdirSync( folder, { recursive: true, mode: 0o700 } );
			writeFileSync( path, JSON.stringify( state, null, '\t' ), {
				mode: 0o600,
			} );
			// A file that was already there keeps its old mode through `writeFileSync`.
			chmodSync( path, 0o600 );
		},
		clear() {
			rmSync( path, { force: true } );
		},
	};
}

/**
 * The SHA-256 of a file, read as a stream.
 *
 * @param {string} path The file.
 * @return {Promise<string>} Sixty-four lower-case hexadecimal digits.
 */
export function sha256OfFile( path ) {
	return new Promise( ( done, fail ) => {
		const hash = createHash( 'sha256' );
		createReadStream( path )
			.on( 'error', fail )
			.on( 'data', ( chunk ) => hash.update( chunk ) )
			.on( 'end', () => done( hash.digest( 'hex' ) ) );
	} );
}

/**
 * A package on disk in the shape the screen's client sends: its name, its size, and its parts.
 *
 * @param {string} path The package.
 * @return {Promise<Object>} `{ name, size, path, slice }`.
 */
async function openPackage( path ) {
	const blob = await openAsBlob( path );

	return {
		name: basename( path ),
		size: statSync( path ).size,
		path,
		slice: ( from, to ) => blob.slice( from, to ),
	};
}

/**
 * Build the client's package with `build:dist --zip`, showing its output, and give the zip's path.
 *
 * @param {Object}                 asked    What the arguments asked.
 * @param {string}                 repoRoot The checkout the command runs in.
 * @param {(line: string) => void} out      Writes a line.
 * @return {Promise<string>} The package's path.
 */
function buildPackage( asked, repoRoot, out ) {
	const args = [
		join( repoRoot, 'scripts', 'build-shared-host-dist.mjs' ),
		`--client=${ asked.client }`,
		...asked.withPlugins.map( ( plugin ) => `--with-plugin=${ plugin }` ),
		...( asked.pdfFonts ? [ `--pdf-fonts=${ asked.pdfFonts }` ] : [] ),
		'--zip',
	];

	return new Promise( ( done, fail ) => {
		let zip = null;
		const child = spawn( process.execPath, args, {
			cwd: repoRoot,
			stdio: [ 'ignore', 'pipe', 'inherit' ],
		} );
		child.stdout.setEncoding( 'utf8' );
		child.stdout.on( 'data', ( text ) => {
			for ( const line of text.split( /\r?\n/ ).filter( Boolean ) ) {
				out( `  ${ line }` );
				const named = /^package: (.+)$/.exec( line );
				zip = named ? resolve( repoRoot, named[ 1 ] ) : zip;
			}
		} );
		child.on( 'error', fail );
		child.on( 'close', ( code ) =>
			code === 0 && zip
				? done( zip )
				: fail( new Error( `build:dist stopped (exit ${ code })` ) )
		);
	} );
}

const megabytes = ( bytes ) => `${ ( bytes / 1048576 ).toFixed( 1 ) } MB`;

/**
 * What to tell the person about an installation that stands on the site.
 *
 * @param {Object} standing The site's account of it.
 * @return {string} One sentence.
 */
function standingSentence( standing ) {
	const at = {
		swap: 'as the release was being put in place',
		finish: "after the release's files were put in place, before its database step finished",
		stuck: 'part one release and part the other: a folder would not move',
	}[ standing.step ];

	return `An installation of CoreX ${ standing.version } (${
		standing.package
	}) stands on the site, ${ at ?? `at its "${ standing.step }" step, before anything of the site moved` }.`;
}

/**
 * Install a release on a site, from the arguments to the version it runs after.
 *
 * @param {Object} asked    What the arguments asked.
 * @param {Object} settings The site, the account and its password.
 * @param {Object} deps     What it works with: `fetch`, `createReleaseClient`, `runInstallation`,
 *                          `buildPackage`, `openPackage`, `sha256OfFile`, `state`, `out`, `wait`.
 * @return {Promise<number>} The exit code.
 */
export async function pushRelease( asked, settings, deps ) {
	const { out } = deps;
	const signed = signedFetch( deps.fetch, settings.user, settings.password );
	const restUrl = `${ settings.site }/?rest_route=/corex/v1/releases`;
	const clientWith = ( more = {} ) =>
		deps.createReleaseClient( {
			restUrl,
			fetch: signed.fetch,
			wait: deps.wait,
			...more,
		} );
	let client;
	let started = false;
	let preparedHere = false;

	const wordpressRefused = ( { status, code, message } ) =>
		`WordPress refused the account (${ status } ${ code }): ${ message } Check the user and its Application Password, and that the account may install releases (corex_manage_releases).`;

	const refusalOf = ( failure ) => {
		const wordpress = signed.turnedAway();
		if ( failure.name === 'ReleaseUnreachableError' && wordpress ) {
			return wordpressRefused( wordpress );
		}
		return failure.reason
			? `${ failure.message } (${ failure.reason })`
			: failure.message;
	};

	const theWayBack = ( failure ) => {
		if ( ! started ) {
			out( 'Nothing on the site changed.' );
			if ( preparedHere ) {
				// Prepared and never confirmed: the key opens nothing that will run.
				deps.state.clear();
			}
			return;
		}
		if ( ! failure.filesInPlace ) {
			out(
				'No folder of the site was moved: it still runs the release it ran.'
			);
			if ( failure.name === 'ReleaseRefusedError' ) {
				// A step the site refused ends the installation, on record (spec 107).
				deps.state.clear();
			} else {
				out(
					'If the installation still stands, run this command again with --resume, or give it up from CoreX > Releases.'
				);
			}
			return;
		}
		const kept = deps.state.read();
		out( "The release's folders may already be in place. Either:" );
		out(
			'  - run this command again with --resume, to go on with the installation; or'
		);
		out(
			'  - put the previous release back: CoreX > Releases on the site, "Go back", or the recovery link:'
		);
		out( `      ${ kept?.recovery_link ?? '(not kept on this machine)' }` );
		out(
			`The key and the link stay in ${ deps.state.path } until the installation ends.`
		);
	};

	out(
		`Site: ${ settings.site }, signed in as ${ settings.user } (settings from ${ settings.from })`
	);
	if ( ! ( await signsIn() ) ) {
		out( 'Nothing on the site changed.' );
		return EXIT.REFUSED;
	}
	client = clientWith();
	try {
		const opening = await client.overview();
		out(
			`It runs: ${
				opening.installed?.known
					? `CoreX ${ opening.installed.version }${ opening.installed.client ? ` (${ opening.installed.client })` : '' }`
					: 'a release it cannot name'
			}`
		);

		const standing = opening.installation;
		if ( standing && standing.step !== AWAITING ) {
			out( standingSentence( standing ) );
			if ( ! asked.resume ) {
				out(
					'Run the command again with --resume to go on with it, or deal with it from CoreX > Releases.'
				);
				return EXIT.REFUSED;
			}
			started = true;
			return await goOn( standing );
		}
		if ( asked.resume ) {
			out(
				'No installation stands on the site: there is nothing to go on with.'
			);
			return EXIT.DONE;
		}
		if ( opening.blockers?.length ) {
			opening.blockers.forEach( ( b ) =>
				out( `Blocked: ${ b.message }` )
			);
			return EXIT.REFUSED;
		}

		return await installAnew( opening );
	} catch ( failure ) {
		if ( ! EXPLAINED.includes( failure.name ) ) {
			// A usage error, or a fault of this command: neither is the site's answer.
			throw failure;
		}
		if ( failure.name === 'BuildError' ) {
			out( `The build failed: ${ failure.message }. Nothing was sent.` );
			return EXIT.BUILD;
		}
		out( `Stopped: ${ refusalOf( failure ) }` );
		if ( failure.name === 'ReleaseUnreachableError' && started ) {
			out( 'What happened on the site after that is not known.' );
		}
		theWayBack( failure );
		return failure.name === 'ReleaseUnreachableError' &&
			! signed.turnedAway()
			? EXIT.UNREACHABLE
			: EXIT.REFUSED;
	}

	async function installAnew( opening ) {
		const { name, sha256 } = await sendPackage( opening.part_bytes );
		if ( asked.uploadOnly ) {
			out(
				`On the site as ${ name }, SHA-256 ${ sha256 }. Not installed (--upload-only).`
			);
			return EXIT.DONE;
		}

		const statement = await client.inspect( name );
		const unmeant = whatIsNotMeant( statement, asked );
		if ( unmeant.length ) {
			unmeant.forEach( ( line ) => out( line ) );
			out( 'Nothing on the site changed.' );
			return statement.blockers?.length ? EXIT.REFUSED : EXIT.USAGE;
		}
		out(
			`Installing CoreX ${ statement.version }${ statement.client ? ` for ${ statement.client }` : '' }, in place of ${ statement.replaces ?? 'a release the site cannot name' }`
		);

		await prepareAndConfirm( name, statement.version );
		started = true;

		return finishAndCheck(
			await deps.runInstallation( client, progress() ),
			statement.version
		);
	}

	async function sendPackage( partBytes ) {
		const path = asked.packagePath
			? resolve( asked.packagePath )
			: await built();
		if ( ! A_PACKAGE_NAME.test( basename( path ) ) ) {
			throw new UsageError(
				`${ basename( path ) } is not a package's name: a site takes only corex-release-<name>.zip.`
			);
		}
		const file = await deps.openPackage( path );
		out( `Package: ${ file.name } (${ megabytes( file.size ) })` );

		let shown = -1;
		let sha256 = null;
		const name = await client.upload( file, {
			partBytes,
			hash: async ( f ) => ( sha256 = await deps.sha256OfFile( f.path ) ),
			onProgress: ( held ) => {
				const tenth = Math.floor( ( held / file.size ) * 10 );
				if ( tenth > shown ) {
					shown = tenth;
					out( `  on the site: ${ tenth * 10 }%` );
				}
			},
		} );

		return { name, sha256 };
	}

	async function prepareAndConfirm( name, version ) {
		const prepared = await client.prepare( name );
		deps.state.write( {
			site: settings.site,
			package: name,
			version,
			key: prepared.key,
			recovery_link: prepared.recovery_link,
		} );
		preparedHere = true;
		( prepared.drops ?? [] ).forEach( ( folder ) =>
			out( `  goes out with the previous release: ${ folder }` )
		);
		await client.confirm( version, {
			...( asked.client && { client: asked.client } ),
			older: asked.acceptOlder,
			downgrade: asked.acceptDowngrades,
		} );
	}

	/**
	 * Whether WordPress signs the account in with its Application Password. Asked of WordPress's
	 * own route once: a wrong password is a 401 the installer's client would take for a host's
	 * page and ask again for half a minute.
	 */
	async function signsIn() {
		const answered = await signed
			.fetch( `${ settings.site }/?rest_route=/wp/v2/users/me` )
			.then(
				( response ) => response.status,
				() => null
			);
		const wordpress = signed.turnedAway();
		if ( answered !== null && wordpress ) {
			out( `Stopped: ${ wordpressRefused( wordpress ) }` );
			return false;
		}
		// Anything else, unreachable included, is left to the installer's client, which waits and asks again.
		return true;
	}

	async function built() {
		out( `Building the package for ${ asked.client }:` );
		try {
			return await deps.buildPackage( asked );
		} catch ( error ) {
			error.name = 'BuildError';
			throw error;
		}
	}

	function progress() {
		return {
			onStand: ( stand ) =>
				out(
					`  ${ stand.step }${ stand.of ? ` ${ stand.cursor }/${ stand.of }` : '' }`
				),
			onWait: ( ms ) =>
				out(
					`  the site did not answer; asking again in ${ Math.round( ms / 1000 ) } s`
				),
			wait: deps.wait,
		};
	}

	async function goOn( standing ) {
		if ( standing.step === 'stuck' ) {
			theWayBack( { filesInPlace: true } );
			return EXIT.REFUSED;
		}
		const kept = deps.state.read();
		const key = kept?.package === standing.package ? kept.key : null;
		if ( key ) {
			out(
				'Going on with it, with the key the run that started it kept.'
			);
		} else {
			out(
				'No key for it is kept on this machine. If the site holds visitors off, WordPress lets requests in again by itself about ten minutes after the release was put in place: asking until then.'
			);
		}
		client = clientWith( key ? { key } : { tries: TRIES_WITHOUT_THE_KEY } );

		return finishAndCheck(
			await deps.runInstallation( client, {
				...progress(),
				from: standing.step,
			} ),
			standing.version
		);
	}

	async function finishAndCheck( installed, version ) {
		deps.state.clear();
		out(
			`Installed: CoreX ${ installed.version }${ installed.changes?.length ? `, ${ installed.changes.length } database change(s)` : '' }.`
		);

		let runs;
		try {
			runs = ( await client.overview() ).installed?.version;
		} catch ( failure ) {
			if ( ! EXPLAINED.includes( failure.name ) ) {
				throw failure;
			}
			// The installation is over; only the check is not. The catch below would say nothing moved.
			out(
				`The site finished the installation, then did not answer the check: ${ refusalOf( failure ) } CoreX > Releases on the site says which release it runs.`
			);
			return EXIT.UNREACHABLE;
		}
		const home = await deps
			.fetch( `${ settings.site }/`, { redirect: 'follow' } )
			.then(
				( response ) => response.status,
				() => null
			);
		out(
			`The site says it runs CoreX ${ runs ?? '(cannot say)' }; its home page answers ${ home ?? 'nothing' }.`
		);

		const goBack =
			'Look at it now; CoreX > Releases > "Go back" puts the previous release back.';
		if ( runs !== version ) {
			out( `That is not the release that was installed. ${ goBack }` );
			return EXIT.REFUSED;
		}
		if ( home === null || ( home >= 500 && home !== MAINTENANCE_STATUS ) ) {
			out( `The home page is not answering as it should. ${ goBack }` );
			return EXIT.REFUSED;
		}
		if ( home === MAINTENANCE_STATUS ) {
			out(
				`A home page that answers ${ MAINTENANCE_STATUS } is what a site in maintenance mode shows. If this site is not in maintenance mode: ${ goBack }`
			);
		}
		return EXIT.DONE;
	}
}

/**
 * What the screen would ask a person to accept that this run was not told is meant, or what
 * stands in the way, each as a line to show (FR-015a).
 *
 * @param {Object} statement What the site says of the package.
 * @param {Object} asked     What the arguments asked.
 * @return {string[]} Nothing when the installation may go on.
 */
export function whatIsNotMeant( statement, asked ) {
	const lines = ( statement.blockers ?? [] ).map(
		( b ) => `Blocked: ${ b.message }`
	);

	if (
		asked.client &&
		statement.client &&
		statement.client !== asked.client
	) {
		lines.push(
			`The package was built for ${ statement.client }, not ${ asked.client }.`
		);
	}
	if ( statement.client_unconfirmed && ! asked.client ) {
		lines.push(
			`The site cannot say which client it belongs to. Give --client=${ statement.client } to confirm it is this site's.`
		);
	}
	if ( statement.older && ! asked.acceptOlder ) {
		lines.push(
			`CoreX ${ statement.version } is older than the release the site runs (${ statement.replaces }). Give --accept-older if that is meant.`
		);
	}
	if ( statement.downgrades?.length && ! asked.acceptDowngrades ) {
		lines.push( 'The package holds older versions of what the site has:' );
		statement.downgrades.forEach( ( d ) =>
			lines.push(
				`  ${ d.name } (${ d.path }): ${ d.site } on the site, ${ d.package } in the package`
			)
		);
		lines.push(
			'Give --accept-downgrades if they are meant to go back to them.'
		);
	}

	return lines;
}

/**
 * The entry point: settings, then the installation, with the real world around it.
 *
 * @param {string[]} argv The arguments after the script's name.
 * @param {Object}   env  The environment.
 * @return {Promise<number>} The exit code.
 */
async function main( argv, env ) {
	const out = ( line ) => process.stdout.write( `${ line }\n` );
	try {
		const asked = parseArguments( argv );
		const settings = resolveSettings( {
			asked,
			env,
			home: homedir(),
			exists: existsSync,
			readFile: ( path ) => readFileSync( path, 'utf8' ),
		} );
		const repoRoot = process.cwd();

		// The screen's modules are plain `.js` with `import`, in a package that does not say so;
		// Node runs them and warns that it had to guess. The guess is right, so the warning goes.
		const others = process.listeners( 'warning' );
		process.removeAllListeners( 'warning' );
		process.on( 'warning', ( warning ) => {
			if ( warning.code !== 'MODULE_TYPELESS_PACKAGE_JSON' ) {
				others.forEach( ( listener ) => listener( warning ) );
			}
		} );
		const { createReleaseClient } =
			await import( '../plugins/corex-config/src/Releases/releaseClient.js' );
		const { runInstallation } =
			await import( '../plugins/corex-config/src/Releases/installRun.js' );

		return await pushRelease( asked, settings, {
			fetch: globalThis.fetch,
			createReleaseClient,
			runInstallation,
			buildPackage: ( what ) => buildPackage( what, repoRoot, out ),
			openPackage,
			sha256OfFile,
			state: stateFile(
				env.COREX_PUSH_STATE_DIR ||
					join( homedir(), '.corex', 'release-push', 'state' ),
				settings.site
			),
			out,
			wait: ( ms ) => new Promise( ( done ) => setTimeout( done, ms ) ),
		} );
	} catch ( failure ) {
		if ( failure instanceof UsageError ) {
			process.stderr.write( `release:push: ${ failure.message }\n` );
			return EXIT.USAGE;
		}
		throw failure;
	}
}

if ( basename( process.argv[ 1 ] ?? '' ) === 'release-push.mjs' ) {
	main( process.argv.slice( 2 ), process.env ).then(
		( code ) => process.exit( code ),
		( failure ) => {
			process.stderr.write(
				`release:push failed: ${ failure.stack ?? failure.message }\n` +
					'If it had started an installation, CoreX > Releases on the site says where it stands; running this command again with --resume goes on with it.\n'
			);
			process.exit( EXIT.FAULT );
		}
	);
}
