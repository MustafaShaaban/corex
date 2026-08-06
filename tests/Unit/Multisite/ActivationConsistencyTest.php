<?php

/**
 * @package Corex\Tests\Unit\Multisite
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Boot;
use Corex\Config\Addons\AddonManager;
use Corex\Config\Addons\AddonRegistry;
use Corex\Foundation\AddonProviderRegistry;
use Corex\Foundation\AddonRuntimeState;
use Corex\Multisite\ActivationScope;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\WpPluginActivationInspector;

function consistencyMultisiteContext(bool $enabled): MultisiteContext
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

it('keeps admin and runtime activation state aligned for every install shape', function (
    array $sitePlugins,
    array $networkPlugins,
    ?ActivationScope $filterScope,
    ActivationScope $expected,
) {
    Functions\when('get_option')->alias(
        static fn (string $name, mixed $default = false): mixed => $name === 'active_plugins'
            ? $sitePlugins
            : $default,
    );
    Functions\when('get_site_option')->alias(
        static fn (string $name, mixed $default = false): mixed => $name === 'active_sitewide_plugins'
            ? $networkPlugins
            : $default,
    );
    Functions\when('get_current_blog_id')->justReturn(9);
    Functions\when('apply_filters')->alias(
        static function (string $hook, array $scopes) use ($filterScope): array {
            if ($filterScope !== null) {
                $scopes['corex-ui/corex-ui.php'] = $filterScope;
            }

            return $scopes;
        },
    );

    $inspector = new WpPluginActivationInspector(
        consistencyMultisiteContext($networkPlugins !== [] || $filterScope === ActivationScope::MustUse),
    );
    $adminState = (new AddonManager(new AddonRegistry(), $inspector))->state();

    $method = new ReflectionMethod(Boot::class, 'activeSlugs');
    $method->setAccessible(true);
    [$activeSlugs, $activationScopes] = $method->invoke(
        null,
        (new AddonProviderRegistry())->all(),
        $inspector,
    );
    $runtimeState = new AddonRuntimeState(
        activeSlugs: $activeSlugs,
        activationScopes: $activationScopes,
    );

    expect($adminState->scopeOf('corex-ui'))->toBe($expected)
        ->and($runtimeState->scopeOf('corex-ui'))->toBe($expected)
        ->and($adminState->isActive('corex-ui'))->toBe($runtimeState->isActive('corex-ui'));
})->with([
    'inactive' => [[], [], null, ActivationScope::None],
    'site' => [['corex-ui/corex-ui.php'], [], null, ActivationScope::Site],
    'network' => [[], ['corex-ui/corex-ui.php' => 10], null, ActivationScope::Network],
    'must-use' => [[], [], ActivationScope::MustUse, ActivationScope::MustUse],
]);
