<?php

/**
 * The Overview reads each fact it names (spec 114, US-SAFE-10), on real WordPress.
 *
 * Its rows came from a status that called branding, forms and PageSpeed configured by default,
 * a captcha driver of None configured, and counted hardening warnings as login protection. Here
 * the facts are read from where they live, each set for the test through the option it is
 * stored in, and the rendered Overview is read for the words it draws.
 *
 * @package Corex\Tests\Integration\Config
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Overview\OverviewFacts;
use Corex\Config\Overview\OverviewRenderer;
use Corex\Config\Security\LoginProtection\LoginAddressState;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;

beforeEach(function () {
    $this->filters = [];
    $this->answer  = function (string $option, mixed $value): void {
        $filter = static fn (): mixed => $value;
        add_filter('pre_option_' . $option, $filter);
        $this->filters[$option] = $filter;
    };
});

afterEach(function () {
    foreach ($this->filters as $option => $filter) {
        remove_filter('pre_option_' . $option, $filter);
    }
});

it('reads the login address from the Security Center settings', function (array $stored, string $state) {
    ($this->answer)(LoginProtectionSettingsStore::OPTION, $stored);

    $facts = Boot::app()->container()->make(OverviewFacts::class)->gather([]);

    expect($facts['login'])->toBe($state);
})->with([
    'protection off, hiding on (a fresh install)' => [['enabled' => false, 'block_default_endpoints' => true], LoginAddressState::OFF],
    'protection on, hiding on' => [['enabled' => true, 'custom_slug' => 'team-login', 'block_default_endpoints' => true], LoginAddressState::HIDDEN],
    'spec 124: the admin hidden alone' => [['hide_wp_login' => false, 'hide_admin' => true, 'limit_attempts' => false, 'enabled' => true, 'block_default_endpoints' => false], LoginAddressState::REACHABLE],
    'spec 124: wp-login.php hidden alone' => [['hide_wp_login' => true, 'hide_admin' => false, 'limit_attempts' => false, 'enabled' => true, 'block_default_endpoints' => false], LoginAddressState::HIDDEN],
]);

it('spec 124 FR-013, scenario 7: reports each login switch as it is in force, in either stored shape', function (array $stored, array $switches) {
    ($this->answer)(LoginProtectionSettingsStore::OPTION, $stored);

    $facts = Boot::app()->container()->make(OverviewFacts::class)->gather([]);

    expect($facts['loginSwitches'])->toBe($switches + ['recovery' => defined('COREX_LOGIN_UNGUARD') && COREX_LOGIN_UNGUARD === true]);
})->with([
    'previous release, protection on, hiding on' => [['enabled' => true, 'block_default_endpoints' => true], ['hideWpLogin' => true, 'hideAdmin' => true, 'limitAttempts' => true]],
    'previous release, protection on, hiding off' => [['enabled' => true, 'block_default_endpoints' => false], ['hideWpLogin' => false, 'hideAdmin' => false, 'limitAttempts' => true]],
    'previous release, protection off, hiding on' => [['enabled' => false, 'block_default_endpoints' => true], ['hideWpLogin' => false, 'hideAdmin' => false, 'limitAttempts' => false]],
    'this release, the admin hidden alone' => [['hide_wp_login' => false, 'hide_admin' => true, 'limit_attempts' => false, 'enabled' => true, 'block_default_endpoints' => false], ['hideWpLogin' => false, 'hideAdmin' => true, 'limitAttempts' => false]],
    'this release, wp-login.php hidden and limits' => [['hide_wp_login' => true, 'hide_admin' => false, 'limit_attempts' => true, 'enabled' => true, 'block_default_endpoints' => false], ['hideWpLogin' => true, 'hideAdmin' => false, 'limitAttempts' => true]],
]);

it('reads the latest PageSpeed run, a failed one stored by an earlier release included', function () {
    ($this->answer)('corex_insights', ['performance' => ['latest' => [
        'provider' => 'performance', 'label' => 'Performance', 'score' => 50, 'grade' => 'D', 'status' => 'recommended',
        'summary' => 'PageSpeed cannot crawl a local or private URL. Test a public URL instead.',
        'metrics' => [], 'recommendations' => ['PageSpeed cannot crawl a local or private URL. Test a public URL instead.'],
        'checkedAt' => 1700000000,
    ]]]);

    $latest = Boot::app()->container()->make(OverviewFacts::class)->gather([])['insights']['latest'];

    expect($latest['status'])->toBe('failed')
        ->and($latest['score'])->toBeNull();
});

it('draws a brand with no logo as pending, linked to the Brand settings', function () {
    // Since spec 118 the brand is a check of the one launch checklist, read where it is saved.
    ($this->answer)('corex_brand_company_name', 'Acme Trading');
    ($this->answer)('corex_brand_logo_url', '');

    $html = Boot::app()->container()->make(OverviewRenderer::class)->render([]);

    expect($html)->toContain('<a href="' . esc_url(admin_url('admin.php?page=corex-settings-config&corex_tab=brand')) . '">Brand set</a>')
        ->and($html)->toContain('No admin logo')
        ->and($html)->toContain('Security and hosting')
        ->and($html)->not->toContain('Provider connected');
});
