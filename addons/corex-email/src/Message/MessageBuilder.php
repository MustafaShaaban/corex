<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Message;

defined('ABSPATH') || exit;

use Corex\Email\EmailResult;
use Corex\Email\MailService;
use Corex\Email\Template\MailContext;
use Corex\Email\Template\TemplateRegistry;
use Corex\Email\Template\TemplateRenderer;

/**
 * The fluent API behind the Mail facade. Collects recipients and either a template
 * + context or an ad-hoc subject + body, then `build()`s an immutable EmailMessage
 * (rendering the template through the renderer). `send()` hands the message to the
 * MailService. `build()` is pure and independently testable.
 */
final class MessageBuilder
{
    /** @var list<string> */
    private array $to;
    /** @var list<string> */
    private array $cc = [];
    /** @var list<string> */
    private array $bcc = [];
    private ?string $replyTo = null;
    private ?string $subject = null;
    private ?string $body = null;
    private ?string $templateName = null;
    /** @var array<string,mixed> */
    private array $context = [];

    /**
     * @param string|list<string> $to
     */
    public function __construct(
        string|array $to,
        private readonly TemplateRegistry $templates,
        private readonly TemplateRenderer $renderer,
        private readonly MailService $service,
    ) {
        $this->to = array_values((array) $to);
    }

    /**
     * @param string|list<string> $cc
     */
    public function cc(string|array $cc): self
    {
        $this->cc = array_merge($this->cc, array_values((array) $cc));

        return $this;
    }

    /**
     * @param string|list<string> $bcc
     */
    public function bcc(string|array $bcc): self
    {
        $this->bcc = array_merge($this->bcc, array_values((array) $bcc));

        return $this;
    }

    public function replyTo(string $address): self
    {
        $this->replyTo = $address;

        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function body(string $html): self
    {
        $this->body = $html;

        return $this;
    }

    public function template(string $name): self
    {
        $this->templateName = $name;

        return $this;
    }

    /**
     * @param array<string,mixed> $context
     */
    public function with(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function build(): EmailMessage
    {
        if ($this->templateName !== null) {
            $template = $this->templates->find($this->templateName);

            if ($template !== null) {
                $rendered = $this->renderer->render($template, new MailContext($this->context));

                return $this->message($rendered->subject, $rendered->body);
            }

            // Unknown template → empty message; the service rejects + logs it (non-fatal).
            return $this->message('', '');
        }

        return $this->message((string) $this->subject, (string) $this->body);
    }

    public function send(): EmailResult
    {
        return $this->service->deliver($this->build());
    }

    private function message(string $subject, string $body): EmailMessage
    {
        return new EmailMessage($this->to, $this->cc, $this->bcc, $this->replyTo, $subject, $body);
    }
}
