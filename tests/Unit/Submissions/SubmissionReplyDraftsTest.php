<?php

/**
 * A reply somebody has begun is kept for them, and for nobody else (spec 106, FR-006).
 *
 * The store is a fake: what is under test is whose draft is kept, for which submission, and
 * when it stops being one. WordPress's part of it is `SubmissionReplyDraftTest`.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionEmailService;
use Corex\Config\Submissions\SubmissionReply;
use Corex\Config\Submissions\SubmissionReplyDraft;
use Corex\Config\Submissions\SubmissionReplyDrafts;
use Corex\Config\Submissions\SubmissionReplyDraftStore;
use Corex\Config\Submissions\SubmissionReplyRecord;
use Corex\Config\Submissions\SubmissionReplyStore;
use Corex\Config\Submissions\SubmissionTimelineStore;
use Corex\Config\Submissions\SubmissionWorkflowStore;
use Corex\Mail\MailResult;
use Corex\Mail\ReplyReadiness;
use Corex\Mail\SubmissionEmailGateway;

/** One submission, 31, owned by the sales team. */
function draftedSubmissions(): SubmissionWorkflowStore
{
    return new class() implements SubmissionWorkflowStore {
        public function findWorkflow(int $id): ?array
        {
            return $id === 31
                ? ['id' => 31, 'owner_type' => 'team', 'owner_key' => 'sales', 'submitter_email' => 'sam@example.com']
                : null;
        }

        public function updateWorkflow(int $id, array $changes, string $expectedUpdatedAt): array
        {
            throw new BadMethodCallException();
        }

        public function addWorkflowNote(int $id, int $authorId, string $body, string $visibility): array
        {
            throw new BadMethodCallException();
        }
    };
}

function draftStore(): SubmissionReplyDraftStore
{
    return new class() implements SubmissionReplyDraftStore {
        /** @var array<string,SubmissionReplyDraft> */
        public array $kept = [];

        public function find(int $submissionId, int $userId): ?SubmissionReplyDraft
        {
            return $this->kept[$submissionId . ':' . $userId] ?? null;
        }

        public function keep(int $submissionId, int $userId, SubmissionReplyDraft $draft): void
        {
            $this->kept[$submissionId . ':' . $userId] = $draft;
        }

        public function discard(int $submissionId, int $userId): void
        {
            unset($this->kept[$submissionId . ':' . $userId]);
        }

        public function discardAllOf(int $userId): void
        {
            throw new BadMethodCallException();
        }
    };
}

function draftedReplyGateway(string $state): SubmissionEmailGateway
{
    return new class($state) implements SubmissionEmailGateway {
        public function __construct(private readonly string $state)
        {
        }

        public function readiness(): ReplyReadiness
        {
            return new ReplyReadiness(ReplyReadiness::DELIVER, 'live', '');
        }

        public function reply(string $recipient, string $subject, string $htmlBody, ?\Corex\Mail\ReplyConversation $within = null): MailResult
        {
            return new MailResult(
                attemptId: '22222222-2222-4222-8222-222222222222',
                requestId: '44444444-4444-4444-8444-444444444444',
                state: $this->state,
                provider: 'wp_mail',
                message: 'The mail system answered.',
                occurredAt: new DateTimeImmutable('2026-10-11T12:00:00+00:00'),
                retryable: false,
            );
        }

        public function conversation(string $recipient, array $attemptIds): \Corex\Mail\ReplyConversation
        {
            throw new BadMethodCallException();
        }

        public function preview(string $recipient, string $subject, string $htmlBody): array
        {
            throw new BadMethodCallException();
        }

        public function resend(string $attemptId, string $recipient, array $context): MailResult
        {
            throw new BadMethodCallException();
        }

        public function log(string $attemptId): ?array
        {
            throw new BadMethodCallException();
        }
    };
}

