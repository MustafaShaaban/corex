<?php

/**
 * @package Corex\Tests\Fixtures\Multisite
 */

declare(strict_types=1);

namespace Corex\Tests\Fixtures\Multisite;

defined('ABSPATH') || exit;

use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\SiteContext;
use Corex\Multisite\SiteScopeManager;
use Corex\Support\BootLogger;

final class SiteScopeEnvironment
{
    public readonly SiteScopeManager $scope;

    public function __construct(int $siteId = 1)
    {
        $listeners = new ListenerProvider();
        $this->scope = new SiteScopeManager(
            new EnabledMultisiteContext(),
            new FixedSiteContext($siteId),
            new EventDispatcher($listeners, new BootLogger(false)),
            $listeners,
        );
    }

    public function switchTo(int $siteId): void
    {
        $this->scope->onSwitchBlog($siteId, $this->scope->currentSiteId(), 'switch');
    }
}

final class EnabledMultisiteContext implements MultisiteContext
{
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

final class FixedSiteContext implements SiteContext
{
    public function __construct(private readonly int $siteId)
    {
    }

    public function id(): int
    {
        return $this->siteId;
    }

    public function networkId(): int
    {
        return 1;
    }

    public function isMainSite(): bool
    {
        return $this->siteId === 1;
    }

    public function url(): string
    {
        return 'https://site-' . $this->siteId . '.test';
    }
}
