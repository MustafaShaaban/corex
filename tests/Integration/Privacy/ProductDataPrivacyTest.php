<?php

/**
 * Personal-data visibility, export gating, and retention on real ./wp (spec 068 T225).
 *
 * Personal data must only be visible inside the actor's access scope, must not leave the product
 * without an explicit capability and acknowledgement, and must be removable on the retention
 * window. These tests assert those invariants against real WordPress records.
 *
 * @package Corex\Tests\Integration\Privacy
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Activity\ActivityTable;
use Corex\Config\Data\WpSubmissionsReader;
use Corex\Config\Jobs\JobTable;
use Corex\Config\Retention\SubmissionRetention;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionExportHistory;
use Corex\Config\Submissions\SubmissionExportRequest;
use Corex\Config\Submissions\SubmissionExportService;
use Corex\Config\Submissions\SubmissionInboxQuery;
use Corex\Config\Submissions\WpSubmissionExportStore;
use Corex\Database\Schema\Migrator;
use Corex\Jobs\JobDispatcher;

/** Where `SubmissionRetention` keeps its window. Private there; named here to put it back. */
const PRIVACY_RETENTION_OPTION = 'corex_retention_submissions_days';

function insertPrivacySubmission(string $submitterEmail, string $postDate = ''): int
{
    $args = [
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'Privacy submission',
        'meta_input' => [
            'corex_flow_id' => 90,
            'corex_form_slug' => 'contact',
            'corex_submission_status' => 'new',
            'corex_owner_type' => 'team',
            'corex_owner_key' => 'sales',
            'corex_is_test' => 0,
            'corex_submitter_name' => 'Dana Privacy',
            'corex_submitter_email' => $submitterEmail,
            'corex_values_json' => ['name' => 'Dana Privacy', 'email' => $submitterEmail],
            'corex_submission_updated_at' => '2026-07-10T12:00:00+00:00',
        ],
    ];
    if ($postDate !== '') {
        $args['post_date'] = $postDate;
        $args['post_date_gmt'] = $postDate;
    }

    return (int) wp_insert_post($args);
}

beforeEach(function () {
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }
    Boot::app()->container()->make(WpSubmissionExportStore::class)->registerPostType();

    // The suite runs against a developer's real install, which has submissions of its own. Flow 90
    // is only a number — nothing stops the install having one, and this file's own leftovers used
    // to sit in it — so each test mints a submitter nothing else can share and searches for it.
    $this->submitterEmail = 'dana.' . strtolower(wp_generate_password(12, false)) . '@example.com';

    // Record what this test inserts as it inserts it. This replaced comparing the newest 500 ids
    // before and after, which could not see a row backdated past the 500th: the aged submission
    // of the retention test stayed behind on every run once the install held that many.
    $this->createdPosts = [];
    $this->rememberCreatedPost = function (int $postId, WP_Post $post, bool $update): void {
        if (! $update && in_array($post->post_type, ['corex_submission', WpSubmissionExportStore::POST_TYPE], true)) {
            $this->createdPosts[$postId] = $post->post_type;
        }
    };
    add_action('wp_insert_post', $this->rememberCreatedPost, 10, 3);

    $this->savedRetentionDays = get_option(PRIVACY_RETENTION_OPTION, null);
});

afterEach(function () {
    global $wpdb;

    remove_action('wp_insert_post', $this->rememberCreatedPost, 10);
    if (isset($this->scopeRetentionToThisTest)) {
        remove_action('pre_get_posts', $this->scopeRetentionToThisTest);
    }

    $container = Boot::app()->container();
    $migrator = new Migrator();

    foreach ($this->createdPosts as $postId => $postType) {
        if ($postType === WpSubmissionExportStore::POST_TYPE) {
            // Requesting an export writes more than the export. It queues a job — a row, and an
            // event on the install's own scheduler that would later run against an export deleted
            // here and record a failure — and an audit event under an actor id every run shares.
            // Those audit events are what pushed ProductActivityCoverageTest's fixtures off page one.
            $jobId = (int) $container->make(WpSubmissionExportStore::class)->find($postId)?->jobId;
            if ($jobId > 0) {
                $container->make(JobDispatcher::class)->cancel($jobId);
                $wpdb->delete($migrator->fullName(JobTable::NAME), ['id' => $jobId]);
            }
            $wpdb->delete($migrator->fullName(ActivityTable::NAME), [
                'target_type' => 'submission_export',
                'target_id' => (string) $postId,
            ]);
        }

        wp_delete_post($postId, true);
    }

    // Exactly as it was, including "did not exist". The retention test used to restore this in its
    // last line, which a failed expectation never reaches — that left the install on a 30-day window.
    if ($this->savedRetentionDays === null) {
        delete_option(PRIVACY_RETENTION_OPTION);
    } else {
        update_option(PRIVACY_RETENTION_OPTION, $this->savedRetentionDays, false);
    }
});

