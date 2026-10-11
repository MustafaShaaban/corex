<?php

/**
 * Submission reply/resend/log tests for spec 068 T101 / FR-052 and FR-058.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionEmailService;
use Corex\Config\Submissions\SubmissionReply;
use Corex\Config\Submissions\SubmissionTimelineStore;
use Corex\Config\Submissions\SubmissionWorkflowStore;
use Corex\Config\Submissions\SubmissionReplyRecord;
use Corex\Config\Submissions\SubmissionReplyStore;
use Corex\Mail\MailResult;
use Corex\Mail\ReplyReadiness;
use Corex\Mail\SubmissionEmailGateway;

function emailWorkflowRecord(): array
{
    return [
        'id' => 31,
        'owner_type' => 'team',
        'owner_key' => 'sales',
        'submitter_email' => 'sam@example.com',
        'values' => ['name' => 'Sam', 'email' => 'sam@example.com'],
        'related_emails' => ['bindings' => [
            'team_notification' => ['attempt_id' => '11111111-1111-4111-8111-111111111111'],
        ]],
    ];
}

function emailWorkflowStore(string $submitterEmail = 'sam@example.com'): SubmissionWorkflowStore
{
    return new class($submitterEmail) implements SubmissionWorkflowStore {
        public function __construct(private string $submitterEmail)
        {
        }

        public function findWorkflow(int $id): ?array
        {
            return $id === 31 ? ['submitter_email' => $this->submitterEmail] + emailWorkflowRecord() : null;
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

function submissionEmailGateway(string $mode = ReplyReadiness::CAPTURE, string $state = MailResult::STATE_CAPTURED): SubmissionEmailGateway
{
    return new class($mode, $state) implements SubmissionEmailGateway {
        public array $calls = [];

        public function __construct(private readonly string $mode, private readonly string $state)
        {
        }

        public function readiness(): ReplyReadiness
        {
            $sendable = in_array($this->mode, [ReplyReadiness::DELIVER, ReplyReadiness::CAPTURE], true);

            return new ReplyReadiness($this->mode, 'a_code', $sendable ? '' : 'Replies are not being delivered: ' . $this->mode);
        }

        public function reply(string $recipient, string $subject, string $htmlBody): MailResult
        {
            $this->calls[] = ['reply', $recipient, $subject, $htmlBody];

            return submissionMailResult('22222222-2222-4222-8222-222222222222', $this->state);
        }

        public function preview(string $recipient, string $subject, string $htmlBody): array
        {
            $this->calls[] = ['preview', $recipient, $subject, $htmlBody];

            return ['html' => '<html><body>' . $htmlBody . '</body></html>', 'text' => 'Thanks, Sam.'];
        }

        public function resend(string $attemptId, string $recipient, array $context): MailResult
        {
            $this->calls[] = ['resend', $attemptId, $recipient, $context];

            return submissionMailResult('33333333-3333-4333-8333-333333333333');
        }

        public function log(string $attemptId): ?array
        {
            $this->calls[] = ['log', $attemptId];

            return ['attempt_id' => $attemptId, 'state' => 'captured', 'recipient' => 's***@example.com'];
        }
    };
}

function submissionMailResult(string $attemptId, string $state = MailResult::STATE_CAPTURED): MailResult
{
    return new MailResult(
        attemptId: $attemptId,
        requestId: '44444444-4444-4444-8444-444444444444',
        state: $state,
        provider: 'corex-capture',
        message: 'Captured.',
        occurredAt: new DateTimeImmutable('2026-07-04T12:00:00+00:00'),
        retryable: false,
    );
}

function submissionEmailTimeline(): SubmissionTimelineStore
{
    return new class() implements SubmissionTimelineStore {
        public array $events = [];

        public function append(int $submissionId, string $stage, string $outcome, array $summary): array
        {
            return $this->events[] = compact('submissionId', 'stage', 'outcome', 'summary');
        }

        public function forSubmission(int $submissionId, bool $includeRestricted): array
        {
            return $this->events;
        }
    };
}

function submissionReplyStore(): SubmissionReplyStore
{
    return new class() implements SubmissionReplyStore {
        /** @var array<int,list<SubmissionReplyRecord>> */
        public array $kept = [];

        public function add(int $submissionId, SubmissionReplyRecord $reply): void
        {
            $this->kept[$submissionId][] = $reply;
        }

        public function forSubmission(int $submissionId): array
        {
            return $this->kept[$submissionId] ?? [];
        }
    };
}

