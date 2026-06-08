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

## Boot-time problems

Malformed configuration, unresolvable dependencies, and broken providers are written to the
WordPress debug log. When `WP_DEBUG` is on, they also appear as a single dismissible admin
notice. Boot stays non-fatal.

## Tests

```bash
composer test              # headless unit suite (Pest + Brain Monkey)
composer test:integration  # boots the real ./wp install
```
