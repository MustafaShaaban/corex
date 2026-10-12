<?php

/**
 * A reply somebody began is kept with its submission until it is sent or discarded
 * (spec 106, FR-006).
 *
 * Through the routes and on real WordPress: what is under test is what the pane is sent back
 * on another visit, and what sending, the trash, a permanent delete, an anonymization and the
 * removal of the person then leave.
 *
 * @package Corex\Tests\Integration\Submissions
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Data\WpSubmissionsReader;
use Corex\Config\Submissions\SubmissionReplyDraftStore;
use Corex\Config\Submissions\SubmissionsController;
use Corex\Config\Submissions\WpSubmissionReplyDraftStore;
use Corex\Tests\Support\MailDelivery;

function draftRequest(string $method, int $submission, string $path = '/reply/draft', array $payload = [], bool $proven = true): WP_REST_Request
{
    $request = new WP_REST_Request($method, '/corex/v1/submissions/' . $submission . $path);
    if ($proven) {
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    }
    $request->set_url_params(['id' => (string) $submission]);
    $request->set_body_params($payload);

    return $request;
}

/** @return array<string,mixed>|null The draft the person signed in is shown on the submission. */
function draftShownOn(object $test): ?array
{
    $shown = $test->draftController->show(draftRequest('GET', $test->draftSubmission, ''))->get_data();

    return $shown['data']['submission']['reply_draft'];
}

beforeEach(function () {
    $this->mailSettings = MailDelivery::answerAs(MailDelivery::CAPTURE);
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->administrator = (int) ($administrators[0] ?? 0);
    $this->colleague = (int) wp_insert_user([
        'user_login' => 'draft-colleague-' . strtolower(wp_generate_password(8, false)),
        'user_pass' => wp_generate_password(24),
        'role' => 'administrator',
    ]);
    wp_set_current_user($this->administrator);
    $this->draftController = Boot::app()->container()->make(SubmissionsController::class);
    $this->draftSubmission = wp_insert_post([
        'post_type' => 'corex_submission',
        'post_status' => 'private',
        'post_title' => 'A submission being answered',
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
    wp_delete_post($this->draftSubmission, true);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($this->colleague);
});

it('gives a person back the reply they began, on another visit', function () {
    $kept = $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', [
        'subject' => 'Re: your message',
        'body' => '<p>Hello <strong>Salma</strong>,</p><p>The file is at C:\\offers\\one.</p><script>steal()</script>',
    ]))->get_data();

    $shown = draftShownOn($this);

    expect($kept['ok'])->toBeTrue()
        ->and($kept['data']['draft'])->toBe($shown)
        ->and($shown['subject'])->toBe('Re: your message')
        // Reduced as a reply is, and a path keeps its slashes through the store.
        ->and($shown['body'])->toContain('<p>Hello <strong>Salma</strong>,</p>')->toContain('C:\\offers\\one.')
        ->and($shown['body'])->not->toContain('script')->not->toContain('steal')
        ->and(strtotime($shown['saved_at']))->toBeGreaterThan(time() - 60);
});

// Spec 106, FR-039: how the reply was to be sent is part of what was begun.
it('gives the draft back set to be sent the way it was', function (mixed $asked, string $kept) {
    $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', [
        'subject' => 'Your kitchen',
        'body' => '<p>Hello Salma</p>',
        'thread' => $asked,
    ]));

    expect(draftShownOn($this)['thread'])->toBe($kept);
})->with([
    'as a new email' => ['new', 'new'],
    'in the conversation' => ['conversation', 'conversation'],
    'something that is neither' => ["new\r\nBcc: x@example.com", ''],
    'not text' => [['new'], ''],
]);