function draftedReplyService(SubmissionReplyDraftStore $drafts, string $state): SubmissionEmailService
{
    $replies = new class() implements SubmissionReplyStore {
        public function replace(int $submissionId, SubmissionReplyRecord $was, SubmissionReplyRecord $now): void
        {
        }

        public function add(int $submissionId, SubmissionReplyRecord $reply): void
        {
        }

        public function forSubmission(int $submissionId): array
        {
            return [];
        }
    };
    $timeline = new class() implements SubmissionTimelineStore {
        public function append(int $submissionId, string $stage, string $outcome, array $summary): array
        {
            return [];
        }

        public function forSubmission(int $submissionId, bool $includeRestricted): array
        {
            return [];
        }
    };

    return new SubmissionEmailService(draftedSubmissions(), draftedReplyGateway($state), $timeline, $replies, $drafts);
}

const SALES = ['sales'];

it('keeps what one person has written to one submission, and gives it back to them', function () {
    $store = draftStore();
    $drafts = new SubmissionReplyDrafts(draftedSubmissions(), $store);

    $kept = $drafts->keep(new SubmissionAccessScope(7, false, SALES), 31, 'Re: Contact', '<p>Hello Sam,</p>');

    expect($kept['subject'])->toBe('Re: Contact')
        ->and($kept['body'])->toBe('<p>Hello Sam,</p>')
        ->and(strtotime($kept['saved_at']))->toBeGreaterThan(0)
        ->and($drafts->of(7, 31))->toBe($kept);
});

// Spec 106, FR-039: somebody who chose a new email and comes back to their draft must not find
// it set to join the conversation.
it('keeps how the reply was to be sent with what was written', function (string $given, string $kept) {
    $drafts = new SubmissionReplyDrafts(draftedSubmissions(), draftStore());

    $drafts->keep(new SubmissionAccessScope(7, false, SALES), 31, 'Your kitchen', '<p>Hello Sam,</p>', $given);

    expect($drafts->of(7, 31)['thread'])->toBe($kept);
})->with([
    'in the conversation' => ['conversation', 'conversation'],
    'as a new email' => ['new', 'new'],
    'not said' => ['', ''],
    'something that is neither' => ['bcc', ''],
]);

it('keeps half a reply: a subject with no message yet, or a message with no subject', function () {
    $drafts = new SubmissionReplyDrafts(draftedSubmissions(), draftStore());
    $scope = new SubmissionAccessScope(7, false, SALES);

    expect($drafts->keep($scope, 31, 'Re: Contact', '')['body'])->toBe('')
        ->and($drafts->keep($scope, 31, '', '<p>Hello</p>')['subject'])->toBe('');
});

it('shows nobody a draft that is somebody else\'s', function () {
    $drafts = new SubmissionReplyDrafts(draftedSubmissions(), draftStore());
    $drafts->keep(new SubmissionAccessScope(7, false, SALES), 31, 'Re: Contact', '<p>Mine</p>');
    $drafts->keep(new SubmissionAccessScope(8, false, SALES), 31, 'Re: Contact', '<p>Theirs</p>');

    expect($drafts->of(7, 31)['body'])->toBe('<p>Mine</p>')
        ->and($drafts->of(8, 31)['body'])->toBe('<p>Theirs</p>')
        ->and($drafts->of(9, 31))->toBeNull();
});

it('keeps a draft for the latest of what was written, not each version of it', function () {
    $store = draftStore();
    $drafts = new SubmissionReplyDrafts(draftedSubmissions(), $store);
    $scope = new SubmissionAccessScope(7, false, SALES);

    $drafts->keep($scope, 31, 'Re: Contact', '<p>First</p>');
    $drafts->keep($scope, 31, 'Re: Contact', '<p>Second</p>');

    expect($store->kept)->toHaveCount(1)
        ->and($drafts->of(7, 31)['body'])->toBe('<p>Second</p>');
});

it('keeps no draft of nothing: emptying what was written discards it', function () {
    $store = draftStore();
    $drafts = new SubmissionReplyDrafts(draftedSubmissions(), $store);
    $scope = new SubmissionAccessScope(7, false, SALES);
    $drafts->keep($scope, 31, 'Re: Contact', '<p>Hello</p>');

    expect($drafts->keep($scope, 31, '  ', ''))->toBeNull()
        ->and($store->kept)->toBe([]);
});

