<?php

/**
 * Integration tests for the retired Data screen's address.
 *
 * `page=corex-data` and `page=corex-data-models` rendered the identical explorer, so the standalone
 * screen was retired. The address has to keep working: people have it bookmarked, and docs link it.
 *
 * @package Corex\Tests\Integration\DataModels
 */

declare(strict_types=1);

use Corex\Config\Data\DataAdminScreen;

beforeEach(function () {
    $this->screen = new DataAdminScreen();
});

it('redirects the retired Data address and leaves every other page alone', function (string $page, bool $expected) {
    expect($this->screen->redirects($page))->toBe($expected);
})->with([
    'the retired address' => ['corex-data', true],
    'its replacement (must not loop)' => ['corex-data-models', false],
    // The prefix overlap is the trap here: a substring match would hijack all of these.
    'submissions' => ['corex-submissions', false],
    'settings' => ['corex-settings', false],
    'no page at all' => ['', false],
]);

it('lands on the tab that does what the old screen did', function () {
    $target = $this->screen->target();

    expect($target)->toContain('page=corex-data-models')
        ->and($target)->toContain('tab=records')
        ->and($target)->toStartWith(admin_url());
});

it('sends the redirect somewhere wp_safe_redirect will actually allow', function () {
    // wp_safe_redirect drops off-host targets to the admin, so a target it rejects would silently
    // strand the visitor rather than move them.
    expect(wp_validate_redirect($this->screen->target(), ''))->toBe($this->screen->target());
});

it('no longer registers a menu entry, so Data appears once', function () {
    expect(method_exists($this->screen, 'menu'))->toBeFalse()
        ->and(method_exists($this->screen, 'render'))->toBeFalse();
});
