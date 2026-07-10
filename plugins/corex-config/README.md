# Corex Config

Corex's product identity + (forthcoming) settings/dashboard. Part of the free core.

## Brand identity — the Core X logo package

Corex ships the approved **Core X** product mark as a package of optimized SVGs under `assets/brand/`, with a
provenance manifest (`assets/brand/logo-manifest.json`: source, owner, OFL rights, approval date, viewBoxes,
filenames, sha256 checksums). The mark is five rounded 12u modules on a 48×48 grid (3u gutters, 2.5u corner
radius); four corners use `currentColor` and the center module is CoreX brass `#c9a25e`.

| Variant | File | viewBox | Use |
|---|---|---|---|
| Symbol | `corex-symbol.svg` | `0 0 48 48` | Bare mark / favicon / app-icon source (decorative) |
| Wordmark | `corex-wordmark.svg` | `0 0 2600.5 728` | "Corex" type, brass terminal `x` (named image) |
| Lockup | `corex-lockup.svg` | `0 0 170.02 48` | Mark + wordmark — the default product logo (named image) |
| Monochrome | `corex-monochrome.svg` | `0 0 170.02 48` | Single-ink (`currentColor`) for print / forced-colors |
| Contrast | `corex-contrast.svg` | `0 0 170.02 48` | AA-darkened brass `#ad8643` for light / high-contrast |

**Usage rules:** keep at least one module of clear space on every side; minimum size 16px (favicon) / 24px in-app;
never recolor, rotate, stretch, or add effects to the mark. On a light background use the contrast variant (AA
brass); the wordmark glyphs are outlined vector paths (no live font dependency) so the mark renders without the
Space Grotesk webfont. The legacy navy/cyan `assets/corex-logo.svg` is **retained only as rollback/migration
evidence**, not approved production artwork.

**Accessibility & client separation:** the bare symbol is decorative (empty `alt`) when adjacent text already
names the product; the wordmark/lockup are named images (`alt="CoreX"`); a linked brand mark gets an accessible
name (e.g. "CoreX home"). This is the CoreX **product** identity — it is never imposed on a client site. A per-site
`brand.logo_url` override always wins, so client sites keep their own identity.

## Admin branding

`AdminBranding` applies the Corex **product** brand in wp-admin (kept separate from any client site's
look — client sites stay neutral):

- the **login page** logo (the Core X lockup, via `brand.logo_url` → the bundled lockup),
- the login link → the site home (or `brand.login_url`),
- the **admin footer** → "Powered by Corex" (or `brand.footer_text`).

All overridable via the Config engine (`brand.logo_url`, `brand.login_url`, `brand.footer_text`).

## Overview (Corex → Overview)

The Overview is the command center. It composes a dense readiness dashboard from **already-gathered, real
WordPress facts** — it never fabricates a count, an integration, a readiness score, or an activity feed. It
shows: stat tiles (published posts/pages, stored submissions, active/installed add-ons); a launch-readiness
checklist (brand, kit applied, front page, transactional email, spam protection, hardening); an analytics &
security integrations panel (honest connected / not-configured chips); a data-sources summary; a Forms & Flows
card with the real registered-form count and versioned-flow count; and a **recent-activity feed** projected
from the core `Corex\Activity\ActivityService` (the five latest real events, with an honest empty state when
there are none). The pure `OverviewModel` composes the regions; `OverviewRenderer` gathers the live facts and
resolves optional services (`FlowRepository`, `ActivityService`) lazily so the screen never hard-depends on
them.

## Settings dashboard

A top-level **Corex** admin menu → a tabbed settings screen: **Brand / Mail / Forms / Captcha / Media / Insights
/ Advanced**. Each field is a Config dot-key, so saving persists to the prefixed option the **Config engine reads**
— the framework consumes settings with no extra wiring (e.g. saving `mail.from.address` is what `WpMailDriver`
reads). Saving is nonce + `manage_options` gated and sanitized by the pure `SettingsSanitizer` (invalid
email/URL/unknown-select values are rejected, not saved; empty write-only secrets are preserved). **Advanced** is a
read-only system-diagnostics read-out (PHP/WordPress/environment/memory/multisite) — destructive resets live behind
their own typed-confirmation surfaces (Operations & Security, Setup Wizard), and Operations, Data Sources, and
Design Tokens keep their dedicated screens rather than being duplicated here.

