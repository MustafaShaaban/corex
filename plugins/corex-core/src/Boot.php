<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex;

defined('ABSPATH') || exit;

use Corex\Blocks\BlocksServiceProvider;
use Corex\Abilities\AbilitiesProvider;
use Corex\Cli\CliServiceProvider;
use Corex\Config\ConfigServiceProvider;
use Corex\Foundation\AddonProvider;
use Corex\Foundation\AddonProviderRegistry;
use Corex\Foundation\AddonProviderResolver;
use Corex\Foundation\AddonRuntimeState;
use Corex\Foundation\Application;
use Corex\Events\EventServiceProvider;
use Corex\Foundation\CoreServiceProvider;
use Corex\Foundation\DataServiceProvider;
use Corex\Foundation\HttpServiceProvider;
use Corex\Assets\AssetsServiceProvider;
use Corex\Forms\FormsServiceProvider;
use Corex\Security\SecurityModule;
use Corex\Theme\ThemeServiceProvider;
use RuntimeException;

/**
 * The framework entry point. Called once from corex-core.php; hooks the
 * bootstrap onto `plugins_loaded` so Corex self-initializes in every context
 * (front-end, admin, REST, WP-CLI, cron) independent of any theme (spec FR-001–003).
 */
final class Boot
{
    /**
     * @var list<class-string<\Corex\Foundation\ServiceProvider>>
     */
    private const CORE_PROVIDERS = [
        CoreServiceProvider::class,
        HttpServiceProvider::class,
        AssetsServiceProvider::class,
        ConfigServiceProvider::class,
        EventServiceProvider::class,
        DataServiceProvider::class,
        CliServiceProvider::class,
        BlocksServiceProvider::class,
        SecurityModule::class,
        ThemeServiceProvider::class,
        FormsServiceProvider::class,
        AbilitiesProvider::class,
    ];

    private static bool $booted = false;

    private static ?Application $app = null;

    public static function init(): void
    {
        add_action('plugins_loaded', [self::class, 'boot']);
    }

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        $debug = defined('WP_DEBUG') && WP_DEBUG;

        self::$app = new Application($debug, providers: self::providersForState(self::runtimeState()));
        self::$app->boot();
    }

    public static function app(): Application
    {
        if (self::$app === null) {
            throw new RuntimeException('Corex has not booted yet; Boot::init() runs on plugins_loaded.');
        }

        return self::$app;
    }

    /**
     * @return list<class-string<\Corex\Foundation\ServiceProvider>>
     */
    public static function providersForState(AddonRuntimeState $state): array
    {
        return (new AddonProviderResolver((new AddonProviderRegistry())->all()))
            ->resolve(self::CORE_PROVIDERS, $state)
            ->providerClasses();
    }

    private static function runtimeState(): AddonRuntimeState
    {
        $providers = (new AddonProviderRegistry())->all();
        $activePlugins = self::activePlugins();

        return new AddonRuntimeState(
            activeSlugs: self::activeSlugs($providers, $activePlugins),
            installedPluginFiles: self::installedPluginFiles($providers),
            enabledFlags: self::enabledFlags($providers),
            externalGates: self::externalGates($providers),
        );
    }

    /**
     * @return list<string>
     */
    private static function activePlugins(): array
    {
        if (! function_exists('get_option')) {
            return [];
        }

        return array_map('strval', (array) get_option('active_plugins', []));
    }

    /**
     * @param list<AddonProvider> $providers
     * @param list<string>        $activePlugins
     *
     * @return list<string>
     */
    private static function activeSlugs(array $providers, array $activePlugins): array
    {
        $activeSlugs = [];

        foreach ($providers as $provider) {
            if (in_array($provider->pluginFile, $activePlugins, true)) {
                $activeSlugs[] = $provider->slug;
            }
        }

        return $activeSlugs;
    }

    /**
     * @param list<AddonProvider> $providers
     *
     * @return list<string>
     */
    private static function installedPluginFiles(array $providers): array
    {
        if (! defined('WP_PLUGIN_DIR')) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (AddonProvider $provider): string => $provider->pluginFile,
                $providers,
            ),
            static fn (string $pluginFile): bool => is_file(WP_PLUGIN_DIR . '/' . $pluginFile),
        ));
    }

    /**
     * @param list<AddonProvider> $providers
     *
     * @return list<string>
     */
    private static function enabledFlags(array $providers): array
    {
        $enabledFlags = [];

        foreach ($providers as $provider) {
            if ($provider->featureFlag !== null && self::featureFlagEnabled($provider->featureFlag)) {
                $enabledFlags[] = $provider->featureFlag;
            }
        }

        return array_values(array_unique($enabledFlags));
    }

    private static function featureFlagEnabled(string $flag): bool
    {
        if (function_exists('get_option')) {
            $optionValue = get_option('corex_features_' . $flag, false);

            if (in_array($optionValue, [true, 1, '1'], true)) {
                return true;
            }
        }

        $environmentValue = getenv('FEATURES_' . strtoupper($flag));

        return is_string($environmentValue)
            && in_array(strtolower(trim($environmentValue)), ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * @param list<AddonProvider> $providers
     *
     * @return array<string, bool>
     */
    private static function externalGates(array $providers): array
    {
        $gates = [];

        foreach ($providers as $provider) {
            if ($provider->externalGate !== null) {
                $gates[$provider->externalGate] = self::externalGateOpen($provider->externalGate);
            }
        }

        return $gates;
    }

    private static function externalGateOpen(string $gate): bool
    {
        return match ($gate) {
            'woocommerce' => class_exists('WooCommerce'),
            default => false,
        };
    }
}
