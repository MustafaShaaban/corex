<?php

/**
 * Which test email counts for the launch checklist (spec 118, US-OVR-03).
 *
 * Contract: a test from Email Studio that the transport accepted (sent or opened) in the window;
 * a captured or failed test, a test older than the window, or an email that was not a test does
 * not count.
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Email\Delivery\EmailAttempt;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Tests\Support\InMemoryEmailStudioStore;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('wp_salt')->justReturn('unit-test-salt');
});

function recordedAttempt(EmailAttemptRepository $attempts, string $state, string $source, string $when): void
{
    $attempts->record(new EmailAttempt(
        id: 0,
        attemptId: sprintf('%08x-0000-4000-8000-%012x', random_int(0, 0xffffffff), random_int(0, 0xffffffffffff)),
        requestId: '64d15a02-8cf2-4e47-9ea3-fbbbc22ce22c',
        parentAttemptId: null,
        recipient: 'owner@example.com',
        subject: 'A test',
        templateSlug: null,
        state: $state,
        provider: 'wp-mail',
        providerEvent: null,
        retryable: false,
        occurredAt: new DateTimeImmutable($when),
        source: $source,
    ));
}

/** A store that answers `all()` as WordPress's does: the first 500 ever stored, oldest first. */
function cappedAttemptStore(): Corex\Email\Studio\EmailStudioStore
{
    return new class implements Corex\Email\Studio\EmailStudioStore {
        private InMemoryEmailStudioStore $inner;

        public function __construct()
        {
            $this->inner = new InMemoryEmailStudioStore();
        }

        public function create(string $type, string $slug, string $name, int $parentId, array $payload): int
        {
            return $this->inner->create($type, $slug, $name, $parentId, $payload);
        }

        public function update(int $id, string $name, array $payload): bool
        {
            return $this->inner->update($id, $name, $payload);
        }

        public function find(int $id): ?array
        {
            return $this->inner->find($id);
        }

        public function findBySlug(string $type, string $slug): ?array
        {
            return $this->inner->findBySlug($type, $slug);
        }

        public function all(string $type, ?int $parentId = null): array
        {
            return array_slice($this->inner->all($type, $parentId), 0, 500);
        }

        public function latest(string $type, int $limit): array
        {
            return $this->inner->latest($type, $limit);
        }
    };
}

it('finds a recent test on a site with more than 500 attempts', function () {
    $attempts = new EmailAttemptRepository(cappedAttemptStore());
    for ($i = 0; $i < 550; $i++) {
        recordedAttempt($attempts, EmailAttempt::STATE_SENT, 'route', '2026-10-01T00:00:00+00:00');
    }
    recordedAttempt($attempts, EmailAttempt::STATE_SENT, 'test', '2026-10-20T00:00:00+00:00');
    recordedAttempt($attempts, EmailAttempt::STATE_SENT, 'route', '2026-10-21T00:00:00+00:00');

    expect($attempts->latestAcceptedTest(new DateTimeImmutable('2026-07-25T00:00:00+00:00')))->not->toBeNull();
});

it('counts only a test the transport accepted inside the window', function (string $state, string $source, string $when, bool $counts) {
    $attempts = new EmailAttemptRepository(new InMemoryEmailStudioStore());
    recordedAttempt($attempts, $state, $source, $when);

    $found = $attempts->latestAcceptedTest(new DateTimeImmutable('2026-07-25T00:00:00+00:00'));

    expect($found !== null)->toBe($counts);
})->with([
    'a test, sent' => [EmailAttempt::STATE_SENT, 'test', '2026-10-01T00:00:00+00:00', true],
    'a test, opened' => [EmailAttempt::STATE_OPENED, 'test', '2026-10-01T00:00:00+00:00', true],
    'a test, captured' => [EmailAttempt::STATE_CAPTURED, 'test', '2026-10-01T00:00:00+00:00', false],
    'a test, failed' => [EmailAttempt::STATE_FAILED, 'test', '2026-10-01T00:00:00+00:00', false],
    'a test before the window' => [EmailAttempt::STATE_SENT, 'test', '2026-07-01T00:00:00+00:00', false],
    'not a test' => [EmailAttempt::STATE_SENT, 'route', '2026-10-01T00:00:00+00:00', false],
]);