```php
$store = $container->make(Corex\Config\Settings\SettingsStore::class);
$store->save('brand.footer_text', 'Powered by Acme');   // → option corex_brand_footer_text
Config::get('brand.footer_text');                        // 'Powered by Acme'
```

## Add-ons screen

A **Corex → Add-ons** submenu lists every Corex add-on with its state (Active / Inactive / Not installed) and,
where it has one, its feature flag. Enabling or disabling an add-on toggles **its plugin and its feature flag
together**. The screen is **dependency-aware**: it refuses to disable an add-on an active add-on requires
(naming the dependent), and refuses to enable one whose dependency is inactive (naming the missing
dependency) — so a toggle can never leave the site broken.

The decisions are the pure, unit-tested `Corex\Config\Addons\AddonRegistry` + `AddonManager` (the kit add-ons
require `corex-ui`, mirroring the blueprints); the `AddonsScreen` only renders + gates (via the shared
`Corex\Security\Admin\AdminGuard`, cap + nonce) and delegates the plugin/flag writes to `AddonActivator`.
Companion to the setup wizard (which composes a whole kit at once).

The truthful summary bar (active / installed / site-kit counts) is projected by the pure
`Corex\Config\Addons\AddonCatalogService`, shared with the Overview add-on tile. CoreX ships no update-checker,
so the Updates cell honestly reads **not tracked** — never a faked count — and a not-installed add-on shows a
real installation path (add the plugin package to `wp-content/plugins`) rather than a fabricated update.

## Data screen (Corex → Data)

A **Corex → Data** workspace lists submissions and registered custom-table sources through the canonical
`corex/v1/data` REST boundary. Search, declared field filters, source-approved sorting, detail, pagination, create,
edit, delete, bounded bulk actions, and CSV/XLSX exports appear only when the current actor and source both allow the
operation. Every write first returns an exact, actor-bound five-minute preview and accepts that preview once. Export
scope can be the current query, selected rows, or all accessible rows; columns are explicit and personal-data fields
require acknowledgement.

**Corex → Data Models** uses the same capability catalog for schema inspection and records, plus CSV upload/mapping/
dry-run/commit, rejection reports, export history/downloads, and provider-declared migration apply/rollback. Imports
write only accepted rows from the confirmed checksum. Migration previews show the exact plan and create a provider
snapshot before queueing. Unsupported actions are omitted rather than presented as dead controls.

The base `DataSource` remains read compatible. Add-ons opt into richer behavior with `QueryableDataSource`,
`FieldAwareDataSource`, `CapabilityAwareDataSource`, `WritableDataSource`, or `MigrationAwareDataSource`; the UI never
infers a write path without the matching adapter. See the
[Data management guide](../../docs-app/src/content/docs/guides/data-management.mdx) for the extension contracts and
safety model.

**Custom tables appear automatically.** Mark any Corex-managed table **managed** and it shows up in Corex → Data
like a post-type list — browsable and paginated, with richer actions only when it supplies their adapters — with no
admin code (spec 038):

```php
// in a service provider's boot(), once the table exists
$container->make(Corex\Database\Schema\ManagedTables::class)->register(
    new Corex\Database\Schema\ManagedTable('invoices', __('Invoices', 'corex'), [
        ['id' => 'number', 'label' => __('Number', 'corex')],
        ['id' => 'total',  'label' => __('Total', 'corex')],
    ]),
);
```

Each managed table becomes a `TableDataSource` (key `table-<name>`). The `$wpdb` access is the
`WpTableDataReader` boundary — every query is **prepared** (`%i` identifiers, `%d` ids) and the page read is
**bounded** (`LIMIT/OFFSET`); the row/column shaping is the pure, unit-tested `TableDataSource`. It is **opt-in**:
Corex never enumerates arbitrary database tables.

## Submissions Inbox (Corex → Submissions)

