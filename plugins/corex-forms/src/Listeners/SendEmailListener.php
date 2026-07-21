<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Listeners;

defined('ABSPATH') || exit;

use Corex\Forms\Submission\NotificationDelivery;
use Corex\Forms\Submission\NotificationDispatcher;
use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Mail\MailRequest;
use Corex\Support\Config\ConfigInterface;

/**
 * Emails a legacy (registered, non-flow) submission to the configured recipient
 * (`forms.email.recipient`, falling back to the site admin).
 *
 * The transport ladder — CoreX Mail's router, a bound mailer, then the wp_mail() floor — lives in
 * the shared {@see NotificationDispatcher}, so this listener no longer carries its own copy (and no
 * longer mislabels wp_mail acceptance as "sent"). No hard dependency on CoreX Mail either way
 * (Principle IX).
 */
final class SendEmailListener
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly ConfigInterface $config,
    ) {
    }

    public function __invoke(FormSubmittedEvent $event): void
    {
        $this->dispatch($event);
    }

    public function dispatch(FormSubmittedEvent $event): NotificationDelivery
    {
        $context = [
            'submission' => $event->values,
            'form'       => ['slug' => $event->formSlug],
            'site'       => ['name' => get_bloginfo('name')],
        ];

        $recipient = (string) $this->config->get('forms.email.recipient', '');
        if ($recipient === '') {
            $recipient = (string) get_option('admin_email');
        }

        $request = new MailRequest(
            to: $recipient !== '' ? [$recipient] : [],
            templateName: 'contact-notification',
            context: $context,
            subject: sprintf(
                /* translators: %s: form slug */
                __('New "%s" form submission', 'corex'),
                $event->formSlug,
            ),
            body: NotificationDispatcher::plainTextBody($event->values),
        );

        return $this->dispatcher->dispatch('forms.' . $event->formSlug . '.submitted', $context, $request);
    }
}
