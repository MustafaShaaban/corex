<?php

/**
 * Each Overview card loads on its own (spec 118, US-OVR-06), on real WordPress.
 *
 * Each card's source is made to throw in turn. The card then says "Could not load", never a 0, an
 * empty state or another cause; every other card still renders; and an administrator reads a short
 * reference that is the same one written to the PHP error log.
 *
 * @package Corex\Tests\Integration\Config
 */

declare(strict_types=1);

use Corex\Activity\ActivityRepository;
use Corex\Activity\ActivityService;
use Corex\Boot;
use Corex\Config\Activity\ActivitySentences;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\DataSource;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Config\Overview\Cards\ActivityCard;
use Corex\Config\Overview\Cards\AttentionCard;
use Corex\Config\Overview\Cards\DataSourcesCard;
use Corex\Config\Overview\Cards\FormsCard;
use Corex\Config\Overview\OverviewCard;
use Corex\Config\Overview\OverviewCardFailure;
use Corex\Config\Overview\OverviewRenderer;
use Corex\Config\Submissions\SubmissionAccessPolicy;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionInboxQuery;
use Corex\Config\Submissions\SubmissionInboxReader;
use Corex\Config\Submissions\SubmissionQueryService;
use Corex\Container\ContainerInterface;
use Corex\Multisite\PluginActivationInspector;
use Corex\Notifications\NotificationService;
use Corex\Security\Admin\AdminGuard;
use Corex\Support\DateTime\AdminDateTime;
use Corex\Tests\Support\AdminScreen;

beforeEach(function () {
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
    $this->container = Boot::app()->container();

    // The failure's line goes to the PHP error log: read it from a file of this test's own.
    $this->log      = tempnam(sys_get_temp_dir(), 'corex-overview-log');
    $this->errorLog = ini_set('error_log', $this->log);
});

afterEach(function () {
    ini_set('error_log', (string) $this->errorLog);
    @unlink($this->log);
    wp_set_current_user(0);
    AdminScreen::set('front');
});

/** A source that fails the way a broken one does. */
function failingRead(): never
{
    throw new RuntimeException('The source could not be read.');
}

/** The Overview with one card in place of the one the container builds. */
function overviewWith(ContainerInterface $c, string $place, OverviewCard $card): string
{
    $real = new ReflectionProperty(OverviewRenderer::class, 'cards');
    $cards = $real->getValue($c->make(OverviewRenderer::class));
    $cards[$place] = $card;

    return (new OverviewRenderer(
        $c->make(OperationsMode::class),
        $c->make(OperationsModeStore::class),
        $c->make(OverviewCardFailure::class),
        $cards,
    ))->render([]);
}

/** The markup of one card, by its `data-card`. */
function overviewCardHtml(string $html, string $key): string
{
    preg_match('/<section[^>]*data-card="' . preg_quote($key, '/') . '".*?<\/section>/s', $html, $match);

    return $match[0] ?? '';
}

it('says "Could not load" on a card whose source throws, and renders every other card', function (string $place, Closure $card) {
    $html   = overviewWith($this->container, $place, $card($this->container));
    $failed = overviewCardHtml($html, $place);

    expect($failed)->toContain('Could not load')
        ->and($failed)->toContain('Try again')
        ->and($failed)->not->toMatch('/>\s*0\s*</')
        ->and($failed)->not->toContain('Forms are off')
        ->and($failed)->not->toContain('No recent framework events yet');

    foreach (['readiness', 'forms', 'data', 'attention', 'integrations', 'activity'] as $other) {
        if ($other !== $place) {
            expect($html)->toContain('data-card="' . $other . '"');
        }
    }
    expect($html)->toContain('data-card="tiles"');

    // The reference an administrator reads is the one in the PHP error log.
    preg_match('/Reference: ([0-9A-F]{8})/', $failed, $reference);
    expect($reference[1] ?? '')->not->toBe('')
        ->and((string) file_get_contents($this->log))->toContain('reference ' . $reference[1]);
})->with([
    'the submissions count, forms on' => ['forms', static fn (ContainerInterface $c): OverviewCard => new FormsCard(
        new SubmissionQueryService(
            new class implements SubmissionInboxReader {
                public function queryInbox(SubmissionInboxQuery $query, SubmissionAccessScope $scope): array
                {
                    failingRead();
                }

                public function findInbox(int $id, SubmissionAccessScope $scope): ?array
                {
                    failingRead();
                }
            },
            $c->make(SubmissionAccessPolicy::class),
        ),
        $c->make(AdminGuard::class),
        $c->make(PluginActivationInspector::class),
        $c,
    )],
    'the activity service' => ['activity', static fn (ContainerInterface $c): OverviewCard => new ActivityCard(
        new ActivityService(new class implements ActivityRepository {
            public function append(\Corex\Activity\ActivityEvent $event): \Corex\Activity\ActivityEvent
            {
                failingRead();
            }

            public function find(int $id): ?\Corex\Activity\ActivityEvent
            {
                failingRead();
            }

            public function query(array $filters = [], int $page = 1, int $perPage = 20): array
            {
                failingRead();
            }

            public function pruneExpired(DateTimeImmutable $now, int $limit = 500): int
            {
                failingRead();
            }
        }),
        $c->make(ActivitySentences::class),
        $c->make(AdminDateTime::class),
    )],
    'the notification service' => ['attention', static fn (ContainerInterface $c): OverviewCard => new AttentionCard(
        new class implements NotificationService {
            public function publish(\Corex\Notifications\Notification $notification): \Corex\Notifications\Notification
            {
                failingRead();
            }

            public function resolve(string $dedupKey, string $reason): int
            {
                failingRead();
            }

            public function forCurrentActor(\Corex\Notifications\NotificationQuery $query): array
            {
                failingRead();
            }

            public function unreadCountForCurrentActor(): int
            {
                failingRead();
            }

            public function findForCurrentActor(int $notificationId): ?array
            {
                failingRead();
            }

            public function markReadForCurrentActor(int $notificationId): bool
            {
                failingRead();
            }

            public function markUnreadForCurrentActor(int $notificationId): bool
            {
                failingRead();
            }

            public function dismissForCurrentActor(int $notificationId): bool
            {
                failingRead();
            }

            public function snoozeForCurrentActor(int $notificationId, DateTimeImmutable $until): bool
            {
                failingRead();
            }

            public function markAllReadForCurrentActor(): int
            {
                failingRead();
            }

            public function resolveById(int $notificationId, string $reason): bool
            {
                failingRead();
            }
        },
    )],
]);

it('lists a data source that fails with "Could not load" instead of leaving it out', function () {
    $registry = new DataRegistry();
    $registry->register(new class implements DataSource {
        public function key(): string
        {
            return 'broken';
        }

        public function label(): string
        {
            return 'Broken leads';
        }

        public function columns(): array
        {
            return [];
        }

        public function rows(int $page, int $perPage): array
        {
            failingRead();
        }

        public function total(): int
        {
            failingRead();
        }

        public function delete(int $id): bool
        {
            return false;
        }
    });

    $html = (new DataSourcesCard($registry))->render([]);

    expect($html)->toContain('Broken leads')
        ->and($html)->toContain('Could not load');
});

it('lists only the owner\'s data on the data card, never the logs', function () {
    $html = $this->container->make(DataSourcesCard::class)->render([]);

    expect($html)->toContain('Form submissions')
        ->and($html)->not->toContain('Activity events')
        ->and($html)->not->toContain('Bounded jobs')
        ->and($html)->not->toContain('Login attempts')
        ->and($html)->toContain('page=corex-data-models&#038;tab=records&#038;source=submissions');
});
