<?php

/**
 * A reply leaves the site as a designed email with a plain-text version (spec 106, slice 2).
 *
 * Through the reply route and the real container, on a site answered as a development one: the
 * email is read from where such a site keeps it, and reaches nobody.
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Submissions\SubmissionsController;
use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Design\EmailPalette;
use Corex\Tests\Support\MailDelivery;

beforeEach(function () {
    $this->mailSettings = MailDelivery::answerAs(MailDelivery::CAPTURE);
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
    $this->recipient = 'salma.' . strtolower(wp_generate_password(10, false)) . '@example.com';
    $this->designSubmission = wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'A submission answered in the site design',
        'meta_input' => [
            'corex_form_slug' => 'contact',
            'corex_submission_status' => 'new',
            'corex_owner_type' => 'none',
            'corex_submitter_email' => $this->recipient,
        ],
    ]);
});

afterEach(function () {
    MailDelivery::stop($this->mailSettings);
    // Deleting the submission takes the email kept for it with it (spec 105).
    wp_delete_post($this->designSubmission, true);
});

it('sends the reply in the standard design, named for the site, with its words as plain text', function () {
    $request = new WP_REST_Request('POST', '/corex/v1/submissions/' . $this->designSubmission . '/reply');
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_url_params(['id' => (string) $this->designSubmission]);
    $request->set_body_params([
        'subject' => 'Re: your message',
        'body' => "Hello Salma,\n\nSee <a href=\"https://example.com/offer\">the offer</a>.",
    ]);

    $answer = Boot::app()->container()->make(SubmissionsController::class)->reply($request)->get_data();
    $kept = array_values(array_filter(
        Boot::app()->container()->make(CapturedEmailRepository::class)->latest(20),
        fn ($capture): bool => $capture->to === [$this->recipient],
    ));
    $email = $kept[0] ?? null;
    $site = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);

    expect($answer['data']['result']['state'] ?? $answer)->toBe('captured')
        ->and($kept)->toHaveCount(1)
        ->and($email->subject)->toBe('Re: your message')
        ->and($email->body)->toStartWith('<!DOCTYPE html>')
        ->and($email->body)->toContain('dir="' . (is_rtl() ? 'rtl' : 'ltr') . '"')
        ->and($email->body)->toContain(htmlspecialchars($site, ENT_QUOTES))
        ->and($email->body)->toContain('border-top:4px solid ' . EmailPalette::light()->accent)
        ->and(substr_count($email->body, 'Hello Salma,'))->toBe(1)
        ->and($email->body)->toContain('href="https://example.com/offer">the offer</a>')
        ->and($email->plainText)->toStartWith("Hello Salma,\n\nSee the offer (https://example.com/offer).\n\n-- \n")
        ->and($email->plainText)->toContain($site)
        ->and($email->plainText)->not->toContain('<');
});
