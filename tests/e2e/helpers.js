/**
 * Shared Playwright E2E helpers (spec 052): a console-error collector with a documented
 * allow-list. Admin authentication is handled once in `global-setup.js` (storageState),
 * so specs start already logged in and don't each re-run a flaky login.
 */
/**
 * Known, non-Corex console noise that must NOT fail the sweep (documented allow-list).
 * Keep this tiny and justified — the default is zero tolerated errors.
 */
const ALLOW_LIST = [
	/Failed to load resource: net::ERR_/i, // transient network/infra, not a code regression
	/favicon\.ico/i, // missing favicon on a bare dev install
];

/**
 * Attach console + pageerror listeners; returns the array of real errors (allow-list
 * filtered). Warnings are ignored — only errors fail the sweep (FR-004).
 *
 * @param {import('@playwright/test').Page} page The page to listen on.
 * @return {Array} The collected errors, filled as the page runs.
 */
function collectConsoleErrors( page ) {
	const errors = [];

	page.on( 'console', ( msg ) => {
		if ( msg.type() !== 'error' ) {
			return;
		}
		const text = msg.text();
		if ( ALLOW_LIST.some( ( re ) => re.test( text ) ) ) {
			return;
		}
		// Record where it came from. "Failed to load resource: 404" names no resource on its own,
		// which makes a failure report unactionable — you know something 404s, not what. Filtering
		// still happens on the raw text so the allow-list keeps matching.
		const { url } = msg.location();
		errors.push( url ? `${ text }  <- ${ url }` : text );
	} );

	page.on( 'pageerror', ( err ) => errors.push( err.message ) );

	return errors;
}

/**
 * A published flow with one real and one marked-test submission, so a spec that needs the inbox to
 * have rows can make some rather than skip.
 *
 * Lifted out of `submissions-inbox.spec.js` when a second spec needed it (spec 087). Two copies of
 * this would drift, and the one that drifted silently would be whichever spec was not the one
 * somebody was debugging.
 */
const { expect: helperExpect } = require( '@playwright/test' );

const FLOW_SLUG = 'corex-inbox-e2e';

/** Unique per run, so a seeded row is searchable to exactly one result on a shared site. */
const defaultEmail = () => `corex-inbox-e2e-${ Date.now() }@example.com`;

/**
 * The configuration the inbox fixture needs.
 *
 * `protection.captcha: 'off'` is load-bearing rather than incidental: this spec exercises the
 * inbox, and on any site where a captcha driver *is* configured the protection stage judges an
 * empty token and rejects every seeded submission. Opting the fixture out keeps the spec about
 * the inbox on a bare CI site and on a real one alike.
 */
const FIXTURE_CONFIGURATION = {
	schema: [
		{
			uuid: 'email-field',
			key: 'email',
			label: 'Email',
			type: 'email',
			required: true,
		},
	],
	validation: { email: [ 'required', 'email' ] },
	routing: { rules: [], fallback: { type: 'flow_owner', config: {} } },
	email_routes: [],
	success: { type: 'inline', message: 'Received.' },
	placement_snapshot: { type: 'block' },
	protection: { captcha: 'off' },
};

async function seedSubmission(
	page,
	slug = FLOW_SLUG,
	email = defaultEmail()
) {
	await page.goto( '/wp-admin/admin.php?page=corex-forms' );
	await helperExpect( page.getByText( 'Loading forms…' ) ).toBeHidden();
	return page.evaluate(
		async ( fixture ) => {
			const api = window.Corex.api;
			const config = window.corexFlows;
			const list = await api.get(
				`${ config.restUrl }?search=${ fixture.slug }`,
				{
					nonce: config.nonce,
				}
			);
			let flow = list.envelope.data.flows.find(
				( item ) => item.slug === fixture.slug
			);
			if ( ! flow ) {
				const created = await api.post(
					config.restUrl,
					{
						slug: fixture.slug,
						name: 'Inbox E2E flow',
						owner_id: config.ownerId,
						placement_type: 'block',
						configuration: fixture.configuration,
					},
					{ nonce: config.nonce }
				);
				flow = created.envelope.data.flow;
			}

			// Bring an existing fixture back to the shape this spec needs, rather than trusting
			// whatever state a previous run — or a person clicking around the builder — left it in.
			// The old seed only configured and published a flow it had just created, so once this
			// flow drifted, every test in the file failed permanently and told you nothing useful.
			let detail = await api.get( `${ config.restUrl }/${ flow.id }`, {
				nonce: config.nonce,
			} );
			let latest = detail.envelope.data.versions.at( -1 );
			const captchaOff =
				latest.configuration?.protection?.captcha === 'off';

			if ( ! captchaOff ) {
				await api.patch(
					`${ config.restUrl }/${ flow.id }`,
					{
						expected_version: latest.version_number,
						expected_checksum: latest.checksum,
						configuration: fixture.configuration,
					},
					{ nonce: config.nonce }
				);
				detail = await api.get( `${ config.restUrl }/${ flow.id }`, {
					nonce: config.nonce,
				} );
				latest = detail.envelope.data.versions.at( -1 );
			}

			if (
				detail.envelope.data.flow.state !== 'published' ||
				detail.envelope.data.flow.published_version !==
					latest.version_number
			) {
				if ( detail.envelope.data.flow.state === 'published' ) {
					await api.post(
						`${ config.restUrl }/${ flow.id }/unpublish`,
						{ expected_version: latest.version_number },
						{ nonce: config.nonce }
					);
				}
				await api.post(
					`${ config.restUrl }/${ flow.id }/publish`,
					{ expected_version: latest.version_number },
					{ nonce: config.nonce }
				);
				detail = await api.get( `${ config.restUrl }/${ flow.id }`, {
					nonce: config.nonce,
				} );
				latest = detail.envelope.data.versions.at( -1 );
			}

			const version = latest.version_number;
			const real = await api.post(
				`${ config.restUrl }/${ flow.id }/submit`,
				{ email: fixture.email, utm_source: 'playwright' },
				{ nonce: config.nonce }
			);
			const marked = await api.post(
				`${ config.restUrl }/${ flow.id }/test`,
				{
					expected_version: version,
					values: { email: 'marked-test@example.com' },
				},
				{ nonce: config.nonce }
			);
			return { real, marked };
		},
		{ slug, email, configuration: FIXTURE_CONFIGURATION }
	);
}

module.exports = { collectConsoleErrors, seedSubmission, FLOW_SLUG };
