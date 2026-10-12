<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Mail\MailRequest;
use Corex\Mail\MessageId;
use Corex\Mail\ReplyConversation;
use Corex\Mail\SubmissionEmailGateway;

/**
 * The conversation a reply to a submission can be sent in: the emails its sender was already
 * sent about it, in order (spec 106, FR-038).
 *
 * Read from what is already kept: what a site said it sent itself, the emails a flow sent and
 * bound to the submission, and each reply's sendings. Which of a flow's emails and of the
 * replies the person really has, and under which id, is the email add-on's to say.
 *
 * A site that sends its own confirmation through CoreX's mailer tells CoreX so, once, where it
 * sends it, with the request it sends:
 *
 *     $request = new MailRequest(to: [$email], subject: $subject, body: $html);
 *     $mailer->send($request);
 *     $conversation->began($submissionId, $request);
 *
 * Replies to that submission can then be sent under that confirmation. The request is what
 * is recorded, and not what became of it: the email may be waiting in a queue, and it leaves
 * under an id made of the request's own whenever it does.
 */
final readonly class SubmissionConversation
{
    /**
     * @param string $host The site's host, which an email's id is made of.
     */
    public function __construct(
        private SubmissionReplyStore $replies,
        private SubmissionConversationStore $own,
        private SubmissionEmailGateway $email,
        private SubmissionWorkflowStore $submissions,
        private string $host,
    ) {
    }

    /**
     * @param array<string,mixed> $record A submission, as the inbox reads it.
     */
    public function of(array $record): ReplyConversation
    {
        $recipient = (string) ($record['submitter_email'] ?? '');
        $submissionId = (int) ($record['id'] ?? 0);
        if ($submissionId < 1 || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return new ReplyConversation();
        }
        // What the site sent the person itself came first: it is what they wrote in to get.
        $own = $this->own->emailsOf($submissionId);
        $since = $this->email->conversation($recipient, [...$this->bound($record), ...$this->replied($submissionId)]);
        $latestOwn = $own === [] ? '' : $own[count($own) - 1]['subject'];

        return new ReplyConversation(
            [...array_column($own, 'message_id'), ...$since->messageIds],
            $since->isEmpty() ? $latestOwn : $since->subject,
        );
    }

    /**
     * Records an email a site sends the submission's sender itself, so that replies continue
     * from it.
     *
     * Only an email that goes to the person who sent the submission: a site's listener sends
     * the team's notice from the same place, and a reply to a customer must not answer, or be
     * headed by the subject of, an email they were never sent. Nothing is recorded where the
     * site has no host to make an id of.
     */
    public function began(int $submissionId, MailRequest $request): void
    {
        $sender = strtolower(trim((string) ($this->submissions->findWorkflow($submissionId)['submitter_email'] ?? '')));
        $goesToThem = $sender !== '' && in_array($sender, array_map(
            static fn (mixed $address): string => is_string($address) ? strtolower(trim($address)) : '',
            $request->to,
        ), true);
        $messageId = MessageId::of($request->requestId, $this->host);
        if ($goesToThem && $messageId !== '') {
            $this->own->add($submissionId, $messageId, (string) ($request->subject ?? ''));
        }
    }

    /**
     * The emails a flow sent for this submission. To whom is not said here: the gateway keeps
     * only those that went to the person.
     *
     * @param array<string,mixed> $record
     *
     * @return list<string>
     */
    private function bound(array $record): array
    {
        $bindings = $record['related_emails']['bindings'] ?? [];
        $attempts = [];
        foreach (is_array($bindings) ? $bindings : [] as $binding) {
            if (is_array($binding) && is_string($binding['attempt_id'] ?? null)) {
                $attempts[] = $binding['attempt_id'];
            }
        }

        return $attempts;
    }

    /** @return list<string> Every sending of every reply. */
    private function replied(int $submissionId): array
    {
        $attempts = [];
        foreach ($this->replies->forSubmission($submissionId) as $reply) {
            foreach ($reply->attempts as $attempt) {
                $attempts[] = $attempt['attempt_id'];
            }
        }

        return $attempts;
    }
}
