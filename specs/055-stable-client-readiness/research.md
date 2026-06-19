# Research: Stable Client Readiness (055)

Phase 0 research records the planning decisions that resolve spec 055's technical unknowns without implementing
them. The recurring rule is: prove readiness with small, pure checks first; only then wire the minimum runtime or CI
surface needed by tasks.

## D1 - No clarification gap before planning

**Decision:** proceed directly to `/speckit-plan`; do not run `/speckit-clarify`.

**Rationale:** `checklists/requirements.md` has every requirement box checked, no `[NEEDS CLARIFICATION]` markers
exist in the spec, and the scope boundaries are explicit: framework readiness only, no final visual redesign, and no
implementation before tasks.

**Alternatives considered:** ask more questions about the first two client sites. Rejected for planning because the
spec intentionally targets framework readiness before client-specific branding details.

## D2 - Runtime gating authority

**Decision:** add-on runtime gating should be resolved below the admin Add-ons UI, before optional service providers
boot. The planned shape is a pure provider-resolution contract in core/runtime foundation code that consumes known
provider metadata, feature flags, dependency probes, and explicit activation state, then returns the provider classes
safe to pass into `Application`.

**Rationale:** `plugins/corex-core/src/Boot.php` currently lists every provider directly, including add-ons. That
means a disabled add-on can still register hooks, routes, blocks, admin menus, assets, migrations, tables, or cron if
its provider boots. The `corex-config` Add-ons screen is useful UI, but the runtime safety property must exist before
UI and before provider boot.

**Alternatives considered:** rely only on `AddonManager` in `corex-config` (rejected: it manages toggle decisions,
not boot); add guards inside every add-on provider (rejected as the only solution because it is easy to forget and
does not centralize evidence); hard-disable by WordPress plugin activation only (rejected because Corex loads
junctioned add-ons through its own provider list).

## D3 - WooCommerce dependency gating

**Decision:** keep the existing pure `WooKitGate` pattern, but require the runtime provider resolver and Woo provider
tests to prove Woo-specific behavior is absent unless both WooCommerce is available and the Corex kit state enables
it.

**Rationale:** Principle IX requires Corex to run fully without WooCommerce. `WooKitGate` already models
`wooActive && feature flag`, but the Woo service provider is still in the unconditional boot list today. Spec 055
must prove both the pure decision and the boot result.

**Alternatives considered:** check `class_exists('WooCommerce')` directly inside many methods (rejected: scattered
optional dependency checks); make WooCommerce a composer/plugin requirement (constitution violation).

## D4 - Metadata/version consistency check

**Decision:** implement or document a single metadata consistency check that reads the same release surfaces the
project already stamps or publishes: root `package.json`, root `composer.json`, plugin/add-on headers, version
constants, Update URI headers, README, CHANGELOG, PROGRESS, docs references, and tags/release notes where available.
It should report exact file/value mismatches rather than silently normalizing them.

**Rationale:** `VersionPlan` already stamps headers/constants; spec 055 needs the inverse audit: confirm all release
surfaces agree. The root `package.json` currently has its own version value, so the check must distinguish framework
package metadata from release narrative and report precise mismatches.

**Alternatives considered:** rely on manual release review (rejected: repeatable checks are required by SC-004);
blindly stamp every file (rejected: docs and package metadata may need policy-specific handling).

## D5 - CI/security hardening

**Decision:** keep fast headless CI separate from heavier environment-gated checks, but make the coverage explicit:
composer validation, PHP lint, Pest, npm build, Jest, docs-app build, integration/wp-env where available, Playwright
smoke/console sweep, and guard/compliance status. Add missing governance controls as repo files when possible, and
document GitHub-settings-only controls separately.

**Rationale:** `.github/workflows/ci.yml` is currently PHP-headless only; `.github/workflows/e2e.yml` runs on nightly
and manual dispatch; `.github/workflows/docs.yml` builds docs on main. Spec 055 should improve or document the
readiness matrix without pretending browser/Docker checks are available in every local environment.

**Alternatives considered:** put every check in the fast PR workflow immediately (rejected: wp-env/browser checks are
heavier and may be environment-gated); leave missing controls implicit (rejected: SC-006 requires distinction).

## D6 - make:site validation and framework-folder protection

**Decision:** validate generated client sites by inspecting the scaffold output from `SiteScaffolder`, not by editing
Corex framework paths. The validation must prove plugin/theme isolation, client namespace/prefix placeholders,
governance files, `specs/`, `brand.json` or token strategy, starter/minimal behavior, and a compliance check that
flags direct client branding edits under Corex framework folders.

**Rationale:** `SiteScaffolder` already emits client plugin/theme/governance structure and starter slices. Client
readiness depends on proving that structure is isolated and repeatable before the first company sites begin.

**Alternatives considered:** manually create the first client sites and fix the generator later (rejected: hides
generator gaps inside client work); convert Corex itself into a client project (rejected: violates framework/client
separation).

## D7 - Deployment profile coverage

**Decision:** maintain deployment readiness as a profile matrix first: minimal, standard, full, Woo, client-site,
shared-host, Azure/container, local Docker, wp-env stable, and wp-env trunk. Each profile records package shape,
build commands, dependency assumptions, required secrets, and blockers.

**Rationale:** deployment target #11 remains open, so spec 055 should avoid overfitting to one host. The matrix lets
client-site work choose a profile knowingly while preserving tag-based release discipline.

**Alternatives considered:** choose Azure or shared hosting now (rejected: the project still records deploy target as
open); defer all deployment work (rejected: client-site readiness requires known blockers).

## D8 - Component coverage and native-first UI readiness

**Decision:** produce a component coverage matrix that classifies each company-site need by mechanism:
`corex-block`, `wordpress-core-block-style`, `pattern`, `form-field`, `admin-component`, `utility`, `missing`,
`deferred`, or `pro-candidate`. Only missing items required by the first two company-identity websites may become
build scope.

**Rationale:** spec 054 already proved the DLS should be native-first and not custom-block everything. Spec 055
extends that discipline to client readiness without doing the final visual redesign.

**Alternatives considered:** start a visual redesign now (rejected by FR-001/SC-008); create a block for every page
section (rejected by spec 054's native-first decision).

## D9 - Free/Core vs Pro boundaries

**Decision:** Free/Core must include adoption and trust basics: core framework, basic blocks/DLS, basic forms/contact
form, basic config/options, basic media fields, basic captcha/honeypot, accessibility, RTL, i18n, basic make:site,
and basic docs/deployment docs. Pro candidates can include advanced automation, vertical workflows, white-label
admin, advanced data/email/media, and governance dashboards.

**Rationale:** security-critical basics cannot be paywalled without harming adoption and trust. Commercial scope is
valid only when it is advanced or vertical-specific.

**Alternatives considered:** classify Data Manager, captcha, or a11y basics as Pro (rejected by FR-019); make every
advanced kit Free/Core (rejected: not required for client readiness and undermines add-on strategy).

## D10 - Multi-agent safety

**Decision:** make branch/spec ownership, git status first, no main-branch work, no overlapping edits, handoff
format, guard evidence, and final report format explicit and machine-checkable where possible. Keep durable memory
in repo files, not chat.

**Rationale:** Corex already relies on Spec Kit and durable files. Parallel agent work increases the risk of
uncoordinated edits, stale release claims, and skipped guards.

**Alternatives considered:** rely on chat coordination (rejected: disposable and invisible to new agents).
