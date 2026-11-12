<?php

/**
 * @package Corex\Newsletter
 */

declare(strict_types=1);

namespace Corex\Newsletter\Subscription;

defined('ABSPATH') || exit;

use Corex\Mail\DesignedEmails;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Newsletter\Subscriber\SubscriberStore;
use Corex\Newsletter\Templates\NewsletterLinks;
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

    /** The Email Studio templates a subscriber is sent, where they are active (spec 120). */
    public const OPT_IN_TEMPLATE  = 'double-opt-in';
    public const WELCOME_TEMPLATE = 'newsletter-welcome';

    public function __construct(
        private readonly SubscriberStore $store,
        private readonly TokenSigner $signer,
        private readonly Mailer $mailer,
        /** Email Studio's designed emails, where CoreX Mail is active (spec 120). */
        private readonly ?DesignedEmails $designed = null,
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

        $confirmToken = $this->signer->sign('confirm:' . $email);
        // The "Double opt-in" template an owner designed, when it is active; the add-on's own
        // email otherwise (spec 120, story 4).
        $designed = $this->designed?->send(
            self::OPT_IN_TEMPLATE,
            [$email],
            $this->designedContext($email, ['action' => ['url' => NewsletterLinks::confirm($confirmToken)]]),
            __('Newsletter: confirmation', 'corex'),
        );
        if ($designed === null) {
            $this->mailer->send(new MailRequest(
                to: [$email],
                templateName: 'newsletter-confirm',
                context: [
                    'email' => $email,
                    'confirm_token' => $confirmToken,
                    'unsubscribe_token' => $this->unsubscribeToken($email),
                ],
            ));
        }

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
        // A welcome, when the "Newsletter welcome" template is active; none otherwise (spec 120).
        $this->designed?->send(
            self::WELCOME_TEMPLATE,
            [$email],
            $this->designedContext($email, []),
            __('Newsletter: welcome', 'corex'),
        );

        return true;
    }

    /**
     * What a designed newsletter email is drawn from: its links, and whatever it adds.
     *
     * @param array<string,mixed> $more
     *
     * @return array<string,mixed>
     */
    private function designedContext(string $email, array $more): array
    {
        return [
            'links' => [
                'unsubscribe' => NewsletterLinks::unsubscribe($this->unsubscribeToken($email)),
                'preferences' => NewsletterLinks::preferences($email),
            ],
            'recipient' => ['email' => $email],
            'email' => ['kind' => 'newsletter'],
        ] + $more;
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