// A subject goes back into the field it was typed in. What `sanitize_text_field` does to a
// line (it wrote "Price &lt;500 EGP", and took the address out of "Sam <sam@example.com>")
// would have come back as something the person never wrote.
it('keeps a subject as it was typed', function (string $typed) {
    $kept = $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', [
        'subject' => $typed,
        'body' => '',
    ]))->get_data();

    expect($kept['data']['draft']['subject'])->toBe($typed)
        ->and(draftShownOn($this)['subject'])->toBe($typed);
})->with([
    'a price' => ['Price <500 EGP'],
    'a name and an address' => ['Sam <sam@example.com> asked'],
    'a percentage' => ['Save 50%de today'],
    'an ampersand and quotes' => ['Tom & Jerry\'s "offer"'],
    'Arabic' => ['رد: رسالتك'],
]);

it('answers what the next visit is shown, for lines that were typed as lines', function () {
    $kept = $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', [
        'subject' => 'Re: your message',
        'body' => "Hello Salma,\nthank you.\n\n\nRegards",
    ]))->get_data();

    expect($kept['data']['draft'])->toBe(draftShownOn($this));
});

// Reduced, a message beyond what is read as a reply is nothing, and nothing discards a draft.
it('refuses a message too long to be one, and leaves the draft already kept', function () {
    $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', [
        'subject' => 'Re: your message',
        'body' => '<p>The draft I have</p>',
    ]));

    $refused = $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', [
        'subject' => '',
        'body' => str_repeat('<p>word</p>', 30_000),
    ]));

    expect($refused->get_status())->toBe(422)
        ->and($refused->get_data()['message'])->toContain('too long')
        ->and(draftShownOn($this)['body'])->toBe('<p>The draft I have</p>');
});

it('keeps the same words twice in a moment without a word of complaint', function () {
    $written = ['subject' => 'Re: your message', 'body' => '<p>Hello</p>'];

    $first = $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', $written));
    $second = $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', $written));

    expect($first->get_status())->toBe(200)
        ->and($second->get_status())->toBe(200)
        ->and(get_post_meta($this->draftSubmission, WpSubmissionReplyDraftStore::keyOf($this->administrator)))->toHaveCount(1);
});

// The pane changes a submission against the date it last read. A draft written in between
// must not make that change a conflict.
it('leaves the submission\'s own date of change alone', function () {
    $dates = fn (): array => [
        get_post_meta($this->draftSubmission, 'corex_submission_updated_at', true),
        get_post_field('post_modified_gmt', $this->draftSubmission),
    ];
    $before = $dates();

    $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', [
        'subject' => 'Re: your message',
        'body' => '<p>Hello</p>',
    ]));
    $this->draftController->discardReplyDraft(draftRequest('DELETE', $this->draftSubmission));

    expect($dates())->toBe($before);
});

it('keeps and discards nothing where there is no such submission, or the person may not manage them', function () {
    $page = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Not a submission']);
    $written = ['subject' => 'Re: your message', 'body' => '<p>Hello</p>'];
    $statuses = [];
    foreach ([$page, 999_999_999] as $id) {
        $statuses[] = $this->draftController->keepReplyDraft(draftRequest('POST', $id, '/reply/draft', $written))->get_status();
        $statuses[] = $this->draftController->discardReplyDraft(draftRequest('DELETE', $id))->get_status();
    }
    $subscriber = (int) wp_insert_user([
        'user_login' => 'draft-reader-' . strtolower(wp_generate_password(8, false)),
        'user_pass' => wp_generate_password(24),
        'role' => 'subscriber',
    ]);
    wp_set_current_user($subscriber);
    $statuses[] = $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', $written))->get_status();
    $statuses[] = $this->draftController->discardReplyDraft(draftRequest('DELETE', $this->draftSubmission))->get_status();
    wp_set_current_user($this->administrator);
    global $wpdb;
    $written = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND post_id IN (%d, %d)",
        $wpdb->esc_like(WpSubmissionReplyDraftStore::META_PREFIX) . '%',
        $page,
        $this->draftSubmission,
    ));
    wp_delete_post($page, true);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($subscriber);

    expect($statuses)->toBe([404, 404, 404, 404, 403, 403])
        ->and($written)->toBe(0);
});

