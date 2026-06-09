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
    $query = new WP_Query([
        'post_type'      => 'corex_submission',
        'post_status'    => 'any',
        'fields'         => 'ids',
        'posts_per_page' => 100,
        'no_found_rows'  => true,
    ]);

    return $query->post_count;
}

$valid = ['name' => 'Mustafa', 'email' => 'm@example.com', 'message' => 'Hello from the test'];

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
