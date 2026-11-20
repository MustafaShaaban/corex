#!/usr/bin/env node
/**
 * The CoreX theme's preview image and its fingerprint (spec 134, US-BRD-07, FR-025 to FR-027;
 * DECISIONS #638).
 *
 *   npm run theme:preview -- --url=<address> --wp-path=<WordPress folder>
 *
 * Captures the front page of a scratch site at 1200 by 900 into theme/screenshot.png, and writes
 * theme/screenshot.fingerprint.json beside it: a SHA-256 hash of the colours of theme/theme.json
 * and of theme/styles/dark.json. tests/theme-preview.test.js recomputes the hashes from the two
 * files and fails, naming this command, when a colour changed after the capture.
 *
 * What a hash covers, per file: `settings.color.palette` as slug to colour, `settings.custom.color`
 * whole, and `settings.shadow.presets` as slug to shadow. Each is read into an object whose keys
 * are sorted at every depth and serialised as JSON with no whitespace, so the order of keys or
 * entries, the file's layout and a colour's display name do not change the hash; a value does,
 * down to its case and its spaces.
 *
 * The site is named on purpose (`--url` or COREX_BASE_URL, `--wp-path` or COREX_WP_PATH; no
 * defaults). The command runs no write of its own: it loads WordPress once through WP-CLI and
 * requests the front page once, as a visitor would. WP-CLI is started with no shell, so a path is
 * passed as it is (wpCliCommand() says how it is found). The command refuses, before it opens a
 * browser, a site that is not a clean scratch site: CoreX must read it as a development site, the
 * CoreX theme of this checkout must be the active theme with no style chosen and no template
 * changed, the Company kit must be applied with its front page as the site's, and every published
 * post and page must be the kit's or WordPress's own sample. In the browser it refuses a page
 * whose colours are not the files' (so the image and the fingerprint describe the same colours),
 * and a page that shows CoreX product copy. Nothing is written unless every check passed.
 * theme/README.md, "The preview image", says how to make such a site.
 */
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import {
	existsSync,
	mkdtempSync,
	readFileSync,
	realpathSync,
	rmSync,
	statSync,
	writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { parseArgs } from 'node:util';

export const COMMAND = 'npm run theme:preview';
export const IMAGE = 'theme/screenshot.png';
export const FINGERPRINT = 'theme/screenshot.fingerprint.json';
export const SOURCES = [ 'theme/theme.json', 'theme/styles/dark.json' ];
export const SIZE = { width: 1200, height: 900 };

const WHAT_TO_DO = `Run \`${ COMMAND }\` on a scratch site (theme/README.md, "The preview image") and commit the new ${ IMAGE } with ${ FINGERPRINT }.`;

/** What the site-state PHP prints before its answer, so WordPress's own output cannot be taken for it. */
const SITE_MARKER = 'COREX-PREVIEW-SITE ';

const PNG_SIGNATURE = Buffer.from( [ 137, 80, 78, 71, 13, 10, 26, 10 ] );

/** The words that make a page CoreX's own copy rather than neutral sample content. */
const PRODUCT_COPY = /\bcore ?x\b|discipline, at every layer|\bframework\b/gi;

/**
 * WordPress's own content, which a clean site holds beside the kit's pages: the post and the page
 * an install writes, and the menu the Navigation block writes the first time it draws with none
 * (one Page List block, which lists the pages).
 */
const WORDPRESS_SAMPLES = [
	'post: hello-world',
	'page: sample-page',
	'wp_navigation: page list',
];

/**
 * The colours the fingerprint covers, from one parsed theme.json or style variation.
 *
 * @param {Object} json The parsed file.
 * @return {{palette: Object, custom: Object, shadow: Object}} Slug to colour, the custom colours, slug to shadow.
 */
export function colourTokens( json ) {
	const settings = json?.settings ?? {};
	const bySlug = ( entries, key ) =>
		Object.fromEntries(
			( entries ?? [] ).map( ( entry ) => [ entry.slug, entry[ key ] ] )
		);

	return {
		palette: bySlug( settings.color?.palette, 'color' ),
		custom: settings.custom?.color ?? {},
		shadow: bySlug( settings.shadow?.presets, 'shadow' ),
	};
}

/**
 * A value as JSON with its object keys sorted at every depth and no whitespace.
 *
 * @param {string|number|boolean|null|Object|Array} value Any JSON value.
 * @return {string} The canonical text.
 */
export function canonical( value ) {
	if ( Array.isArray( value ) ) {
		return `[${ value.map( canonical ).join( ',' ) }]`;
	}

	if ( value !== null && typeof value === 'object' ) {
		const keys = Object.keys( value ).sort();

		return `{${ keys
			.map(
				( key ) =>
					`${ JSON.stringify( key ) }:${ canonical( value[ key ] ) }`
			)
			.join( ',' ) }}`;
	}

	return JSON.stringify( value );
}

/**
 * The hash of one file's colours.
 *
 * @param {string} root The repository root.
 * @param {string} file A file of SOURCES, relative to the root.
 * @return {string} The SHA-256 hash, in hexadecimal.
 */
function hashOf( root, file ) {
	const json = JSON.parse( readFileSync( path.join( root, file ), 'utf8' ) );

	return createHash( 'sha256' )
		.update( canonical( colourTokens( json ) ), 'utf8' )
		.digest( 'hex' );
}

/**
 * The fingerprint the files give now, as written beside the image.
 *
 * @param {string} root The repository root.
 * @return {Object} The fingerprint.
 */
export function fingerprintOf( root ) {
	return {
		command: COMMAND,
		image: path.basename( IMAGE ),
		width: SIZE.width,
		height: SIZE.height,
		algorithm: 'sha256',
		covers: [
			'settings.color.palette, slug to color',
			'settings.custom.color',
			'settings.shadow.presets, slug to shadow',
		],
		canonical: 'keys sorted at every depth, JSON with no whitespace, UTF-8',
		sources: Object.fromEntries(
			SOURCES.map( ( file ) => [ file, hashOf( root, file ) ] )
		),
	};
}

/**
 * What makes the image older than the colours: one message per file whose colours changed after
 * the capture, each naming the command. Empty when the image is current.
 *
 * @param {string} root The repository root.
 * @return {string[]} The messages.
 */
export function staleness( root ) {
	let written;

	try {
		written = JSON.parse(
			readFileSync( path.join( root, FINGERPRINT ), 'utf8' )
		);
	} catch {
		return [
			`${ FINGERPRINT } is missing or unreadable, so nothing says which colours ${ IMAGE } shows. ${ WHAT_TO_DO }`,
		];
	}

	const now = fingerprintOf( root ).sources;

	return SOURCES.filter(
		( file ) => written?.sources?.[ file ] !== now[ file ]
	).map(
		( file ) =>
			`The colours of ${ file } (its palette, custom colours or shadows) changed after ${ IMAGE } was captured. ${ WHAT_TO_DO }`
	);
}

/**
 * The size of a PNG, read from its header.
 *
 * @param {Buffer} buffer The file's bytes.
 * @return {{width: number, height: number}} The size in pixels.
 */
export function pngSize( buffer ) {
	if (
		buffer.length < 24 ||
		! buffer.subarray( 0, 8 ).equals( PNG_SIGNATURE ) ||
		buffer.toString( 'latin1', 12, 16 ) !== 'IHDR'
	) {
		throw new Error( 'The file is not a PNG.' );
	}

	return {
		width: buffer.readUInt32BE( 16 ),
		height: buffer.readUInt32BE( 20 ),
	};
}

/**
 * The CoreX product copy in a text, each phrase once, in the order found.
 *
 * @param {string} text The text of a page.
 * @return {string[]} The phrases.
 */
export function productCopy( text ) {
	return [ ...new Set( String( text ).match( PRODUCT_COPY ) ?? [] ) ];
}

/**
 * A custom key as WordPress names its property: `bandInk` is `band-ink`.
 *
 * @param {string} key The key in theme.json.
 * @return {string} The key in the property's name.
 */
const kebab = ( key ) =>
	key
		.replace( /([a-z0-9])([A-Z])/g, '$1-$2' )
		.replace( /([a-zA-Z])([0-9])/g, '$1-$2' )
		.toLowerCase();

/**
 * The custom properties WordPress prints for the colours, with the value each should have.
 *
 * @param {{palette: Object, custom: Object, shadow: Object}} tokens colourTokens() of a file.
 * @return {Array<[string, string]>} Property and value.
 */
export function customProperties( tokens ) {
	const custom = ( object, prefix ) =>
		Object.entries( object ).flatMap( ( [ key, value ] ) =>
			value !== null && typeof value === 'object'
				? custom( value, `${ prefix }--${ kebab( key ) }` )
				: [ [ `${ prefix }--${ kebab( key ) }`, String( value ) ] ]
		);

	return [
		...Object.entries( tokens.palette ).map( ( [ slug, colour ] ) => [
			`--wp--preset--color--${ slug }`,
			colour,
		] ),
		...custom( tokens.custom, '--wp--custom--color' ),
		...Object.entries( tokens.shadow ).map( ( [ slug, shadow ] ) => [
			`--wp--preset--shadow--${ slug }`,
			shadow,
		] ),
	];
}

const sameValue = ( a, b ) =>
	String( a ).trim().replace( /\s+/g, ' ' ).toLowerCase() ===
	String( b ).trim().replace( /\s+/g, ' ' ).toLowerCase();

/**
 * Where the colours a page serves differ from a file's, one message per property.
 *
 * @param {{palette: Object, custom: Object, shadow: Object}} tokens colourTokens() of the file.
 * @param {Object<string, string>}                            served Property to the value the page computes.
 * @param {string}                                            source The file's name, for the message.
 * @return {string[]} The messages.
 */
export function colourDifferences( tokens, served, source = 'theme.json' ) {
	return customProperties( tokens )
		.filter(
			( [ property, value ] ) =>
				! sameValue( served[ property ] ?? '', value )
		)
		.map(
			( [ property, value ] ) =>
				`${ property } is ${ served[ property ] || '(not set)' }, ${ source } has ${ value }`
		);
}

/**
 * Whether two paths name one folder: separators, a trailing separator and, on Windows, case aside.
 *
 * @param {string} a A path.
 * @param {string} b Another.
 * @return {boolean} Whether they are the same.
 */
const sameFolder = ( a, b ) => {
	const normal = ( value ) => {
		const text = String( value )
			.replace( /\\/g, '/' )
			.replace( /\/+$/, '' );

		return process.platform === 'win32' ? text.toLowerCase() : text;
	};

	return normal( a ) === normal( b );
};

/**
 * Why a site is not the scratch site the capture needs, one message per reason. Empty when it is.
 *
 * @param {Object}   site              The site as WP-CLI read it (SITE_STATE below).
 * @param {string}   site.home         Its home address.
 * @param {string}   site.environment  CoreX's reading of it: development, staging or production.
 * @param {string}   site.stylesheet   The active theme.
 * @param {string}   site.template     The active theme's parent, or itself.
 * @param {string}   site.themeDir     The active theme's folder, with links resolved.
 * @param {string[]} site.userStyles   The parts of the site's own styles that hold something.
 * @param {string[]} site.customised   Templates and parts changed in the Site Editor.
 * @param {string[]} site.kits         The kits applied.
 * @param {string}   site.showOnFront  What the front page shows.
 * @param {Object}   site.front        The front page: its slug, and whether the kit wrote it.
 * @param {string}   site.kitFront     The slug of the page the Company kit makes the front page.
 * @param {string[]} site.otherContent Published posts, pages and menus the kit did not write.
 * @param {Object}   where             What the site must match.
 * @param {string}   where.url         The address the capture opens.
 * @param {string}   where.themeDir    This checkout's theme folder.
 * @return {string[]} The messages.
 */
export function refusals( site, { url, themeDir } ) {
	const address = ( value ) => {
		try {
			return new URL( value ).href.replace( /\/?$/, '/' );
		} catch {
			return String( value );
		}
	};
	const theme = site.stylesheet === 'corex' && site.template === 'corex';
	const otherContent = site.otherContent.filter(
		( item ) => ! WORDPRESS_SAMPLES.includes( item )
	);
	const checks = [
		[
			address( site.home ) === address( url ),
			`WP-CLI reads a site at ${ site.home }, not ${ url }: point --wp-path at the WordPress that serves --url.`,
		],
		[
			site.environment === 'development',
			`CoreX reads this site as ${ site.environment }. The preview is captured on a development scratch site only (WP_ENVIRONMENT_TYPE local or development, and no production, staging, Coming soon or Maintenance mode).`,
		],
		[
			theme,
			`The active theme is ${ site.stylesheet }, not the CoreX theme (corex).`,
		],
		[
			! theme || sameFolder( site.themeDir, themeDir ),
			`The CoreX theme the site runs is ${ site.themeDir }, not this checkout's ${ themeDir }: link this checkout's theme/ as wp-content/themes/corex.`,
		],
		[
			site.userStyles.length === 0,
			`The site has a style chosen or styles of its own (${ site.userStyles.join(
				', '
			) }). The preview shows the default style: reset the styles in the Site Editor.`,
		],
		[
			site.customised.length === 0,
			`Templates or parts were changed in the Site Editor (${ site.customised.join(
				', '
			) }). The preview shows the theme's own.`,
		],
		[ site.kits.includes( 'company' ), 'The Company kit is not applied.' ],
		[
			site.showOnFront === 'page' &&
				site.front.kit &&
				site.front.slug === site.kitFront,
			`The front page is not the Company kit's front page (${ site.kitFront }).`,
		],
		[
			otherContent.length === 0,
			`The site holds content that is not the Company kit's sample content (${ otherContent.join(
				', '
			) }), so it is not a clean scratch site.`,
		],
	];

	return checks
		.filter( ( [ passes ] ) => ! passes )
		.map( ( [ , why ] ) => why );
}

/**
 * PHP that prints the site's state on its last line, after a marker, for refusals(). Class names
 * and constants are CoreX's own, so the option, the meta key and the kit's front page cannot drift.
 * It only reads.
 */
const SITE_STATE = `<?php
$kitMeta   = \\Corex\\Provisioning\\PageDisposition::META;
$container = \\Corex\\Boot::app()->container();
$kits      = class_exists('\\Corex\\Kit\\Provisioning\\KitAppliedRecord') ? (array) get_option(\\Corex\\Kit\\Provisioning\\KitAppliedRecord::OPTION, []) : [];
$blueprint = class_exists('\\Corex\\Kit\\BlueprintRegistry') ? $container->make(\\Corex\\Kit\\BlueprintRegistry::class)->find('company') : null;
$kitFront  = '';
foreach ($blueprint ? $blueprint->pages() : [] as $kitPage) {
    if (! empty($kitPage['front'])) {
        $kitFront = (string) $kitPage['slug'];
    }
}
$front = (int) get_option('page_on_front');
$user  = WP_Theme_JSON_Resolver::get_user_data()->get_raw_data();
unset($user['version'], $user['isGlobalStylesUserThemeJSON']);
$label = static fn (WP_Post $post): string => $post->post_type === 'wp_navigation' && trim($post->post_content) === '<!-- wp:page-list /-->'
    ? 'wp_navigation: page list'
    : $post->post_type . ': ' . $post->post_name;
$others = get_posts(['post_type' => ['post', 'page', 'wp_navigation'], 'post_status' => 'publish', 'numberposts' => -1, 'meta_query' => [['key' => $kitMeta, 'compare' => 'NOT EXISTS']]]);
$customised = get_posts(['post_type' => ['wp_template', 'wp_template_part'], 'post_status' => 'any', 'numberposts' => -1, 'tax_query' => [['taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => get_stylesheet()]]]);
echo "\\nCOREX-PREVIEW-SITE " . wp_json_encode([
    'home'         => home_url('/'),
    'environment'  => $container->make(\\Corex\\Environment\\SiteEnvironment::class)->current()->environment,
    'stylesheet'   => get_stylesheet(),
    'template'     => get_template(),
    'themeDir'     => (string) realpath(get_stylesheet_directory()),
    'userStyles'   => array_keys(array_filter($user)),
    'customised'   => array_map($label, $customised),
    'kits'         => array_values($kits),
    'showOnFront'  => (string) get_option('show_on_front'),
    'front'        => ['slug' => $front > 0 ? (string) get_post_field('post_name', $front) : '', 'kit' => $front > 0 && get_post_meta($front, $kitMeta, true) !== ''],
    'kitFront'     => $kitFront,
    'otherContent' => array_map($label, $others),
]);
`;

/**
 * How to run WP-CLI with no shell between this script and it, so a path is passed as it is.
 *
 * `COREX_WP_CLI` names a WP-CLI phar or `php/boot-fs.php`, run with `WP_CLI_PHP` or `php`. Otherwise
 * `wp` is run, except on Windows, where it is a batch file Node will not start without a shell: there
 * the PHP behind the first `wp.bat` on the PATH is run instead, Composer's `boot-fs.php` or a phar
 * beside it.
 *
 * @param {Object}                    context          Where to look.
 * @param {string}                    context.platform `process.platform`.
 * @param {Object<string, string>}    context.env      The environment.
 * @param {(file: string) => boolean} context.exists   Whether a file exists.
 * @return {{file: string, prefix: string[]}|null} The program and its first arguments, or null.
 */
export function wpCliCommand( { platform, env, exists } ) {
	const php = env.WP_CLI_PHP || 'php';

	if ( env.COREX_WP_CLI ) {
		return { file: php, prefix: [ env.COREX_WP_CLI ] };
	}
	if ( platform !== 'win32' ) {
		return { file: 'wp', prefix: [] };
	}

	const folders = ( env.PATH ?? env.Path ?? '' )
		.split( ';' )
		.filter( Boolean );

	for ( const folder of folders ) {
		if ( ! exists( path.win32.join( folder, 'wp.bat' ) ) ) {
			continue;
		}

		const entry = [
			path.win32.join(
				folder,
				'..',
				'wp-cli',
				'wp-cli',
				'php',
				'boot-fs.php'
			),
			path.win32.join( folder, 'wp-cli.phar' ),
			path.win32.join( folder, 'wp.phar' ),
		].find( exists );

		if ( entry ) {
			return { file: php, prefix: [ entry ] };
		}
	}

	return null;
}

/**
 * The site's state, read with one WP-CLI run that loads WordPress once.
 *
 * @param {string} wpPath The WordPress folder.
 * @return {{site: Object}|{error: string}} The state, or why there is none.
 */
function readSite( wpPath ) {
	const command = wpCliCommand( {
		platform: process.platform,
		env: process.env,
		exists: existsSync,
	} );

	if ( command === null ) {
		return {
			error: 'WP-CLI was not found. Set COREX_WP_CLI to a WP-CLI phar, or to the php/boot-fs.php of a Composer install of WP-CLI.',
		};
	}

	const folder = mkdtempSync( path.join( tmpdir(), 'corex-preview-' ) );
	const file = path.join( folder, 'site-state.php' );

	try {
		writeFileSync( file, SITE_STATE );
		const run = spawnSync(
			command.file,
			[ ...command.prefix, `--path=${ wpPath }`, 'eval-file', file ],
			{ cwd: wpPath, encoding: 'utf8', windowsHide: true }
		);
		const line = ( run.stdout ?? '' )
			.split( /\r?\n/ )
			.find( ( text ) => text.startsWith( SITE_MARKER ) );

		if ( run.error || run.status !== 0 || ! line ) {
			return {
				error: [
					`WP-CLI (${ [ command.file, ...command.prefix ].join(
						' '
					) }) gave no site state for ${ wpPath }:`,
					run.error?.message,
					run.stderr?.trim(),
					run.stdout?.trim(),
				]
					.filter( Boolean )
					.join( '\n' ),
			};
		}

		return { site: JSON.parse( line.slice( SITE_MARKER.length ) ) };
	} finally {
		rmSync( folder, { recursive: true, force: true } );
	}
}

/**
 * Opens the front page as a visitor would, once.
 *
 * @param {import('@playwright/test').Browser} browser The browser.
 * @param {string}                             url     The front page.
 * @return {Promise<{page: Object}|{problem: string}>} The page, or why it did not open.
 */
async function openFrontPage( browser, url ) {
	const page = await browser.newPage( {
		viewport: SIZE,
		deviceScaleFactor: 1,
		colorScheme: 'light',
		reducedMotion: 'reduce',
		locale: 'en-US',
	} );
	// A cold PHP server can take most of a minute to answer the first request.
	const response = await page.goto( url, {
		waitUntil: 'networkidle',
		timeout: 120_000,
	} );

	if ( ! response || response.status() !== 200 ) {
		return {
			problem: `${ url } answered ${
				response ? response.status() : 'nothing'
			}, not 200.`,
		};
	}

	await page.evaluate( () => document.fonts.ready );

	return { page };
}

/**
 * The values the page computes for the colour properties, on its root.
 *
 * @param {Object}                                            page   The open page.
 * @param {{palette: Object, custom: Object, shadow: Object}} tokens colourTokens() of theme.json.
 * @return {Promise<Object<string, string>>} Property to value.
 */
function servedColours( page, tokens ) {
	return page.evaluate(
		( names ) => {
			const style = document.defaultView.getComputedStyle(
				document.documentElement
			);

			return Object.fromEntries(
				names.map( ( name ) => [
					name,
					style.getPropertyValue( name ),
				] )
			);
		},
		customProperties( tokens ).map( ( [ name ] ) => name )
	);
}

/**
 * Every word the page shows or names: its title, its text, and its alternative texts and labels.
 *
 * @param {Object} page The open page.
 * @return {Promise<string>} The words.
 */
function pageWords( page ) {
	return page.evaluate( () =>
		[
			document.title,
			document.body.innerText,
			...[
				...document.querySelectorAll( '[alt], [aria-label], [title]' ),
			].map( ( element ) =>
				[ 'alt', 'aria-label', 'title' ]
					.map( ( name ) => element.getAttribute( name ) ?? '' )
					.join( ' ' )
			),
		].join( '\n' )
	);
}

/**
 * Why a page may not be the preview: colours other than the file's, or CoreX product copy.
 *
 * @param {{palette: Object, custom: Object, shadow: Object}} tokens colourTokens() of theme.json.
 * @param {Object<string, string>}                            served What the page computes.
 * @param {string}                                            words  What the page shows.
 * @return {string[]} The messages.
 */
export function pageProblems( tokens, served, words ) {
	return [
		...colourDifferences( tokens, served, SOURCES[ 0 ] ),
		...productCopy( words ).map(
			( phrase ) =>
				`The page shows CoreX product copy ("${ phrase }"); the preview shows neutral sample content.`
		),
	];
}

/**
 * The viewport as it is drawn.
 *
 * @param {Object} page The open page.
 * @return {Promise<Buffer>} The PNG.
 */
function shoot( page ) {
	return page.screenshot( {
		animations: 'disabled',
		caret: 'hide',
		scale: 'css',
	} );
}

/**
 * Opens the front page, judges it, and takes the image when it passes.
 *
 * @param {string}                                            url    The front page.
 * @param {{palette: Object, custom: Object, shadow: Object}} tokens colourTokens() of theme.json.
 * @return {Promise<{problems: string[], image?: Buffer}>} The image, or why there is none.
 */
async function capture( url, tokens ) {
	const { chromium } = await import( '@playwright/test' );
	// Greyscale text edges: subpixel (LCD) text follows the screen it was drawn for.
	const browser = await chromium.launch( { args: [ '--disable-lcd-text' ] } );

	try {
		const opened = await openFrontPage( browser, url );

		if ( opened.problem ) {
			return { problems: [ opened.problem ] };
		}

		const problems = pageProblems(
			tokens,
			await servedColours( opened.page, tokens ),
			await pageWords( opened.page )
		);

		return problems.length > 0
			? { problems }
			: { problems: [], image: await shoot( opened.page ) };
	} finally {
		await browser.close();
	}
}

/** What the command reaches outside itself: WP-CLI, the browser and the terminal. */
const BOUNDARY = {
	readSite,
	capture,
	log: ( line ) => console.log( line ),
	error: ( line ) => console.error( line ),
};

/**
 * Whether a folder holds a WordPress (its `wp-load.php`).
 *
 * @param {string} folder The folder.
 * @return {boolean} Whether it does.
 */
const holdsWordPress = ( folder ) =>
	existsSync( folder ) &&
	statSync( folder ).isDirectory() &&
	existsSync( path.join( folder, 'wp-load.php' ) );

/**
 * The command: refuse, or capture and write the image with its fingerprint. Nothing is written
 * unless every check passed.
 *
 * @param {string}   root     The repository root.
 * @param {string[]} args     The command-line arguments.
 * @param {Object}   boundary WP-CLI, the browser and the terminal (BOUNDARY; a test stubs them).
 * @return {Promise<number>} The exit code.
 */
export async function main( root, args, boundary = BOUNDARY ) {
	const { values } = parseArgs( {
		args,
		options: {
			url: { type: 'string', default: process.env.COREX_BASE_URL ?? '' },
			'wp-path': {
				type: 'string',
				default: process.env.COREX_WP_PATH ?? '',
			},
		},
	} );
	const url = values.url;
	const wpPath = values[ 'wp-path' ] && path.resolve( values[ 'wp-path' ] );
	const refuse = ( problems ) => {
		boundary.error( `Nothing was captured from ${ url || 'any site' }:` );
		problems.forEach( ( problem ) => boundary.error( `  - ${ problem }` ) );
		return 1;
	};

	if ( ! url || ! wpPath ) {
		return refuse( [
			`Name the scratch site: ${ COMMAND } -- --url=<its address> --wp-path=<its WordPress folder> (or COREX_BASE_URL and COREX_WP_PATH). theme/README.md, "The preview image", says how to make one.`,
		] );
	}
	if ( ! holdsWordPress( wpPath ) ) {
		return refuse( [
			`${ wpPath } is not a WordPress folder (no wp-load.php in it).`,
		] );
	}

	const read = boundary.readSite( wpPath );

	if ( read.error ) {
		return refuse( [ read.error ] );
	}

	const themeDir = realpathSync( path.join( root, 'theme' ) );
	const refused = refusals( read.site, { url, themeDir } );

	if ( refused.length > 0 ) {
		return refuse( refused );
	}

	const tokens = colourTokens(
		JSON.parse( readFileSync( path.join( root, SOURCES[ 0 ] ), 'utf8' ) )
	);
	const { problems, image } = await boundary.capture( url, tokens );

	if ( problems.length > 0 ) {
		return refuse( problems );
	}

	const size = pngSize( image );
	const fingerprint = fingerprintOf( root );
	writeFileSync( path.join( root, IMAGE ), image );
	writeFileSync(
		path.join( root, FINGERPRINT ),
		`${ JSON.stringify( fingerprint, null, '\t' ) }\n`
	);

	boundary.log(
		`${ IMAGE }: ${ size.width } by ${ size.height }, from ${ url }`
	);
	Object.entries( fingerprint.sources ).forEach( ( [ file, hash ] ) =>
		boundary.log( `${ FINGERPRINT }: ${ file } ${ hash }` )
	);

	return 0;
}

// Run as a command, not when a test imports it: `import.meta` does not survive Jest's transform.
if ( path.basename( process.argv[ 1 ] ?? '' ) === 'theme-preview.mjs' ) {
	const root = path.resolve( path.dirname( process.argv[ 1 ] ), '..' );
	main( root, process.argv.slice( 2 ) ).then(
		( code ) => {
			process.exitCode = code;
		},
		( error ) => {
			console.error(
				`The capture stopped: ${ error.message }\nNothing was written. Check that Playwright's Chromium is installed (npx playwright install chromium) and that --url serves the scratch site.`
			);
			process.exitCode = 1;
		}
	);
}
