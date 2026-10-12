<?php

/**
 * Email Studio's store hands back the text it was handed.
 *
 * WordPress takes one level of slashes off a post's title and off a meta value on their way in.
 * The store handed its records over as they were, so a backslash was gone from everything it
 * kept: an email with the subject "C:\offers\new" was listed as "C:offersnew", a template's
 * `content:"\201C"` was stored as `content:"201C"`, and a name with two backslashes lost one
 * more each time its record was saved again (measured 2026-10-13, DECISIONS #329).
 *
 * On real WordPress, through the store and through the repositories the screens read. Nothing
 * is sent.
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Email\Capture\CapturedEmailRepository;
use Corex\Email\Delivery\EmailAttempt;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Email\Message\EmailMessage;
use Corex\Email\Studio\EmailLayout;
use Corex\Email\Studio\EmailLayoutRepository;
use Corex\Email\Studio\EmailPartial;
use Corex\Email\Studio\EmailPartialRepository;
use Corex\Email\Studio\EmailTemplateRepository;
use Corex\Email\Studio\EmailTemplateVersion;
use Corex\Email\Studio\WpEmailStudioStore;
use Corex\Support\Uuid;
use Corex\Tests\Support\CreatedPosts;

/** A path, a CSS escape, both quotes, an ampersand and words that are not ASCII, in one text. */
const KEPT_TEXT = 'C:\\offers\\new, q::before{content:"\\201C"}, Tom & Jerry\'s "offer", عرض café';

/** The same, without the ampersand: a name is a post's title, and a title is not markup's place. */
const KEPT_NAME = 'C:\\offers\\new and \\\\server\\share, Jerry\'s "offer", عرض café';

function keptSlug(string $prefix): string
{
    return $prefix . '-' . strtolower(wp_generate_password(10, false));
}

function keptDraft(int $templateId, string $htmlBody, int $actorId): EmailTemplateVersion
{
    return new EmailTemplateVersion(
        id: 1,
        templateId: $templateId,
        versionNumber: 1,
        subject: KEPT_TEXT,
        fromName: KEPT_NAME,
        fromAddress: 'hello@example.com',
        htmlBody: $htmlBody,
        plainText: KEPT_TEXT,
        plainTextMode: EmailTemplateVersion::PLAIN_MANUAL,
        layoutId: 1,
        layoutVersion: 1,
        variableKeys: [],
        createdBy: $actorId,
        createdAt: new DateTimeImmutable('2026-10-13T09:00:00+00:00'),
        // Replaced by the repository with the checksum of the content it stores.
        checksum: hash('sha256', 'a draft'),
    );
}

beforeEach(function () {
    $this->store = new WpEmailStudioStore();
    $this->store->registerPostType();
    $this->records = CreatedPosts::watch(WpEmailStudioStore::POST_TYPE);
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->actorId = (int) ($administrators[0] ?? 0);
    wp_set_current_user($this->actorId);
});

afterEach(function () {
    $this->records->delete();
});

dataset('text a record may hold', [
    'a path' => ['C:\\offers\\new'],
    'a network path' => ['\\\\server\\share'],
    'a CSS escape' => ['q::before{content:"\\201C"}'],
    'a backslash before each quote' => ['a \\"quoted\\" word and it\\\'s'],
    'a backslash at the end' => ['ends with \\'],
    // WordPress changed neither of these two: they are here so that the fix does not.
    'both quotes' => ['Jerry\'s "offer"'],
    'Arabic and an accent' => ['عرض "خاص" في café'],
]);

it('keeps the name and the text of a record as they were handed over', function (string $text) {
    $id = $this->store->create('store_probe', keptSlug('probe'), $text, 0, [
        'text' => $text,
        'inside' => ['a list' => [$text], 'deeper' => ['still' => $text]],
    ]);
    $kept = $this->store->find($id);

    expect($kept['name'])->toBe($text)
        ->and($kept['payload']['text'])->toBe($text)
        ->and($kept['payload']['inside'])->toBe(['a list' => [$text], 'deeper' => ['still' => $text]]);
})->with('text a record may hold');

it('keeps them when the record is saved again, however many times', function (string $text) {
    $id = $this->store->create('store_probe', keptSlug('probe'), 'A name', 0, ['text' => 'some text']);

    $this->store->update($id, $text, ['text' => $text]);
    $read = $this->store->find($id);
    // A record is read and written back whole when one of its values changes.
    $this->store->update($id, $read['name'], $read['payload']);
    $kept = $this->store->find($id);

    expect($kept['name'])->toBe($text)
        ->and($kept['payload'])->toBe(['text' => $text]);
})->with('text a record may hold');

it('hands back what is not text as the kind of value it was', function () {
    // An attempt's template is read only where it is a whole number, and a route's switch
    // where it is a boolean: putting slashes on must not make text of either.
    $payload = ['count' => 3, 'ratio' => 1.5, 'on' => true, 'off' => false, 'nothing' => null, 'empty' => ''];

    $id = $this->store->create('store_probe', keptSlug('probe'), 'A name', 0, $payload);

    expect($this->store->find($id)['payload'])->toBe($payload);
});

it('keeps the name of a record made in a request nobody is signed in for', function () {
    // An email is kept while a visitor's form is handled, and WordPress cleans a title for a
    // person who may not post markup.
    wp_set_current_user(0);

    $id = $this->store->create('store_probe', keptSlug('probe'), KEPT_NAME, 0, ['text' => KEPT_TEXT]);

    expect($this->store->find($id)['name'])->toBe(KEPT_NAME)
        ->and($this->store->find($id)['payload']['text'])->toBe(KEPT_TEXT);
});

