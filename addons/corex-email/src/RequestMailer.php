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
use Corex\Mail\Mailer;

/**
 * The corex-core Mailer seam, implemented by Corex Mail. Turns a transport-neutral
 * MailRequest into a real send through the engine — so a consumer (e.g. the Forms
 * email listener) depends only on the seam, never on this engine's types. Best-effort:
 * the underlying service catches and logs, so this never throws.
 */
final class RequestMailer implements Mailer
{
    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly TemplateRenderer $renderer,
        private readonly RecipientResolver $resolver,
        private readonly MailService $service,
    ) {
    }

    public function send(MailRequest $request): void
    {
        $builder = new MessageBuilder($request->to, $this->templates, $this->renderer, $this->resolver, $this->service);

        if ($request->templateName !== null) {
            $builder->template($request->templateName)->with($request->context);
        } else {
            $builder->subject((string) $request->subject)->body((string) $request->body);
        }

        if ($request->replyTo !== null && $request->replyTo !== '') {
            $builder->replyTo($request->replyTo);
        }

        $builder->send();
    }
}
