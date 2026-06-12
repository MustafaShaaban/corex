# Corex Core

The self-booting engine of the Corex framework: a dependency-injection container, a
service-provider lifecycle, layered configuration, declarative WordPress hooks, and
controller auto-discovery. Presentation-free; works in front-end, admin, REST, WP-CLI,
and cron with no theme and no optional plugins active.

> Status: foundation only (spec `001-corex-core-foundation`). No business modules yet.

## Boot

`corex-core.php` calls `Corex\Boot::init()`, which hooks the framework's bootstrap onto
`plugins_loaded`. Boot is idempotent — it runs exactly once per request. After boot,
`Corex\Boot::app()` returns the `Corex\Foundation\Application`.

## Container

`Corex\Container\Container` implements PSR-11 plus a Laravel-style surface:

```php
$c = Corex\Boot::app()->container();

$c->bind(Mailer::class);              // transient: new instance per make()
$c->singleton(Clock::class);          // shared: one instance for the request
$c->instance(Logger::class, $logger); // register an existing object

$service = $c->make(Greeter::class);  // autowires constructor dependencies
```

- Constructor dependencies are autowired from their type hints.
- An **interface** type hint must have an explicit `Interface → Concrete` binding
  (register it in a provider); resolving an unbound interface throws
  `BindingResolutionException`.
- An unresolvable scalar/untyped parameter throws `BindingResolutionException` naming the
  class and parameter; a dependency cycle throws `CircularDependencyException`.

Application services and controllers **must** receive dependencies via their constructor.
A bounded global accessor, `Corex\Support\Facades\Corex::make()`, exists only for framework
boundaries (hook callbacks, CLI/cron bootstrap) where injection cannot reach.

## Service providers

A service provider is the single extension seam. Bind services in `register()`; wire
behavior in `boot()` (which runs only after every provider has registered):

```php
use Corex\Foundation\ServiceProvider;

final class JobsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(JobRepository::class);
    }

    public function boot(): void
    {
        // runs after all providers registered
    }

    /** Hook subscribers to wire (see Hooks). @return list<class-string> */
    public function subscribers(): array
    {
        return [JobNotifications::class];
    }

    /** Controller locations to auto-discover: PSR-4 prefix => directory. */
    public function controllerPaths(): array
    {
        return ['Acme\\Jobs\\Controllers\\' => __DIR__ . '/Controllers'];
    }
}
```

Providers are registered through the `Application`'s provider list (currently the core
list in `Boot`). Automatic discovery of add-on providers via Composer
`extra.corex.providers` and `config('app.providers')` is planned and not yet wired.

A provider whose `register()` or `boot()` throws is logged and skipped — one broken
provider never aborts boot.

## Configuration

`Config::get()` resolves a dot-notation key across three layers, first match wins:
`.env` (project root) → WordPress options → shipped defaults (`config/app.php`).

```php
use Corex\Support\Facades\Config;

Config::get('app.name');             // 'Corex' from defaults
Config::get('missing.key', 'fallback');
```

- A dot key maps to an option named `corex_<key_with_underscores>` and to an env var
  `KEY_WITH_UNDERSCORES` uppercased (`app.name` → option `corex_app_name`, env `APP_NAME`).
- A missing `.env` is fine; a malformed `.env` is logged and ignored — boot continues on
  options/defaults. See `.env.example`.

Shipped config namespaces (one file each in `config/`): `app`, `query`, `security`, `theme`,
`features`. A site overrides any key through the **settings UI** (`corex-config`, which persists
to the `corex_*` options the option layer reads) or `.env` — never by editing the defaults.

### Feature flags

`features.*` flags gate optional/edition behaviour through the same layered engine, so a flag
flips by option (`corex_features_<flag>`) or env (`FEATURES_<FLAG>`) with no code change. Only a
truthy value (`1/true/on/yes`) enables a flag; anything else (including absent) is off.

```php
use Corex\Support\Facades\Config;
use Corex\Support\Config\FeatureFlags;

Config::enabled('mail_queue');                  // false until enabled
$flags = $container->make(FeatureFlags::class); // inject where you need it
$flags->enabled('pro');                          // edition gate
$flags->all();                                   // ['pro' => false, 'mail_queue' => false, …]
```

