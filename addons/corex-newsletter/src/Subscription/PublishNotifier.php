<?php

/**
 * @package Corex\Newsletter
 */

declare(strict_types=1);

namespace Corex\Newsletter\Subscription;

defined('ABSPATH') || exit;

use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Newsletter\Subscriber\SubscriberStore;

/**
 * Emails the confirmed, non-suppressed subscribers whose topics intersect a
 * published post's topics — once each, through the Mailer seam, with a signed
 * unsubscribe token. Returns how many were sent. (Bounded pass; the mail queue is
 * the proper batcher for very large lists — deferred.)
 */
final class PublishNotifier
{
    public function __construct(
        private readonly SubscriberStore $store,
        private readonly Mailer $mailer,
        private readonly SubscriptionService $subscriptions,
    ) {
    }

    /**
     * @param list<string> $topics the published post's newsletter topics
     */
    public function notify(array $topics, string $title, string $url): int
    {
        if ($topics === []) {
            return 0;
        }

        $sent = 0;

        foreach ($this->store->confirmedForTopics($topics) as $subscriber) {
            $this->mailer->send(new MailRequest(
                to: [$subscriber['email']],
                templateName: 'newsletter-notify',
                context: [
                    'title'             => $title,
                    'url'               => $url,
                    'unsubscribe_token' => $this->subscriptions->unsubscribeToken($subscriber['email']),
                ],
            ));

            $sent++;
        }

        return $sent;
    }
}
