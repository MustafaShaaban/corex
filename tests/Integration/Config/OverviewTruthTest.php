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