Registered flags (`config/features.php`): `pro` (edition gate), `mail_queue`, `dataviews_admin`,
`woocommerce_kit`. Add a flag by adding a key to `config/features.php`; it is then readable,
overridable, and reported by `all()`. The **Free/Pro split** rides on `features.pro` — Free builds
leave it off; Pro distributions enable it (env or bundled option).

## Declarative hooks

A class declares the WordPress actions/filters it responds to; the framework wires them,
resolving the class from the container so its dependencies inject:

```php
use Corex\Hooks\SubscribesToHooks;

final class JobNotifications implements SubscribesToHooks
{
    public function hooks(): array
    {
        return [
            'init'      => 'onInit',                 // priority 10, 1 arg
            'the_title' => ['filterTitle', 20, 1],   // priority 20, 1 arg
        ];
    }

    public function onInit(): void {}
    public function filterTitle(string $title): string { return $title; }
}
```

List the subscriber in your provider's `subscribers()`. A subscriber hook is wired at most
once.

## Controllers

Any instantiable class placed in a module's `Controllers/` directory (declared via the
provider's `controllerPaths()`) is discovered and registered with the container — no
annotations, no central list. Abstract classes, interfaces, and non-class files are
ignored.

## Data layer

Read and write WordPress data without touching `WP_Query`, `$wpdb`, or `get_post_meta` directly.

**Model** — a read-only value object. Declare the entity's shape:

```php
use Corex\Models\Model;

final class Job extends Model {
    public static function postType(): string { return 'job'; }
    public static function fields(): array    { return ['salary' => 'job_salary', 'company_id' => 'company_id']; }
    public static function casts(): array     { return ['salary' => 'int']; }
    public static function relations(): array {
        return ['company' => ['type' => 'belongsTo', 'model' => Company::class, 'foreignKey' => 'company_id']];
    }
}

$job->id();                 // int
$job->get('salary');        // cast to int; a default is returned for an absent attribute
$job->get('missing', 0);    // 0
```

Models hold data only — no `save()`. `withAttribute($name, $value)` returns a *new* Model (used to
attach eager-loaded relations).

**Repository** — the only layer that talks to the data source. Extend `PostRepository`:

```php
use Corex\Repositories\PostRepository;

final class JobRepository extends PostRepository {
    protected function model(): string { return Job::class; }
}

$jobs = Corex\Boot::app()->container()->make(JobRepository::class);
$jobs->find(12);                       // ?Job — null when absent
$jobs->create(['title' => 'Dev', 'salary' => 90000]);   // Job (persisted, fresh)
$jobs->update(12, ['salary' => 95000]);
$jobs->delete(12);                     // bool
```

`PostRepository` autowires the field driver, hydrator, and query executor — **bind your concrete
repository in your own service provider** (it has no other dependencies to declare).

**Field driver (ACF-optional)** — declared fields resolve through ACF when it is installed and native
post meta when it is not, behind one interface. Your model/repository code is identical either way;
the framework runs fully with ACF absent.

**QueryBuilder** — fluent, capped, and safe:

```php
$jobs->query()
    ->where('salary', 80000, '>=')     // declared field → meta_query (value bound as data)
    ->where('post_status', 'publish')  // core field → WP_Query arg
    ->orderBy('salary', 'DESC')
    ->limit(20)
    ->with('company')                  // eager-load a belongs-to relation (no N+1)
    ->get();                           // Collection<Job> — empty, never null, when nothing matches
```

An unbounded query is capped at `config('query.max')` (default 500) — never `posts_per_page => -1`.
Eager loading a relation across N entities runs a bounded number of queries (a belongs-to relation
is two queries, not N+1). A `Collection` exposes `all()`, `first()`, `isEmpty()`, `count()`, and
iterates.

**Complex queries.** The builder is a pure arg-builder — every method below adds to a capped,
value-bound `WP_Query` args array (proven per-scenario in `tests/Unit/Data/QueryBuilderTest`):