it('discards a draft when asked, and only that person\'s', function () {
    $store = draftStore();
    $drafts = new SubmissionReplyDrafts(draftedSubmissions(), $store);
    $drafts->keep(new SubmissionAccessScope(7, false, SALES), 31, 'Re: Contact', '<p>Mine</p>');
    $drafts->keep(new SubmissionAccessScope(8, false, SALES), 31, 'Re: Contact', '<p>Theirs</p>');

    $drafts->discard(new SubmissionAccessScope(7, false, SALES), 31);

    expect($drafts->of(7, 31))->toBeNull()
        ->and($drafts->of(8, 31)['body'])->toBe('<p>Theirs</p>');
});

it('keeps and discards nothing on a submission the person cannot reach', function (int $submissionId, array $teams) {
    $store = draftStore();
    $drafts = new SubmissionReplyDrafts(draftedSubmissions(), $store);
    $scope = new SubmissionAccessScope(9, false, $teams);

    expect(fn () => $drafts->keep($scope, $submissionId, 'Re: Contact', '<p>Hello</p>'))->toThrow(DomainException::class)
        ->and(fn () => $drafts->discard($scope, $submissionId))->toThrow(DomainException::class)
        ->and($store->kept)->toBe([]);
})->with([
    'another team\'s' => [31, ['support']],
    'one that is not there' => [404, SALES],
]);

it('keeps a date only where it is one', function () {
    expect((new SubmissionReplyDraft('Re: Contact', '', '2026-10-11T12:00:00+00:00'))->savedAt)->toBe('2026-10-11T12:00:00+00:00')
        ->and((new SubmissionReplyDraft('Re: Contact', '', '<b>when</b>'))->savedAt)->toBe('');
});

it('makes a subject one line of a subject\'s length', function () {
    $drafts = new SubmissionReplyDrafts(draftedSubmissions(), draftStore());

    $kept = $drafts->keep(new SubmissionAccessScope(7, false, SALES), 31, "Re:\r\nBcc: x@example.com " . str_repeat('long ', 200), '');

    expect($kept['subject'])->not->toContain("\n")->not->toContain("\r")
        ->and($kept['subject'])->toStartWith('Re: Bcc: x@example.com')
        ->and(mb_strlen($kept['subject']))->toBeLessThanOrEqual(300);
});

// A reply that has gone is no longer being written. One that was not sent still is: the box
// keeps its words for another try (FR-031), and so does the draft.
it('stops being a draft when the reply is sent, and stays one when it was not', function (string $state, bool $stillADraft) {
    $store = draftStore();
    $scope = new SubmissionAccessScope(7, false, SALES);
    (new SubmissionReplyDrafts(draftedSubmissions(), $store))->keep($scope, 31, 'Re: Contact', '<p>Hello Sam,</p>');
    $store->keep(31, 8, new SubmissionReplyDraft('Re: Contact', '<p>Theirs</p>', '2026-10-11T12:00:00+00:00'));

    draftedReplyService($store, $state)->reply($scope, 31, new SubmissionReply('Re: Contact', '<p>Hello Sam,</p>'));

    expect($store->find(31, 7) !== null)->toBe($stillADraft)
        // Somebody else's draft to the same person is theirs to send or discard.
        ->and($store->find(31, 8))->not->toBeNull();
})->with([
    'sent' => [MailResult::STATE_SENT, false],
    'accepted by the provider' => [MailResult::STATE_ACCEPTED, false],
    'queued' => [MailResult::STATE_QUEUED, false],
    'kept on the site' => [MailResult::STATE_CAPTURED, false],
    'opened by the person it went to' => [MailResult::STATE_OPENED, false],
    // The pane keeps the words in its box for this one, so the draft keeps them as well.
    'still being sent' => [MailResult::STATE_SENDING, true],
    'failed' => [MailResult::STATE_FAILED, true],
    'refused' => [MailResult::STATE_REJECTED, true],
    'returned' => [MailResult::STATE_BOUNCED, true],
]);
