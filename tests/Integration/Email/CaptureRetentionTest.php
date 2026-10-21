<?php

/**
 * Spec 113, US4 and US5 (FR-007, FR-020): captured and held emails are deleted after their 30
 * days, every one of them, and the "Capture only CoreX mail" switch is saved through its route.
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Email\Capture\CaptureRetention;
use Corex\Email\Studio\EmailStudioController;
use Corex\Email\Studio\WpEmailStudioStore;
use Corex\Tests\Support\OptionRow;

beforeEach(function () {
    $this->container = Corex\Boot::app()->container();
    (new WpEmailStudioStore())->registerPostType();
    $this->made = [];
});

afterEach(function () {
    foreach ($this->made as $id) {
        wp_delete_post($id, true);
    }
});

/** Store a captured email as it was kept `$daysAgo` days ago. */
function keptDaysAgo(object $test, int $daysAgo, string $kind = 'captured_email'): int
{
    $when = gmdate('Y-m-d H:i:s', time() - $daysAgo * DAY_IN_SECONDS);
    $id = wp_insert_post([
        'post_type'     => WpEmailStudioStore::POST_TYPE,
        'post_status'   => 'private',
        'post_title'    => 'Kept ' . $daysAgo,
        'post_date'     => get_date_from_gmt($when),
        'post_date_gmt' => $when,
    ]);
    update_post_meta($id, '_corex_email_asset_type', $kind);
    $test->made[] = $id;

    return $id;
}

it('deletes every captured email past its 30 days and keeps the newer ones and the rest', function () {
    $old = [];
    for ($i = 0; $i < 550; $i++) {
        $old[] = keptDaysAgo($this, 31 + ($i % 20));
    }
    $recent = [];
    for ($i = 0; $i < 50; $i++) {
        $recent[] = keptDaysAgo($this, $i % 29);
    }
    $template = keptDaysAgo($this, 400, 'email_template');

    $removed = $this->container->make(CaptureRetention::class)->run();
    $left = array_filter($this->made, static fn (int $id): bool => get_post($id) instanceof WP_Post);

    expect($removed)->toBeGreaterThanOrEqual(550)
        ->and(array_values(array_intersect($old, $left)))->toBe([])
        ->and(array_values(array_intersect($recent, $left)))->toBe($recent)
        ->and(get_post($template))->toBeInstanceOf(WP_Post::class);
});

it('schedules the clean-up once a day', function () {
    $retention = $this->container->make(CaptureRetention::class);
    $retention->ensureScheduled();

    expect(wp_next_scheduled(CaptureRetention::HOOK))->not->toBeFalse()
        ->and(has_action(CaptureRetention::HOOK, [$retention, 'run']))->not->toBeFalse();
});

it('saves the capture scope through its route and refuses anything else', function () {
    $row = OptionRow::capture(EmailStudioController::CAPTURE_SCOPE_OPTION);
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
    $post = static function (string $scope): WP_REST_Response {
        $request = new WP_REST_Request('POST', '/corex/v1/email-studio/capture-scope');
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        $request->set_body_params(['scope' => $scope]);

        return rest_do_request($request);
    };

    $saved = $post('corex');
    $refused = $post('everything');
    $stored = get_option(EmailStudioController::CAPTURE_SCOPE_OPTION);

    OptionRow::restore(EmailStudioController::CAPTURE_SCOPE_OPTION, $row);
    wp_set_current_user(0);

    expect($saved->get_status())->toBe(200)
        ->and($saved->get_data()['data']['scope'])->toBe('corex')
        ->and($saved->get_data()['data']['outcome'])->toBeArray()
        ->and($refused->get_status())->toBe(400)
        ->and($stored)->toBe('corex');
});
