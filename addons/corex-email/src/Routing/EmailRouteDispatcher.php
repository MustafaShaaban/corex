<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Routing;

defined('ABSPATH') || exit;

use Corex\Email\Studio\EmailDeliveryContextFactory;
use Corex\Email\Studio\EmailStudioService;
use Corex\Mail\MailResult;

/**
 * Delivers a prepared routed message through the Studio safety policy.
 */
final readonly class EmailRouteDispatcher
{
    public function __construct(
        private EmailRouteMessageFactory $messages,
        private EmailStudioService $studio,
        private EmailDeliveryContextFactory $delivery,
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
            $this->delivery->make(),
            $prepared->template,
        );
    }
}
