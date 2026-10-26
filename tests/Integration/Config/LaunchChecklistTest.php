<?php

/**
 * The one launch checklist on real WordPress (spec 118: US-OVR-01, US-OVR-03, US-SEC-09).
 *
 * The facts are set through the options they live in, for this test only (a `pre_option_` filter,
 * removed after), so the site is left as it was. Every screen reads the same evaluation: the
 * Overview's "N of M" and Operations' "N of M ready" are the same numbers, a blocked check is one
 * notification however often pages are opened, and the site checks never block going live.
 *
 * @package Corex\Tests\Integration\Config
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Notifications\Producers\RaisedReadinessChecks;
use Corex\Config\Operations\ProductionReadinessSnapshotFactory;
use Corex\Config\Operations\ReadinessSnapshot;
use Corex\Config\Overview\Cards\ReadinessCard;
use Corex\Config\Readiness\HardeningFactSource;
use Corex\Config\Readiness\LaunchChecklist;
use Corex\Config\Readiness\ReadinessWords;
use Corex\Config\Readiness\SiteReadinessFacts;
use Corex\Config\Security\HardeningChecks;
use Corex\Config\Security\OperationsSecurityScreen;
use Corex\Config\Settings\SettingsStore;
use Corex\Multisite\ActivationScope;
use Corex\Multisite\PluginActivationInspector;
use Corex\Tests\Support\AdminScreen;
use Corex\Tests\Support\NotificationRows;

const LAUNCH_TEST_DEDUP = 'readiness.blocker:';

beforeEach(function () {
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
    $this->container = Boot::app()->container();
    $this->filters   = [];
    $this->answer    = function (string $option, mixed $value): void {
        $filter = static fn (): mixed => $value;
        add_filter('pre_option_' . $option, $filter);
        $this->filters[$option] = $filter;
    };
    $this->pages = [];

    // Evaluating readiness raises the install's own blockers. They are the install's: handed back
    // as they were, with the producer's memory of them.
    $this->readinessRows = NotificationRows::snapshotPrefixed(LAUNCH_TEST_DEDUP);
    $this->raised        = get_option(RaisedReadinessChecks::OPTION, null);
});

afterEach(function () {
    foreach ($this->filters as $option => $filter) {
        remove_filter('pre_option_' . $option, $filter);
    }
    foreach ($this->pages as $id) {
        wp_delete_post($id, true);
    }
    NotificationRows::restorePrefixed(LAUNCH_TEST_DEDUP, $this->readinessRows);
    $this->raised === null
        ? delete_option(RaisedReadinessChecks::OPTION)
        : update_option(RaisedReadinessChecks::OPTION, $this->raised, false);
    wp_set_current_user(0);
    AdminScreen::set('front');
});

/** @return array<string,array<string,mixed>> */
function launchChecks(ProductionReadinessSnapshotFactory $factory): array
{
    return array_column($factory->fromCurrentSite(new DateTimeImmutable('now'))->checks(), null, 'key');
}

function launchPage(object $test, string $status): int
{
    $id = (int) wp_insert_post(['post_type' => 'page', 'post_status' => $status, 'post_title' => 'Launch test ' . $status]);
    $test->pages[] = $id;

    return $id;
}

it('reads both groups, and passes the front page on a theme that draws its own', function () {
    ($this->answer)('show_on_front', 'posts');

    $checks = launchChecks($this->container->make(ProductionReadinessSnapshotFactory::class));
    $groups = array_unique(array_column($checks, 'group'));

    sort($groups);
    expect($groups)->toBe([ReadinessSnapshot::GROUP_SECURITY, ReadinessSnapshot::GROUP_SITE])
        ->and($checks['front_page']['state'])->toBe(ReadinessSnapshot::PASS)
        ->and($checks['ssl']['resolution_url'])->toBe(admin_url('options-general.php'));
});

it('says a draft privacy page is pending and links to it', function () {
    $draft = launchPage($this, 'draft');
    ($this->answer)('wp_page_for_privacy_policy', $draft);

    $privacy = launchChecks($this->container->make(ProductionReadinessSnapshotFactory::class))['privacy_page'];

    expect($privacy['state'])->toBe(ReadinessSnapshot::WARNING)
        ->and($privacy['resolution_url'])->toBe(admin_url('post.php?post=' . $draft . '&action=edit'));
});

