<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Delivery;

defined('ABSPATH') || exit;

use Corex\Email\Studio\EmailDeliveryContextFactory;
use Corex\Email\Studio\EmailStudioService;
use Corex\Environment\SiteEnvironment;
use Corex\Mail\Capture\MailDecision;
use Corex\Mail\Capture\MailGate;

/**
 * What happens to mail on this site, in words, for the statement on every Email Studio tab
 * (spec 113, US4).
 *
 * It is read from the decisions the sends themselves take: Email Studio's delivery policy for
 * the Studio's own sends, and the mail gate for every other path. So the statement says what the
 * site does, and names the paths that do not follow Email Studio, as on a production site with no
 * provider, where the Studio's sends are blocked and form mail still goes out (FR-019).
 */
final readonly class MailOutcome
{
    public const DELIVERED = 'delivered';
    public const CAPTURED  = 'captured';
    public const BLOCKED   = 'blocked';

    public function __construct(
        private SiteEnvironment $environment,
        private EmailDeliveryContextFactory $delivery,
        private EmailStudioService $studio,
        private MailGate $gate,
    ) {
    }

    /**
     * @return array{state:string,title:string,detail:string,facts:list<string>,exceptions:list<string>,
     *     environment:string,decided_by:string,locked:bool,provider:string,provider_configured:bool,
     *     live:bool,scope:string}
     */
    public function describe(): array
    {
        $answer = $this->environment->current();
        $context = $this->delivery->make();
        $state = match ($this->studio->decide($context)->action) {
            DeliveryDecision::ACTION_DELIVER => self::DELIVERED,
            DeliveryDecision::ACTION_CAPTURE => self::CAPTURED,
            default                          => self::BLOCKED,
        };
        $scope = $this->gate->scope();

        $exceptions = [];
        if ($state !== self::DELIVERED) {
            if ($this->gate->decide(true) === MailDecision::Deliver) {
                $exceptions[] = __('form notifications, newsletter, booking, careers and guides emails, and site code that sends through CoreX', 'corex');
            }
            if ($this->gate->decide(false) === MailDecision::Deliver) {
                $exceptions[] = __('WordPress\'s own emails and other plugins\' mail', 'corex');
            }
        }

        return [
            'state' => $state,
            'title' => $this->title($state, $exceptions),
            'detail' => $this->detail($state, $exceptions),
            'facts' => $this->facts($answer->summary(), $context->liveDeliveryEnabled, $this->delivery->providerName(), $answer->isProduction() ? null : $scope),
            'exceptions' => $exceptions,
            'environment' => $answer->environment,
            'decided_by' => $answer->decidedBy,
            'locked' => $answer->isLocked(),
            'provider' => $this->delivery->providerName(),
            'provider_configured' => $context->providerConfigured,
            'live' => $context->liveDeliveryEnabled,
            'scope' => $scope,
        ];
    }

    /** @param list<string> $exceptions */
    private function title(string $state, array $exceptions): string
    {
        return match (true) {
            $state === self::DELIVERED => __('Delivered for real', 'corex'),
            $state === self::CAPTURED && $exceptions === [] => __('Captured: nothing leaves this site', 'corex'),
            $state === self::CAPTURED => __('Captured: CoreX mail stays on this site', 'corex'),
            $exceptions === [] => __('Blocked: nothing is sent', 'corex'),
            default => __('Blocked: Email Studio sends nothing', 'corex'),
        };
    }

    /** @param list<string> $exceptions */
    private function detail(string $state, array $exceptions): string
    {
        $what = match ($state) {
            self::DELIVERED => __('Mail from this site reaches the people it is addressed to.', 'corex'),
            self::CAPTURED  => __('Each email is kept under Delivery logs instead of being sent, and nobody receives it.', 'corex'),
            default         => __('Email Studio\'s test sends, replies and routed emails are refused until a provider is set and live delivery is on.', 'corex'),
        };
        if ($exceptions === []) {
            return $what;
        }

        return $what . ' ' . sprintf(
            /* translators: %s: the kinds of email that are still sent. */
            __('Still sent for real: %s.', 'corex'),
            implode('; ', $exceptions),
        );
    }

    /** @return list<string> */
    private function facts(string $environment, bool $live, string $provider, ?string $scope): array
    {
        $facts = [
            sprintf(
                /* translators: %s: the environment and where it is set, e.g. "Development (set by the operations mode)". */
                __('Environment: %s', 'corex'),
                $environment,
            ),
            $live ? __('Live delivery: on', 'corex') : __('Live delivery: off', 'corex'),
            $provider === ''
                ? __('Provider: none set', 'corex')
                /* translators: %s: the mail provider's name. */
                : sprintf(__('Provider: %s', 'corex'), $provider),
        ];
        if ($scope !== null) {
            $facts[] = $scope === MailGate::SCOPE_COREX
                ? __('Captures: only CoreX mail', 'corex')
                : __('Captures: all mail from this site', 'corex');
        }

        return $facts;
    }
}