it('keeps an Arabic draft as it was written, and half a reply as half', function () {
    $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', [
        'subject' => '',
        'body' => '<p>مرحبا سلمى، سنتصل بك غدا</p>',
    ]));

    expect(draftShownOn($this))->toMatchArray(['subject' => '', 'body' => '<p>مرحبا سلمى، سنتصل بك غدا</p>']);
});

it('shows a draft to the person who wrote it and to nobody else', function () {
    $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', [
        'subject' => 'Re: your message',
        'body' => '<p>Mine</p>',
    ]));

    wp_set_current_user($this->colleague);
    $theirs = draftShownOn($this);
    $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', [
        'subject' => 'Re: your message',
        'body' => '<p>My colleague\'s</p>',
    ]));
    wp_set_current_user($this->administrator);

    expect($theirs)->toBeNull()
        ->and(draftShownOn($this)['body'])->toBe('<p>Mine</p>');
});

it('discards a draft when asked, and when what was written is emptied', function () {
    $write = fn (string $subject, string $body) => $this->draftController->keepReplyDraft(
        draftRequest('POST', $this->draftSubmission, '/reply/draft', ['subject' => $subject, 'body' => $body]),
    );

    $write('Re: your message', '<p>Hello</p>');
    $discarded = $this->draftController->discardReplyDraft(draftRequest('DELETE', $this->draftSubmission))->get_data();
    $afterDiscard = draftShownOn($this);
    $write('Re: your message', '<p>Hello again</p>');
    // What an emptied box posts: no subject, and a paragraph with nothing in it.
    $emptied = $write('  ', '<p> </p>')->get_data();

    expect($discarded['ok'])->toBeTrue()
        ->and($afterDiscard)->toBeNull()
        ->and($emptied['data']['draft'])->toBeNull()
        ->and(draftShownOn($this))->toBeNull()
        ->and(get_post_meta($this->draftSubmission, WpSubmissionReplyDraftStore::keyOf($this->administrator)))->toBe([]);
});

it('is no longer a draft once the reply is sent', function () {
    $written = ['subject' => 'Re: your message', 'body' => '<p>Hello Salma</p>'];
    $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', $written));

    $sent = $this->draftController->reply(draftRequest('POST', $this->draftSubmission, '/reply', $written))->get_data();
    $shown = $this->draftController->show(draftRequest('GET', $this->draftSubmission, ''))->get_data()['data']['submission'];

    expect($sent['data']['result']['state'])->toBe('captured')
        ->and($shown['replies'])->toHaveCount(1)
        ->and($shown['reply_draft'])->toBeNull();
});

it('answers the draft routes only to somebody with the request\'s own proof', function () {
    $kept = $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', [
        'subject' => 'Re: your message',
        'body' => '<p>Hello</p>',
    ], proven: false));
    $discarded = $this->draftController->discardReplyDraft(draftRequest('DELETE', $this->draftSubmission, proven: false));

    expect($kept->get_status())->toBe(403)
        ->and($discarded->get_status())->toBe(403)
        ->and(draftShownOn($this))->toBeNull();
});

it('keeps no draft on a submission in the trash, and brings one back with the submission', function () {
    $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', [
        'subject' => 'Re: your message',
        'body' => '<p>Written before the trash</p>',
    ]));
    wp_trash_post($this->draftSubmission);

    $refused = $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', [
        'subject' => 'Re: your message',
        'body' => '<p>Written in the trash</p>',
    ]));
    wp_untrash_post($this->draftSubmission);
    wp_update_post(['ID' => $this->draftSubmission, 'post_status' => 'private']);

    expect($refused->get_status())->toBe(409)
        ->and($refused->get_data()['message'])->toContain('in the trash')
        ->and(draftShownOn($this)['body'])->toBe('<p>Written before the trash</p>');
});