it('sends a reply only to the accessible submission address and timelines the result', function () {
    $gateway = submissionEmailGateway();
    $timeline = submissionEmailTimeline();
    $service = new SubmissionEmailService(emailWorkflowStore(), $gateway, $timeline, submissionReplyStore());
    $scope = new SubmissionAccessScope(7, false, ['sales']);
    // What somebody wrote is reduced against WordPress itself, in ReplyBodyTest. Here the reply
    // is already a reply.
    $sent = $service->reply($scope, 31, new SubmissionReply('Re: Contact', '<p>Thanks, Sam.</p>'));

    expect($sent->result->state)->toBe(MailResult::STATE_CAPTURED)
        ->and($sent->recorded)->toBeTrue()
        ->and($gateway->calls[0])->toBe(['reply', 'sam@example.com', 'Re: Contact', '<p>Thanks, Sam.</p>'])
        ->and($timeline->events[0]['stage'])->toBe('email')
        ->and($timeline->events[0]['summary'])->not->toHaveKey('body');
});

it('resends only an attempt already related to the accessible submission', function () {
    $gateway = submissionEmailGateway();
    $timeline = submissionEmailTimeline();
    $service = new SubmissionEmailService(emailWorkflowStore(), $gateway, $timeline, submissionReplyStore());
    $scope = new SubmissionAccessScope(7, false, ['sales']);
    $attemptId = '11111111-1111-4111-8111-111111111111';

    $result = $service->resend($scope, 31, $attemptId);

    expect($result->parentAttemptId)->toBeNull()
        ->and($gateway->calls[0][0])->toBe('resend')
        ->and($gateway->calls[0][1])->toBe($attemptId)
        ->and($gateway->calls[0][3]['submission']['name'])->toBe('Sam');

    expect(fn () => $service->resend($scope, 31, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'))
        ->toThrow(DomainException::class, 'related');
});

it('opens only a related redacted delivery log', function () {
    $service = new SubmissionEmailService(emailWorkflowStore(), submissionEmailGateway(), submissionEmailTimeline(), submissionReplyStore());
    $scope = new SubmissionAccessScope(7, false, ['sales']);

    $log = $service->log($scope, 31, '11111111-1111-4111-8111-111111111111');

    expect($log)->toMatchArray(['state' => 'captured', 'recipient' => 's***@example.com']);
});

// Spec 106, FR-021: what was answered is on the submission, whatever happened to it.
it('keeps the reply with its subject, its text, who sent it and what happened to it', function (string $state) {
    $replies = submissionReplyStore();
    $timeline = submissionEmailTimeline();
    $service = new SubmissionEmailService(emailWorkflowStore(), submissionEmailGateway(ReplyReadiness::DELIVER, $state), $timeline, $replies);

    $service->reply(new SubmissionAccessScope(7, false, ['sales']), 31, new SubmissionReply('Re: Contact', '<p>Thanks, Sam.</p>'));

    $kept = $replies->kept[31][0];
    expect($replies->kept[31])->toHaveCount(1)
        ->and($kept->subject)->toBe('Re: Contact')
        ->and($kept->bodyHtml)->toBe('<p>Thanks, Sam.</p>')
        ->and($kept->actorId)->toBe(7)
        ->and($kept->state())->toBe($state)
        ->and($kept->attempts[0]['attempt_id'])->toBe('22222222-2222-4222-8222-222222222222')
        ->and($timeline->events[0]['summary']['reply_id'])->toBe($kept->id)
        ->and($timeline->events[0]['summary']['state'])->toBe($state)
        ->and(json_encode($timeline->events[0]['summary']))->not->toContain('Thanks');
})->with([MailResult::STATE_SENT, MailResult::STATE_CAPTURED, MailResult::STATE_FAILED, MailResult::STATE_REJECTED]);

// Spec 106, FR-028: where a reply is certain to be refused, none is sent and none is recorded.
it('sends nothing and keeps nothing where a reply would be refused, and says why', function (string $mode) {
    $gateway = submissionEmailGateway($mode);
    $replies = submissionReplyStore();
    $timeline = submissionEmailTimeline();
    $service = new SubmissionEmailService(emailWorkflowStore(), $gateway, $timeline, $replies);
    $reply = new SubmissionReply('Re: Contact', '<p>Thanks, Sam.</p>');

    expect(fn () => $service->reply(new SubmissionAccessScope(7, false, ['sales']), 31, $reply))
        ->toThrow(DomainException::class, 'Replies are not being delivered: ' . $mode)
        ->and($gateway->calls)->toBe([])
        ->and($replies->kept)->toBe([])
        ->and($timeline->events)->toBe([]);
})->with([ReplyReadiness::REFUSE, ReplyReadiness::UNAVAILABLE]);

// The email has gone by then. An error would be answered by sending it again.
it('says the reply was sent and not recorded when it cannot be written down afterwards', function () {
    $gateway = submissionEmailGateway(ReplyReadiness::DELIVER, MailResult::STATE_SENT);
    $timeline = submissionEmailTimeline();
    $full = new class() implements SubmissionReplyStore {
        public function add(int $submissionId, SubmissionReplyRecord $reply): void
        {
            throw new DomainException('The reply could not be kept with its submission.');
        }

        public function forSubmission(int $submissionId): array
        {
            return [];
        }
    };
    $service = new SubmissionEmailService(emailWorkflowStore(), $gateway, $timeline, $full);

    $sent = $service->reply(new SubmissionAccessScope(7, false, ['sales']), 31, new SubmissionReply('Re: Contact', '<p>Thanks, Sam.</p>'));

    expect($sent->result->state)->toBe(MailResult::STATE_SENT)
        ->and($sent->recorded)->toBeFalse()
        ->and($gateway->calls)->toHaveCount(1);
});

it('keeps no reply for a submission the person cannot reach', function () {
    $gateway = submissionEmailGateway();
    $replies = submissionReplyStore();
    $service = new SubmissionEmailService(emailWorkflowStore(), $gateway, submissionEmailTimeline(), $replies);
    $reply = new SubmissionReply('Re: Contact', '<p>Thanks, Sam.</p>');

    expect(fn () => $service->reply(new SubmissionAccessScope(9, false, ['support']), 31, $reply))
        ->toThrow(DomainException::class)
        ->and($gateway->calls)->toBe([])
        ->and($replies->kept)->toBe([]);
});

// Spec 106, FR-005: read before it is sent. Nothing leaves, and nothing is written down.
it('previews the email for the address a reply would go to, sending and keeping nothing', function () {
    $gateway = submissionEmailGateway();
    $replies = submissionReplyStore();
    $timeline = submissionEmailTimeline();
    $service = new SubmissionEmailService(emailWorkflowStore(), $gateway, $timeline, $replies);

    $preview = $service->preview(new SubmissionAccessScope(7, false, ['sales']), 31, new SubmissionReply('Re: Contact', '<p>Thanks, Sam.</p>'));

    expect($preview)->toBe(['html' => '<html><body><p>Thanks, Sam.</p></body></html>', 'text' => 'Thanks, Sam.'])
        ->and($gateway->calls)->toBe([['preview', 'sam@example.com', 'Re: Contact', '<p>Thanks, Sam.</p>']])
        ->and($replies->kept)->toBe([])
        ->and($timeline->events)->toBe([]);
});

it('previews nothing of a submission the person cannot reach', function () {
    $gateway = submissionEmailGateway();
    $service = new SubmissionEmailService(emailWorkflowStore(), $gateway, submissionEmailTimeline(), submissionReplyStore());

    expect(fn () => $service->preview(new SubmissionAccessScope(9, false, ['support']), 31, new SubmissionReply('Re: Contact', '<p>Thanks, Sam.</p>')))
        ->toThrow(DomainException::class)
        ->and($gateway->calls)->toBe([]);
});

it('previews nothing where there is no address a reply could go to', function () {
    $gateway = submissionEmailGateway();
    $service = new SubmissionEmailService(emailWorkflowStore('not an address'), $gateway, submissionEmailTimeline(), submissionReplyStore());

    expect(fn () => $service->preview(new SubmissionAccessScope(7, false, ['sales']), 31, new SubmissionReply('Re: Contact', '<p>Thanks, Sam.</p>')))
        ->toThrow(DomainException::class, 'replyable email address')
        ->and($gateway->calls)->toBe([]);
});