it('exposes submitter personal data only inside the actor access scope', function () {
    $submissionId = insertPrivacySubmission($this->submitterEmail);
    $reader = new WpSubmissionsReader();
    $query = SubmissionInboxQuery::from(['flow' => 90, 'search' => $this->submitterEmail]);

    $inScope = $reader->queryInbox($query, new SubmissionAccessScope(7, false, ['sales']));
    $outOfScope = $reader->queryInbox($query, new SubmissionAccessScope(8, false, ['support']));

    expect($inScope['total'])->toBe(1)
        ->and($inScope['items'][0])->toMatchArray([
            'id' => $submissionId,
            'submitter_name' => 'Dana Privacy',
            'submitter_email' => $this->submitterEmail,
        ])
        ->and($outOfScope['total'])->toBe(0)
        ->and($reader->findInbox($submissionId, new SubmissionAccessScope(8, false, ['support'])))->toBeNull();
});

it('refuses a personal-data export without both capability and acknowledgement', function () {
    insertPrivacySubmission($this->submitterEmail);
    $service = Boot::app()->container()->make(SubmissionExportService::class);
    $request = fn (bool $ack): SubmissionExportRequest => SubmissionExportRequest::from([
        'scope' => 'filtered', 'columns' => ['identity', 'submitted_fields'],
        'query' => ['flow' => 90, 'search' => $this->submitterEmail], 'personal_data_acknowledged' => $ack,
    ]);

    // Capability withheld.
    expect(fn () => $service->request(new SubmissionAccessScope(7, false, ['sales'], canExportPersonalData: false), $request(true)))
        ->toThrow(DomainException::class, 'This actor cannot export submission personal data.');

    // Capable but unacknowledged.
    expect(fn () => $service->request(new SubmissionAccessScope(7, false, ['sales'], canExportPersonalData: true), $request(false)))
        ->toThrow(DomainException::class, 'The actor must acknowledge the personal data export warning.');
});

it('queues an acknowledged personal-data export and isolates its download to the owner', function () {
    insertPrivacySubmission($this->submitterEmail);
    $service = Boot::app()->container()->make(SubmissionExportService::class);
    $owner = new SubmissionAccessScope(7, false, ['sales'], canExportPersonalData: true);

    $run = $service->request($owner, SubmissionExportRequest::from([
        'scope' => 'filtered', 'columns' => ['identity', 'submitted_fields'],
        'query' => ['flow' => 90, 'search' => $this->submitterEmail], 'personal_data_acknowledged' => true,
    ]));

    expect($run->actorId)->toBe(7)
        ->and($run->recordCount)->toBe(1);

    // A different scoped actor cannot download another actor's personal-data export.
    $other = new SubmissionAccessScope(8, false, ['support'], canExportPersonalData: true);
    $history = Boot::app()->container()->make(SubmissionExportHistory::class);
    expect(fn () => $history->download($other, $run->id))
        ->toThrow(DomainException::class, 'The submission export is unavailable.');
});

it('anonymizes personal data on the retention window and never prunes when disabled', function () {
    $retention = Boot::app()->container()->make(SubmissionRetention::class);

    $agedId = insertPrivacySubmission($this->submitterEmail, gmdate('Y-m-d H:i:s', strtotime('-120 days')));
    $recentId = insertPrivacySubmission($this->submitterEmail);

    // Retention acts on every submission older than its window, and this is a developer's real
    // install: unscoped, the prune below anonymized whatever they had that was over thirty days
    // old, on every run. So the retention query is narrowed to this test's two records. The window
    // still does the choosing between them — that is what is under test.
    $this->scopeRetentionToThisTest = static function (WP_Query $query) use ($agedId, $recentId): void {
        if ($query->get('post_type') === 'corex_submission') {
            $query->set('post__in', [$agedId, $recentId]);
        }
    };
    add_action('pre_get_posts', $this->scopeRetentionToThisTest);

    // Disabled retention prunes nothing.
    $retention->setDays(0);
    expect($retention->preview()['willPrune'])->toBeFalse()
        ->and($retention->prune('anonymize'))->toBe(0);

    // A 30-day window measures only the aged record. Asserted on its own, before anything is
    // pruned: if the narrowing above ever stops applying, the test fails here and the prune never
    // reaches the install.
    $retention->setDays(30);
    expect($retention->preview()['count'])->toBe(1);

    expect($retention->prune('anonymize'))->toBe(1)
        ->and(get_post_meta($agedId, 'corex_retention_state', true))->toBe('anonymized')
        ->and(get_post_meta($agedId, 'corex_submitter_email', true))->toBe('')
        ->and(get_post_meta($agedId, 'corex_submitter_name', true))->toBe('')
        ->and(get_post_meta($agedId, 'corex_values_json', true))->toBe(['anonymized' => true])
        // The recent record keeps its personal data.
        ->and(get_post_meta($recentId, 'corex_submitter_email', true))->toBe($this->submitterEmail);
});
