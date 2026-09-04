<?php

/**
 * @package Corex\Tests\Unit\Multisite
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Multisite\Events\SiteScopeSwitched;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\SingleSiteContext;
use Corex\Multisite\SingleSiteMultisiteContext;
use Corex\Multisite\SingleSiteScope;
use Corex\Multisite\SiteScoped;
use Corex\Multisite\SiteScopeManager;
use Corex\Support\BootLogger;

/**
 * @return array{0: SiteScopeManager, 1: ListenerProvider}
 */
function multisiteScopeManager(bool $multisiteEnabled = true): array
{
    $listeners = new ListenerProvider();
    $events = new EventDispatcher($listeners, new BootLogger(false));

    $multisite = $multisiteEnabled
        ? new class implements MultisiteContext {
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
        }
        : new SingleSiteMultisiteContext();

    return [
        new SiteScopeManager(
            $multisite,
            new SingleSiteContext(),
            $events,
            $listeners,
        ),
        $listeners,
    ];
}

function recordingSiteScopedService(): SiteScoped
{
    return new class implements SiteScoped {
        /** @var list<int> */
        public array $forgotten = [];

        public function forgetSiteState(int $siteId): void
        {
            $this->forgotten[] = $siteId;
        }
    };
}

it('does nothing when the switch leaves the current site unchanged', function () {
    [$scope] = multisiteScopeManager();
    $service = recordingSiteScopedService();
    $scope->register($service);

    $scope->onSwitchBlog(1, 1, 'switch');

    expect($scope->currentSiteId())->toBe(1)
        ->and($service->forgotten)->toBe([]);
});

it('invalidates every registered service with the new site id', function () {
    [$scope] = multisiteScopeManager();
    $first = recordingSiteScopedService();
    $second = recordingSiteScopedService();
    $scope->register($first);
    $scope->register($second);

    $scope->onSwitchBlog(2, 1, 'switch');

    expect($scope->currentSiteId())->toBe(2)
        ->and($first->forgotten)->toBe([2])
        ->and($second->forgotten)->toBe([2]);
});

it('handles a switch and restore through the same invalidation path', function () {
    [$scope] = multisiteScopeManager();
    $service = recordingSiteScopedService();
    $scope->register($service);

    $scope->onSwitchBlog(2, 1, 'switch');
    $scope->onSwitchBlog(1, 2, 'restore');

    expect($scope->currentSiteId())->toBe(1)
        ->and($service->forgotten)->toBe([2, 1]);
});

it('registers each site-scoped service instance only once', function () {
    [$scope] = multisiteScopeManager();
    $service = recordingSiteScopedService();
    $scope->register($service);
    $scope->register($service);

    $scope->onSwitchBlog(2, 1);

    expect($service->forgotten)->toBe([2]);
});

it('returns the callback value and restores the previous site', function () {
    Functions\expect('switch_to_blog')->once()->with(2);
    Functions\expect('restore_current_blog')->once();
    [$scope] = multisiteScopeManager();

    expect($scope->run(2, static fn (): string => 'done'))->toBe('done');
});

it('restores the previous site and propagates a callback exception', function () {
    Functions\expect('switch_to_blog')->once()->with(2);
    Functions\expect('restore_current_blog')->once();
    [$scope] = multisiteScopeManager();

    expect(fn () => $scope->run(2, static function (): never {
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class, 'boom');
});

it('runs directly when the requested site is already current', function () {
    Functions\expect('switch_to_blog')->never();
    Functions\expect('restore_current_blog')->never();
    [$scope] = multisiteScopeManager();

    expect($scope->run(1, static fn (): int => 42))->toBe(42);
});

it('dispatches a site-scope event only when a listener consumes it', function () {
    [$scope, $listeners] = multisiteScopeManager();
    $events = [];

    expect($listeners->hasListenersFor(SiteScopeSwitched::class))->toBeFalse();

    $scope->onSwitchBlog(2, 1);
    $listeners->listen(SiteScopeSwitched::class, function (SiteScopeSwitched $event) use (&$events): void {
        $events[] = $event;
    });
    $scope->onSwitchBlog(3, 2);

    expect($listeners->hasListenersFor(SiteScopeSwitched::class))->toBeTrue()
        ->and($events)->toHaveCount(1)
        ->and($events[0]->toSiteId)->toBe(3)
        ->and($events[0]->fromSiteId)->toBe(2);
});

it('registers the switch hook at the invalidation-first priority', function () {
    Functions\expect('add_action')->once()->with('switch_blog', \Mockery::type('array'), 0, 3);
    [$scope] = multisiteScopeManager();

    $scope->listen();
});

it('never hooks switch_blog on a single-site install', function () {
    Functions\expect('add_action')->never();
    [$scope] = multisiteScopeManager(multisiteEnabled: false);

    $scope->listen();
});

it('runs a single-site callback without WordPress interaction', function () {
    Functions\expect('switch_to_blog')->never();
    Functions\expect('restore_current_blog')->never();
    Functions\expect('get_current_blog_id')->never();

    expect((new SingleSiteScope())->run(99, static fn (): string => 'single'))->toBe('single');
});
