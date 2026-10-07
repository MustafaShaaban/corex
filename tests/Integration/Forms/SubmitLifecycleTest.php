<?php

/**
 * Integration test: the secured contact-form submit lifecycle on real ./wp
 * (spec US3: FR-009, FR-010, FR-011, SC-004). Nonce, honeypot, and validation
 * gate the side effects; only a fully valid submission stores + dispatches.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Forms\Submission\SubmitController;
use Corex\Tests\Support\WatchedTransients;

function submitController(): SubmitController
{
    return Boot::app()->container()->make(SubmitController::class);
}

/**
 * @param array<string,mixed> $body
 */
function submitRequest(array $body, string $nonce): WP_REST_Request
{
    $request = new WP_REST_Request('POST', '/corex/v1/forms/contact');
    $request->set_url_params(['slug' => 'contact']);
    $request->set_body_params($body);
    $request->set_header('X-WP-Nonce', $nonce);

    return $request;
}

function submissionCount(): int
{
    // found_posts, not post_count: post_count only counts the rows this page returned, so a
    // per-page cap silently becomes the answer. With the cap at 100 and 100+ submissions on the
    // box, "before" and "after" both read 100 and `before + 1` could never match — the test
    // defeated itself as soon as the table outgrew one page, and every run added a row.
    $query = new WP_Query([
        'post_type'      => 'corex_submission',
        'post_status'    => 'any',
        'fields'         => 'ids',
        'posts_per_page' => 1,
    ]);

    return (int) $query->found_posts;
}

$valid = ['name' => 'Mustafa', 'email' => 'm@example.com', 'message' => 'Hello from the test'];

/**
 * Remember the submission a valid submit stores, and the email it logs, so `afterEach` can delete
 * exactly those.
 *
 * The listeners write both and hand back neither id, so they are caught as they are inserted.
 * Without this every run left one `contact` submission and one logged email on the install.
 */
beforeEach(function () {
    $this->createdPosts = [];
    $this->rememberCreatedPost = function (int $postId, WP_Post $post, bool $update): void {
        if (! $update && in_array($post->post_type, ['corex_submission', 'corex_email_log'], true)) {
            $this->createdPosts[] = $postId;
        }
    };
    add_action('wp_insert_post', $this->rememberCreatedPost, 10, 3);

    // Every submit that gets past the nonce is counted by the rate limiter, in a transient keyed
    // by form and client. A second run inside the window finds the first run's counter.
    $this->transients = WatchedTransients::watch('corex_throttle_');
});

afterEach(function () {
    remove_action('wp_insert_post', $this->rememberCreatedPost, 10);

    foreach ($this->createdPosts as $postId) {
        wp_delete_post($postId, true);
    }

    $this->transients->restore();
});

it('accepts a valid nonced submission: 200, stored, and both listeners run', function () use ($valid) {
    $mailed = [];
    add_filter('pre_wp_mail', function ($short, $atts) use (&$mailed) {
        $mailed[] = $atts;

        return true; // short-circuit actual delivery
    }, 10, 2);

    $before   = submissionCount();
    $response = submitController()->submit(submitRequest($valid, wp_create_nonce('wp_rest')));

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['ok'])->toBeTrue()
        ->and(submissionCount())->toBe($before + 1)
        ->and($mailed)->toHaveCount(1); // SendEmailListener ran (StoreSubmissionListener proven by the count)

    remove_all_filters('pre_wp_mail');
});

it('rejects an invalid nonce with 403 and no side effect', function () use ($valid) {
    $before   = submissionCount();
    $response = submitController()->submit(submitRequest($valid, 'not-a-valid-nonce'));

    expect($response->get_status())->toBe(403)
        ->and(submissionCount())->toBe($before);
});

it('rejects a filled honeypot with no side effect', function () use ($valid) {
    $before   = submissionCount();
    $response = submitController()->submit(submitRequest($valid + ['corex_hp' => 'bot-was-here'], wp_create_nonce('wp_rest')));

    expect($response->get_status())->toBe(422)
        ->and(submissionCount())->toBe($before);
});

it('rejects an empty required field with 422 field errors and no side effect', function () {
    $before   = submissionCount();
    $response = submitController()->submit(
        submitRequest(['name' => 'Mustafa', 'email' => 'm@example.com', 'message' => ''], wp_create_nonce('wp_rest')),
    );

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['errors']['message'])->toBe('required')
        ->and(submissionCount())->toBe($before);
});

/**
 * An email answer was cleaned before it was judged. `sanitize_email()` removes what it does not
 * accept, so `sal,ma@example.com` became `salma@example.com`, passed the email rule, and the
 * submission was stored under — and any reply sent to — an address nobody typed. An answer with
 * no address in it was emptied, so a required field said "required" where it meant "not an
 * address", and an optional one was dropped without a word. Reported on 2026-10-07 from a client
 * site.
 */
it('refuses an email address it would have to alter, and stores nothing', function (string $typed) use ($valid) {
    // If this ever regresses the submission is valid, and a valid submission sends mail.
    add_filter('pre_wp_mail', '__return_true');

    $before   = submissionCount();
    $response = submitController()->submit(
        submitRequest(['email' => $typed] + $valid, wp_create_nonce('wp_rest')),
    );

    remove_filter('pre_wp_mail', '__return_true');

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['errors']['email'] ?? null)->toBe('email')
        ->and(submissionCount())->toBe($before);
})->with([
    'a comma typed for a dot' => ['sal,ma@example.com'],
    'a letter the cleaner drops' => ['josé@example.com'],
    'a doubled at sign' => ['salma@@example.com'],
    'no address at all' => ['not-an-email'],
]);
