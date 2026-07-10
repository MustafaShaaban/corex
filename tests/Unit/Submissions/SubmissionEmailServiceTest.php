<?php

/**
 * Submission reply/resend/log tests for spec 068 T101 / FR-052 and FR-058.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionEmailService;
use Corex\Config\Submissions\SubmissionReply;
use Corex\Config\Submissions\SubmissionTimelineStore;
use Corex\Config\Submissions\SubmissionWorkflowStore;
use Corex\Mail\MailResult;
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

function emailWorkflowStore(): SubmissionWorkflowStore
{
    return new class() implements SubmissionWorkflowStore {
        public function findWorkflow(int $id): ?array
        {
            return $id === 31 ? emailWorkflowRecord() : null;
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

function submissionEmailGateway(): SubmissionEmailGateway
{
    return new class() implements SubmissionEmailGateway {
        public array $calls = [];

        public function reply(string $recipient, string $subject, string $htmlBody): MailResult
        {
            $this->calls[] = ['reply', $recipient, $subject, $htmlBody];

            return submissionMailResult('22222222-2222-4222-8222-222222222222');
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

function submissionMailResult(string $attemptId): MailResult
{
    return new MailResult(
        attemptId: $attemptId,
        requestId: '44444444-4444-4444-8444-444444444444',
        state: MailResult::STATE_CAPTURED,
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

it('sends a reply only to the accessible submission address and timelines the result', function () {
    $gateway = submissionEmailGateway();
    $timeline = submissionEmailTimeline();
    $service = new SubmissionEmailService(emailWorkflowStore(), $gateway, $timeline);
    $scope = new SubmissionAccessScope(7, false, ['sales']);

    $result = $service->reply($scope, 31, new SubmissionReply('Re: Contact', '<p>Thanks, Sam.</p>'));

    expect($result->state)->toBe(MailResult::STATE_CAPTURED)
        ->and($gateway->calls[0])->toBe(['reply', 'sam@example.com', 'Re: Contact', '<p>Thanks, Sam.</p>'])
        ->and($timeline->events[0]['stage'])->toBe('email')
        ->and($timeline->events[0]['summary'])->not->toHaveKey('body');
});

it('resends only an attempt already related to the accessible submission', function () {
    $gateway = submissionEmailGateway();
    $timeline = submissionEmailTimeline();
    $service = new SubmissionEmailService(emailWorkflowStore(), $gateway, $timeline);
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
    $service = new SubmissionEmailService(emailWorkflowStore(), submissionEmailGateway(), submissionEmailTimeline());
    $scope = new SubmissionAccessScope(7, false, ['sales']);

    $log = $service->log($scope, 31, '11111111-1111-4111-8111-111111111111');

    expect($log)->toMatchArray(['state' => 'captured', 'recipient' => 's***@example.com']);
});
