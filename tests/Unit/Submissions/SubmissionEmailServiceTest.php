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
use Corex\Mail\UnavailableSubmissionEmailGateway;
use Corex\Mail\MailRequest;
use Corex\Mail\ReplyReadiness;
use Corex\Mail\SubmissionEmailGateway;
use Corex\Config\Submissions\ReplySubject;
use Corex\Config\Submissions\SubmissionConversation;
use Corex\Config\Submissions\SubmissionConversationStore;
use Corex\Mail\ReplyConversation;

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

        /** @var list<\Corex\Mail\ReplyConversation|null> What each reply was sent under. */
        public array $within = [];

        /** @var list<array{0:string,1:list<string>}> What a conversation was asked for. */
        public array $asked = [];

        public function conversation(string $recipient, array $attemptIds): \Corex\Mail\ReplyConversation
        {
            $this->asked[] = [$recipient, $attemptIds];

            // Every attempt named is taken to be an email this person has.
            return new \Corex\Mail\ReplyConversation(
                array_map(static fn (string $id): string => '<' . $id . '@acme.test>', $attemptIds),
                'We received your enquiry',
            );
        }

        public function __construct(private readonly string $mode, private readonly string $state)
        {
        }

        public function readiness(): ReplyReadiness
        {
            $sendable = in_array($this->mode, [ReplyReadiness::DELIVER, ReplyReadiness::CAPTURE], true);

            return new ReplyReadiness($this->mode, 'a_code', $sendable ? '' : 'Replies are not being delivered: ' . $this->mode);
        }

        public function reply(string $recipient, string $subject, string $htmlBody, ?\Corex\Mail\ReplyConversation $within = null): MailResult
        {
            $this->calls[] = ['reply', $recipient, $subject, $htmlBody];
            $this->within[] = $within;

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

// Spec 106, FR-038 to FR-042: a reply is sent in the conversation the person already has, or
// as a new email. The email add-on is the fake above: what is held here is which emails a
// submission says it knows of, and what a reply is then sent under and recorded as.
/** The email a flow sent for submission 31 and bound to it. Whom it went to is the gateway's to say. */
const A_FLOW_EMAIL = '11111111-1111-4111-8111-111111111111';
const EARLIER_REPLY = '66666666-6666-4666-8666-666666666666';

function ownConversationStore(): SubmissionConversationStore
{
    return new class() implements SubmissionConversationStore {
        /** @var array<int,list<array{message_id:string,subject:string}>> */
        public array $kept = [];

        public function emailsOf(int $submissionId): array
        {
            return $this->kept[$submissionId] ?? [];
        }

        public function add(int $submissionId, string $messageId, string $subject): void
        {
            $this->kept[$submissionId][] = ['message_id' => $messageId, 'subject' => $subject];
        }
    };
}

/** The conversation of the fakes above, on a site at acme.test, where submission 31 is Sam's. */
function conversationOn(SubmissionReplyStore $replies, SubmissionConversationStore $own, SubmissionEmailGateway $gateway, string $host = 'acme.test'): SubmissionConversation
{
    return new SubmissionConversation($replies, $own, $gateway, emailWorkflowStore(), $host);
}

it('reads a submission\'s conversation from what the site said it sent, the emails a flow sent, and each reply', function () {
    $gateway = submissionEmailGateway();
    $replies = submissionReplyStore();
    $replies->add(31, new SubmissionReplyRecord('reply-1', 'Re: Contact', '<p>Hello</p>', 7, '2026-10-12T12:00:00+00:00', [
        ['attempt_id' => EARLIER_REPLY, 'state' => 'sent', 'message' => '', 'occurred_at' => '2026-10-12T12:00:00+00:00'],
    ]));
    $own = ownConversationStore();
    $own->add(31, '<ownconfirmation@acme.test>', 'We have your request');

    $conversation = conversationOn($replies, $own, $gateway)->of(emailWorkflowRecord());

    // The email add-on is asked about the flow's emails and the replies, for the person who
    // wrote in. What the site sent itself is the site's word, and came first.
    expect($gateway->asked)->toBe([['sam@example.com', [A_FLOW_EMAIL, EARLIER_REPLY]]])
        ->and($conversation->messageIds)->toBe([
            '<ownconfirmation@acme.test>',
            '<' . A_FLOW_EMAIL . '@acme.test>',
            '<' . EARLIER_REPLY . '@acme.test>',
        ])
        ->and($conversation->latest())->toBe('<' . EARLIER_REPLY . '@acme.test>')
        ->and($conversation->subject)->toBe('We received your enquiry');
});

it('has no conversation for a submission with nobody to write to', function (array $record) {
    $gateway = submissionEmailGateway();

    $conversation = conversationOn(submissionReplyStore(), ownConversationStore(), $gateway)->of($record);

    expect($conversation->isEmpty())->toBeTrue()
        ->and($gateway->asked)->toBe([]);
})->with([
    'no address' => [['id' => 31, 'submitter_email' => '']],
    'something that is not one' => [['id' => 31, 'submitter_email' => 'not an address']],
    'no submission' => [['submitter_email' => 'sam@example.com']],
]);

// FR-042: a site that sends its own confirmation says so, where it sends it, with the request
// it sends. The email leaves under an id made of the request's own, whenever it leaves.
it('records the email a site sends itself by the id it will leave under, once', function () {
    $own = ownConversationStore();
    $conversation = conversationOn(submissionReplyStore(), $own, new UnavailableSubmissionEmailGateway());
    $request = new MailRequest(to: ['sam@example.com'], subject: 'We have your request', body: '<p>Thank you</p>', requestId: '55555555-6666-4777-8888-999999999999');

    $conversation->began(31, $request);
    $continued = $conversation->of(emailWorkflowRecord());

    expect($own->emailsOf(31))->toBe([['message_id' => '<55555555666647778888@acme.test>', 'subject' => 'We have your request']])
        // With nothing else sent, it is the conversation, and its subject is what a reply answers.
        ->and($continued->messageIds)->toBe(['<55555555666647778888@acme.test>'])
        ->and($continued->subject)->toBe('We have your request');
});

// A site's listener sends the team's notice from the same place as the person's confirmation.
// A reply to the person must not answer an email they were never sent, or start from its subject.
it('records only an email that goes to the person who sent the submission', function (array $to, int $submissionId) {
    $own = ownConversationStore();
    $conversation = conversationOn(submissionReplyStore(), $own, new UnavailableSubmissionEmailGateway());

    $conversation->began($submissionId, new MailRequest(to: $to, subject: 'New enquiry from Sam', body: '<p>Sam wrote in</p>'));

    expect($own->kept)->toBe([])
        ->and($conversation->of(emailWorkflowRecord())->isEmpty())->toBeTrue();
})->with([
    'the team\'s notice' => [['sales@acme.test'], 31],
    'nobody' => [[], 31],
    'a submission there is none of' => [['sam@example.com'], 404],
]);

it('records an email to the person however their address was typed, among others it went to', function () {
    $own = ownConversationStore();

    conversationOn(submissionReplyStore(), $own, new UnavailableSubmissionEmailGateway())
        ->began(31, new MailRequest(to: ['sales@acme.test', ' Sam@Example.com '], subject: 'We have your request', body: '<p>Thank you</p>'));

    expect($own->emailsOf(31))->toHaveCount(1);
});

it('records nothing for a site with no host to make an id of', function () {
    $own = ownConversationStore();

    conversationOn(submissionReplyStore(), $own, submissionEmailGateway(), '')->began(31, new MailRequest(to: ['sam@example.com'], subject: 'Hello', body: '<p>Hi</p>'));

    expect($own->emailsOf(31))->toBe([]);
});

it('sends a reply under the emails the person has when that is asked for, and records it so', function () {
    $gateway = submissionEmailGateway(ReplyReadiness::DELIVER, MailResult::STATE_SENT);
    $replies = submissionReplyStore();
    $service = new SubmissionEmailService(
        emailWorkflowStore(),
        $gateway,
        submissionEmailTimeline(),
        $replies,
        null,
        conversationOn($replies, ownConversationStore(), $gateway),
    );
    $scope = new SubmissionAccessScope(7, false, ['sales']);
    $reply = new SubmissionReply('Re: We received your enquiry', '<p>Hello Sam</p>');

    $service->reply($scope, 31, $reply, inConversation: true);
    $service->reply($scope, 31, $reply);

    expect($gateway->within[0]->latest())->toBe('<' . A_FLOW_EMAIL . '@acme.test>')
        // The second was asked for as a new email, and names nothing.
        ->and($gateway->within[1])->toBeNull()
        ->and(array_map(static fn (SubmissionReplyRecord $kept): string => $kept->toArray()['thread'], $replies->forSubmission(31)))
        ->toBe(['conversation', 'new']);
});

it('sends a new email, and records a new email, where there is nothing to continue', function () {
    $gateway = submissionEmailGateway(ReplyReadiness::DELIVER, MailResult::STATE_SENT);
    $replies = submissionReplyStore();
    // A submission that names no email of its own, with nobody to say which there were.
    $service = new SubmissionEmailService(emailWorkflowStore(), $gateway, submissionEmailTimeline(), $replies);

    $service->reply(new SubmissionAccessScope(7, false, ['sales']), 31, new SubmissionReply('Re: Contact', '<p>Hello</p>'), inConversation: true);

    expect($gateway->within[0])->toBeNull()
        ->and($replies->forSubmission(31)[0]->toArray()['thread'])->toBe('new');
});

it('reads a reply kept before there was a choice as a new email', function () {
    $kept = SubmissionReplyRecord::fromArray(['id' => 'reply-1', 'subject' => 'Re: Contact', 'body_html' => '<p>Hello</p>', 'attempts' => []]);

    expect($kept->inConversation)->toBeFalse()
        ->and($kept->toArray()['thread'])->toBe('new');
});

it('answers an email\'s subject once', function (string $subject, string $answer) {
    Functions\when('__')->returnArg();

    expect(ReplySubject::answering($subject))->toBe($answer);
})->with([
    'a subject' => ['We received your enquiry', 'Re: We received your enquiry'],
    'one that answers already' => ['Re: We received your enquiry', 'Re: We received your enquiry'],
    'in capitals' => ['RE: Offer', 'RE: Offer'],
    'none' => ['', ''],
    'with a line break in it' => ["Offer\r\nBcc: x@example.com", 'Re: Offer Bcc: x@example.com'],
]);

it('keeps what an empty conversation would have been out of a header', function () {
    expect((new ReplyConversation())->latest())->toBe('')
        ->and((new ReplyConversation(['not an id']))->isEmpty())->toBeTrue();
});