| Method | Builds | Example |
|---|---|---|
| `where($field, $value, $compare)` | declared field → `meta_query`; core field → arg | `->where('salary', 80000, '>=')` |
| `orWhere($field, $value, $compare)` | a meta condition under an `OR` relation | `->orWhere('salary', 30000, '<=')` |
| `whereMeta($key, $value, $compare, $type)` | a raw-meta condition with an explicit SQL type | `->whereMeta('featured', '1', '=', 'NUMERIC')` |
| `whereBetween($field, $min, $max)` | a numeric `BETWEEN` range | `->whereBetween('salary', 40000, 90000)` |
| `metaRelation('OR'\|'AND')` | the relation joining meta conditions | `->metaRelation('OR')` |
| `whereTax($tax, $terms, $field, $op)` | a `tax_query` clause (term_id/slug/name) | `->whereTax('department', [3,4])` |
| `taxRelation('OR'\|'AND')` | the relation joining tax clauses | `->taxRelation('OR')` |
| `whereDate($after, $before, $inclusive)` | a post-date range (`date_query`) | `->whereDate('2026-01-01', '2026-06-30')` |
| `search($term)` | full-text `s` | `->search('senior php')` |
| `orderBy($field, $dir)` | `meta_value` (declared) or core orderby | `->orderBy('title', 'ASC')` |
| `orderByNumeric($field, $dir)` | `meta_value_num` (declared, numeric sort) | `->orderByNumeric('salary', 'DESC')` |
| `paginate($perPage, $page)` | capped `posts_per_page` + `paged` + found-rows on | `->paginate(20, 2)` |
| `with($relation)` | eager-load a belongs-to relation (no N+1) | `->with('company')` |

They compose — one chain can carry nested meta (AND/OR) + a tax query + a date range + search +
meta-numeric ordering + pagination + eager loading. Values are always passed as data into the
relevant `*_query` clause (never interpolated), so `WP_Query` prepares them.

```php
$jobs->query()
    ->where('salary', 50000, '>=')
    ->whereBetween('salary', 50000, 120000)
    ->metaRelation('AND')
    ->whereTax('department', 'engineering', 'slug')
    ->whereDate('2026-01-01')
    ->search('engineer')
    ->orderByNumeric('salary', 'DESC')
    ->paginate(20, 1)
    ->with('company')
    ->get();
```

**Custom-table entities** (many-row data, not posts) use the spec-011 `TableRepository` instead of
`WP_Query` — typed CRUD + `where()` with `$wpdb->prepare()`. Cross-table **joins** are that
repository's boundary (a deliberate seam), not faked through `WP_Query`; reach for it when an entity
is a row in a `{prefix}corex_*` table rather than a post.

**Custom tables** — for entities that are many queryable rows (subscribers, applications, bookings),
not posts. Define the schema fluently and run it idempotently, then use a typed `TableRepository`:

```php
use Corex\Database\Schema\Table;

$this->migrator->create(
    (new Table('subscribers'))->id()->string('email')->boolean('confirmed')
        ->text('topics')->datetime('confirmed_at', nullable: true)->timestamps()
);

final class SubscriberRepository extends Corex\Repositories\TableRepository {
    protected function table(): string { return 'subscribers'; }
    protected function casts(): array  { return ['confirmed' => 'bool', 'topics' => 'json']; }
}

$id  = $subscribers->insert(['email' => 'a@b.com', 'confirmed' => false, 'topics' => ['news']]);
$row = $subscribers->find($id);          // ['confirmed' => false, 'topics' => ['news'], …] — typed
$subscribers->where('email', 'a@b.com'); // parameterized; [] when none
```

Tables are created under the site prefix + a `corex_` namespace via WordPress's idempotent `dbDelta`
(modules create their tables on activation). Every variable query is parameterized; values cast to/from
their declared types (`int`/`bool`/`string`/`decimal`/`array`/`json`/`datetime`); a malformed stored JSON
value hydrates to `[]`. The repository is the only layer that runs the queries (Principle III).

## CLI generators

Scaffold the framework's own patterns with `wp corex make:*` (registered only when WP-CLI is present —
the framework runs fully without it):

