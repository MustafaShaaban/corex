<?php

/**
 * An open notification is reworded without another occurrence (spec 118, DECISIONS #432).
 *
 * A readiness blocker is raised once; each later evaluation only says it again in the words of the
 * request evaluating it, title, body and action label, and writes nothing when the words are the
 * same. The rows are this file's own and are removed.
 *
 * @package Corex\Tests\Integration\Notifications
 */

declare(strict_types=1);

use Corex\Config\Notifications\NotificationTable;
use Corex\Config\Notifications\NotificationUserStateTable;
use Corex\Config\Notifications\WpNotificationRepository;
use Corex\Database\Schema\Migrator;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationAction;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationRecipient;
use Corex\Notifications\NotificationSeverity;
use Corex\Tests\Support\NotificationRows;

const REWORD_TEST_DEDUP = 'reword.test:blocker';

beforeEach(function () {
    $migrator = new Migrator();
    $migrator->create((new NotificationTable())->schema());
    $migrator->create((new NotificationUserStateTable())->schema());
    $this->repo = new WpNotificationRepository($migrator);
    NotificationRows::forget(REWORD_TEST_DEDUP);
});

afterEach(function () {
    NotificationRows::forget(REWORD_TEST_DEDUP);
});

function rewordNotice(string $title, string $label): Notification
{
    return Notification::create(
        type: 'readiness.blocker',
        category: NotificationCategory::READINESS,
        severity: NotificationSeverity::WARNING,
        sourceModule: 'operations',
        titleKey: 't',
        messageKey: 'm',
        rendered: ['title' => $title, 'body' => 'B'],
        dedupKey: REWORD_TEST_DEDUP,
        recipient: NotificationRecipient::forUser(990_000_119),
        occurredAt: new DateTimeImmutable('now'),
        action: NotificationAction::to('k', 'https://example.test/fix', 'corex_manage_operations', $label),
    );
}

it('rewrites the title and action label of an open notice, and adds no occurrence', function () {
    $stored = $this->repo->upsertByDedupKey(rewordNotice('Check not met', 'Fix this check'));

    $changed = $this->repo->rewordByDedupKey(rewordNotice('[Ćĥéçķ ńöţ ḿéţ]', '[Ƒîẋ ţĥîš çĥéçķ]'));
    $again   = $this->repo->rewordByDedupKey(rewordNotice('[Ćĥéçķ ńöţ ḿéţ]', '[Ƒîẋ ţĥîš çĥéçķ]'));
    $now     = $this->repo->find((int) $stored->id);

    expect($changed)->toBe(1)
        ->and($again)->toBe(0)
        ->and($now->rendered['title'])->toBe('[Ćĥéçķ ńöţ ḿéţ]')
        ->and($now->action?->toArray()['label'] ?? '')->toBe('[Ƒîẋ ţĥîš çĥéçķ]')
        ->and($now->occurrences)->toBe(1);
});