The Submissions Inbox is the operational workspace over persisted flow responses. It provides permission-scoped
search, flow/status/owner/date filters, marked-test visibility, page selection, unread state, ownership, and the six
workflow statuses: New, In Progress, Replied, Closed, Spam, and Archived. Opening a row shows submitted values, hidden
and UTM metadata, consent and flow-version snapshots, spam/retention/export state, internal notes, related email
attempts, and the append-only timeline.

Every mutation uses `corex/v1/submissions` with the CoreX submissions ability and a REST nonce. Status, read, and
assignment writes carry the record's `updated_at` value, so stale tabs receive a conflict. Bulk mark-read, assignment,
spam, and archive actions require a bounded actor-bound preview token and abort if any selected record changed or became
inaccessible. Email replies, safe resends, and redacted attempt logs cross the neutral `SubmissionEmailGateway`; the
optional Email Studio add-on supplies the implementation without becoming a hard dependency.

Exports support all accessible records, the current filter, or selected rows. Administrators choose explicit data
classes and must acknowledge personal-data columns. Export work runs through CoreX bounded jobs, stores its CSV artifact
in private WordPress data, marks exported submissions, and records scope, actor, columns, count, and time in both export
history and the shared activity stream. Marked-test submissions are excluded unless explicitly included.

Retention defaults to the same marked-test exclusion and offers dry-run counts plus confirmed Archive, recoverable
Trash, or Anonymize operations. Anonymization removes submitted values, submitter projections, hidden/UTM/consent data,
and notes while retaining non-personal workflow evidence and a retention timeline event.

## Email Studio (Corex → Email Studio)

The Email Studio is gated on the optional CoreX Email add-on. When active, its REST-backed React client provides
**Overview / Templates / Layouts / Partials / Variables / Routing / Preview / Plain text / Test send / Delivery logs / Health**.
It creates editable templates, saves immutable draft revisions, activates a selected revision, revises layouts
and partials, binds triggers with recipient and optional reply-to rules, renders desktop/mobile/RTL sample previews,
captures or delivers tests according to the environment policy, and shows persisted captures and typed delivery
attempts with retry lineage.

All reads require `manage_options`; mutations additionally require the REST nonce localized into the screen. HTML
variables are escaped, unsafe executable content and injected headers are rejected, preview iframes are sandboxed,
and styles use the shared CoreX admin tokens with logical properties. Development never reaches a live transport;
Production requires the matching configured provider plus deliberate live activation. When CoreX Email is inactive,
the screen shows a direct Add-ons activation path and does not instantiate add-on services.

## Blog Pro (Corex → Blog Pro)

Blog Pro is a functional workspace over **native WordPress posts**. Its REST-backed React client renders
**Analytics / Native posts / Editorial workflow / Moderation queue / Authors / Sharing** from live state — no
sample data. Analytics are **first-party and consent-aware**: visitor identity is hashed (no raw IP or user
agent stored) and reading events are retained for 180 days. The editorial workflow maps explicit states
(Draft, Ready for Review, Needs Changes, Approved, Scheduled, Published) onto native post statuses via
`wp_update_post()`; comment moderation and author analytics use the native WordPress comment and user APIs.

The native front end ships complete templates in `theme/`: `single.html` renders the post plus the
`corex/social-share` block, the `corex/newsletter-signup` block, the native comment thread and reply form, and a
related-posts query; `index.html` and `archive.html` render a responsive post grid with excerpts, a no-results
state, and pagination. The sharing and newsletter blocks require the CoreX Email and CoreX Newsletter add-ons.

## Insights screen (Corex → Insights)

A **Corex → Insights** screen shows the designed widget set. Two are **runnable result cards** —
**Performance** (Google PageSpeed Insights / Lighthouse) and **Readiness** (the site's agent-readiness: HTTPS, an
`llms.txt`, a sitemap, agent-permitting robots, exposed MCP abilities) — each with a **Run check** button; every
result is **scored, graded (A–F), cached, and history-kept**. Below them, five **informational widgets** render
from real gathered facts (`InsightWidgetFacts` → `InsightWidgets`): **Cloudflare** (connected/not-connected),
**Security events** (recent operations/access activity), **SEO & indexing readiness**, **Operations health**
(mode/cron/PHP/WordPress versions), and **Forms & Flows analytics** (live submission/flow counts). Aggregated
recommendations from every provider's latest result are available at `GET corex/v1/insights/recommendations`.

