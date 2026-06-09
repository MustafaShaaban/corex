<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Listeners;

defined('ABSPATH') || exit;

use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Support\Config\ConfigInterface;

/**
 * Emails the submission to the configured recipient (`forms.email.recipient`,
 * falling back to the site admin). The dispatcher isolates and logs any failure.
 */
final class SendEmailListener
{
    public function __construct(private readonly ConfigInterface $config)
    {
    }

    public function __invoke(FormSubmittedEvent $event): void
    {
        $recipient = (string) $this->config->get('forms.email.recipient', '');

        if ($recipient === '') {
            $recipient = (string) get_option('admin_email');
        }

        $subject = sprintf(
            /* translators: %s: form slug */
            __('New "%s" form submission', 'corex'),
            $event->formSlug
        );

        wp_mail($recipient, $subject, $this->body($event->values));
    }

    /**
     * @param array<string,mixed> $values
     */
    private function body(array $values): string
    {
        $lines = [];

        foreach ($values as $name => $value) {
            $lines[] = sprintf('%s: %s', $name, is_array($value) ? wp_json_encode($value) : (string) $value);
        }

        return implode("\n", $lines);
    }
}
