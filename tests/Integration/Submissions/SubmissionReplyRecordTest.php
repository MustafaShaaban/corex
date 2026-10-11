<?php

/**
 * A reply is kept with its submission, and the pane is told what would happen to one sent now
 * (spec 106, slice 1: FR-021, FR-023, FR-028).
 *
 * Through the routes and on real WordPress: what is under test is what the pane is sent, and
 * what the trash, a permanent delete and an anonymization then leave.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Data\WpSubmissionsReader;
use Corex\Config\Submissions\SubmissionReplyStore;
use Corex\Config\Submissions\SubmissionsController;
use Corex\Config\Submissions\WpSubmissionReplyStore;
use Corex\Tests\Support\MailDelivery;

function replyRecordRequest(string $method, string $route, array $payload = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_body_params($payload);
    if (isset($payload['id'])) {
        $request->set_url_params(['id' => (string) $payload['id']]);
    }

    return $request;
}

beforeEach(function () {
    $this->mailSettings = MailDelivery::answerAs(MailDelivery::CAPTURE);
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->administrator = (int) ($administrators[0] ?? 0);
    wp_set_current_user($this->administrator);
    $this->replyController = Boot::app()->container()->make(SubmissionsController::class);
    $this->replySubmission = wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'A submission that is answered',
        'meta_input' => [
            'corex_form_slug' => 'contact',
            'corex_submission_status' => 'new',
            'corex_owner_type' => 'none',
            'corex_submitter_name' => 'Salma',
            'corex_submitter_email' => 'salma.' . strtolower(wp_generate_password(10, false)) . '@example.com',
        ],
    ]);
});

afterEach(function () {
    MailDelivery::stop($this->mailSettings);
    wp_delete_post($this->replySubmission, true);
});

it('lists a sent reply on the submission with its text, who sent it, when, and what happened', function () {
    $sent = $this->replyController->reply(replyRecordRequest('POST', '/corex/v1/submissions/' . $this->replySubmission . '/reply', [
        'id' => $this->replySubmission,
        'subject' => 'Re: your message',
        'body' => "Hello Salma,\n\nWe will call you <strong>tomorrow</strong>.<script>alert(1)</script>",
    ]))->get_data();
    $request = new WP_REST_Request('GET', '/corex/v1/submissions/' . $this->replySubmission);
    $request->set_url_params(['id' => (string) $this->replySubmission]);
    $submission = $this->replyController->show($request)->get_data()['data']['submission'];
    $reply = $submission['replies'][0];

    expect($sent['message'] ?? '')->toBe('')
        ->and($sent['ok'])->toBeTrue()
        ->and($submission['replies'])->toHaveCount(1)
        ->and($reply['subject'])->toBe('Re: your message')
        ->and($reply['body_html'])->toContain('<p>Hello Salma,</p>')->toContain('<strong>tomorrow</strong>')
        ->and($reply['body_html'])->not->toContain('script')->not->toContain('alert')
        ->and($reply['actor_id'])->toBe($this->administrator)
        ->and($reply['actor_name'])->toBe(get_userdata($this->administrator)->display_name)
        ->and($sent['data']['result']['state'])->toBe('captured')
        ->and($sent['data']['recorded'])->toBeTrue()
        ->and($reply['state'])->toBe('captured')
        ->and($reply['attempts'][0]['attempt_id'])->toBe($sent['data']['result']['attempt_id'])
        ->and(strtotime($reply['created_at']))->toBeGreaterThan(time() - 60)
        ->and($submission['reply_delivery']['mode'])->toBe('capture')
        ->and($submission['reply_delivery']['reason'])->toContain('development site');
});

it('refuses a reply on a site with live delivery off, with the reason, and keeps nothing', function () {
    // Asked later, so answered first: these stand in front of the development answer.
    array_push($this->mailSettings, ...MailDelivery::answerAs(MailDelivery::REFUSE));
    $request = new WP_REST_Request('GET', '/corex/v1/submissions/' . $this->replySubmission);
    $request->set_url_params(['id' => (string) $this->replySubmission]);
    $before = $this->replyController->show($request)->get_data()['data']['submission']['reply_delivery'];

    $refused = $this->replyController->reply(replyRecordRequest('POST', '/corex/v1/submissions/' . $this->replySubmission . '/reply', [
        'id' => $this->replySubmission,
        'subject' => 'Re: your message',
        'body' => 'Hello Salma',
    ]));
    $after = $this->replyController->show($request)->get_data()['data']['submission'];

    expect($before['mode'])->toBe('refuse')
        ->and($before['code'])->toBe('live_off')
        ->and($refused->get_status())->toBe(422)
        // The route says what the pane was told before anything was typed.
        ->and($refused->get_data()['message'])->toBe($before['reason'])
        ->and($after['replies'])->toBe([])
        ->and(array_column($after['timeline'], 'stage'))->not->toContain('email');
});

it('refuses a reply with no words or no subject, and keeps nothing', function (string $subject, string $body, string $reason) {
    $refused = $this->replyController->reply(replyRecordRequest('POST', '/corex/v1/submissions/' . $this->replySubmission . '/reply', [
        'id' => $this->replySubmission,
        'subject' => $subject,
        'body' => $body,
    ]));

    expect($refused->get_status())->toBe(422)
        ->and($refused->get_data()['message'])->toContain($reason)
        ->and(Boot::app()->container()->make(SubmissionReplyStore::class)->forSubmission($this->replySubmission))->toBe([]);
})->with([
    'only spaces' => ['Re: your message', " \n\n ", 'Write a message'],
    'an empty list' => ['Re: your message', '<ul><li> </li></ul>', 'Write a message'],
    'only a script' => ['Re: your message', '<script>alert(1)</script>', 'Write a message'],
    'no subject' => ['  ', '<p>Hello</p>', 'subject'],
]);

it('keeps each reply as a row of its own, in the order they were sent, as it was written', function () {
    $store = Boot::app()->container()->make(SubmissionReplyStore::class);
    $reply = static fn (string $id, string $at): \Corex\Config\Submissions\SubmissionReplyRecord =>
        new \Corex\Config\Submissions\SubmissionReplyRecord($id, 'Re: your message', '<p>C:\\offers\\one</p>', 1, $at, []);

    // Each is added without reading what is there first, so two people answering at once
    // cannot write over each other; the store gives them back by when they were sent.
    $store->add($this->replySubmission, $reply('second', '2026-10-11T10:00:05+00:00'));
    $store->add($this->replySubmission, $reply('first', '2026-10-11T10:00:00+00:00'));
    $kept = $store->forSubmission($this->replySubmission);

    expect(array_map(static fn ($record): string => $record->id, $kept))->toBe(['first', 'second'])
        ->and($kept[0]->bodyHtml)->toBe('<p>C:\\offers\\one</p>');
});

it('removes the replies when the submission is anonymized', function () {
    $store = Boot::app()->container()->make(SubmissionReplyStore::class);
    $store->add($this->replySubmission, new \Corex\Config\Submissions\SubmissionReplyRecord(
        'one',
        'Re: your message',
        '<p>Dear Salma</p>',
        1,
        '2026-10-11T10:00:00+00:00',
        [],
    ));

    Boot::app()->container()->make(WpSubmissionsReader::class)->anonymizeForRetention($this->replySubmission);

    expect($store->forSubmission($this->replySubmission))->toBe([])
        ->and(get_post_meta($this->replySubmission, WpSubmissionReplyStore::META_KEY))->toBe([]);
});

it('gives the settings address only to somebody who may change settings', function () {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    $screen = Boot::app()->container()->make(\Corex\Config\Submissions\SubmissionsInboxScreen::class);
    $asAdministrator = $screen->configuration()['mailSettingsUrl'];
    $answerer = wp_insert_user([
        'user_login' => 'reply-answerer-' . strtolower(wp_generate_password(8, false)),
        'user_pass' => wp_generate_password(24),
        'role' => 'subscriber',
    ]);
    get_userdata($answerer)->add_cap(\Corex\Access\CorexAbility::MANAGE_SUBMISSIONS);
    wp_set_current_user($answerer);
    $asAnswerer = $screen->configuration()['mailSettingsUrl'];
    wp_set_current_user($this->administrator);
    wp_delete_user($answerer);

    expect($asAdministrator)->toContain('page=corex-settings-config')->toContain('corex_tab=mail')
        ->and($asAnswerer)->toBe('');
});