// A draft quotes and names the customer it answers, as a sent reply does. That it goes with a
// submission deleted for good is WordPress's doing, which is why it is kept where it is.
it('is removed when the submission is anonymized, everybody\'s', function () {
    $written = ['subject' => 'Re: your message', 'body' => '<p>Dear Salma of 12 Nile Street</p>'];
    $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', $written));
    wp_set_current_user($this->colleague);
    $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', $written));
    wp_set_current_user($this->administrator);

    Boot::app()->container()->make(WpSubmissionsReader::class)->anonymizeForRetention($this->draftSubmission);
    global $wpdb;
    $left = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND post_id = %d",
        $wpdb->esc_like(WpSubmissionReplyDraftStore::META_PREFIX) . '%',
        $this->draftSubmission,
    ));

    expect($left)->toBe(0);
});

it('is removed with the person who wrote it, and nobody else\'s is', function () {
    $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', [
        'subject' => 'Re: your message',
        'body' => '<p>Mine</p>',
    ]));
    wp_set_current_user($this->colleague);
    $this->draftController->keepReplyDraft(draftRequest('POST', $this->draftSubmission, '/reply/draft', [
        'subject' => 'Re: your message',
        'body' => '<p>My colleague\'s</p>',
    ]));
    wp_set_current_user($this->administrator);
    $drafts = Boot::app()->container()->make(SubmissionReplyDraftStore::class);

    // Their work is handed to somebody else, as an administrator usually chooses: the reply
    // they had begun is not work that can be handed on.
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($this->colleague, $this->administrator);

    expect($drafts->find($this->draftSubmission, $this->colleague))->toBeNull()
        ->and(get_post_meta($this->draftSubmission, WpSubmissionReplyDraftStore::keyOf($this->colleague)))->toBe([])
        ->and($drafts->find($this->draftSubmission, $this->administrator)?->htmlBody)->toBe('<p>Mine</p>');
});

it('is not a draft where what is stored is not one', function (mixed $stored) {
    update_post_meta($this->draftSubmission, WpSubmissionReplyDraftStore::keyOf($this->administrator), $stored);

    expect(draftShownOn($this))->toBeNull();
})->with([
    'a string' => ['a string, not a draft'],
    'a list' => [['x', 'y']],
    'fields of the wrong kind' => [['subject' => ['x'], 'body' => 5, 'saved_at' => false]],
    'a date and nothing else' => [['saved_at' => '2026-10-11T12:00:00+00:00']],
]);

it('gives back what is stored reduced, and its date only where it is one', function () {
    update_post_meta($this->draftSubmission, WpSubmissionReplyDraftStore::keyOf($this->administrator), [
        'subject' => 'Re: your message',
        'body' => '<p onclick="steal()">Hi</p><script>steal()</script><a href="javascript:alert(1)">l</a>',
        'saved_at' => '<b>when</b>',
    ]);

    $shown = draftShownOn($this);

    expect($shown['subject'])->toBe('Re: your message')
        ->and($shown['body'])->toContain('Hi')->toContain('<p>')
        ->and($shown['body'])->not->toContain('script')->not->toContain('onclick')->not->toContain('href')->not->toContain('steal')
        ->and($shown['saved_at'])->toBe('');
});

// Spec 106, FR-035: the box opens with a subject, from the form the person filled in.
it('sends the pane a subject to start a reply from, with the submission', function () {
    // As WordPress stores a title: its `&` and `<` as entities.
    update_post_meta($this->draftSubmission, 'corex_flow_label_snapshot', 'Sales &amp; quotes');

    $shown = $this->draftController->show(draftRequest('GET', $this->draftSubmission, ''))->get_data()['data']['submission'];

    expect($shown['reply_subject'])->toBe('Re: Sales & quotes');
});

// A form defined in code says its own name, and the inbox's filter lists it under that.
it('names a form defined in code as the inbox\'s own filter names it', function () {
    update_post_meta($this->draftSubmission, 'corex_form_slug', 'quote_v2');
    $named = static fn (array $forms): array => [...$forms, ['id' => 0, 'name' => 'Request a quote', 'slug' => 'quote_v2']];
    add_filter('corex_submission_filter_options', $named);

    $shown = $this->draftController->show(draftRequest('GET', $this->draftSubmission, ''))->get_data()['data']['submission'];
    remove_filter('corex_submission_filter_options', $named);

    expect($shown['reply_subject'])->toBe('Re: Request a quote');
});