```bash
wp corex make:model Career            # read-only Model → <app>/Models/Career.php
wp corex make:repository Career        # PostRepository bound to Career → Repositories/
wp corex make:service Career           # service with its repository injected → Services/
wp corex make:controller Career        # thin controller with its service injected → Controllers/
wp corex make:model Career --force     # overwrite an existing file (otherwise the run is skipped)
```

Each generated file is constitution-shaped (read-only Models, thin controllers, fat services,
repositories own data access, constructor injection, `ABSPATH` guard) and passes the guards unedited.
The output base path, namespace, and prefix come from the Config engine (`app.path`, `app.namespace`,
`app.prefix`) — set per project by `wp corex init`; when `app.path` is empty the default is
`wp-content/corex-app`. An invalid class name is rejected before any file is written.

## Blocks

Blocks register themselves by convention. Drop a folder with a `block.json` under
`plugins/corex-blocks/src/blocks/<name>/`; on `init` the engine discovers and registers it — no central
list. A block's declared assets (`style`/`script` in `block.json`) load **only** when the block renders
(Principle VI; the framework enqueues no global library).

A dynamic (server-rendered) block names its renderer in `block.json` (`"corex": { "renderer": "…" }`);
the engine resolves that `Corex\Blocks\BlockRenderer` from the container so the render stays thin and
injectable, and a render that throws yields empty output (logged), never a fatal page.

To surface Corex data in the editor, register a connector:

```php
final class CareerConnector extends Corex\Blocks\Connectors\RepositoryConnector {
    public function name(): string { return 'corex/career'; }
    // value() resolves a field through the injected Repository (escaped, empty-safe)
}
// in a provider boot(): $registry->register(new CareerConnector($careerRepository));
```

A site editor binds a core block attribute to `corex/career` via the WordPress Block Bindings API; the
value is sourced through the Repository (the only data-source layer) and escaped on output. Block
styling uses `theme.json` CSS variables and logical properties (RTL-correct by default).

## Middleware & security

Security is declarative — controllers route and validate; middleware enforce. A controller declares the
middleware that protect its actions; the framework runs them automatically before the handler:

```php
// declared on a controller/route:
public function middleware(): array { return ['nonce', 'auth:manage_options', 'throttle']; }

// the framework resolves and runs them:
$mw = $resolver->resolveAll($controller->middleware());
$response = $pipeline->run($request, $handler, ...$mw);
```

Each middleware returns a `Response` to short-circuit (reject) or calls `$next` to pass inward (onion
model). A middleware that throws is caught and converted to a fail-closed rejection; the handler never
runs on a rejection. The four standard aliases are registered by the `SecurityModule`:

- `nonce` — rejects a state-changing (non-GET) request without a valid WP nonce.
- `auth:<capability>` — rejects unless `current_user_can(<capability>)`.
- `throttle` — rate-limits by key (transient-backed; `config('security.throttle.*')`, default 60/60s).
- `sanitize` — reduces input to the declared, sanitized shape before the handler sees it.

An unknown middleware name **fails closed** (resolves to a rejecting middleware), never a silent skip.
Controllers contain **no** hand-written nonce/capability checks — the middleware own them (Principle VII).

## Theme & design tokens

The theme is a **skin**: `theme/theme.json` (v3) is the single source of design tokens — colors,
font sizes, spacing, layout — exposed as `--wp--preset--*` CSS custom properties. The theme's styling
consumes only those variables (no hardcoded colors/sizes/fonts, no CSS framework), and registers no
post types, taxonomies, or routes — that logic lives in the plugins.

A site rebrands with a **`brand.json`** placed at the active theme root (or the path in
`config('theme.brand_path')`). `ThemeServiceProvider` reads it on the `wp_theme_json_data_theme`
filter and deep-merges it onto the theme.json data:

```php
use Corex\Theme\BrandResolver;

$merged = $resolver->merge($themeJsonData, $resolver->read('/path/to/brand.json'));
```

