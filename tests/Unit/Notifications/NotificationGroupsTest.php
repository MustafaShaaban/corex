<?php

/**
 * Which notifications are shown as one item (spec 118, US-SHL-09). No WordPress.
 *
 * Contract: finished exports and failed jobs group by the kind of job; anything that asks for a
 * decision is never grouped; the title counts with plural forms.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Notifications\NotificationGroups;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationRecipient;
use Corex\Notifications\NotificationSeverity;

beforeEach(function () {
    Functions\when('_n')->alias(static fn (string $single, string $plural, int $number): string => $number === 1 ? $single : $plural);
    Functions\when('number_format_i18n')->alias(static fn ($number): string => (string) $number);
});

function groupedNotification(string $type, string $jobKind = ''): Notification
{
    return Notification::create(
        type: $type,
        category: NotificationCategory::SYSTEM,
        severity: NotificationSeverity::INFORMATION,
        sourceModule: 'jobs',
        titleKey: 't',
        messageKey: 'm',
        rendered: ['title' => 'T', 'body' => 'B'],
        dedupKey: $type . ':' . uniqid(),
        recipient: NotificationRecipient::forUser(1),
        occurredAt: new DateTimeImmutable('now'),
        metadata: $jobKind === '' ? [] : ['job_kind' => $jobKind],
    );
}

it('groups finished exports and failed jobs by the kind of job', function () {
    $groups = new NotificationGroups();

    expect($groups->keyOf(groupedNotification('export.ready', 'submissions.export')))
        ->toBe($groups->keyOf(groupedNotification('export.ready', 'submissions.export')))
        ->and($groups->keyOf(groupedNotification('export.ready', 'submissions.export')))
        ->not->toBe($groups->keyOf(groupedNotification('export.ready', 'data.export')))
        ->and($groups->keyOf(groupedNotification('job.failed', 'data.import')))->toBe('job.failed|data.import');
});

it('never groups a notification that asks for a decision', function (string $type) {
    expect((new NotificationGroups())->keyOf(groupedNotification($type)))->toBeNull();
})->with(['access.request', 'submission.assigned', 'submission.email_failed', 'email.delivery_failed', 'readiness.blocker']);

it('counts a group in its title', function () {
    $groups = new NotificationGroups();

    expect($groups->title('export.ready', 3))->toBe('3 exports finished')
        ->and($groups->title('job.failed', 2))->toBe('2 jobs did not finish');
});
