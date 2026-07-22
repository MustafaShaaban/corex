<?php

/**
 * Unit tests for the admin-toolbar notification entry (spec 072 US2: FR-017).
 *
 * Adds a toolbar node with the actor's unread count and a link to the center — but only when the
 * actor may manage notifications and is not on a CoreX screen (where the shell bell already shows,
 * so the two never appear at once).
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\AdminUi\CorexAdminAssets;
use Corex\Config\Notifications\NotificationToolbar;
use Corex\Tests\Support\RecordingNotificationService;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('_n')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('admin_url')->alias(static fn (string $p = ''): string => 'http://example.test/wp-admin/' . $p);
    Functions\when('is_admin')->justReturn(false); // front end: not a CoreX admin screen
});

// Minimal WP_Admin_Bar stub (WordPress is not loaded in the unit suite) that records added nodes,
// so the production `WP_Admin_Bar` type hint is satisfied by a real instance of that class name.
if (! class_exists('WP_Admin_Bar')) {
    class WP_Admin_Bar
    {
        /** @var list<array<string,mixed>> */
        public array $nodes = [];

        /** @param array<string,mixed> $node */
        public function add_node(array $node): void
        {
            $this->nodes[] = $node;
        }
    }
}

function fakeAdminBar(): WP_Admin_Bar
{
    return new WP_Admin_Bar();
}

function toolbarFor(int $unread): NotificationToolbar
{
    $service = new RecordingNotificationService();
    $service->unreadCount = $unread;

    return new NotificationToolbar($service, new CorexAdminAssets());
}

it('adds a notification node with the unread count for a manager off a CoreX screen', function () {
    Functions\when('is_user_logged_in')->justReturn(true);
    Functions\when('current_user_can')->justReturn(true);
    $bar = fakeAdminBar();

    toolbarFor(3)->addNode($bar);

    expect($bar->nodes)->toHaveCount(1);
    $node = $bar->nodes[0];
    expect($node['id'])->toBe('corex-notifications')
        ->and($node['href'])->toContain('page=corex-notifications')
        ->and($node['title'])->toContain('3');
});

it('adds no node for a user who cannot manage notifications', function () {
    Functions\when('is_user_logged_in')->justReturn(true);
    Functions\when('current_user_can')->justReturn(false);
    $bar = fakeAdminBar();

    toolbarFor(3)->addNode($bar);

    expect($bar->nodes)->toBeEmpty();
});

it('caps the visible count at 99+ while keeping the true count in the label', function () {
    Functions\when('is_user_logged_in')->justReturn(true);
    Functions\when('current_user_can')->justReturn(true);
    $bar = fakeAdminBar();

    toolbarFor(150)->addNode($bar);

    expect($bar->nodes[0]['title'])->toContain('99+')
        ->and($bar->nodes[0]['meta']['title'])->toContain('150');
});
