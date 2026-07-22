<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Mail\MailResult;
use Corex\Mail\RoutedMailer;
use Corex\Mail\TemplateMailer;

/**
 * Optional Email Studio adapter for versioned flow notification bindings.
 */
final readonly class FlowEmailSender
{
    public function __construct(
        private ?RoutedMailer $mailer,
        private FlowEmailAddressResolver $addresses,
    ) {
    }

    /**
     * @param array<string,mixed> $binding
     * @param array<string,mixed> $context
     */
    /**
     * Whether CoreX Mail's routed transport is available. When false, the flow has no Email Studio
     * to route through and the pipeline falls back to the wp_mail() floor instead (FR-017).
     */
    public function canRoute(): bool
    {
        return $this->mailer !== null;
    }

    public function send(array $binding, string $flowSlug, array $context): ?MailResult
    {
        if ($this->mailer === null) {
            return null;
        }
        $event = (string) ($binding['event'] ?? 'submitted');
        if ($this->mailer instanceof TemplateMailer && (int) ($binding['template_id'] ?? 0) > 0) {
            $recipients = $this->addresses->recipients((string) ($binding['recipient'] ?? ''), $context);
            if ($recipients === []) {
                return null;
            }

            return $this->mailer->dispatchTemplate(
                (int) $binding['template_id'],
                $recipients,
                $this->addresses->replyTo((string) ($binding['reply_to'] ?? ''), $context),
                $context,
            );
        }

        return $this->mailer->dispatch('forms.' . $flowSlug . '.' . $event, $context);
    }
}
