<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Environment\SiteEnvironment;
use Corex\Health\HealthModule;
use Corex\Mail\Capture\MailGate;
use Corex\Mail\Capture\MailHold;
use Corex\Mail\Capture\MailPath;
use Corex\Mail\Capture\WordPressMailNames;
use Corex\Mail\Capture\WpMailInterceptor;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\NetworkContext;
use Corex\Support\BootLogger;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Config\FeatureFlags;
use Corex\Support\Config\Repository;
use Corex\Support\Config\SettingRegistry;
use Corex\Support\Config\Sources\DefaultsSource;
use Corex\Support\Config\Sources\DotenvSource;
use Corex\Support\Config\Sources\NetworkDefaultsSource;
use Corex\Support\Config\Sources\NetworkLockSource;
use Corex\Support\Config\Sources\OptionsSource;
use Corex\Support\DateTime\AdminDateTime;
use Corex\Support\DateTime\AdminDateTimeFormatter;
use Corex\Support\Options\LoadedOptions;
use Corex\Update\UpdateChecker;
use Corex\Update\UpdateService;

/**
 * The foundation's own provider. Binds the layered config engine
 * (.env → network lock → WP options → network default → defaults) as a shared service.
 */
final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(SettingRegistry::class);
        // One list for every provider: each names the options of its own that every request reads.
        $this->container->singleton(LoadedOptions::class);

        // The three layers that can lock a value, one instance each: the config repository and
        // the site environment (spec 113) read the same ones, and `.env` is read once.
        $this->container->singleton(
            DotenvSource::class,
            fn (ContainerInterface $container): DotenvSource => new DotenvSource($this->projectRoot(), $container->make(BootLogger::class)),
        );
        $this->container->singleton(
            NetworkLockSource::class,
            static fn (ContainerInterface $container): NetworkLockSource => new NetworkLockSource(
                $container->make(SettingRegistry::class),
                $container->make(MultisiteContext::class),
                $container->make(NetworkContext::class),
            ),
        );
        $this->container->singleton(OptionsSource::class);

        $this->container->singleton(ConfigInterface::class, function (ContainerInterface $container): Repository {
            $registry = $container->make(SettingRegistry::class);
            $multisite = $container->make(MultisiteContext::class);
            $network = $container->make(NetworkContext::class);

            return new Repository([
                $container->make(DotenvSource::class),
                // Deployment-owned .env stays highest: allowing a network-admin database row to
                // override it would invert privilege. A lock must beat a site option, while a
                // network default must lose to one, fixing the sources on opposite sides of it.
                $container->make(NetworkLockSource::class),
                $container->make(OptionsSource::class),
                new NetworkDefaultsSource($registry, $multisite, $network),
                new DefaultsSource($this->defaults()),
            ]);
        });

        // One name and one hold for the whole request: whoever sends, the gate reads the same ones.
        $this->container->singleton(MailPath::class);
        $this->container->singleton(MailHold::class);
        $this->container->singleton(MailGate::class);
        $this->container->singleton(WpMailInterceptor::class);
        $this->container->singleton(WordPressMailNames::class);

        // The lock layers of `app.env`, read one by one: the repository's defaults layer would
        // answer "production" for every site (spec 113, research R1).
        $this->container->singleton(SiteEnvironment::class, static fn (ContainerInterface $container): SiteEnvironment => new SiteEnvironment([
            [SiteEnvironment::BY_DOTENV, $container->make(DotenvSource::class)],
            [SiteEnvironment::BY_NETWORK, $container->make(NetworkLockSource::class)],
            [SiteEnvironment::BY_OPTION, $container->make(OptionsSource::class)],
        ]));

        $this->container->singleton(
            FeatureFlags::class,
            static fn (ContainerInterface $container): FeatureFlags => new FeatureFlags(
                $container->make(ConfigInterface::class),
            ),
        );

        $this->container->singleton(
            UpdateService::class,
            static fn (ContainerInterface $container): UpdateService => new UpdateService(
                new UpdateChecker(),
                plugin_basename(COREX_CORE_FILE),
                'corex-core',
                COREX_CORE_VERSION,
                $container->make(ConfigInterface::class),
            ),
        );

        $this->container->singleton(
            HealthModule::class,
            static fn (ContainerInterface $container): HealthModule => new HealthModule(
                $container->make(ConfigInterface::class),
            ),
        );

        // The cache contract (spec 078). The registry is a singleton because it is the authority on
        // what may be cleared, and two of those in one request could disagree.
        $this->container->singleton(\Corex\Cache\CacheRegistry::class);
        $this->container->singleton(
            \Corex\Cache\CacheStore::class,
            static fn (): \Corex\Cache\WordPressCacheStore => new \Corex\Cache\WordPressCacheStore(),
        );
        $this->container->singleton(
            \Corex\Cache\CacheManager::class,
            static fn (ContainerInterface $container): \Corex\Cache\CacheManager => new \Corex\Cache\CacheManager(
                $container->make(\Corex\Cache\CacheStore::class),
                $container->make(\Corex\Cache\CacheRegistry::class),
            ),
        );

        // How every CoreX admin surface presents a date (spec 076). Bound to the interface so a
        // screen depends on "a way to present a date" rather than on wp_date().
        $this->container->singleton(
            AdminDateTime::class,
            static fn (): AdminDateTimeFormatter => new AdminDateTimeFormatter(),
        );

        // The language an email is written and drawn in: the site's, not that of the request,
        // which in the admin is the signed-in person's own (DECISIONS #356, #362). Bound here
        // and not by CoreX Mail: a form's notification and a Guides support message are written
        // on a site that has not switched that add-on on, too.
        $this->container->singleton(
            \Corex\Mail\EmailLanguage::class,
            static fn (): \Corex\Mail\SiteLanguage => new \Corex\Mail\SiteLanguage(),
        );

        // Default kit-activation seam (spec 042): a Null Object so corex-config can always resolve
        // KitProvisioner and degrade gracefully. The kit framework overrides this binding when active.
        $this->container->singleton(
            \Corex\Provisioning\KitProvisioner::class,
            static fn (): \Corex\Provisioning\NullKitProvisioner => new \Corex\Provisioning\NullKitProvisioner(),
        );
    }

    /**
     * Wire Corex into WordPress's plugin-update flow so a published newer version surfaces as
     * an available update in wp-admin (spec 034), and register the Corex health probes into the
     * Site Health screen (spec 036). Both are fail-safe / advisory.
     */
    public function boot(): void
    {
        $this->container->make(UpdateService::class)->register();
        $this->container->make(HealthModule::class)->register();
        // Every wp_mail() meets the mail gate (spec 113): captured on a development or staging
        // site, held during a flow test, untouched in production.
        $this->container->make(WpMailInterceptor::class)->register();
        $this->container->make(WordPressMailNames::class)->register();

        // Load the shared `corex` text domain so bundled translations apply (spec 036). All Corex
        // plugins/add-ons use this one literal domain; the .pot lives under corex-core/languages.
        add_action('init', static function (): void {
            load_plugin_textdomain('corex', false, dirname(plugin_basename(COREX_CORE_FILE)) . '/languages');
        });

        // Last on `init`, once every provider has booted and named its options, and once what an
        // add-on stores on `init` is stored.
        $container = $this->container;
        add_action('init', static function () use ($container): void {
            $container->make(LoadedOptions::class)->ensureLoaded();
        }, PHP_INT_MAX);
    }

    /**
     * @return array<string, string>
     */
    public function controllerPaths(): array
    {
        return ['Corex\\Controllers\\' => COREX_CORE_PATH . 'src/Controllers'];
    }

    /**
     * Aggregate every shipped config file as `basename => contents`
     * (config/app.php → 'app', config/query.php → 'query', …).
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        $defaults = [];

        foreach (glob(COREX_CORE_PATH . 'config/*.php') ?: [] as $file) {
            $defaults[basename($file, '.php')] = require $file;
        }

        return $defaults;
    }

    /**
     * The one directory a `.env` is read from: two levels above this plugin's real location
     * (`__FILE__` follows a link). Linked into WordPress from a repository, that is the
     * repository. Copied into wp-content/plugins/, it is wp-content/, inside the web root.
     */
    private function projectRoot(): string
    {
        return dirname(COREX_CORE_PATH, 2);
    }
}