It is built on a pluggable `InsightProvider` seam; each provider's **normaliser/scorer is pure and unit-tested**
(`PsiNormalizer`, `CloudflareNormalizer`, `ReadinessScorer`, `Grade`, `InsightStore`), while the HTTP fetch, the
REST run, and the cards are thin boundaries. Runs go through the cap+nonce-gated `corex/v1/insights[/run]` routes
(`manage_options` + a REST nonce); **secrets are never returned** in a response. Providers **degrade gracefully**
(Principle IX): with no PSI key or Cloudflare token the cards still render a useful "configure me" state and never
error. The Readiness card is useful from native signals alone; a configured Cloudflare token adds a URL-scan
security signal. Secrets (`insights.psi.key`, `insights.cloudflare.token`, `insights.cloudflare.account_id`) are
set in **Corex → Settings** (write-only password fields). (Spec 037.)

## Option pages (Corex → custom screens)

Add your own admin settings page with one declaration — no form HTML, nonce, or save loop (spec 039):

```php
use Corex\Config\Options\OptionPage;

$container->make(Corex\Config\Options\OptionPageRegistry::class)->register(new OptionPage(
    slug: 'billing', title: 'Billing', menuLabel: 'Billing',
    capability: 'manage_options', parent: 'corex-settings',
    fields: [
        ['key' => 'billing.tax_id', 'label' => 'Tax ID', 'type' => 'text'],
        ['key' => 'billing.logo',   'label' => 'Logo',   'type' => 'media'],
    ],
));
```

An `OptionPage` is a `FieldSections` — the **same seam** the built-in settings screen uses — so the one
`SettingsForm` (per-type controls) + the one save loop render and persist it with **no duplicated form code**.
The `OptionPageScreen` adds the menu and saves on submit (capability + per-page nonce + per-type sanitise; output
escaped per type). Field keys are ordinary `Config` dot-keys, readable via `$config->get(...)`; `password` fields
are write-only. Scaffold one with `wp corex make:option-page <Name>`.

## Tests

```bash
composer test              # headless: branding + settings + the add-on registry/manager dependency rules
composer test:integration  # real ./wp: a saved setting read back; the add-on activator flag sync
```

> The rendered admin appearance (the login page showing the Corex logo) is a browser check. The full
> **settings/dashboard UI** (React/DataViews) is spec 017 and needs a Node build + a browser to author.


## Modern settings controls (spec 032)

The settings screen renders the right control per field: the **logo** is a WordPress **media picker** (pick or
upload — no URL typing; the value is the image URL the branding reads), the **captcha driver** is a **select**,
and fields can be `text/email/url/password/media/select/checkbox`. The configured logo appears in the settings
header so the branding is findable. The media wiring degrades to an editable URL field without JavaScript.

## Prompt-to-apply kit activation + the Site-status card (spec 042)

Enabling a kit used to flip a plugin + feature flag and create nothing, so it looked like nothing happened.
Now, enabling a kit add-on (Corex → Add-ons) queues an activation prompt instead of changing anything silently:

- `KitActivationNotice` shows a dismissible banner — *"The \<kit\> kit is ready. Apply its starter content?"* —
  previewing exactly what applying would do (which pages are created / filled in / left unchanged, and which
  becomes the front page). The preview is **read-only** (no writes until you choose **Apply**).
- **Apply** runs the kit through the one shared apply path (spec 041 rules) and shows a "what changed" summary
  with links to the created/populated pages and the site. **Not now** dismisses the prompt (recallable).
- Apply and dismiss are capability + nonce gated via the shared `AdminGuard`.

The Corex dashboard (the top **Corex** screen) gains a **Site status** card: which kits are applied, the live
contact-submission count linked to **Corex → Data**, and the current front-page status — with an actionable empty
state when nothing is applied. It reads the optional `KitProvisioner` seam and the submissions source, and
degrades gracefully (count `0`, never an error) when the forms add-on or kit framework is inactive.
