/**
 * What a browser run leaves on the site it ran against (spec 116, US-NFR-09).
 *
 * The browser suite writes to a real site: it submits forms and seeds the inbox. Run against a
 * developer's site, it used to leave every submission it made there, and the inbox, Data, Insights
 * and the Settings counts showed test rows instead of the product. Now:
 *
 * - **Before the run**, the suite says which site it will write to, and refuses one whose CoreX
 *   operations mode is declared `production`. It removes the submissions earlier runs left that
 *   carry a run's marker, and notes the highest submission id, the mark this run starts from.
 * - **After the run**, it removes the submissions this run made that carry a marker, and fails if
 *   any submission this run made is still there, naming each one.
 *
 * A **marker** is what only a test puts on a submission: a flow whose slug contains `e2e`, a
 * `utm_source` of `playwright`, or, for a submission this run made, a sender at `example.com` or
 * `example.test`, the domains reserved for examples, which no person writes from. The last is
 * limited to this run's own rows because CI seeds the inbox with such rows before the run.
 *
 * The clean-up needs WP-CLI on the install COREX_BASE_URL serves (`wpEval`, helpers.js). Where
 * WP-CLI answers for another install, or not at all, it says so and leaves the site alone.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const MARK_FILE = path.join( __dirname, '.auth', 'residue-mark.json' );

/**
 * PHP that leaves the ids of marked submissions above `$from` in `$ids`.
 * @param {number} from    Only submissions with a higher id.
 * @param {number} ownFrom Submissions above this id are this run's, and a sender address marks them too.
 */
const markedSubmissions = ( from, ownFrom ) => `
global $wpdb;
$ids = $wpdb->get_col( $wpdb->prepare(
	"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
	LEFT JOIN {$wpdb->postmeta} slug ON slug.post_id = p.ID AND slug.meta_key = 'corex_form_slug'
	LEFT JOIN {$wpdb->postmeta} utm ON utm.post_id = p.ID AND utm.meta_key = 'corex_utm_json'
	LEFT JOIN {$wpdb->postmeta} sender ON sender.post_id = p.ID AND sender.meta_key IN ( 'corex_submitter_email', 'corex_field_email' )
	WHERE p.post_type = 'corex_submission' AND p.ID > %d AND (
		slug.meta_value LIKE %s OR utm.meta_value LIKE %s
		OR ( p.ID > %d AND ( sender.meta_value LIKE %s OR sender.meta_value LIKE %s ) )
	)",
	${ Number( from ) }, '%e2e%', '%playwright%', ${ Number( ownFrom ) }, '%@example.com', '%@example.test'
) );
`;

const REMOVE = `
foreach ( $ids as $id ) {
	wp_delete_post( (int) $id, true );
}
echo count( $ids );
`;

/**
 * Whether WP-CLI acts on the site under test. It reads `./wp` or COREX_WP_PATH, and a suite run
 * against another address must not clean an install the browser never touched.
 *
 * @param {(php: string) => (string|null)} wpEval  helpers.js's wpEval.
 * @param {string}                         baseUrl The site under test.
 * @return {boolean} Whether it does.
 */
function wpCliServes( wpEval, baseUrl ) {
	const home = wpEval( 'echo home_url();' );

	if ( home === null ) {
		return false;
	}

	try {
		return new URL( home ).origin === new URL( baseUrl ).origin;
	} catch {
		return false;
	}
}

/**
 * The CoreX operations mode of the site, read from its Operations screen as an administrator.
 *
 * @param {import('@playwright/test').Page} page A page signed in as an administrator.
 * @return {Promise<{mode: string, declared: boolean}>} The mode, and whether someone chose it.
 */
async function operationsMode( page ) {
	await page
		.goto(
			'/wp-admin/admin.php?page=corex-operations-security&tab=environment'
		)
		.catch( () => {} );
	const form = page.locator( '[data-corex-mode-form]' ).first();

	return {
		mode:
			( await form
				.getAttribute( 'data-current-mode', { timeout: 5_000 } )
				.catch( () => null ) ) || 'unknown',
		declared:
			( await form
				.getAttribute( 'data-mode-declared', { timeout: 1_000 } )
				.catch( () => null ) ) === '1',
	};
}

/**
 * Says where the suite will write, and refuses a production site. Run once, before any test.
 *
 * @param {import('@playwright/test').Page} page    A page signed in as an administrator.
 * @param {string}                          baseUrl The site under test.
 */
async function refuseProduction( page, baseUrl ) {
	const { mode, declared } = await operationsMode( page );

	process.stdout.write(
		`\nThe browser tests will write to ${ baseUrl } (CoreX operations mode: ${ mode }, ${
			declared
				? 'chosen on the site'
				: 'not chosen, read from the environment'
		}).\n`
	);

	if ( declared && mode === 'production' ) {
		throw new Error(
			`${ baseUrl } is a production site (its CoreX operations mode is Production). The browser tests submit forms and change settings, so they do not run there. Point COREX_BASE_URL at a scratch site.`
		);
	}
}

/**
 * Before the run: removes what earlier runs left, and notes where this run starts.
 *
 * @param {(php: string) => (string|null)} wpEval  helpers.js's wpEval.
 * @param {string}                         baseUrl The site under test.
 */
function startRun( wpEval, baseUrl ) {
	fs.mkdirSync( path.dirname( MARK_FILE ), { recursive: true } );

	if ( ! wpCliServes( wpEval, baseUrl ) ) {
		fs.writeFileSync( MARK_FILE, JSON.stringify( { from: null } ) );
		process.stdout.write(
			`WP-CLI does not answer for ${ baseUrl }: what this run leaves on it is not removed or counted.\n`
		);
		return;
	}

	const removed = wpEval(
		markedSubmissions( 0, Number.MAX_SAFE_INTEGER ) + REMOVE
	);
	const from = Number(
		wpEval(
			'global $wpdb; echo (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" );'
		)
	);

	fs.writeFileSync( MARK_FILE, JSON.stringify( { from } ) );
	process.stdout.write(
		`Removed ${ removed } submission(s) earlier runs left; this run's start from post id ${ from }.\n`
	);
}

/**
 * After the run: removes this run's marked submissions, and lists any it made that are left.
 *
 * @param {(php: string) => (string|null)} wpEval helpers.js's wpEval.
 * @return {string[]} One line per submission left, empty when the run left nothing.
 */
function finishRun( wpEval ) {
	const { from } = fs.existsSync( MARK_FILE )
		? JSON.parse( fs.readFileSync( MARK_FILE, 'utf8' ) )
		: { from: null };

	if ( ! Number.isInteger( from ) ) {
		return [];
	}

	wpEval( markedSubmissions( from, from ) + REMOVE );

	const left = wpEval(
		`global $wpdb; foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'corex_submission' AND ID > %d", ${ from } ) ) as $row ) { echo $row->ID . ' ' . str_replace( "\\n", ' ', $row->post_title ) . "\\n"; }`
	);

	return ( left || '' ).split( '\n' ).filter( Boolean );
}

module.exports = { refuseProduction, startRun, finishRun, markedSubmissions };
