<?php

/**
 * @package Corex\Tests\Unit\Multisite
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Multisite\SingleSiteCapabilities;
use Corex\Multisite\SingleSiteContext;
use Corex\Multisite\SingleSiteMultisiteContext;
use Corex\Multisite\SingleSiteNetworkContext;
use Corex\Multisite\WpMultisiteContext;
use Corex\Multisite\WpNetworkCapabilities;
use Corex\Multisite\WpNetworkContext;
use Corex\Multisite\WpSiteContext;

it('returns exact single-site context constants without asking WordPress', function () {
    foreach ([
        'is_multisite',
        'get_current_blog_id',
        'get_current_network_id',
        'is_main_site',
        'get_sites',
        'get_site',
        'is_network_admin',
        'ms_is_switched',
    ] as $function) {
        Functions\expect($function)->never();
    }

    $multisite = new SingleSiteMultisiteContext();
    $site = new SingleSiteContext();
    $network = new SingleSiteNetworkContext();

    expect($multisite->enabled())->toBeFalse()
        ->and($multisite->subdomainInstall())->toBeFalse()
        ->and($multisite->isNetworkAdmin())->toBeFalse()
        ->and($multisite->isSwitched())->toBeFalse()
        ->and($site->id())->toBe(1)
        ->and($site->networkId())->toBe(1)
        ->and($site->isMainSite())->toBeTrue()
        ->and($network->id())->toBe(1)
        ->and($network->mainSiteId())->toBe(1)
        ->and($network->siteCount())->toBe(1)
        ->and($network->siteIds())->toBe([1])
        ->and($network->siteIds(1, 50))->toBe([1])
        ->and($network->exists(1))->toBeTrue()
        ->and($network->exists(2))->toBeFalse();
});

it('asks WordPress only for the configured single-site URL', function () {
    Functions\expect('home_url')->once()->andReturn('https://single.test');

    expect((new SingleSiteContext())->url())->toBe('https://single.test');
});

it('maps single-site capability checks to WordPress capabilities', function () {
    $capabilities = [];
    Functions\when('current_user_can')->alias(function (string $capability) use (&$capabilities): bool {
        $capabilities[] = $capability;

        return $capability === 'manage_options';
    });

    $context = new SingleSiteCapabilities();

    expect($context->currentUserIsSuperAdmin())->toBeTrue()
        ->and($context->currentUserCan('edit_posts'))->toBeFalse()
        ->and($capabilities)->toBe(['manage_options', 'edit_posts']);
});

it('returns values supplied by WordPress multisite functions', function () {
    Functions\expect('is_multisite')->once()->andReturn(true);
    Functions\expect('is_network_admin')->once()->andReturn(true);
    Functions\expect('ms_is_switched')->once()->andReturn(true);
    Functions\expect('get_current_blog_id')->once()->andReturn(7);
    Functions\expect('get_current_network_id')->twice()->andReturn(4);
    Functions\expect('is_main_site')->once()->andReturn(false);
    Functions\expect('home_url')->once()->andReturn('https://site-7.test');
    Functions\expect('get_network')->once()->andReturn((object) ['site_id' => '3']);
    Functions\expect('get_site')->twice()->andReturnUsing(
        static fn (int $siteId): ?object => $siteId === 7 ? (object) ['blog_id' => 7] : null,
    );
    Functions\expect('is_super_admin')->once()->andReturn(true);
    Functions\expect('current_user_can')->once()->with('manage_network')->andReturn(false);

    $multisite = new WpMultisiteContext();
    $site = new WpSiteContext();
    $network = new WpNetworkContext();
    $capabilities = new WpNetworkCapabilities();

    expect($multisite->enabled())->toBeTrue()
        ->and($multisite->subdomainInstall())->toBe(defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL)
        ->and($multisite->isNetworkAdmin())->toBeTrue()
        ->and($multisite->isSwitched())->toBeTrue()
        ->and($site->id())->toBe(7)
        ->and($site->networkId())->toBe(4)
        ->and($site->isMainSite())->toBeFalse()
        ->and($site->url())->toBe('https://site-7.test')
        ->and($network->id())->toBe(4)
        ->and($network->mainSiteId())->toBe(3)
        ->and($network->exists(7))->toBeTrue()
        ->and($network->exists(8))->toBeFalse()
        ->and($capabilities->currentUserIsSuperAdmin())->toBeTrue()
        ->and($capabilities->currentUserCan('manage_network'))->toBeFalse();
});

it('enumerates active and archived sites while excluding deleted and spam sites', function () {
    $queries = [];
    Functions\when('get_sites')->alias(function (array $query) use (&$queries): array|int {
        $queries[] = $query;

        return isset($query['count']) ? 2 : ['3', '7'];
    });

    $network = new WpNetworkContext();

    expect($network->siteCount())->toBe(2)
        ->and($network->siteIds(25, 50))->toBe([3, 7])
        ->and($queries[0])->toBe([
            'count' => true,
            'deleted' => 0,
            'spam' => 0,
        ])
        ->and($queries[1])->toBe([
            'fields' => 'ids',
            'number' => 25,
            'offset' => 50,
            'orderby' => 'id',
            'order' => 'ASC',
            'deleted' => 0,
            'spam' => 0,
        ])
        ->and($queries[1])->not->toHaveKey('archived');
});