it('asks for a terms page until one is chosen, and passes a published one', function () {
    $store = $this->container->make(SettingsStore::class);
    ($this->answer)($store->optionName(SiteReadinessFacts::TERMS_PAGE_KEY), '');
    $unchosen = launchChecks($this->container->make(ProductionReadinessSnapshotFactory::class))['terms_page'];

    remove_filter('pre_option_' . $store->optionName(SiteReadinessFacts::TERMS_PAGE_KEY), $this->filters[$store->optionName(SiteReadinessFacts::TERMS_PAGE_KEY)]);
    ($this->answer)($store->optionName(SiteReadinessFacts::TERMS_PAGE_KEY), (string) launchPage($this, 'publish'));
    $chosen = launchChecks($this->container->make(ProductionReadinessSnapshotFactory::class))['terms_page'];

    expect($unchosen['summary'])->toBe('Choose a terms page')
        ->and($unchosen['state'])->toBe(ReadinessSnapshot::WARNING)
        ->and($chosen['state'])->toBe(ReadinessSnapshot::PASS);
});

it('leaves the test email out of the count without Corex Mail', function () {
    $noMail = new class implements PluginActivationInspector {
        public function scopeOf(string $pluginFile): ActivationScope
        {
            return ActivationScope::None;
        }

        public function isActive(string $pluginFile): bool
        {
            return false;
        }

        public function activePluginFiles(): array
        {
            return [];
        }

        public function scopes(): array
        {
            return [];
        }
    };
    $factory = new ProductionReadinessSnapshotFactory(
        new HardeningChecks(),
        null,
        new HardeningFactSource(),
        new SiteReadinessFacts(
            $this->container->make(SettingsStore::class),
            $this->container->make(\Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore::class),
            $noMail,
            $this->container,
        ),
    );

    $snapshot = $factory->fromCurrentSite(new DateTimeImmutable('now'));
    $email    = array_column($snapshot->checks(), null, 'key')['test_email'];
    $list     = (new LaunchChecklist())->build($snapshot, ReadinessWords::TODO);

    expect($email['state'])->toBe(ReadinessSnapshot::NOT_APPLICABLE)
        ->and($email['summary'])->toBe('Needs Corex Mail')
        ->and($list['total'])->toBe(count($snapshot->checks()) - 1);
});

it('says "Could not check" when the hardening facts cannot be read, and does not block', function () {
    $factory = new ProductionReadinessSnapshotFactory(
        new HardeningChecks(),
        null,
        new class extends HardeningFactSource {
            public function gather(): array
            {
                throw new RuntimeException('The facts could not be read.');
            }
        },
        $this->container->make(SiteReadinessFacts::class),
    );

    $snapshot = $factory->fromCurrentSite(new DateTimeImmutable('now'));
    $security = array_filter($snapshot->checks(), static fn (array $c): bool => $c['group'] === ReadinessSnapshot::GROUP_SECURITY);

    expect(array_unique(array_column($security, 'state')))->toBe([ReadinessSnapshot::UNAVAILABLE])
        ->and($snapshot->hasBlockingChecks())->toBeFalse();
});

it('shows the same count on the Overview and on Operations', function () {
    $launch = (new LaunchChecklist())->build(
        $this->container->make(ProductionReadinessSnapshotFactory::class)->fromCurrentSite(new DateTimeImmutable('now')),
        ReadinessWords::GATE,
    );

    $overview = $this->container->make(ReadinessCard::class)->render([]);
    $screen   = $this->container->make(OperationsSecurityScreen::class);
    $summary  = (new ReflectionMethod($screen, 'overviewCard'))->invoke(
        $screen,
        $this->container->make(ProductionReadinessSnapshotFactory::class)->fromCurrentSite(new DateTimeImmutable('now')),
    );

    expect($overview)->toContain('>' . $launch['done'] . ' of ' . $launch['total'] . '<')
        ->and($summary)->toContain($launch['done'] . ' of ' . $launch['total'] . ' ready');
});

it('keeps one notification for a blocked check however often readiness is evaluated', function () {
    $factory = $this->container->make(ProductionReadinessSnapshotFactory::class);
    $blocked = $factory->fromCurrentSite(new DateTimeImmutable('now'))->blockingKeys();
    if ($blocked === []) {
        $this->markTestSkipped('This install has no blocked hardening check to watch.');
    }

    $occurrences = static function (string $key): array {
        global $wpdb;

        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            'SELECT occurrences FROM ' . $wpdb->prefix . 'corex_notifications WHERE dedup_key = %s',
            LAUNCH_TEST_DEDUP . $key,
        )));
    };
    $before = $occurrences($blocked[0]);

    for ($i = 0; $i < 10; $i++) {
        $factory->fromCurrentSite(new DateTimeImmutable('now'));
    }

    expect($before)->toHaveCount(1)
        ->and($occurrences($blocked[0]))->toBe($before);
});
