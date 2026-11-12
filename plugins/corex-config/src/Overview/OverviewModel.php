<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Overview;

use Corex\Config\Insights\InsightResult;
use Corex\Config\Security\LoginProtection\LoginAddressState;

defined('ABSPATH') || exit;

/**
 * Pure view model for the Overview's analytics and security card (spec 064, spec 114). Each row is
 * in the words of what is so, from facts {@see OverviewFacts} has read; a fact that could not be
 * read is "Could not check", never a success. The launch checklist, the tiles and the other cards
 * moved to their own classes with spec 118. WordPress-free, so it is unit-testable.
 */
final class OverviewModel
{
    public const TONE_SUCCESS = 'success';
    public const TONE_WARNING = 'warning';
    public const TONE_INFO    = 'info';
    public const TONE_NEUTRAL = 'neutral';

    /** The captcha providers that need keys, by driver, as the Settings screen names them. */
    private const PROVIDERS = [
        'recaptcha' => 'reCAPTCHA',
        'turnstile' => 'Cloudflare Turnstile',
        'hcaptcha'  => 'hCaptcha',
    ];

    /**
     * Analytics & security integrations, each in the words of what is true.
     *
     * @param array<string,mixed> $facts
     *
     * @return list<array{label:string,note:string,tone:string}>
     */
    public function integrations(array $facts): array
    {
        return [
            $this->insightsRow($facts['insights']),
            $this->captchaRow($facts['captcha']),
            [
                'label' => __('Transactional email', 'corex'),
                'note'  => $facts['mailFrom'] ? __('From address set', 'corex') : __('Not configured', 'corex'),
                'tone'  => $facts['mailFrom'] ? self::TONE_SUCCESS : self::TONE_NEUTRAL,
            ],
            $this->loginRow($facts['login']),
        ];
    }

    /**
     * "Connected" only with a saved key whose latest run completed. It read "Provider connected"
     * on every site, key or none.
     *
     * @param array{key:bool,latest:array<string,mixed>|null}|null $insights
     *
     * @return array{label:string,note:string,tone:string}
     */
    private function insightsRow(?array $insights): array
    {
        $label = __('Insights / PageSpeed', 'corex');

        if ($insights === null) {
            return $this->notCheckedIntegration($label);
        }

        $latest = $insights['latest'];
        if (($latest['status'] ?? '') === InsightResult::STATUS_FAILED) {
            return [
                'label' => $label,
                /* translators: %s: why the last PageSpeed run could not complete */
                'note'  => sprintf(__('Error: %s', 'corex'), (string) ($latest['summary'] ?? '')),
                'tone'  => self::TONE_WARNING,
            ];
        }

        if (! $insights['key']) {
            return ['label' => $label, 'note' => __('Keyless (shared quota)', 'corex'), 'tone' => self::TONE_NEUTRAL];
        }

        return $latest === null
            ? ['label' => $label, 'note' => __('Key saved, not checked yet', 'corex'), 'tone' => self::TONE_NEUTRAL]
            : ['label' => $label, 'note' => __('Connected', 'corex'), 'tone' => self::TONE_SUCCESS];
    }

    /**
     * It read "Configured" with the driver None, or with the captcha add-on off.
     *
     * @param array{active:bool,driver:string,keys:bool}|null $captcha
     *
     * @return array{label:string,note:string,tone:string}
     */
    private function captchaRow(?array $captcha): array
    {
        $label = __('Captcha', 'corex');

        if ($captcha === null) {
            return $this->notCheckedIntegration($label);
        }

        if (! $captcha['active']) {
            return ['label' => $label, 'note' => __('Not configured (add-on inactive)', 'corex'), 'tone' => self::TONE_NEUTRAL];
        }

        $name = self::PROVIDERS[$captcha['driver']] ?? null;
        if ($name === null) {
            return ['label' => $label, 'note' => __('Not configured', 'corex'), 'tone' => self::TONE_NEUTRAL];
        }

        if (! $captcha['keys']) {
            return ['label' => $label, 'note' => __('Keys missing', 'corex'), 'tone' => self::TONE_WARNING];
        }

        return [
            'label' => $label,
            /* translators: %s: a captcha provider, e.g. hCaptcha */
            'note'  => sprintf(__('%s configured', 'corex'), $name),
            'tone'  => self::TONE_SUCCESS,
        ];
    }

    /**
     * The Security Center's own answer: protection on or off, and whether the login address is
     * hidden. It counted hardening warnings instead.
     *
     * @return array{label:string,note:string,tone:string}
     */
    private function loginRow(?string $state): array
    {
        $label = __('Login protection', 'corex');

        return match ($state) {
            null                        => $this->notCheckedIntegration($label),
            LoginAddressState::OFF      => ['label' => $label, 'note' => __('Off', 'corex'), 'tone' => self::TONE_WARNING],
            LoginAddressState::HIDDEN   => ['label' => $label, 'note' => __('On, login address hidden', 'corex'), 'tone' => self::TONE_SUCCESS],
            LoginAddressState::RECOVERY => ['label' => $label, 'note' => __('On, recovery active: login address reachable', 'corex'), 'tone' => self::TONE_WARNING],
            default                     => ['label' => $label, 'note' => __('On, login address reachable', 'corex'), 'tone' => self::TONE_INFO],
        };
    }

    /**
     * @return array{label:string,note:string,tone:string}
     */
    private function notCheckedIntegration(string $label): array
    {
        return ['label' => $label, 'note' => __('Could not check', 'corex'), 'tone' => self::TONE_WARNING];
    }
}
