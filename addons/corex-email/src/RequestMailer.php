<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email;

defined('ABSPATH') || exit;

use Corex\Email\Message\MessageBuilder;
use Corex\Email\Recipients\RecipientResolver;
use Corex\Email\Template\TemplateRegistry;
use Corex\Email\Template\TemplateRenderer;
use Corex\Mail\MailRequest;
use Corex\Mail\AttemptingMailer;
use Corex\Mail\MailResult;
use Corex\Mail\MessageId;
use Corex\Support\Uuid;
use DateTimeImmutable;

/**
 * The corex-core Mailer seam, implemented by Corex Mail. Turns a transport-neutral
 * MailRequest into a real send through the engine — so a consumer (e.g. the Forms
 * email listener) depends only on the seam, never on this engine's types. Best-effort:
 * the underlying service catches and logs, so this never throws.
 */
final class RequestMailer implements AttemptingMailer
{
    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly TemplateRenderer $renderer,
        private readonly RecipientResolver $resolver,
        private readonly MailService $service,
        /** The host an email's id is made of: the site's own. '' sends as before. */
        private readonly string $messageHost = '',
    ) {
    }

    public function send(MailRequest $request): void
    {
        $this->attempt($request);
    }

    public function attempt(MailRequest $request): MailResult
    {
        $builder = new MessageBuilder($request->to, $this->templates, $this->renderer, $this->resolver, $this->service);

        if ($request->templateName !== null) {
            $builder->template($request->templateName)->with($request->context);
        } else {
            $builder->subject((string) $request->subject)->body((string) $request->body);
        }

        if ($request->from !== null && $request->from !== '') {
            $builder->from($request->from);
        }

        // Sent under an id made of the request's own, which the request keeps through the
        // queue: whoever made the request can say which email a reply answers (spec 106,
        // FR-042), whenever it is sent.
        $builder->sentUnder(MessageId::of($request->requestId, $this->messageHost));

        foreach ($request->attachments as $attachmentId) {
            $builder->attachMedia($attachmentId);
        }

        if ($request->replyTo !== null && $request->replyTo !== '') {
            $builder->replyTo($request->replyTo);
        }

        $result = $builder->send();

        return new MailResult(
            attemptId: Uuid::v4(),
            requestId: $request->requestId,
            state: $result->status,
            provider: 'corex-mail',
            message: $result->message,
            occurredAt: new DateTimeImmutable('now'),
            retryable: $result->status === MailResult::STATE_FAILED,
            logId: $result->logId,
            parentAttemptId: $request->parentAttemptId,
        );
    }
}
