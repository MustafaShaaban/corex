<?php

/**
 * Resending an email from the inbox reaches the people it was for (spec 112, User Story 7,
 * FR-032 to FR-034, SC-008).
 *
 * The recipients an email was sent to are recorded on the submission when it is sent; a resend
 * goes to them. An email sent before they were recorded is resent only when the flow's recipients,
 * worked out again, are one address that the email's own attempt confirms. Anything else is not
 * resent, and says why.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionEmailService;
use Corex\Config\Submissions\SubmissionReplyStore;
use Corex\Config\Submissions\SubmissionTimelineStore;
use Corex\Config\Submissions\SubmissionWorkflowStore;
use Corex\Mail\MailResult;
use Corex\Mail\RecipientAwareSubmissionEmailGateway;
use Corex\Mail\ReplyConversation;
use Corex\Mail\ReplyReadiness;
use Corex\Mail\SubmissionEmailGateway;
use Corex\Mail\SubmissionEmailRecipients;

const RESEND_ATTEMPT = '11111111-1111-4111-8111-111111111111';

beforeEach(fn () => Functions\when('__')->returnArg());

/** @param array<string,mixed> $binding */
function resendRecord(string $event, array $binding): SubmissionWorkflowStore
{
    return new class($event, $binding) implements SubmissionWorkflowStore {
        public function __construct(private string $event, private array $binding) {}

        public function findWorkflow(int $id): ?array
        {
            return $id !== 31 ? null : [
                'id' => 31, 'owner_type' => 'team', 'owner_key' => 'sales',
                'submitter_email' => 'sam@example.com',
                'values' => ['name' => 'Sam', 'email' => 'sam@example.com'],
                'related_emails' => ['bindings' => [$this->event => $this->binding + ['attempt_id' => RESEND_ATTEMPT, 'retryable' => true]]],
            ];
        }

        public function updateWorkflow(int $id, array $changes, string $expectedUpdatedAt): array { throw new BadMethodCallException(); }
        public function addWorkflowNote(int $id, int $authorId, string $body, string $visibility): array { throw new BadMethodCallException(); }
    };
}

function resendResult(): MailResult
{
    return new MailResult(
        attemptId: '33333333-3333-4333-8333-333333333333',
        requestId: '44444444-4444-4444-8444-444444444444',
        state: MailResult::STATE_CAPTURED,
        provider: 'corex-capture',
        message: 'Captured.',
        occurredAt: new DateTimeImmutable('2026-10-21T12:00:00+00:00'),
        retryable: false,
    );
}

/** A gateway that is only the interface a client site implements. */
class ResendPlainGateway implements SubmissionEmailGateway
{
    /** @var list<string> Who each resend went to. */
    public array $sentTo = [];

    public function readiness(): ReplyReadiness { return new ReplyReadiness(ReplyReadiness::CAPTURE, 'a_code', ''); }
    public function reply(string $recipient, string $subject, string $htmlBody, ?ReplyConversation $within = null): MailResult { throw new BadMethodCallException(); }
    public function conversation(string $recipient, array $attemptIds): ReplyConversation { throw new BadMethodCallException(); }
    public function preview(string $recipient, string $subject, string $htmlBody): array { throw new BadMethodCallException(); }
    public function log(string $attemptId): ?array { return null; }

    public function resend(string $attemptId, string $recipient, array $context): MailResult
    {
        $this->sentTo[] = $recipient;

        return resendResult();
    }
}

/** Email Studio's gateway: one email to every recipient, and an attempt that can confirm an address. */
class ResendAwareGateway extends ResendPlainGateway implements RecipientAwareSubmissionEmailGateway
{
    /** @var list<list<string>> */
    public array $sentTogether = [];

    public function __construct(private array $confirmed = []) {}

    public function resendTo(string $attemptId, array $recipients, array $context): MailResult
    {
        $this->sentTogether[] = $recipients;

        return resendResult();
    }

    public function wasSentTo(string $attemptId, string $address): bool
    {
        return in_array($address, $this->confirmed, true);
    }
}

function resendRecipients(?array $resolved): SubmissionEmailRecipients
{
    return new class($resolved) implements SubmissionEmailRecipients {
        public function __construct(private ?array $resolved) {}
        public function resolve(int $submissionId, string $event): ?array { return $this->resolved; }
    };
}

function resendTimeline(): SubmissionTimelineStore
{
    return new class() implements SubmissionTimelineStore {
        public array $events = [];
        public function append(int $submissionId, string $stage, string $outcome, array $summary): array { return $this->events[] = $summary; }
        public function forSubmission(int $submissionId, bool $includeRestricted): array { return $this->events; }
    };
}

