<?php

/**
 * Unit test: what happened to a site's release, written down twice (spec 107, US5; FR-051).
 *
 * In the installer's own log, a file beside the releases, and in the activity log. The first
 * has to hold when the second cannot take an entry: the activity log is a table of the release
 * being installed, and its own change may be the one that failed.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityRepository;
use Corex\Activity\ActivityService;
use Corex\Config\Releases\ReleaseEvent;
use Corex\Config\Releases\ReleaseRecord;
use Corex\Config\Releases\ReleaseStore;
use Corex\Tests\Support\RecordingActivityRepository;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('wp_mkdir_p')->alias(static fn (string $path): bool => is_dir($path) || mkdir($path, 0777, true));
    Functions\when('wp_json_encode')->alias('json_encode');

    $this->content = sys_get_temp_dir() . '/corex-content-' . bin2hex(random_bytes(4));
    mkdir($this->content);
    $this->store = new ReleaseStore($this->content);
    $this->at    = new DateTimeImmutable('2026-10-11T12:30:00+00:00');

    $this->recordInto = fn (ActivityRepository $activity): ReleaseRecord => new ReleaseRecord(
        $this->store,
        new ActivityService($activity),
        static fn (int $id): string => 'Mona',
    );
});

afterEach(function () {
    $remove = static function (string $path) use (&$remove): void {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            is_dir("$path/$entry") ? $remove("$path/$entry") : unlink("$path/$entry");
        }
        rmdir($path);
    };
    $remove($this->content);
});

it('writes who, when, from which release to which, and what else was given', function () {
    $activity = new RecordingActivityRepository();

    ($this->recordInto)($activity)->write(
        new ReleaseEvent(ReleaseEvent::INSTALLED, '0.45.0', '0.44.0', ['package' => 'corex-release-acme-0.45.0.zip', 'changes' => 2]),
        7,
        $this->at,
    );

    expect($this->store->log())->toBe([[
        'event'   => 'installed',
        'version' => '0.45.0',
        'from'    => '0.44.0',
        'by'      => 7,
        'at'      => '2026-10-11T12:30:00+00:00',
        'package' => 'corex-release-acme-0.45.0.zip',
        'changes' => 2,
    ]]);

    $entry = $activity->events[0];
    expect($entry->kind)->toBe('release.installed')
        ->and($entry->actorId)->toBe(7)
        ->and($entry->actorLabel)->toBe('Mona')
        ->and($entry->area)->toBe(ActivityEvent::AREA_OPERATIONS)
        ->and($entry->targetId)->toBe('0.45.0')
        ->and($entry->summary)->toBe(['key' => 'release.installed', 'args' => ['from' => '0.44.0', 'to' => '0.45.0']]);
});

it('says how each kind of event ended', function (string $kind, string $outcome) {
    $activity = new RecordingActivityRepository();

    ($this->recordInto)($activity)->write(new ReleaseEvent($kind, '0.45.0', null), 7, $this->at);

    expect($activity->events[0]->outcome)->toBe($outcome);
})->with([
    [ReleaseEvent::INSTALLED, ActivityEvent::OUTCOME_SUCCESS],
    [ReleaseEvent::FAILED, ActivityEvent::OUTCOME_FAILURE],
    [ReleaseEvent::CANCELLED, ActivityEvent::OUTCOME_WARNING],
]);

it('keeps the event in its own log when the activity log cannot take it', function () {
    $tableIsNotThere = new class () implements ActivityRepository {
        public function append(ActivityEvent $event): ActivityEvent
        {
            throw new RuntimeException('CoreX could not append the activity event.');
        }

        public function find(int $id): ?ActivityEvent
        {
            return null;
        }

        public function query(array $filters = [], int $page = 1, int $perPage = 20): array
        {
            return [];
        }

        public function pruneExpired(DateTimeImmutable $now, int $limit = 500): int
        {
            return 0;
        }
    };

    ($this->recordInto)($tableIsNotThere)->write(
        new ReleaseEvent(ReleaseEvent::FAILED, '0.45.0', '0.44.0', ['reason' => 'database_change_failed']),
        7,
        $this->at,
    );

    expect($this->store->log()[0])->toMatchArray(['event' => 'failed', 'reason' => 'database_change_failed']);
});
