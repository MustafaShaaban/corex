<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Listeners;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Support\Config\ConfigInterface;

/**
 * Emails the submission to the configured recipient (`forms.email.recipient`,
 * falling back to the site admin). When the Corex Mail engine is active (the Mailer
 * seam is bound), the notification is delivered as a templated, logged email;
 * otherwise a basic `wp_mail` fallback is used — no hard dependency either way
 * (Principle IX). The dispatcher isolates and logs any failure.
 */
final class SendEmailListener
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ConfigInterface $config,
    ) {
    }

    public function __invoke(FormSubmittedEvent $event): void
    {
        $recipient = (string) $this->config->get('forms.email.recipient', '');

        if ($recipient === '') {
            $recipient = (string) get_option('admin_email');
        }

        if ($this->container->has(Mailer::class)) {
            $this->container->make(Mailer::class)->send(new MailRequest(
                to: [$recipient],
                templateName: 'contact-notification',
                context: [
                    'submission' => $event->values,
                    'form'       => ['slug' => $event->formSlug],
                    'site'       => ['name' => get_bloginfo('name')],
                ],
            ));

            return;
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
