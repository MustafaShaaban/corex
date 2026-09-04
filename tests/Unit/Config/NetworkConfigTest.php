<?php

/**
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Container\Container;
use Corex\Foundation\CoreServiceProvider;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\NetworkContext;
use Corex\Support\BootLogger;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Config\Repository;
use Corex\Support\Config\SettingDefinition;
use Corex\Support\Config\SettingRegistry;
use Corex\Support\Config\SettingScope;
use Corex\Support\Config\Sources\DefaultsSource;
use Corex\Support\Config\Sources\DotenvSource;
use Corex\Support\Config\Sources\NetworkDefaultsSource;
use Corex\Support\Config\Sources\NetworkLockSource;
use Corex\Support\Config\Sources\OptionsSource;

function networkConfigMultisite(bool $enabled): MultisiteContext
{
    return new class ($enabled) implements MultisiteContext {
        public function __construct(private readonly bool $enabled)
        {
        }

        public function enabled(): bool
        {
            return $this->enabled;
        }

        public function subdomainInstall(): bool
        {
            return false;
        }

        public function isNetworkAdmin(): bool
        {
            return false;
        }

        public function isSwitched(): bool
        {
            return false;
        }
    };
}

function networkConfigContext(int $networkId = 7): NetworkContext
{
    return new class ($networkId) implements NetworkContext {
        public function __construct(private readonly int $networkId)
        {
        }

        public function id(): int
        {
            return $this->networkId;
        }

        public function mainSiteId(): int
        {
            return 1;
        }

        public function siteCount(): int
        {
            return 1;
        }

        public function siteIds(int $limit = 0, int $offset = 0): array
        {
            return [1];
        }

        public function exists(int $siteId): bool
        {
            return $siteId === 1;
        }
    };
}

function networkConfigEnv(?string $contents = null): string
{
    $directory = sys_get_temp_dir() . '/corex_network_config_' . uniqid('', true);
    mkdir($directory);

    if ($contents !== null) {
        file_put_contents($directory . '/.env', $contents);
    }

    return $directory;
}

function removeNetworkConfigEnv(string $directory): void
{
    if (is_file($directory . '/.env')) {
        unlink($directory . '/.env');
    }

    rmdir($directory);
}

it('defaults unregistered settings to site scope and keeps the last definition', function () {
    $registry = new SettingRegistry();
    $first = new SettingDefinition('security.throttle.limit', SettingScope::NetworkDefault, 'First');
    $last = new SettingDefinition('security.throttle.limit', SettingScope::NetworkLocked, 'Last');
    $site = new SettingDefinition('app.name', SettingScope::Site);

    $registry->add($first);
    $registry->add($last);
    $registry->add($site);

    expect($registry->scopeOf('unregistered.key'))->toBe(SettingScope::Site)
        ->and($registry->get('security.throttle.limit'))->toBe($last)
        ->and($registry->all())->toBe([$last, $site])
        ->and($registry->withScope(SettingScope::NetworkLocked))->toBe([$last])
        ->and($registry->withScope(SettingScope::Site))->toBe([$site]);
});

it('lazily filters scopes and ignores non-scope filter values', function () {
    $filterCalls = 0;

    Functions\when('apply_filters')->alias(static function (string $hook, array $scopes) use (&$filterCalls): array {
        $filterCalls++;
        expect($hook)->toBe('corex_setting_scopes');

        return [
            ...$scopes,
            'mail.transport' => SettingScope::NetworkLocked,
            'security.throttle.limit' => 'network-locked',
            'host.only' => SettingScope::NetworkDefault,
        ];
    });

    $registry = new SettingRegistry();
    $mail = new SettingDefinition('mail.transport', SettingScope::NetworkDefault, 'Transport');
    $security = new SettingDefinition('security.throttle.limit', SettingScope::NetworkDefault, 'Limit');
    $registry->add($mail);
    $registry->add($security);

    $all = $registry->all();

    expect($registry->scopeOf('mail.transport'))->toBe(SettingScope::NetworkLocked)
        ->and($registry->scopeOf('security.throttle.limit'))->toBe(SettingScope::NetworkDefault)
        ->and($registry->scopeOf('host.only'))->toBe(SettingScope::NetworkDefault)
        ->and($all[0]->scope)->toBe(SettingScope::NetworkLocked)
        ->and($all[1]->scope)->toBe(SettingScope::NetworkDefault)
        ->and($registry->withScope(SettingScope::NetworkLocked))->toHaveCount(1)
        ->and($filterCalls)->toBe(1);
});

it('keeps the old and new resolution chains identical when no network value is stored', function () {
    $siteOptions = [
        'corex_option_only' => 'site option',
        'corex_option_null' => null,
        'corex_collision' => 'site collision',
    ];

    Functions\when('get_option')->alias(
        static fn (string $name, mixed $default = false): mixed => array_key_exists($name, $siteOptions)
            ? $siteOptions[$name]
            : $default,
    );
    Functions\when('get_network_option')->returnArg(2);

    $directory = networkConfigEnv("ENV_ONLY=environment\nCOLLISION=\"environment collision\"\n");
    $defaults = [
        'default_only' => 'code default',
        'collision' => 'code collision',
    ];
    $registry = new SettingRegistry();
    $multisite = networkConfigMultisite(true);
    $network = networkConfigContext();

    try {
        $old = new Repository([
            new DotenvSource($directory, new BootLogger(false)),
            new OptionsSource(),
            new DefaultsSource($defaults),
        ]);
        $new = new Repository([
            new DotenvSource($directory, new BootLogger(false)),
            new NetworkLockSource($registry, $multisite, $network),
            new OptionsSource(),
            new NetworkDefaultsSource($registry, $multisite, $network),
            new DefaultsSource($defaults),
        ]);

        foreach (['env.only', 'option.only', 'default.only', 'missing', 'option.null', 'collision'] as $key) {
            expect($new->has($key))->toBe($old->has($key))
                ->and($new->get($key, 'caller fallback'))->toBe($old->get($key, 'caller fallback'));
        }

        expect($new->has('option.null'))->toBeTrue()
            ->and($new->get('option.null', 'caller fallback'))->toBeNull()
            ->and($new->get('missing', 'caller fallback'))->toBe('caller fallback');
    } finally {
        removeNetworkConfigEnv($directory);
    }
});

it('orders locks defaults site options and environment values by their declared meaning', function () {
    $siteOptions = [
        'corex_locked_key' => 'site locked',
        'corex_default_key' => 'site default',
        'corex_locked_env' => 'site locked env',
        'corex_default_env' => 'site default env',
    ];
    $networkOptions = [
        'corex_network_locked_key' => 'network locked',
        'corex_network_default_key' => 'network default',
        'corex_network_default_only' => 'network-only default',
        'corex_network_locked_env' => 'network locked env',
        'corex_network_default_env' => 'network default env',
    ];

    Functions\when('get_option')->alias(
        static fn (string $name, mixed $default = false): mixed => array_key_exists($name, $siteOptions)
            ? $siteOptions[$name]
            : $default,
    );
    Functions\when('get_network_option')->alias(
        static fn (int $networkId, string $name, mixed $default = false): mixed => array_key_exists($name, $networkOptions)
            ? $networkOptions[$name]
            : $default,
    );

    $registry = new SettingRegistry();
    $registry->add(new SettingDefinition('locked.key', SettingScope::NetworkLocked));
    $registry->add(new SettingDefinition('default.key', SettingScope::NetworkDefault));
    $registry->add(new SettingDefinition('default.only', SettingScope::NetworkDefault));
    $registry->add(new SettingDefinition('locked.env', SettingScope::NetworkLocked));
    $registry->add(new SettingDefinition('default.env', SettingScope::NetworkDefault));

    $directory = networkConfigEnv("LOCKED_ENV=\"deployment locked\"\nDEFAULT_ENV=\"deployment default\"\n");

    try {
        $repository = new Repository([
            new DotenvSource($directory, new BootLogger(false)),
            new NetworkLockSource($registry, networkConfigMultisite(true), networkConfigContext()),
            new OptionsSource(),
            new NetworkDefaultsSource($registry, networkConfigMultisite(true), networkConfigContext()),
            new DefaultsSource(['default' => ['only' => 'code default']]),
        ]);

        expect($repository->get('locked.key'))->toBe('network locked')
            ->and($repository->get('default.key'))->toBe('site default')
            ->and($repository->get('default.only'))->toBe('network-only default')
            ->and($repository->get('locked.env'))->toBe('deployment locked')
            ->and($repository->get('default.env'))->toBe('deployment default');
    } finally {
        removeNetworkConfigEnv($directory);
    }
});

it('makes both network sources decline disabled multisite and unregistered keys', function () {
    Functions\when('get_network_option')->justReturn('stored network value');

    $registry = new SettingRegistry();
    $registry->add(new SettingDefinition('locked.key', SettingScope::NetworkLocked));
    $network = networkConfigContext();

    expect((new NetworkLockSource($registry, networkConfigMultisite(false), $network))->has('locked.key'))->toBeFalse()
        ->and((new NetworkLockSource($registry, networkConfigMultisite(true), $network))->has('unregistered.key'))->toBeFalse()
        ->and((new NetworkDefaultsSource($registry, networkConfigMultisite(true), $network))->has('unregistered.key'))->toBeFalse();
});

it('honors a network lock registered after the config singleton was resolved', function () {
    if (! defined('COREX_CORE_PATH')) {
        define('COREX_CORE_PATH', dirname(__DIR__, 3) . '/plugins/corex-core/');
    }

    Functions\when('get_option')->alias(
        static fn (string $name, mixed $default = false): mixed => $name === 'corex_dynamic_key'
            ? 'site value'
            : $default,
    );
    Functions\when('get_network_option')->alias(
        static fn (int $networkId, string $name, mixed $default = false): mixed => $name === 'corex_network_dynamic_key'
            ? 'network value'
            : $default,
    );

    $container = new Container();
    $container->instance(BootLogger::class, new BootLogger(false));
    $container->instance(MultisiteContext::class, networkConfigMultisite(true));
    $container->instance(NetworkContext::class, networkConfigContext());
    (new CoreServiceProvider($container))->register();

    $config = $container->make(ConfigInterface::class);

    expect($config->get('dynamic.key'))->toBe('site value');

    $container->make(SettingRegistry::class)->add(
        new SettingDefinition('dynamic.key', SettingScope::NetworkLocked),
    );

    expect($config->get('dynamic.key'))->toBe('network value');
});
