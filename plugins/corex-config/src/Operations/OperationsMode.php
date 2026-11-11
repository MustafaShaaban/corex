<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * Pure model for the CoreX Operations Mode (spec 065). The operations mode is a real, operator-declared
 * state (persisted by {@see OperationsModeStore}) — distinct from `wp_get_environment_type()`, which is
 * the default when no mode has been declared. This model owns the truthful metadata for each mode:
 * label, tone, description, the warnings the mode implies, whether changing to it needs confirmation
 * (production, maintenance and coming soon do), and whether it changes public behaviour (maintenance
 * and coming soon do). WordPress-free, so it is unit-testable. It NEVER changes site behaviour itself
 * — the boundary does, safely and reversibly.
 *
 * Forms paused (spec 124) is the sixth: the site stays open and public submissions are refused.
 *
 * Coming soon (spec 101) is the fifth mode. Spec 063 named it and spec 065 shipped without it; what
 * each of the two closed modes does to a visitor is {@see MaintenanceGuard} and
 * {@see ComingSoonGuard}, and they are deliberately not the same thing.
 */
final class OperationsMode
{
    public const DEVELOPMENT = 'development';
    public const STAGING     = 'staging';
    public const PRODUCTION  = 'production';
    public const MAINTENANCE = 'maintenance';
    public const COMING_SOON = 'coming-soon';
    public const FORMS_PAUSED = 'forms-paused';

    public const TONE_INFO    = 'info';
    public const TONE_WARNING = 'warning';
    public const TONE_SUCCESS = 'success';
    public const TONE_DANGER  = 'danger';

    /**
     * The selectable modes, in display order.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return [self::DEVELOPMENT, self::STAGING, self::PRODUCTION, self::COMING_SOON, self::FORMS_PAUSED, self::MAINTENANCE];
    }

    public function isValid(string $mode): bool
    {
        return in_array($mode, $this->all(), true);
    }

    /**
     * Normalise an arbitrary value to a real mode; unknown values fall back to production (the safe
     * default that changes no behaviour), never to an invented mode.
     */
    public function normalize(string $mode): string
    {
        return $this->isValid($mode) ? $mode : self::PRODUCTION;
    }

    /** Changing to production, maintenance, coming soon or forms paused requires an explicit confirmation step. */
    public function requiresConfirmation(string $mode): bool
    {
        return in_array($mode, [self::PRODUCTION, self::MAINTENANCE, self::COMING_SOON, self::FORMS_PAUSED], true);
    }

    /**
     * Maintenance, coming soon and forms paused change public behaviour: a 503 notice in one, the
     * coming-soon page and a redirect to it in another, refused submissions in the third.
     */
    public function affectsPublic(string $mode): bool
    {
        return in_array($mode, [self::MAINTENANCE, self::COMING_SOON, self::FORMS_PAUSED], true);
    }

    /**
     * The mode's tone in words, for the mode cards (spec 124, US-OPS-07): what a visitor gets,
     * said so that it does not depend on the card's colour.
     */
    public function toneWord(string $mode): string
    {
        return match ($this->normalize($mode)) {
            self::DEVELOPMENT  => __('Test site', 'corex'),
            self::STAGING      => __('Review copy', 'corex'),
            self::MAINTENANCE  => __('Closed to visitors', 'corex'),
            self::COMING_SOON  => __('Not open yet', 'corex'),
            self::FORMS_PAUSED => __('Forms closed', 'corex'),
            default            => __('Live', 'corex'),
        };
    }

    /**
     * @return array{mode:string,label:string,tone:string,detail:string}
     */
    public function describe(string $mode): array
    {
        return match ($this->normalize($mode)) {
            self::DEVELOPMENT => [
                'mode'   => self::DEVELOPMENT,
                'label'  => __('Development', 'corex'),
                'tone'   => self::TONE_INFO,
                // Said as it is since spec 113: a development site keeps its email (spec 118).
                'detail' => __('Not a live site. Email is kept in Email Studio instead of being sent.', 'corex'),
            ],
            self::STAGING => [
                'mode'   => self::STAGING,
                'label'  => __('Staging', 'corex'),
                'tone'   => self::TONE_WARNING,
                'detail' => __('Pre-production. Email is kept in Email Studio unless a mail provider and live delivery are set.', 'corex'),
            ],
            self::FORMS_PAUSED => [
                'mode'   => self::FORMS_PAUSED,
                'label'  => __('Forms paused', 'corex'),
                'tone'   => self::TONE_WARNING,
                'detail' => __('The site stays open; every form refuses new submissions with a message until you switch back.', 'corex'),
            ],
            self::MAINTENANCE => [
                'mode'   => self::MAINTENANCE,
                'label'  => __('Maintenance', 'corex'),
                'tone'   => self::TONE_DANGER,
                'detail' => __('Visitors see a maintenance notice; signed-in administrators keep full access.', 'corex'),
            ],
            self::COMING_SOON => [
                'mode'   => self::COMING_SOON,
                'label'  => __('Coming soon', 'corex'),
                'tone'   => self::TONE_WARNING,
                'detail' => __('Visitors see the coming-soon page; anyone signed in who can edit posts sees the real site.', 'corex'),
            ],
            default => [
                'mode'   => self::PRODUCTION,
                'label'  => __('Production', 'corex'),
                'tone'   => self::TONE_SUCCESS,
                'detail' => __('Live environment — changes affect real visitors.', 'corex'),
            ],
        };
    }

    /**
     * The mode-specific warnings an operator should see. Real, advisory guidance — not a fabricated
     * status.
     *
     * @return list<string>
     */
    public function warnings(string $mode): array
    {
        return match ($this->normalize($mode)) {
            self::DEVELOPMENT => [
                __('Email is kept in Email Studio and every public page asks search engines not to index it.', 'corex'),
            ],
            self::STAGING => [
                __('Double-check email recipients and third-party integrations before any test send.', 'corex'),
                ...$this->cautions(self::STAGING),
            ],
            self::MAINTENANCE => [
                __('The public site shows a maintenance notice. Switch back to production to restore it.', 'corex'),
                __('You keep full admin access while signed in — you cannot lock yourself out.', 'corex'),
            ],
            self::COMING_SOON => [
                __('The public site shows the coming-soon page, and every other address is redirected to it. Switch to production to open the site.', 'corex'),
                ...$this->cautions(self::COMING_SOON),
            ],
            self::FORMS_PAUSED => [
                __('Forms, flows, newsletter sign-ups, booking requests, job applications and registration are refused. Switch back to reopen them.', 'corex'),
            ],
            default => [
                __('Changes take effect for real visitors. Review dangerous actions before applying them.', 'corex'),
            ],
        };
    }

    /**
     * The warnings that say something {@see describe()}'s detail does not: what the mode leaves
     * exposed, or what the operator still has to do. Shown under the current mode, where a warning
     * that restates the detail is the same sentence twice.
     *
     * @return list<string>
     */
    public function cautions(string $mode): array
    {
        return match ($this->normalize($mode)) {
            self::STAGING => [
                __('Every public page asks search engines not to index it; the Reading setting is left as it is.', 'corex'),
            ],
            self::COMING_SOON => [
                __('Published content can still be read through the REST API. Keep anything that must stay private in draft.', 'corex'),
            ],
            default => [],
        };
    }
}
