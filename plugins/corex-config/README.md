# Corex Config

Corex's product identity + (forthcoming) settings/dashboard. Part of the free core.

## Brand identity

Corex ships a scalable **SVG logo** (`assets/corex-logo.svg`) — navy `#0B1F3B` + cyan `#00C2FF`, a
layered-core mark with a "Corex" wordmark.

## Admin branding

`AdminBranding` applies the Corex **product** brand in wp-admin (kept separate from any client site's
look — client sites stay neutral):

- the **login page** logo (the Corex SVG),
- the login link → the site home (or `brand.login_url`),
- the **admin footer** → "Powered by Corex" (or `brand.footer_text`).

All overridable via the Config engine (`brand.logo_url`, `brand.login_url`, `brand.footer_text`).

## Settings dashboard

A top-level **Corex** admin menu → a settings screen for **brand / mail / forms / captcha**. Each field
is a Config dot-key, so saving persists to the prefixed option the **Config engine reads** — the framework
consumes settings with no extra wiring (e.g. saving `mail.from.address` is what `WpMailDriver` reads).
Saving is nonce + `manage_options` gated and sanitized.

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

## Data screen (Corex → Data)

A **Corex → Data** admin screen shows your form **submissions** — and any registered Corex custom-table data
source — with a search box, a source/form filter, sortable column headers, pagination, a **CSV Export** button
(the current filtered view, bounded to 5000 rows), a per-record **detail drawer**, and distinct loading / error /
empty states (spec 053 US2; it supersedes the earlier minimal DataViews table). The data is served by the
cap-gated `corex/v1/data/<source>` REST routes (`manage_options`; deletes + export require a nonce); the export
streams from the `corex_data_export` `admin-post` handler.

It is built on a pure `DataSource` abstraction (`key/label/columns/rows/total/delete`): the submissions source is
the reference implementation (`SubmissionsSource` + the `WpSubmissionsReader` boundary); an add-on registers its
own `DataSource` (e.g. over a `TableRepository`) to appear in the same screen with no new UI code. The screen
renders + gates via the shared `AdminGuard`. (Spec 030.)

**Custom tables appear automatically.** Mark any Corex-managed table **managed** and it shows up in Corex → Data
like a post-type list — browsable, paginated, and deletable — with no admin code (spec 038):

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

## Insights screen (Corex → Insights)

A **Corex → Insights** screen shows two result cards — **Performance** (Google PageSpeed Insights / Lighthouse)
and **Readiness** (the site's agent-readiness: HTTPS, an `llms.txt`, a sitemap, agent-permitting robots, exposed
MCP abilities) — each with a **Run check** button. Every result is **scored, graded (A–F), cached, and
history-kept**.

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

> The **React/DataViews UI** (DataViews tables for submissions/subscribers/applications, the setup wizard,
> a health-check runner) is the deferred upgrade — it needs a Node build + a browser to author and verify.
> The current settings screen is server-rendered (Settings-API style) and fully testable.

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
