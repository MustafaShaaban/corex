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

use Corex\Config\Notifications\NotificationTable;
use Corex\Config\Notifications\NotificationUserStateTable;
use Corex\Config\Notifications\OptionalDashboardWidgets;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Config\Settings\SettingsStore;
use Corex\Database\Schema\Migrator;

/**
 * Opt a widget in exactly the way the settings screen does — writing the option the Config engine
 * reads — so this exercises the real path rather than a test-only door.
 */
function optInToWidget(string $widgetId, bool $on): void
{
    $key = OptionalDashboardWidgets::catalogue()[$widgetId]['configKey'];
    (new SettingsStore())->save($key, $on ? '1' : '');
}

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
    foreach (OptionalDashboardWidgets::catalogue() as $definition) {
        delete_option((new SettingsStore())->optionName($definition['configKey']));
    }

    // Remove this test's seeded notifications. Without it every run leaves an unread row behind,
    // and those accumulate into the next run's widget output — which is exactly how this test first
    // failed against itself.
    global $wpdb;
    $migrator = new Migrator();
    $table    = $migrator->fullName(NotificationTable::NAME);
    $ids      = $wpdb->get_col("SELECT id FROM {$table} WHERE dedup_key LIKE 'widget.test:%'");

    if ($ids !== []) {
        $list = implode(',', array_map('intval', $ids));
        $wpdb->query("DELETE FROM " . $migrator->fullName(NotificationUserStateTable::NAME) . " WHERE notification_id IN ({$list})");
        $wpdb->query("DELETE FROM {$table} WHERE id IN ({$list})");
    }

    $this->modes->set($this->wasMode, get_current_user_id());
    // Leave a front-end screen behind: a lingering admin screen leaks into later tests.
    set_current_screen('front');
    wp_set_current_user(0);
});

it('registers nothing on a dashboard that opted into nothing', function () {
    expect(registeredDashboardIds($this->widgets))
        ->not->toContain(OptionalDashboardWidgets::ATTENTION)
        ->not->toContain(OptionalDashboardWidgets::DEVELOPMENT);
});

it('keeps an opted-in Development widget off a Production dashboard', function () {
    optInToWidget(OptionalDashboardWidgets::DEVELOPMENT, true);
    $this->modes->set(OperationsMode::PRODUCTION, get_current_user_id());

    expect(registeredDashboardIds($this->widgets))
        ->not->toContain(OptionalDashboardWidgets::DEVELOPMENT);
});

it('registers the opted-in Development widget once the site is in Development', function () {
    optInToWidget(OptionalDashboardWidgets::DEVELOPMENT, true);
    $this->modes->set(OperationsMode::DEVELOPMENT, get_current_user_id());

    expect(registeredDashboardIds($this->widgets))
        ->toContain(OptionalDashboardWidgets::DEVELOPMENT);
});

it('treats an unchecked setting as off rather than as merely absent', function () {
    // The settings form saves an unchecked box as '' — that must read as off, exactly like a key
    // that was never written, so re-saving the form can never silently enable a widget.
    optInToWidget(OptionalDashboardWidgets::DEVELOPMENT, false);
    $this->modes->set(OperationsMode::DEVELOPMENT, get_current_user_id());

    expect(registeredDashboardIds($this->widgets))
        ->not->toContain(OptionalDashboardWidgets::DEVELOPMENT);
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

it('lists a real unread notification and drops it once the actor reads it', function () {
    // The earlier version of this test asserted only that the output was non-empty, and passed while
    // the widget was iterating the {items,total,page,per_page} envelope and emitting empty <li>s.
    // Seeding a real notification is what makes the assertion mean anything.
    $service = Corex\Boot::app()->container()->make(Corex\Notifications\NotificationService::class);
    $actorId = get_current_user_id();
    // Unique per run: a fixed title would collide with rows this test left behind on earlier runs,
    // which stay unread and would keep appearing after this run's notification is marked read.
    $title   = 'Widget test ' . uniqid('', false);

    $stored = $service->publish(Corex\Notifications\Notification::create(
        type: 'submission.new',
        category: Corex\Notifications\NotificationCategory::SUBMISSIONS,
        severity: Corex\Notifications\NotificationSeverity::ACTION,
        sourceModule: 'forms',
        titleKey: 'notifications.submission.new.title',
        messageKey: 'notifications.submission.new.body',
        rendered: ['title' => $title, 'body' => 'Seeded by the widget test'],
        dedupKey: 'widget.test:' . $title,
        recipient: Corex\Notifications\NotificationRecipient::forUser($actorId),
        occurredAt: new DateTimeImmutable('now'),
    ));

    ob_start();
    $this->widgets->renderAttention();
    $unreadHtml = (string) ob_get_clean();

    // The empty-<li> check is the envelope bug's exact signature: iterating {items,total,page,
    // per_page} produced list items with no title in them.
    expect($unreadHtml)->toContain($title)
        ->and($unreadHtml)->not->toContain('<li></li>')
        ->and(substr_count($unreadHtml, '<li>'))->toBeGreaterThan(0);

    // Per-user read state, not resolution: the item is still unresolved, but it is no longer this
    // actor's problem, so "Attention" must stop showing it.
    $service->markReadForCurrentActor((int) $stored->id);

    ob_start();
    $this->widgets->renderAttention();
    $readHtml = (string) ob_get_clean();

    expect($readHtml)->not->toContain($title);
});
