<?php

/**
 * Integration tests for the optional Dashboard widgets (spec 072 US7: T024, FR-025).
 *
 * Real WordPress. The unit test proves the four rules in isolation; this proves the wiring actually
 * feeds them — that the site option really gates registration, and that a Development-only widget is
 * absent from a Production dashboard even when it is opted into and the actor is an administrator.
 *
 * @package Corex\Tests\Integration\Notifications
 */

declare(strict_types=1);

use Corex\Config\Notifications\OptionalDashboardWidgets;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;

/** Register onto a clean dashboard and return the ids that landed on it. */
function registeredDashboardIds(OptionalDashboardWidgets $widgets): array
{
    require_once ABSPATH . 'wp-admin/includes/dashboard.php';
    set_current_screen('dashboard');
    global $wp_meta_boxes;
    unset($wp_meta_boxes['dashboard']);

    $widgets->add();

    return array_keys($wp_meta_boxes['dashboard']['normal']['core'] ?? []);
}

beforeEach(function () {
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));

    $this->widgets = Corex\Boot::app()->container()->make(OptionalDashboardWidgets::class);
    $this->modes   = Corex\Boot::app()->container()->make(OperationsModeStore::class);
    $this->wasMode = $this->modes->current();
});

afterEach(function () {
    delete_option(OptionalDashboardWidgets::OPTION);
    $this->modes->set($this->wasMode, get_current_user_id());
    // Leave a front-end screen behind: a lingering admin screen leaks into later tests.
    set_current_screen('front');
    wp_set_current_user(0);
});

it('registers nothing on a dashboard that opted into nothing', function () {
    delete_option(OptionalDashboardWidgets::OPTION);

    expect(registeredDashboardIds($this->widgets))
        ->not->toContain(OptionalDashboardWidgets::ATTENTION)
        ->not->toContain(OptionalDashboardWidgets::DEVELOPMENT);
});

it('keeps an opted-in Development widget off a Production dashboard', function () {
    update_option(OptionalDashboardWidgets::OPTION, [OptionalDashboardWidgets::DEVELOPMENT]);
    $this->modes->set(OperationsMode::PRODUCTION, get_current_user_id());

    expect(registeredDashboardIds($this->widgets))
        ->not->toContain(OptionalDashboardWidgets::DEVELOPMENT);
});

it('registers the opted-in Development widget once the site is in Development', function () {
    update_option(OptionalDashboardWidgets::OPTION, [OptionalDashboardWidgets::DEVELOPMENT]);
    $this->modes->set(OperationsMode::DEVELOPMENT, get_current_user_id());

    expect(registeredDashboardIds($this->widgets))
        ->toContain(OptionalDashboardWidgets::DEVELOPMENT);
});

it('ignores an id in the option that is not a declared widget', function () {
    // A stale or hand-edited option must not conjure a widget with no ability rule attached.
    update_option(OptionalDashboardWidgets::OPTION, ['corex_not_a_widget']);
    $this->modes->set(OperationsMode::DEVELOPMENT, get_current_user_id());

    expect(registeredDashboardIds($this->widgets))->not->toContain('corex_not_a_widget');
});

it('renders the Development widget with navigation-only links and no fatal', function () {
    $this->modes->set(OperationsMode::DEVELOPMENT, get_current_user_id());

    ob_start();
    $this->widgets->renderDevelopment();
    $html = (string) ob_get_clean();

    expect($html)->toContain('page=corex-operations-security')
        ->and($html)->not->toContain('<form')
        ->and($html)->not->toContain('<button');
});

it('renders the attention widget without querying for itself', function () {
    // Reuses NotificationService::forCurrentActor — the same bounded read the screen and drawer use.
    // With nothing unread it must still render an honest line rather than fatal on an empty result.
    ob_start();
    $this->widgets->renderAttention();
    $html = (string) ob_get_clean();

    expect($html)->not->toBe('');
});