`BrandResolver::merge()` is pure and headless: associative arrays merge key-by-key (the deepest
overriding key wins, siblings preserved, unknown keys added); scalars and lists are replaced
wholesale. A missing `brand.json` yields `[]` (defaults stand); a malformed one yields `[]` and is
logged, so a bad file never breaks the site. Full alternate styles live in `theme/styles/*.json`
(e.g. `dark.json`) and WordPress auto-registers them as selectable style variations.

## Events

A framework-wide event seam lets modules react to what happens elsewhere without coupling
to each other. Register a listener for an event class; dispatch an event object; every
listener for that class runs once, in registration order:

```php
use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;

$provider = $c->make(ListenerProvider::class);
$provider->listen(OrderPlaced::class, $sendReceipt);   // any callable(object): void

$c->make(EventDispatcher::class)->dispatch(new OrderPlaced($order));
```

An event is any object (mark it with the `Corex\Events\Event` interface to signal intent).
Dispatch is **best-effort**: a listener that throws is caught and logged (via `BootLogger`),
and the remaining listeners still run — one failing listener never blocks the others or the
request. `dispatch()` returns the event. `ListenerProvider` and `EventDispatcher` are
container singletons, so every module shares one registry. Corex Forms builds on this seam,
and Corex Mail will reuse it.

## Mail seam

A neutral mail interface lets a module send email without depending on any concrete mail engine.
Depend on `Corex\Mail\Mailer`; describe the message with the primitive `Corex\Mail\MailRequest`
(scalars/arrays only). A consumer checks the container to decide whether a real engine is active
(detect-and-defer):

```php
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;

if ($container->has(Mailer::class)) {
    $container->make(Mailer::class)->send(new MailRequest(
        to: [$recipient],
        templateName: 'contact-notification',
        context: ['submission' => $values, 'site' => ['name' => get_bloginfo('name')]],
    ));
} else {
    wp_mail($recipient, $subject, $body);   // fallback when no engine is bound
}
```

corex-core ships only the seam; the **Corex Mail** add-on binds an implementation that renders the
template, validates recipients, guards against header injection, delivers, and logs. `Mailer::send()`
is best-effort and never throws. This is the same pattern the Forms email listener uses.

## Boot-time problems

Malformed configuration, unresolvable dependencies, and broken providers are written to the
WordPress debug log. When `WP_DEBUG` is on, they also appear as a single dismissible admin
notice. Boot stays non-fatal.

## Self-update

Corex updates through WordPress's own plugin-update flow. The Core plugin declares an `Update URI`
header so WP routes its update check to Corex; on each check `UpdateService` fetches a JSON manifest
from `updates.endpoint` (config, default empty), and a pure `UpdateChecker` decides — by `version_compare`
— whether it advertises a newer release. If so, a standard update object is injected into WP's update
transient and the update appears in **Plugins → Updates**, installed by WordPress's own updater.

```php
// config/app.php (or .env: COREX_UPDATES_ENDPOINT=…)
'updates' => ['endpoint' => 'https://updates.example.com/corex/manifest.json'],
```

```json
{ "version": "0.22.0", "package": "https://…/corex-core.zip", "url": "https://…", "requires": "7.0", "tested": "7.0" }
```

**Fail-safe:** an empty, unreachable, or malformed source is a silent no-op — Corex never phones home
unless you configure a source you control. An update replaces **framework files only**; your `corex-app/`,
`brand.json`, content, and data are never touched. Full guide + the safe-edit boundary:
[`docs/en/05-deployment/updates-and-distribution.md`](../../docs/en/05-deployment/updates-and-distribution.md).

## Tests

```bash
composer test              # headless unit suite (Pest + Brain Monkey)
composer test:integration  # boots the real ./wp install
```

## Abilities (WP 7.0 / MCP)

When the WordPress 7.0 Abilities API is present, Corex registers read-only, capability-gated
abilities for agent/MCP discovery — `corex/list-blocks` (the registered `corex/*` blocks) and
`corex/site-info` (a small site/framework summary) — under a `corex` ability category. They
are annotated `readonly` and exposed in REST. The provider is guarded by
`function_exists('wp_register_ability')`, so the framework runs unchanged on older cores; the
data logic (`CorexAbilities`) is pure and unit-tested.
