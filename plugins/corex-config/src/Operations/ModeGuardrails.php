<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Environment\SiteEnvironment;
use Corex\Mail\Capture\MailDecision;
use Corex\Mail\Capture\MailGate;
use Throwable;

/**
 * What each operations mode does to email, form submissions, search engines and analytics, in four
 * rows (spec 124, US-OPS-01), for the mode in force and for any mode being considered.
 *
 * Every row is read from what enforces it, never written beside it: email is the mail gate's answer
 * for the environment the mode would give the site (a lock decides it when there is one), form
 * submissions follow the gate that closes them in Maintenance and Forms paused, search engines
 * follow {@see ModeRobots} and the Reading setting, and CoreX collects no analytics in any mode. A
 * row whose answer cannot be read says so instead of a reassuring value.
 */
final class ModeGuardrails
{
    public const TONE_ON      = 'on';
    public const TONE_OFF     = 'off';
    public const TONE_CLOSED  = 'closed';
    public const TONE_NEUTRAL = 'neutral';

    public function __construct(
        private readonly OperationsModeStore $store,
        private readonly SiteEnvironment $environment,
        private readonly MailGate $mail,
    ) {
    }

    /**
     * @return list<array{key:string,label:string,value:string,tone:string}>
     */
    public function rows(string $mode): array
    {
        return [
            $this->row('email', __('Email', 'corex'), fn (): array => $this->email($mode)),
            $this->row('forms', __('Form submissions', 'corex'), fn (): array => $this->forms($mode)),
            $this->row('search', __('Search engines', 'corex'), fn (): array => $this->search($mode)),
            $this->row('analytics', __('Analytics', 'corex'), static fn (): array => [
                __('None collected by CoreX', 'corex'),
                self::TONE_NEUTRAL,
            ]),
        ];
    }

    /**
     * @param callable(): array{0:string,1:string} $answer
     *
     * @return array{key:string,label:string,value:string,tone:string}
     */
    private function row(string $key, string $label, callable $answer): array
    {
        try {
            [$value, $tone] = $answer();
        } catch (Throwable) {
            [$value, $tone] = [__('Could not check', 'corex'), self::TONE_NEUTRAL];
        }

        return ['key' => $key, 'label' => $label, 'value' => $value, 'tone' => $tone];
    }

    /** @return array{0:string,1:string} */
    private function email(string $mode): array
    {
        $lock        = $this->store->lock();
        $environment = $lock?->environment ?? $this->environment->fromMode($mode) ?? SiteEnvironment::PRODUCTION;

        [$value, $tone] = match ($this->mail->decideIn($environment)) {
            MailDecision::Deliver => [__('Delivered', 'corex'), self::TONE_ON],
            MailDecision::Refuse  => [__('Not sent, and not kept', 'corex'), self::TONE_OFF],
            default               => [__('Captured, nothing leaves', 'corex'), self::TONE_OFF],
        };

        return $lock === null ? [$value, $tone] : [
            sprintf(
                /* translators: 1: what happens to email, e.g. "Delivered", 2: where the environment is set. */
                __('%1$s (locked by %2$s)', 'corex'),
                $value,
                $lock->source(),
            ),
            $tone,
        ];
    }

    /** @return array{0:string,1:string} */
    private function forms(string $mode): array
    {
        return match ($mode) {
            OperationsMode::MAINTENANCE  => [__('Closed', 'corex'), self::TONE_CLOSED],
            OperationsMode::FORMS_PAUSED => [__('Paused', 'corex'), self::TONE_CLOSED],
            default                      => [__('Accepted', 'corex'), self::TONE_ON],
        };
    }

    /** @return array{0:string,1:string} */
    private function search(string $mode): array
    {
        if (ModeRobots::keepsOut($mode)) {
            return [__('Kept out (noindex)', 'corex'), self::TONE_OFF];
        }

        if ($mode === OperationsMode::MAINTENANCE) {
            return [__('Told to come back later (503)', 'corex'), self::TONE_CLOSED];
        }

        if ((string) get_option('blog_public', '1') === '0') {
            return [__('Discouraged by Settings > Reading', 'corex'), self::TONE_OFF];
        }

        return $mode === OperationsMode::COMING_SOON
            ? [__('Coming-soon page allowed', 'corex'), self::TONE_ON]
            : [__('Allowed', 'corex'), self::TONE_ON];
    }
}