function resendService(SubmissionWorkflowStore $store, SubmissionEmailGateway $gateway, ?SubmissionEmailRecipients $recipients = null, ?SubmissionTimelineStore $timeline = null): SubmissionEmailService
{
    return new SubmissionEmailService(
        submissions: $store,
        email: $gateway,
        timeline: $timeline ?? resendTimeline(),
        replies: Mockery::mock(SubmissionReplyStore::class),
        recipients: $recipients,
    );
}

function resendScope(): SubmissionAccessScope
{
    return new SubmissionAccessScope(7, false, ['sales']);
}

it('resends a team notification to the team it was recorded as sent to, never to the submitter', function () {
    $gateway = new ResendPlainGateway();
    $timeline = resendTimeline();

    $resent = resendService(resendRecord('team_notification', ['recipients' => ['team@example.com']]), $gateway, timeline: $timeline)
        ->resend(resendScope(), 31, RESEND_ATTEMPT);

    expect($gateway->sentTo)->toBe(['team@example.com'])
        ->and($resent->recipients)->toBe(['team@example.com'])
        ->and($resent->basis)->toBe(SubmissionEmailService::BASIS_RECORDED)
        ->and($timeline->events[0]['recipients'])->toBe(['team@example.com']);
});

it('resends a confirmation to the submitter it was sent to', function () {
    $gateway = new ResendPlainGateway();

    resendService(resendRecord('submitter_confirmation', ['recipients' => ['sam@example.com']]), $gateway)
        ->resend(resendScope(), 31, RESEND_ATTEMPT);

    expect($gateway->sentTo)->toBe(['sam@example.com']);
});

it('sends one email to every recipient where the gateway can, and one each where it cannot', function () {
    $recorded = ['recipients' => ['a@example.com', 'b@example.com']];
    $aware = new ResendAwareGateway();
    $plain = new ResendPlainGateway();

    resendService(resendRecord('team_notification', $recorded), $aware)->resend(resendScope(), 31, RESEND_ATTEMPT);
    resendService(resendRecord('team_notification', $recorded), $plain)->resend(resendScope(), 31, RESEND_ATTEMPT);

    expect($aware->sentTogether)->toBe([['a@example.com', 'b@example.com']])
        ->and($aware->sentTo)->toBe([])
        ->and($plain->sentTo)->toBe(['a@example.com', 'b@example.com']);
});

it('resends an email sent before recipients were recorded when the flow\'s one recipient is confirmed by its attempt', function () {
    $gateway = new ResendAwareGateway(['team@example.com']);

    $resent = resendService(resendRecord('team_notification', []), $gateway, resendRecipients(['team@example.com']))
        ->resend(resendScope(), 31, RESEND_ATTEMPT);

    expect($gateway->sentTogether)->toBe([['team@example.com']])
        ->and($resent->basis)->toBe(SubmissionEmailService::BASIS_RECHECKED);
});

it('refuses to resend an older email whose recipients cannot be confirmed, and sends nothing', function (?array $resolved, array $confirmed, bool $aware) {
    $gateway = $aware ? new ResendAwareGateway($confirmed) : new ResendPlainGateway();

    expect(fn () => resendService(resendRecord('team_notification', []), $gateway, resendRecipients($resolved))
        ->resend(resendScope(), 31, RESEND_ATTEMPT))
        ->toThrow(DomainException::class, 'recipients')
        ->and($gateway->sentTo)->toBe([]);
})->with([
    'the flow now names someone else'       => [['new@example.com'], ['team@example.com'], true],
    'the flow now names two people'         => [['team@example.com', 'b@example.com'], ['team@example.com', 'b@example.com'], true],
    'the flow cannot be worked out again'   => [null, ['team@example.com'], true],
    'a gateway that cannot confirm anybody' => [['team@example.com'], [], false],
]);

it('says of each email what it is and whether it can be resent, and why not', function (string $event, array $binding, string $kind, bool $resendable, ?string $reason) {
    $emails = resendService(resendRecord($event, $binding), new ResendAwareGateway(), resendRecipients(null))
        ->describe(['id' => 31] + resendRecord($event, $binding)->findWorkflow(31));

    expect($emails[$event])->toMatchArray(['kind' => $kind, 'resendable' => $resendable, 'not_resendable_reason' => $reason]);
})->with([
    'a recorded team notification'    => ['team_notification', ['recipients' => ['team@example.com']], 'team_notification', true, null],
    'a recorded confirmation'         => ['submitter_confirmation', ['recipients' => ['sam@example.com']], 'confirmation', true, null],
    'a failure notice not retryable'  => ['admin_failure', ['recipients' => ['admin@example.com'], 'retryable' => false], 'failure_notice', false, 'not_retryable'],
    'an older email not confirmable'  => ['team_notification', [], 'team_notification', false, 'recipients_unknown'],
]);
