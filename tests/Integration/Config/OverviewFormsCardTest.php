<?php

/**
 * The Overview's forms card counts open, assigned and unread submissions (spec 118, US-OVR-05).
 *
 * Ten submissions: four new, two in progress, one closed, one spam, one marked as a test and one in
 * the trash; two of the new ones are the viewer's; three of the open ones were never read. The card
 * reads Open 6, Assigned to you 2, Unread 3, and the inbox asked with each number's filter answers
 * the same. Every submission query in this file is narrowed to the ten it inserted, so the
 * developer's own submissions change nothing.
 *
 * @package Corex\Tests\Integration\Config
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Overview\Cards\FormsCard;
use Corex\Config\Submissions\SubmissionInboxQuery;
use Corex\Config\Submissions\SubmissionQueryService;
use Corex\Tests\Support\AdminScreen;

beforeEach(function () {
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->actor = (int) ($administrators[0] ?? 0);
    wp_set_current_user($this->actor);

    $this->ids = [];
    $this->onlyThese = function (WP_Query $query): void {
        if ($query->get('post_type') === 'corex_submission') {
            $query->set('post__in', $this->ids === [] ? [0] : $this->ids);
        }
    };
    add_action('pre_get_posts', $this->onlyThese);

    // status, read, the viewer's
    $seed = [
        ['new', false, true],
        ['new', true, true],
        ['new', false, false],
        ['new', true, false],
        ['in_progress', false, false],
        ['in_progress', true, false],
        ['closed', true, false],
        ['spam', false, false],
    ];
    foreach ($seed as [$status, $read, $mine]) {
        $this->ids[] = formsCardSubmission($status, $read, $mine ? $this->actor : 0);
    }
    $this->ids[] = formsCardSubmission('new', false, 0, ['corex_is_test' => 1]);
    $trashed     = formsCardSubmission('new', false, 0);
    wp_trash_post($trashed);
    $this->ids[] = $trashed;
});

afterEach(function () {
    remove_action('pre_get_posts', $this->onlyThese);
    foreach ($this->ids as $id) {
        wp_delete_post($id, true);
    }
    wp_set_current_user(0);
    AdminScreen::set('front');
});

/** @param array<string,mixed> $meta */
function formsCardSubmission(string $status, bool $read, int $owner, array $meta = []): int
{
    return (int) wp_insert_post([
        'post_type'   => 'corex_submission',
        'post_status' => 'private',
        'post_title'  => 'Forms card submission',
        'meta_input'  => array_filter(array_merge([
            'corex_form_slug'         => 'contact',
            'corex_submission_status' => $status,
            'corex_is_test'           => 0,
            'corex_read_at'           => $read ? '2026-10-22T10:00:00+00:00' : null,
            'corex_owner_type'        => $owner > 0 ? 'user' : null,
            'corex_owner_key'         => $owner > 0 ? (string) $owner : null,
        ], $meta), static fn ($value): bool => $value !== null),
    ]);
}

/** @return array<string,string> label => number, as the card draws them */
function formsCardNumbers(string $html): array
{
    preg_match_all('/number-value">([^<]+)<\/span><span class="corex-overview__number-label">([^<]+)</', $html, $found, PREG_SET_ORDER);

    return array_column($found, 1, 2);
}

it('counts open, assigned and unread submissions, leaving out tests, the trash and spam', function () {
    $numbers = formsCardNumbers(Boot::app()->container()->make(FormsCard::class)->render([]));

    expect($numbers)->toBe(['Open' => '6', 'Assigned to you' => '2', 'Unread' => '3']);
});

it('links each number to the inbox with the filter it counted, and the inbox counts the same', function () {
    $html    = Boot::app()->container()->make(FormsCard::class)->render([]);
    $queries = Boot::app()->container()->make(SubmissionQueryService::class);

    preg_match_all('/class="corex-overview__number" href="([^"]+)"/', $html, $links);
    $totals = array_map(function (string $href) use ($queries): int {
        parse_str((string) parse_url(html_entity_decode($href), PHP_URL_QUERY), $query);
        unset($query['page']);

        return (int) $queries->query($this->actor, SubmissionInboxQuery::from($query))['total'];
    }, $links[1]);

    expect($totals)->toBe([6, 2, 3]);
});
