<?php

/**
 * Unit tests for the admin-shell notification bell (spec 072 US2: FR-016).
 *
 * A keyboard-operable button showing the actor's real unread count — capped visually at 99+, with the
 * true count in the accessible label.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Notifications\NotificationBell;
use Corex\Tests\Support\RecordingNotificationService;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('_n')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_html')->returnArg();
});

it('renders a keyboard-operable bell with the unread count and a true-count label', function () {
    $service = new RecordingNotificationService();
    $service->unreadCount = 5;

    $html = (new NotificationBell($service))->render();

    expect($html)->toContain('<button')
        ->and($html)->toContain('type="button"')
        ->and($html)->toContain('corex-notification-bell')
        ->and($html)->toContain('data-corex-notification-bell')
        ->and($html)->toContain('aria-haspopup="dialog"')
        ->and($html)->toContain('>5</span>')                    // badge shows the count
        ->and($html)->toContain('Notifications, 5 unread');     // accessible label
});

it('caps the badge at 99+ but keeps the true count in the label', function () {
    $service = new RecordingNotificationService();
    $service->unreadCount = 150;

    $html = (new NotificationBell($service))->render();

    expect($html)->toContain('>99+</span>')
        ->and($html)->toContain('Notifications, 150 unread');
});

it('shows no badge and a plain label when there is nothing unread', function () {
    $html = (new NotificationBell(new RecordingNotificationService()))->render();

    expect($html)->not->toContain('corex-notification-bell__badge')
        ->and($html)->toContain('aria-label="Notifications"');
});

it('appends itself to the header-actions filter without dropping existing actions', function () {
    $service = new RecordingNotificationService();
    $service->unreadCount = 1;

    $appended = (new NotificationBell($service))->append('<span>existing</span>');

    expect($appended)->toStartWith('<span>existing</span>')
        ->and($appended)->toContain('corex-notification-bell');
});
