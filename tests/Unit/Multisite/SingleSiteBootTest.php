<?php

/**
 * @package Corex\Tests\Unit\Multisite
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Multisite\RuntimeContexts;
use Corex\Multisite\SingleSiteCapabilities;
use Corex\Multisite\SingleSiteContext;
use Corex\Multisite\SingleSiteMultisiteContext;
use Corex\Multisite\SingleSiteNetworkContext;
use Corex\Multisite\SingleSiteScope;
use Corex\Multisite\SiteScopeManager;
use Corex\Multisite\WpMultisiteContext;
use Corex\Multisite\WpNetworkCapabilities;
use Corex\Multisite\WpNetworkContext;
use Corex\Multisite\WpSiteContext;

it('detects a single-site runtime without registering a switch hook', function () {
    Functions\expect('is_multisite')->once()->andReturn(false);
    Functions\expect('add_action')->never();

    $contexts = RuntimeContexts::detect();

    expect($contexts->multisite)->toBeInstanceOf(SingleSiteMultisiteContext::class)
        ->and($contexts->site)->toBeInstanceOf(SingleSiteContext::class)
        ->and($contexts->network)->toBeInstanceOf(SingleSiteNetworkContext::class)
        ->and($contexts->capabilities)->toBeInstanceOf(SingleSiteCapabilities::class)
        ->and($contexts->scope)->toBeInstanceOf(SingleSiteScope::class);
});

it('detects WordPress contexts and a managed scope on multisite', function () {
    Functions\expect('is_multisite')->once()->andReturn(true);
    Functions\expect('get_current_blog_id')->once()->andReturn(6);
    Functions\expect('add_action')->never();

    $contexts = RuntimeContexts::detect();

    expect($contexts->multisite)->toBeInstanceOf(WpMultisiteContext::class)
        ->and($contexts->site)->toBeInstanceOf(WpSiteContext::class)
        ->and($contexts->network)->toBeInstanceOf(WpNetworkContext::class)
        ->and($contexts->capabilities)->toBeInstanceOf(WpNetworkCapabilities::class)
        ->and($contexts->scope)->toBeInstanceOf(SiteScopeManager::class)
        ->and($contexts->scope->currentSiteId())->toBe(6);
});
