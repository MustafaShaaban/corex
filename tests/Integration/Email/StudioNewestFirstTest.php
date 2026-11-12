<?php

/**
 * Spec 113, US5 (FR-021 to FR-025): Email Studio's log reads the newest mail first, however
 * many emails a site has stored, and reading it costs the same few queries.
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Email\Delivery\EmailAttempt;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Email\Studio\WpEmailStudioStore;
use Corex\Support\Uuid;

beforeEach(function () {
    $this->store = new WpEmailStudioStore();
    $this->store->registerPostType();
    $this->attempts = new EmailAttemptRepository($this->store);
    $this->made = [];
});

afterEach(function () {
    foreach ($this->made as $id) {
        wp_delete_post($id, true);
    }
});

/** Store one attempt as the repository stores it, dated $minutesAgo before now. */
function storeAttempt(object $test, string $subject, int $minutesAgo): void
{
    $when = (new DateTimeImmutable('now'))->modify('-' . $minutesAgo . ' minutes')->format(DATE_ATOM);
    $id = Uuid::v4();
    $test->made[] = $test->store->create('email_attempt', $id, $subject, 0, [
        'attempt_id'  => $id,
        'uuid'        => $id,
        'request_id'  => Uuid::v4(),
        'recipient'   => 's***@example.com',
        'subject'     => $subject,
        'state'       => EmailAttempt::STATE_CAPTURED,
        'provider'    => 'corex-capture',
        'retryable'   => false,
        'occurred_at' => $when,
        'created_at'  => $when,
        'source'      => 'test',
        'environment' => 'development',
    ]);
}

it('lists the newest attempt first with more than 500 stored, and a new one at the top', function () {
    for ($i = 600; $i >= 1; $i--) {
        storeAttempt($this, 'Email ' . $i, $i);
    }

    $before = $this->attempts->latest(100);
    storeAttempt($this, 'Sent just now', 0);
    $after = $this->attempts->latest(100);

    expect($before)->toHaveCount(100)
        ->and($before[0]->subject)->toBe('Email 1')
        ->and($before[99]->subject)->toBe('Email 100')
        ->and($after[0]->subject)->toBe('Sent just now');
});

it('reads the log in the same number of queries for 10 rows or 100', function () {
    for ($i = 120; $i >= 1; $i--) {
        storeAttempt($this, 'Email ' . $i, $i);
    }
    $count = static function (int $limit) use (&$queries): int {
        wp_cache_flush();
        $queries = 0;
        $listen = static function (string $sql) use (&$queries): string {
            ++$queries;

            return $sql;
        };
        add_filter('query', $listen);
        (new EmailAttemptRepository(new WpEmailStudioStore()))->latest($limit);
        remove_filter('query', $listen);

        return $queries;
    };

    expect($count(100))->toBe($count(10))
        ->and($count(100))->toBeLessThanOrEqual(4);
});
