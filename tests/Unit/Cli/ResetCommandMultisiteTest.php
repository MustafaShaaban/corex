<?php

/**
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Cli\Commands\ResetCommand;
use Corex\Cli\Reset\ResetAction;
use Corex\Cli\Reset\ResetExecutor;
use Corex\Cli\Reset\ResetGate;
use Corex\Cli\Reset\ResetPlanner;
use Corex\Cli\Reset\ResetRequest;
use Corex\Multisite\ActivationScope;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\PluginActivationInspector;

it('refuses a network-activated add-on without the network flag', function () {
    Functions\when('__')->returnArg();

    $inspector = new class implements PluginActivationInspector {
        public function scopeOf(string $pluginFile): ActivationScope
        {
            return $pluginFile === 'corex-ui/corex-ui.php'
                ? ActivationScope::Network
                : ActivationScope::None;
        }

        public function isActive(string $pluginFile): bool
        {
            return $this->scopeOf($pluginFile)->isActive();
        }

        public function activePluginFiles(): array
        {
            return ['corex-ui/corex-ui.php'];
        }

        public function scopes(): array
        {
            return ['corex-ui/corex-ui.php' => ActivationScope::Network];
        }
    };
    $multisite = new class implements MultisiteContext {
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
    $command = new ResetCommand(
        new ResetPlanner(),
        new ResetGate(),
        new ResetExecutor(),
        $inspector,
        $multisite,
    );
    $method = new ReflectionMethod($command, 'networkGuardMessage');
    $method->setAccessible(true);

    expect($method->invoke($command, new ResetRequest()))->toContain('--network')
        ->and($method->invoke($command, new ResetRequest(network: true)))->toBeNull();
});

it('refuses a full multisite database reset without the network flag', function () {
    $gate = new ResetGate();
    $request = new ResetRequest(ResetRequest::FULL, confirmed: true);

    expect($gate->permitsScope($request, true, false))->toBeFalse()
        ->and($gate->permitsScope(new ResetRequest(
            ResetRequest::FULL,
            confirmed: true,
            network: true,
        ), true, false))->toBeTrue();
});

it('passes the network-wide flag when deactivating a network add-on', function () {
    Functions\expect('deactivate_plugins')
        ->once()
        ->with('corex-ui/corex-ui.php', false, true);

    (new ResetExecutor())->apply(
        new ResetAction(ResetAction::DEACTIVATE_ADDON, 'corex-ui/corex-ui.php', 'Deactivate UI'),
        true,
    );
});
