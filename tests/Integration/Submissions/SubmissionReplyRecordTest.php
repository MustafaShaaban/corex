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

// The subject of a confirmation a site sent may name the person: "Thank you, Salma".
it('removes what the site said it sent them when the submission is anonymized', function () {
    $conversation = Boot::app()->container()->make(\Corex\Config\Submissions\SubmissionConversation::class);
    $conversation->began($this->replySubmission, new \Corex\Mail\MailRequest(
        to: [(string) get_post_meta($this->replySubmission, 'corex_submitter_email', true)],
        subject: 'Thank you, Salma',
        body: '<p>We have your request.</p>',
    ));
    $kept = get_post_meta($this->replySubmission, \Corex\Config\Submissions\WpSubmissionConversationStore::META_KEY);

    Boot::app()->container()->make(WpSubmissionsReader::class)->anonymizeForRetention($this->replySubmission);

    expect($kept)->toHaveCount(1)
        ->and(get_post_meta($this->replySubmission, \Corex\Config\Submissions\WpSubmissionConversationStore::META_KEY))->toBe([]);
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

// Spec 106, FR-037 to FR-041: a reply can be sent under the emails the person already has.
// Through the routes, with the email add-on's own records: the first reply is the only email
// this person has, so it is what the second one continues.
it('sends a second reply in the conversation the first one began, and says which way each went', function () {
    $write = fn (string $subject, string $thread = '') => $this->replyController->reply(replyRecordRequest('POST', '/corex/v1/submissions/' . $this->replySubmission . '/reply', array_filter([
        'id' => $this->replySubmission,
        'subject' => $subject,
        'body' => '<p>Hello Salma</p>',
        'thread' => $thread,
    ])))->get_data();
    $shown = function (): array {
        $request = new WP_REST_Request('GET', '/corex/v1/submissions/' . $this->replySubmission);
        $request->set_url_params(['id' => (string) $this->replySubmission]);

        return $this->replyController->show($request)->get_data()['data']['submission'];
    };
    $before = $shown()['reply_conversation'];

    // Asked for where there is nothing to continue: it goes as a new email, and says so.
    $first = $write('About your enquiry', 'conversation');
    $between = $shown()['reply_conversation'];
    $second = $write('Re: About your enquiry', 'conversation');
    $third = $write('Something else');
    $after = $shown();
    $captures = Boot::app()->container()->make(\Corex\Email\Capture\CapturedEmailRepository::class);
    $headersOf = static function (string $attemptId) use ($captures): array {
        foreach ($captures->latest(50) as $capture) {
            if ($capture->attemptId === $attemptId) {
                return $capture->headers;
            }
        }

        return ['not kept'];
    };
    $sent = array_map(static fn (array $reply): string => $reply['data']['result']['attempt_id'], [$first, $second, $third]);
    $ids = array_map(static fn (string $attemptId): string => $headersOf($attemptId)['Message-ID'] ?? '', $sent);

    expect($before)->toBe(['available' => false, 'subject' => ''])
        ->and($between)->toBe(['available' => true, 'subject' => 'Re: About your enquiry'])
        // Each left under an id of its own, made of the site's host.
        ->and($ids[0])->toBe(\Corex\Mail\MessageId::of($sent[0], (string) wp_parse_url(home_url('/'), PHP_URL_HOST)))
        ->and($ids[0])->not->toBe('')
        ->and(array_unique($ids))->toHaveCount(3)
        ->and($headersOf($sent[0]))->not->toHaveKey('In-Reply-To')
        // The second answers the first, and names it as what came before.
        ->and($headersOf($sent[1])['In-Reply-To'])->toBe($ids[0])
        ->and($headersOf($sent[1])['References'])->toBe($ids[0])
        // The third was sent as a new email, though there was a conversation to continue.
        ->and($headersOf($sent[2]))->not->toHaveKey('In-Reply-To')->not->toHaveKey('References')
        ->and(array_column($after['replies'], 'thread'))->toBe(['new', 'conversation', 'new'])
        // And the next reply would answer the latest of the three.
        ->and($after['reply_conversation'])->toBe(['available' => true, 'subject' => 'Re: Something else']);
});

// FR-042: a site that sends its own confirmation says so, with the request it sends, and
// replies continue from that email.
it('continues from a confirmation the site sends itself, once the site has said it sends it', function () {
    $conversation = Boot::app()->container()->make(\Corex\Config\Submissions\SubmissionConversation::class);
    $request = new \Corex\Mail\MailRequest(
        to: [(string) get_post_meta($this->replySubmission, 'corex_submitter_email', true)],
        subject: 'We have your request',
        body: '<p>Thank you</p>',
    );
    $itsId = \Corex\Mail\MessageId::of($request->requestId, (string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    $shown = function (): array {
        $get = new WP_REST_Request('GET', '/corex/v1/submissions/' . $this->replySubmission);
        $get->set_url_params(['id' => (string) $this->replySubmission]);

        return $this->replyController->show($get)->get_data()['data']['submission']['reply_conversation'];
    };
    $unknown = $shown();

    $conversation->began($this->replySubmission, $request);
    $conversation->began($this->replySubmission, $request);
    $known = $shown();
    $sent = $this->replyController->reply(replyRecordRequest('POST', '/corex/v1/submissions/' . $this->replySubmission . '/reply', [
        'id' => $this->replySubmission,
        'subject' => $known['subject'],
        'body' => '<p>Hello Salma</p>',
        'thread' => 'conversation',
    ]))->get_data();
    $headers = [];
    foreach (Boot::app()->container()->make(\Corex\Email\Capture\CapturedEmailRepository::class)->latest(20) as $capture) {
        if ($capture->attemptId === $sent['data']['result']['attempt_id']) {
            $headers = $capture->headers;
        }
    }

    expect($unknown['available'])->toBeFalse()
        ->and($itsId)->not->toBe('')
        ->and($known)->toBe(['available' => true, 'subject' => 'Re: We have your request'])
        // Said twice, it is recorded once.
        ->and(get_post_meta($this->replySubmission, \Corex\Config\Submissions\WpSubmissionConversationStore::META_KEY))->toHaveCount(1)
        // And the reply answers the email the site said it sent.
        ->and($headers['In-Reply-To'] ?? '')->toBe($itsId)
        ->and($headers['References'] ?? '')->toBe($itsId);
});

// A site's listener sends the team's notice from where it sends the person's confirmation. A
// reply to the person must not answer an email they were never sent.
it('does not continue from an email the site sent somebody else', function () {
    $conversation = Boot::app()->container()->make(\Corex\Config\Submissions\SubmissionConversation::class);

    $conversation->began($this->replySubmission, new \Corex\Mail\MailRequest(to: ['sales@example.com'], subject: 'New enquiry from Salma', body: '<p>Salma wrote in</p>'));
    $get = new WP_REST_Request('GET', '/corex/v1/submissions/' . $this->replySubmission);
    $get->set_url_params(['id' => (string) $this->replySubmission]);

    expect(get_post_meta($this->replySubmission, \Corex\Config\Submissions\WpSubmissionConversationStore::META_KEY))->toBe([])
        ->and($this->replyController->show($get)->get_data()['data']['submission']['reply_conversation'])
        ->toBe(['available' => false, 'subject' => '']);
});

// FR-038: the confirmation a flow sent the person is where their conversation begins. Through
// the reader that reads a submission's bound emails and the add-on that kept them, since what
// is under test is that the two agree on what they name: an attempt, by its id.
it('continues from the confirmation a flow sent the person, and not from what it sent the team', function () {
    $studio = Boot::app()->container()->make(\Corex\Email\Studio\EmailStudioService::class);
    $kept = static fn (string $to, string $subject): \Corex\Mail\MailResult => $studio->send(
        new \Corex\Email\Message\EmailMessage([$to], [], [], null, $subject, '<p>Hello</p>'),
        new \Corex\Email\Studio\EmailDeliveryContext('development', true, true, wp_generate_uuid4()),
    );
    $person = (string) get_post_meta($this->replySubmission, 'corex_submitter_email', true);
    $confirmation = $kept($person, 'We received your enquiry');
    $notice = $kept('sales@example.com', 'New enquiry from Salma');
    update_post_meta($this->replySubmission, 'corex_email_json', ['bindings' => [
        'submitter_confirmation' => ['attempt_id' => $confirmation->attemptId, 'state' => $confirmation->state],
        'team_notification' => ['attempt_id' => $notice->attemptId, 'state' => $notice->state],
    ]]);
    $get = new WP_REST_Request('GET', '/corex/v1/submissions/' . $this->replySubmission);
    $get->set_url_params(['id' => (string) $this->replySubmission]);

    $offered = $this->replyController->show($get)->get_data()['data']['submission']['reply_conversation'];
    $sent = $this->replyController->reply(replyRecordRequest('POST', '/corex/v1/submissions/' . $this->replySubmission . '/reply', [
        'id' => $this->replySubmission,
        'subject' => $offered['subject'],
        'body' => '<p>Hello Salma</p>',
        'thread' => 'conversation',
    ]))->get_data();
    $headers = [];
    foreach (Boot::app()->container()->make(\Corex\Email\Capture\CapturedEmailRepository::class)->latest(20) as $capture) {
        if ($capture->attemptId === $sent['data']['result']['attempt_id']) {
            $headers = $capture->headers;
        }
    }
    $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
    $itsId = \Corex\Mail\MessageId::of($confirmation->attemptId, $host);

    expect($confirmation->state)->toBe('captured')
        ->and($offered)->toBe(['available' => true, 'subject' => 'Re: We received your enquiry'])
        ->and($itsId)->not->toBe('')
        ->and($headers['In-Reply-To'] ?? '')->toBe($itsId)
        // The team's notice is not an email the person has, and is not named.
        ->and($headers['References'] ?? '')->toBe($itsId);
});

