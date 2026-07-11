<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Routing;

defined('ABSPATH') || exit;

use Corex\Email\Studio\EmailDeliveryContext;
use Corex\Email\Studio\EmailStudioService;
use Corex\Mail\MailResult;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Uuid;

/**
 * Delivers a prepared routed message through the Studio safety policy.
 */
final readonly class EmailRouteDispatcher
{
    public function __construct(
        private EmailRouteMessageFactory $messages,
        private EmailStudioService $studio,
        private ConfigInterface $config,
    ) {
    }

    /** @param array<string,mixed> $context */
    public function dispatch(ResolvedEmailRoute $route, array $context): ?MailResult
    {
        $prepared = $this->messages->prepare($route, $context);
        if ($prepared === null) {
            return null;
        }

        return $this->studio->send(
            $prepared->message,
            new EmailDeliveryContext(
                $this->environment(),
                $this->studio->supportsProvider($this->provider()),
                filter_var($this->config->get('mail.live_delivery', false), FILTER_VALIDATE_BOOLEAN),
                Uuid::v4(),
            ),
            $prepared->template,
        );
    }

    private function environment(): string
    {
        $environment = trim((string) $this->config->get('app.env', ''));
        if ($environment !== '') {
            return $environment;
        }

        return function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
    }

    private function provider(): string
    {
        return trim((string) $this->config->get('mail.provider', ''));
    }
}
