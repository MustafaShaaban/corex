<?php

/**
 * @package Corex\Tests\Unit\Multisite
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Multisite\ActivationScope;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\SingleSiteMultisiteContext;
use Corex\Multisite\SiteContext;
use Corex\Multisite\SiteScopeManager;
use Corex\Multisite\WpPluginActivationInspector;
use Corex\Support\BootLogger;

function enabledMultisiteContext(): MultisiteContext
{
    return new class implements MultisiteContext {
        public function enabled(): bool
        {
            return true;
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

it('classifies activation scopes and translates their labels', function () {
    Functions\when('__')->returnArg();

    expect(ActivationScope::None->isActive())->toBeFalse()
        ->and(ActivationScope::Site->isActive())->toBeTrue()
        ->and(ActivationScope::Network->isActive())->toBeTrue()
        ->and(ActivationScope::MustUse->isActive())->toBeTrue()
        ->and(ActivationScope::Network->label())->toBe('Network');
});

it('lets network activation win and returns a deduped stable network-first list', function () {
    Functions\when('get_option')->alias(static fn (string $name, mixed $default = false): mixed => [
        'site-only/site.php',
        'shared/shared.php',
        'site-last/site-last.php',
    ]);
    Functions\when('get_site_option')->alias(static fn (string $name, mixed $default = false): mixed => [
        'network-first/network.php' => 10,
        'shared/shared.php' => 20,
    ]);
    Functions\when('get_current_blog_id')->justReturn(7);
    Functions\when('apply_filters')->returnArg(2);

    $inspector = new WpPluginActivationInspector(enabledMultisiteContext());

    expect($inspector->scopeOf('shared/shared.php'))->toBe(ActivationScope::Network)
        ->and($inspector->activePluginFiles())->toBe([
            'network-first/network.php',
            'shared/shared.php',
            'site-only/site.php',
            'site-last/site-last.php',
        ]);
});

it('accepts must-use only through the filter and drops invalid filter values', function () {
    Functions\when('get_option')->justReturn([]);
    Functions\when('get_site_option')->justReturn([]);
    Functions\when('get_current_blog_id')->justReturn(12);
    Functions\expect('apply_filters')
        ->once()
        ->with('corex_plugin_activation_scopes', [], 12)
        ->andReturn([
            'corex-ui/corex-ui.php' => ActivationScope::MustUse,
            'invalid/invalid.php' => 'must-use',
        ]);

    $inspector = new WpPluginActivationInspector(enabledMultisiteContext());

    expect($inspector->scopes())->toBe([
        'corex-ui/corex-ui.php' => ActivationScope::MustUse,
    ]);
});

it('matches the single-site active_plugins list without reading network state', function () {
    $active = ['corex-ui/corex-ui.php', 'corex-email/corex-email.php'];

    Functions\expect('get_option')->once()->with('active_plugins', [])->andReturn($active);
    Functions\expect('get_site_option')->never();
    Functions\when('apply_filters')->returnArg(2);

    $inspector = new WpPluginActivationInspector(new SingleSiteMultisiteContext());

    expect($inspector->activePluginFiles())->toBe($active)
        ->and($inspector->scopes())->toBe([
            'corex-ui/corex-ui.php' => ActivationScope::Site,
            'corex-email/corex-email.php' => ActivationScope::Site,
        ]);
});

it('drops its memo when the site scope receives switch_blog', function () {
    $siteId = 1;

    Functions\when('get_option')->alias(static function () use (&$siteId): array {
        return [$siteId === 1 ? 'site-one/plugin.php' : 'site-two/plugin.php'];
    });
    Functions\when('get_site_option')->justReturn([]);
    Functions\when('get_current_blog_id')->alias(static fn (): int => $siteId);
    Functions\when('apply_filters')->returnArg(2);

    $multisite = enabledMultisiteContext();
    $site = new class implements SiteContext {
        public function id(): int
        {
            return 1;
        }

        public function networkId(): int
        {
            return 1;
        }

        public function isMainSite(): bool
        {
            return true;
        }

        public function url(): string
        {
            return 'https://site-one.test';
        }
    };
    $listeners = new ListenerProvider();
    $scope = new SiteScopeManager(
        $multisite,
        $site,
        new EventDispatcher($listeners, new BootLogger(false)),
        $listeners,
    );
    $inspector = new WpPluginActivationInspector($multisite);
    $scope->register($inspector);

    expect($inspector->activePluginFiles())->toBe(['site-one/plugin.php']);

    $siteId = 2;
    $scope->onSwitchBlog(2, 1, 'switch');

    expect($inspector->activePluginFiles())->toBe(['site-two/plugin.php']);
});
