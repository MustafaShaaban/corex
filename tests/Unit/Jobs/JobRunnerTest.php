<?php

/**
 * @package Corex\Tests\Unit\Jobs
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

use Brain\Monkey\Functions;
use Corex\Config\Jobs\JobRunner;
use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobDispatcher;
use Corex\Jobs\JobHandler;
use Corex\Jobs\JobHandlerRegistry;
use Corex\Jobs\JobRepository;
use Corex\Tests\Fixtures\Multisite\SiteScopeEnvironment;

final class PhaseSevenJobRepository implements JobRepository
{
    /** @var array<int,BoundedJob> */
    public array $jobs = [];

    public function create(BoundedJob $job): BoundedJob
    {
        $stored = $job->withId(count($this->jobs) + 1);
        $this->jobs[$stored->id] = $stored;

        return $stored;
    }

    public function find(int $id): ?BoundedJob
    {
        return $this->jobs[$id] ?? null;
    }

    public function findActive(string $kind, string $inputHash): ?BoundedJob
    {
        return null;
    }

    public function save(BoundedJob $job): void
    {
        $this->jobs[$job->id] = $job;
    }
}

final class PhaseSevenJobDispatcher implements JobDispatcher
{
    /** @var list<int> */
    public array $siteIds = [];

    public function __construct(private readonly Closure $siteId)
    {
    }

    public function available(): bool
    {
        return true;
    }

    public function dispatch(BoundedJob $job): void
    {
        $this->siteIds[] = ($this->siteId)();
    }

    public function cancel(int $jobId): void
    {
    }
}

final class PhaseSevenJobHandler implements JobHandler
{
    /** @var list<int> */
    public array $siteIds = [];

    public function __construct(private readonly Closure $siteId)
    {
    }

    public function kind(): string
    {
        return 'data.export';
    }

    public function handle(BoundedJob $job, int $batchSize): BoundedJob
    {
        current_user_can('manage_options');
        $this->siteIds[] = ($this->siteId)();

        return $job->advance('', 0, 0, 0, new DateTimeImmutable('+1 minute'), new DateTimeImmutable('now'));
    }
}

it('runs queued work and its capability check under the recorded site', function (
    int $currentSiteId,
    ?int $recordedSiteId,
    int $expectedSiteId,
) {
    $environment = new SiteScopeEnvironment($currentSiteId);
    $siteId = $currentSiteId;
    $siteStack = [];
    $currentUserId = 0;
    $capabilitySiteIds = [];

    Functions\when('switch_to_blog')->alias(
        static function (int $newSiteId) use (&$siteId, &$siteStack, $environment): bool {
            $siteStack[] = $siteId;
            $previousSiteId = $siteId;
            $siteId = $newSiteId;
            $environment->scope->onSwitchBlog($newSiteId, $previousSiteId, 'switch');

            return true;
        },
    );
    Functions\when('restore_current_blog')->alias(
        static function () use (&$siteId, &$siteStack, $environment): bool {
            $previousSiteId = $siteId;
            $siteId = (int) array_pop($siteStack);
            $environment->scope->onSwitchBlog($siteId, $previousSiteId, 'restore');

            return true;
        },
    );
    Functions\when('get_current_user_id')->alias(static fn (): int => $currentUserId);
    Functions\when('wp_set_current_user')->alias(
        static function (int $userId) use (&$currentUserId): void {
            $currentUserId = $userId;
        },
    );
    Functions\when('current_user_can')->alias(
        static function (string $capability) use (&$capabilitySiteIds, &$siteId): bool {
            $capabilitySiteIds[] = $siteId;

            return true;
        },
    );

    $repository = new PhaseSevenJobRepository();
    $repository->jobs[21] = BoundedJob::queued(
        'data.export',
        7,
        0,
        hash('sha256', 'site-scoped-job'),
        new DateTimeImmutable('now'),
    )->withId(21);
    $siteIdReader = static function () use (&$siteId): int {
        return $siteId;
    };
    $handler = new PhaseSevenJobHandler($siteIdReader);
    $handlers = new JobHandlerRegistry();
    $handlers->register($handler);
    $dispatcher = new PhaseSevenJobDispatcher($siteIdReader);
    $runner = new JobRunner($repository, $handlers, $dispatcher, $environment->scope);

    if ($recordedSiteId === null) {
        $runner->run(21);
    } else {
        $runner->run(21, $recordedSiteId);
    }

    expect($handler->siteIds)->toBe([$expectedSiteId])
        ->and($capabilitySiteIds)->toBe([$expectedSiteId])
        ->and($dispatcher->siteIds)->toBe([$expectedSiteId])
        ->and($siteId)->toBe($currentSiteId)
        ->and($currentUserId)->toBe(0);
})->with([
    'recorded subsite' => [1, 2, 2],
    'legacy payload without site id' => [3, null, 3],
]);

it('accepts both the job and site ids from the scheduler payload', function () {
    Functions\expect('add_action')
        ->once()
        ->with(
            \Corex\Config\Jobs\ActionSchedulerJobDispatcher::HOOK,
            \Mockery::type('array'),
            10,
            2,
        );

    $siteIdReader = static fn (): int => 1;
    (new JobRunner(
        new PhaseSevenJobRepository(),
        new JobHandlerRegistry(),
        new PhaseSevenJobDispatcher($siteIdReader),
        (new SiteScopeEnvironment())->scope,
    ))->register();
});
