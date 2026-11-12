<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail\Capture;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityRepository;
use Corex\Activity\ActivityService;
use Corex\Container\ContainerInterface;
use Corex\Environment\SiteEnvironment;
use Corex\Support\Config\ConfigInterface;
use DateTimeImmutable;
use Throwable;

/**
 * Decides, for every email, whether it is sent, captured, held for a test or refused
 * (spec 113, research R3). CoreX's mail service asks it before its driver, and the
 * `pre_wp_mail` filter asks it for everything else that calls `wp_mail()`.
 *
 * Production, Coming soon and Maintenance answer "deliver" before anything else is read: the
 * owner decided that this release changes development and staging only (SAFE-D2).
 *
 * The capture store and the activity log belong to CoreX Mail and corex-config, which may be
 * inactive, so both are looked up when they are needed and only if they are there.
 */
final class MailGate
{
    public const SCOPE_ALL   = 'all';
    public const SCOPE_COREX = 'corex';

    /** How long a refused email's record is kept, as a captured one is. */
    private const REFUSAL_DAYS = 30;

    public function __construct(
        private readonly SiteEnvironment $environment,
        private readonly ConfigInterface $config,
        private readonly MailHold $hold,
        private readonly ContainerInterface $container,
    ) {
    }

    /** @param bool $corexMail whether CoreX sends the email (for the "Capture only CoreX mail" switch) */
    public function decide(bool $corexMail): MailDecision
    {
        if ($this->hold->active()) {
            return $this->store() === null ? MailDecision::Refuse : MailDecision::Hold;
        }

        $answer = $this->environment->current();
        if ($answer->isProduction()) {
            return MailDecision::Deliver;
        }

        if ($answer->isStaging() && $this->liveOnStaging()) {
            return MailDecision::Deliver;
        }

        if (! $corexMail && $this->scope() === self::SCOPE_COREX) {
            return MailDecision::Deliver;
        }

        return $this->store() === null ? MailDecision::Refuse : MailDecision::Capture;
    }

    /** Whether all mail is captured on a development or staging site, or only CoreX's. */
    public function scope(): string
    {
        return $this->config->get('mail.capture_scope', self::SCOPE_ALL) === self::SCOPE_COREX
            ? self::SCOPE_COREX
            : self::SCOPE_ALL;
    }

    /**
     * Keep a message the decision said to keep, as captured or as held for a test. Returns the
     * capture's id, or null when it could not be kept (it is then recorded as refused).
     */
    public function keep(CapturedMessage $message, MailDecision $decision): ?string
    {
        $store = $this->store();
        if ($store === null) {
            $this->refuse($message);

            return null;
        }

        $kept = $decision === MailDecision::Hold
            ? $message->withState(CapturedMessage::STATE_HELD_FOR_TEST)
            : $message;
        $id = $store->keep($kept);
        $this->hold->note($kept->to, $kept->subject, $id, $kept->path);

        return $id;
    }

    /** Record an email that was neither sent nor kept, so that nothing is lost unseen (FR-009). */
    public function refuse(CapturedMessage $message): void
    {
        // The activity log is corex-config's: its repository is bound only while it is active.
        if (! $this->container->has(ActivityRepository::class)) {
            return;
        }

        $now = new DateTimeImmutable('now');
        $user = wp_get_current_user();
        try {
            $this->container->make(ActivityService::class)->record(
                actorId: $user->ID,
                actorKind: $user->ID > 0 ? ActivityEvent::ACTOR_USER : ActivityEvent::ACTOR_SYSTEM,
                actorLabel: $user->ID > 0 && $user->display_name !== '' ? $user->display_name : __('System', 'corex'),
                area: ActivityEvent::AREA_EMAIL,
                kind: 'mail.refused',
                targetType: 'mail',
                targetId: $message->path,
                targetLabel: $message->path,
                outcome: ActivityEvent::OUTCOME_DENIED,
                summary: ['key' => 'mail.refused', 'args' => ['path' => $message->path]],
                context: ['to' => $message->to, 'subject' => $message->subject],
                sensitivity: ActivityEvent::SENSITIVITY_PERSONAL,
                retentionUntil: $now->modify('+' . self::REFUSAL_DAYS . ' days'),
                occurredAt: $now,
            );
        } catch (Throwable) {
            // The email is already not sent; a record that cannot be written must not turn that
            // into an error for whoever was sending it.
        }
    }

    /**
     * Staging delivers only with a provider set and live delivery on, asked as Email Studio asks it
     * when CoreX Mail is there. Without CoreX Mail there is no Email Studio to disagree with, and
     * any provider set counts.
     */
    private function liveOnStaging(): bool
    {
        if (! filter_var($this->config->get('mail.live_delivery', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return $this->container->has(MailProviderCheck::class)
            ? $this->container->make(MailProviderCheck::class)->providerConfigured()
            : trim((string) $this->config->get('mail.provider', '')) !== '';
    }

    private function store(): ?MailCaptureStore
    {
        return $this->container->has(MailCaptureStore::class)
            ? $this->container->make(MailCaptureStore::class)
            : null;
    }
}
