<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Message;

defined('ABSPATH') || exit;

use Corex\Mail\MessageId;
use Corex\Email\EmailResult;
use Corex\Email\MailService;
use Corex\Email\Recipients\RecipientResolver;
use Corex\Email\Template\MailContext;
use Corex\Email\Template\TemplateRegistry;
use Corex\Email\Template\TemplateRenderer;

/**
 * The fluent API behind the Mail facade. Collects recipients (fixed/role/dynamic)
 * and either a template + context or an ad-hoc subject + body, then `build()`s an
 * immutable EmailMessage — resolving recipients through the RecipientResolver and
 * rendering the template through the renderer. `send()` hands the message to the
 * MailService. `build()` is pure and independently testable.
 */
final class MessageBuilder
{
    /** @var list<array{type:string,value:string}> */
    private array $recipients = [];
    /** @var list<string> */
    private array $cc = [];
    /** @var list<string> */
    private array $bcc = [];
    private ?string $replyTo = null;

    private ?string $from = null;

    private string $messageId = '';

    /** @var list<int> */
    private array $attachments = [];
    private ?string $subject = null;
    private ?string $body = null;
    private ?string $plainText = null;
    private ?string $templateName = null;
    /** @var array<string,mixed> */
    private array $context = [];

    /**
     * @param string|list<string> $to fixed recipient address(es)
     */
    public function __construct(
        string|array $to,
        private readonly TemplateRegistry $templates,
        private readonly TemplateRenderer $renderer,
        private readonly RecipientResolver $resolver,
        private readonly MailService $service,
    ) {
        foreach (array_values((array) $to) as $address) {
            $this->recipients[] = ['type' => 'fixed', 'value' => $address];
        }
    }

    public function toRole(string $role): self
    {
        $this->recipients[] = ['type' => 'role', 'value' => $role];

        return $this;
    }

    public function toDynamic(string $contextPath): self
    {
        $this->recipients[] = ['type' => 'dynamic', 'value' => $contextPath];

        return $this;
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

    /**
     * Send this message from a specific mailbox rather than the configured default (#150).
     *
     * The address only — the display name stays configured, because the name is the brand and does
     * not change with the mailbox.
     */
    public function from(string $address): self
    {
        $this->from = $address;

        return $this;
    }

    /**
     * Attach a file from the media library by id (spec 081).
     *
     * Named `attachMedia()` because `../../../../docs/internal/COREX-EMAIL-ADDON.md` has documented that name since the
     * add-on was specified. `attach()` and `attachGenerated()` are documented too and are
     * deliberately absent: `attach()` implied a path, and a framework-generated file has no id
     * until something stores it — at which point this method is what you want.
     */
    public function attachMedia(int $attachmentId): self
    {
        if ($attachmentId > 0) {
            $this->attachments[] = $attachmentId;
        }

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

    /**
     * The same words as plain text, for a message given its subject and body here (spec 120,
     * story 7). A template makes its own.
     */
    public function plainText(string $text): self
    {
        $this->plainText = trim($text) === '' ? null : $text;

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

    /**
     * The id this email is sent under, so that a later one can say it answers it (spec 106,
     * FR-042). Anything that is not an id is passed over.
     */
    public function sentUnder(string $messageId): self
    {
        $this->messageId = MessageId::isOne($messageId) ? $messageId : '';

        return $this;
    }

    public function build(): EmailMessage
    {
        if ($this->templateName !== null) {
            $template = $this->templates->find($this->templateName);

            if ($template !== null) {
                $rendered = $this->renderer->render($template, new MailContext($this->context));

                return $this->message($rendered->subject, $rendered->body, $rendered->plainText);
            }

            // Unknown template → empty message; the service rejects + logs it (non-fatal).
            return $this->message('', '');
        }

        return $this->message((string) $this->subject, (string) $this->body, $this->plainText);
    }

    public function send(): EmailResult
    {
        return $this->service->deliver($this->build());
    }

    private function message(string $subject, string $body, ?string $plainText = null): EmailMessage
    {
        $to = $this->resolver->resolve($this->recipients, new MailContext($this->context))['valid'];

        return new EmailMessage(
            $to,
            $this->cc,
            $this->bcc,
            $this->replyTo,
            $subject,
            $body,
            $this->messageId === '' ? [] : ['Message-ID' => $this->messageId],
            $this->from,
            $this->attachments,
            $plainText,
        );
    }
}
