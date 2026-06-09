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
use Corex\Newsletter\TokenSigner;

/**
 * The subscription lifecycle: double opt-in subscribe → confirm → unsubscribe. Pure
 * orchestration over an injected store + the Mailer seam + the token signer. Consent
 * is required; tokens are signed and fail-closed; there is no email enumeration.
 */
final class SubscriptionService
{
    public const PENDING      = 'pending';
    public const CONFIRMED    = 'confirmed';
    public const UNSUBSCRIBED = 'unsubscribed';

    public function __construct(
        private readonly SubscriberStore $store,
        private readonly TokenSigner $signer,
        private readonly Mailer $mailer,
    ) {
    }

    /**
     * @param list<string> $topics
     *
     * @return bool whether the request was accepted (a confirmation is sent for new/pending)
     */
    public function subscribe(string $email, array $topics, bool $consent): bool
    {
        if (! $consent) {
            return false;
        }

        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $existing = $this->store->findByEmail($email);

        if ($existing !== null && $existing['status'] === self::CONFIRMED) {
            return true; // already subscribed — no duplicate, no enumeration
        }

        if ($existing === null) {
            $this->store->create($email, $topics);
        } elseif ($existing['status'] !== self::PENDING) {
            $this->store->setStatus($existing['id'], self::PENDING);
        }

        $this->mailer->send(new MailRequest(
            to: [$email],
            templateName: 'newsletter-confirm',
            context: ['email' => $email, 'confirm_token' => $this->signer->sign('confirm:' . $email)],
        ));

        return true;
    }

    public function confirm(string $token): bool
    {
        $email = $this->emailFrom($token, 'confirm:');
        if ($email === null) {
            return false;
        }

        $subscriber = $this->store->findByEmail($email);
        if ($subscriber === null || $subscriber['status'] !== self::PENDING) {
            return false;
        }

        $this->store->setStatus($subscriber['id'], self::CONFIRMED);

        return true;
    }

    public function unsubscribe(string $token): bool
    {
        $email = $this->emailFrom($token, 'unsub:');
        if ($email === null) {
            return false;
        }

        $subscriber = $this->store->findByEmail($email);
        if ($subscriber === null) {
            return false;
        }

        $this->store->setStatus($subscriber['id'], self::UNSUBSCRIBED);

        return true;
    }

    public function unsubscribeToken(string $email): string
    {
        return $this->signer->sign('unsub:' . $email);
    }

    private function emailFrom(string $token, string $prefix): ?string
    {
        $payload = $this->signer->verify($token);

        if ($payload === null || ! str_starts_with($payload, $prefix)) {
            return null;
        }

        return substr($payload, strlen($prefix));
    }
}
