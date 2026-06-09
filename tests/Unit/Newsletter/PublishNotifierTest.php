<?php

/**
 * Unit test for the on-publish notifier (spec 013 US3: FR-005, SC-004).
 *
 * @package Corex\Tests\Unit\Newsletter
 */

declare(strict_types=1);

use Corex\Newsletter\Subscriber\SubscriberStore;
use Corex\Newsletter\Subscription\PublishNotifier;
use Corex\Newsletter\Subscription\SubscriptionService;
use Corex\Newsletter\TokenSigner;

it('emails exactly the confirmed subscribers whose topics intersect the post', function () {
    $store = new class implements SubscriberStore {
        public function findByEmail(string $email): ?array
        {
            return null;
        }

        public function create(string $email, array $topics): int
        {
            return 0;
        }

        public function setStatus(int $id, string $status): void
        {
        }

        public function confirmedForTopics(array $topics): array
        {
            // Only subscribers whose topics intersect ['news'] are returned.
            return [
                ['email' => 'a@example.com', 'topics' => ['news']],
                ['email' => 'b@example.com', 'topics' => ['news', 'events']],
            ];
        }
    };

    $mailer  = new FakeNewsletterMailer();
    $service = new SubscriptionService($store, new TokenSigner('k'), $mailer);

    $sent = (new PublishNotifier($store, $mailer, $service))->notify(['news'], 'New article', 'https://example.test/a');

    expect($sent)->toBe(2)
        ->and($mailer->sent)->toHaveCount(2)
        ->and($mailer->sent[0]->templateName)->toBe('newsletter-notify')
        ->and($mailer->sent[0]->context['unsubscribe_token'])->toBeString();
});

it('sends nothing when the post has no topics', function () {
    $store   = new ArraySubscriberStore();
    $mailer  = new FakeNewsletterMailer();
    $service = new SubscriptionService($store, new TokenSigner('k'), $mailer);

    expect((new PublishNotifier($store, $mailer, $service))->notify([], 'x', 'y'))->toBe(0)
        ->and($mailer->sent)->toBe([]);
});