it('lists a captured email under the subject and with the body it left with', function () {
    $recipient = keptSlug('salma') . '@example.com';
    $message = new EmailMessage(
        [$recipient],
        [],
        [],
        null,
        KEPT_TEXT,
        '<p>' . KEPT_TEXT . '</p>',
        ['X-Corex-Note' => KEPT_TEXT],
        plainText: KEPT_TEXT,
    );
    $captures = new CapturedEmailRepository($this->store);

    $captured = $captures->capture($message);
    $listed = array_values(array_filter($captures->latest(50), static fn ($capture): bool => $capture->to === [$recipient]));

    expect($captured->subject)->toBe(KEPT_TEXT)
        ->and($listed)->toHaveCount(1)
        ->and($listed[0]->subject)->toBe(KEPT_TEXT)
        ->and($listed[0]->body)->toBe('<p>' . KEPT_TEXT . '</p>')
        ->and($listed[0]->plainText)->toBe(KEPT_TEXT)
        ->and($listed[0]->headers)->toBe(['X-Corex-Note' => KEPT_TEXT]);
});

it('lists an attempt under the subject of its email', function () {
    $attempts = new EmailAttemptRepository($this->store);
    $attemptId = Uuid::v4();

    $attempts->record(new EmailAttempt(
        id: 0,
        attemptId: $attemptId,
        requestId: Uuid::v4(),
        parentAttemptId: null,
        recipient: 'salma@example.com',
        subject: KEPT_TEXT,
        templateSlug: null,
        state: EmailAttempt::STATE_CAPTURED,
        provider: 'capture',
        providerEvent: null,
        retryable: false,
        occurredAt: new DateTimeImmutable('2026-10-13T09:00:00+00:00'),
    ));

    expect($attempts->findByAttemptId($attemptId)?->subject)->toBe(KEPT_TEXT);
});

it('keeps a template draft as it was written, and the template under its name', function () {
    $templates = new EmailTemplateRepository($this->store);
    $now = new DateTimeImmutable('2026-10-13T09:00:00+00:00');
    $html = '<style>q::before{content:"\\201C"}</style><p>' . KEPT_TEXT . '</p>';

    $template = $templates->create(keptSlug('kept'), KEPT_NAME, $this->actorId, $now);
    // Saving a draft and making it the active one each write the template's own record again.
    $draft = $templates->saveDraft(keptDraft($template->id, $html, $this->actorId));
    $templates->activate($template->id, $draft->versionNumber, $this->actorId, $now);
    $read = $templates->findVersion($template->id, $draft->versionNumber);

    expect($read?->htmlBody)->toBe($html)
        ->and($read->subject)->toBe(KEPT_TEXT)
        ->and($read->fromName)->toBe(KEPT_NAME)
        ->and($read->plainText)->toBe(KEPT_TEXT)
        ->and($templates->find($template->id)?->name)->toBe(KEPT_NAME);
});

it('reads back two contents for two template drafts whose checksums differ', function () {
    // A draft's checksum is taken before it is stored: it stands for the content only while
    // the content read back is the content that was handed over.
    $templates = new EmailTemplateRepository($this->store);
    $template = $templates->create(keptSlug('kept'), 'Two drafts', $this->actorId, new DateTimeImmutable('2026-10-13T09:00:00+00:00'));

    $withBackslashes = $templates->saveDraft(keptDraft($template->id, '<p>C:\\offers\\one</p>', $this->actorId));
    $without = $templates->saveDraft(keptDraft($template->id, '<p>C:offersone</p>', $this->actorId));

    expect($withBackslashes->checksum)->not->toBe($without->checksum)
        ->and($withBackslashes->htmlBody)->not->toBe($without->htmlBody);
});

it('keeps a layout under its name, with each of its regions as written', function () {
    $layouts = new EmailLayoutRepository($this->store);
    $slug = keptSlug('kept');
    $regions = [
        'header' => '<p>' . KEPT_TEXT . '</p>',
        'accent' => '',
        'body' => '<style>q::before{content:"\\201C"}</style>{{ content }}',
        'button' => '',
        'footer' => '<p>C:\\offers\\one</p>',
    ];

    $layouts->save(new EmailLayout(
        id: 0,
        slug: $slug,
        name: KEPT_NAME,
        version: 1,
        regions: $regions,
        dependency: null,
        createdBy: $this->actorId,
        createdAt: new DateTimeImmutable('2026-10-13T09:00:00+00:00'),
    ));
    $read = $layouts->find($slug);

    expect($read?->name)->toBe(KEPT_NAME)
        ->and($read->regions)->toBe($regions);
});

it('keeps a partial under its name, with its HTML and its plain text as written', function () {
    $partials = new EmailPartialRepository($this->store);
    $slug = keptSlug('kept');

    $partials->save(new EmailPartial(
        id: 0,
        slug: $slug,
        name: KEPT_NAME,
        kind: 'custom',
        htmlBody: '<p>' . KEPT_TEXT . '</p>',
        plainText: KEPT_TEXT,
        status: 'active',
        version: 1,
        createdBy: $this->actorId,
        createdAt: new DateTimeImmutable('2026-10-13T09:00:00+00:00'),
    ));
    $read = $partials->find($slug);

    expect($read?->name)->toBe(KEPT_NAME)
        ->and($read->htmlBody)->toBe('<p>' . KEPT_TEXT . '</p>')
        ->and($read->plainText)->toBe(KEPT_TEXT);
});
