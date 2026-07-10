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
use Corex\Mail\AttemptingMailer;
use Corex\Mail\MailRequest;
use Corex\Mail\MailResult;
use Corex\Mail\RoutedMailer;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Uuid;
use DateTimeImmutable;

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
        $this->dispatch($event);
    }

    public function dispatch(FormSubmittedEvent $event): MailResult
    {
        $context = [
            'submission' => $event->values,
            'form'       => ['slug' => $event->formSlug],
            'site'       => ['name' => get_bloginfo('name')],
        ];

        if ($this->container->has(RoutedMailer::class)) {
            $routed = $this->container->make(RoutedMailer::class)->dispatch(
                'forms.' . $event->formSlug . '.submitted',
                $context,
            );

            if ($routed !== null) {
                return $routed;
            }
        }

        $recipient = (string) $this->config->get('forms.email.recipient', '');

        if ($recipient === '') {
            $recipient = (string) get_option('admin_email');
        }

        $request = new MailRequest(
                to: [$recipient],
                templateName: 'contact-notification',
                context: $context,
            );

        if ($this->container->has(Mailer::class)) {
            $mailer = $this->container->make(Mailer::class);

            if ($mailer instanceof AttemptingMailer) {
                return $mailer->attempt($request);
            }

            $mailer->send($request);

            return new MailResult(
                attemptId: Uuid::v4(),
                requestId: $request->requestId,
                state: MailResult::STATE_ACCEPTED,
                provider: 'legacy-mailer',
                message: __('The configured mailer accepted the submission notification without a delivery result.', 'corex'),
                occurredAt: new DateTimeImmutable('now'),
                retryable: false,
            );
        }

        $subject = sprintf(
            /* translators: %s: form slug */
            __('New "%s" form submission', 'corex'),
            $event->formSlug
        );

        $sent = wp_mail($recipient, $subject, $this->body($event->values));

        return new MailResult(
            attemptId: Uuid::v4(),
            requestId: $request->requestId,
            state: $sent ? MailResult::STATE_SENT : MailResult::STATE_FAILED,
            provider: 'wp-mail',
            message: $sent
                ? __('WordPress accepted the submission notification.', 'corex')
                : __('WordPress rejected the submission notification.', 'corex'),
            occurredAt: new DateTimeImmutable('now'),
            retryable: ! $sent,
        );
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
