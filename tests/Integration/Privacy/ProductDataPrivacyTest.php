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
use Corex\Config\Data\WpSubmissionsReader;
use Corex\Config\Retention\SubmissionRetention;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionExportRequest;
use Corex\Config\Submissions\SubmissionExportService;
use Corex\Config\Submissions\SubmissionInboxQuery;
use Corex\Config\Submissions\WpSubmissionExportStore;

function insertPrivacySubmission(array $overrides = [], string $postDate = ''): int
{
    $meta = array_merge([
        'corex_flow_id' => 90,
        'corex_form_slug' => 'contact',
        'corex_submission_status' => 'new',
        'corex_owner_type' => 'team',
        'corex_owner_key' => 'sales',
        'corex_is_test' => 0,
        'corex_submitter_name' => 'Dana Privacy',
        'corex_submitter_email' => 'dana@example.com',
        'corex_values_json' => ['name' => 'Dana Privacy', 'email' => 'dana@example.com'],
        'corex_submission_updated_at' => '2026-07-10T12:00:00+00:00',
    ], $overrides);

    $args = [
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'Privacy submission',
        'meta_input' => $meta,
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

    $this->submissionBaseline = get_posts(['post_type' => 'corex_submission', 'post_status' => 'any', 'posts_per_page' => 500, 'fields' => 'ids']);
    $this->exportBaseline = get_posts(['post_type' => WpSubmissionExportStore::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 500, 'fields' => 'ids']);
});

afterEach(function () {
    foreach ([['corex_submission', 'submissionBaseline'], [WpSubmissionExportStore::POST_TYPE, 'exportBaseline']] as [$type, $key]) {
        $ids = get_posts(['post_type' => $type, 'post_status' => 'any', 'posts_per_page' => 500, 'fields' => 'ids']);
        foreach (array_diff($ids, $this->{$key}) as $id) {
            wp_delete_post((int) $id, true);
        }
    }
});

it('exposes submitter personal data only inside the actor access scope', function () {
    $submissionId = insertPrivacySubmission();
    $reader = new WpSubmissionsReader();
    $query = SubmissionInboxQuery::from(['flow' => 90, 'search' => 'dana@example.com']);

    $inScope = $reader->queryInbox($query, new SubmissionAccessScope(7, false, ['sales']));
    $outOfScope = $reader->queryInbox($query, new SubmissionAccessScope(8, false, ['support']));

    expect($inScope['total'])->toBe(1)
        ->and($inScope['items'][0])->toMatchArray([
            'id' => $submissionId,
            'submitter_name' => 'Dana Privacy',
            'submitter_email' => 'dana@example.com',
        ])
        ->and($outOfScope['total'])->toBe(0)
        ->and($reader->findInbox($submissionId, new SubmissionAccessScope(8, false, ['support'])))->toBeNull();
});

it('refuses a personal-data export without both capability and acknowledgement', function () {
    insertPrivacySubmission();
    $service = Boot::app()->container()->make(SubmissionExportService::class);
    $request = fn (bool $ack): SubmissionExportRequest => SubmissionExportRequest::from([
        'scope' => 'filtered', 'columns' => ['identity', 'submitted_fields'],
        'query' => ['flow' => 90], 'personal_data_acknowledged' => $ack,
    ]);

    // Capability withheld.
    expect(fn () => $service->request(new SubmissionAccessScope(7, false, ['sales'], canExportPersonalData: false), $request(true)))
        ->toThrow(DomainException::class, 'This actor cannot export submission personal data.');

    // Capable but unacknowledged.
    expect(fn () => $service->request(new SubmissionAccessScope(7, false, ['sales'], canExportPersonalData: true), $request(false)))
        ->toThrow(DomainException::class, 'The actor must acknowledge the personal data export warning.');
});

it('queues an acknowledged personal-data export and isolates its download to the owner', function () {
    insertPrivacySubmission();
    $service = Boot::app()->container()->make(SubmissionExportService::class);
    $owner = new SubmissionAccessScope(7, false, ['sales'], canExportPersonalData: true);

    $run = $service->request($owner, SubmissionExportRequest::from([
        'scope' => 'filtered', 'columns' => ['identity', 'submitted_fields'],
        'query' => ['flow' => 90], 'personal_data_acknowledged' => true,
    ]));

    expect($run->actorId)->toBe(7)
        ->and($run->recordCount)->toBe(1);

    // A different scoped actor cannot download another actor's personal-data export.
    $other = new SubmissionAccessScope(8, false, ['support'], canExportPersonalData: true);
    expect(fn () => $service->download($other, $run->id))
        ->toThrow(DomainException::class, 'The submission export is unavailable.');
});

it('anonymizes personal data on the retention window and never prunes when disabled', function () {
    $retention = Boot::app()->container()->make(SubmissionRetention::class);
    $previousDays = $retention->days();

    $agedId = insertPrivacySubmission([], gmdate('Y-m-d H:i:s', strtotime('-120 days')));
    $recentId = insertPrivacySubmission();

    // Disabled retention prunes nothing.
    $retention->setDays(0);
    expect($retention->preview()['willPrune'])->toBeFalse()
        ->and($retention->prune('anonymize'))->toBe(0);

    // A 30-day window measures only the aged record and anonymizes it.
    $retention->setDays(30);
    $preview = $retention->preview();
    $removed = $retention->prune('anonymize');

    expect($preview['count'])->toBeGreaterThanOrEqual(1)
        ->and($removed)->toBeGreaterThanOrEqual(1)
        ->and(get_post_meta($agedId, 'corex_retention_state', true))->toBe('anonymized')
        ->and(get_post_meta($agedId, 'corex_submitter_email', true))->toBe('')
        ->and(get_post_meta($agedId, 'corex_submitter_name', true))->toBe('')
        ->and(get_post_meta($agedId, 'corex_values_json', true))->toBe(['anonymized' => true])
        // The recent record keeps its personal data.
        ->and(get_post_meta($recentId, 'corex_submitter_email', true))->toBe('dana@example.com');

    $retention->setDays($previousDays);
});
