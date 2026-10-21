<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Corex\Environment\SiteEnvironment;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Uuid;

/**
 * The delivery state Email Studio decides every send by: the site's environment, whether the
 * configured provider is the one the Studio sends through, and whether live delivery is on.
 *
 * Three classes built this for themselves from `app.env`, which the defaults layer answers with
 * "production" on every site, so none of them followed the operations mode (spec 113, R5).
 */
final readonly class EmailDeliveryContextFactory
{
    public function __construct(
        private SiteEnvironment $environment,
        private ConfigInterface $config,
        private EmailStudioService $studio,
    ) {
    }

    public function make(?string $requestId = null): EmailDeliveryContext
    {
        return new EmailDeliveryContext(
            $this->environment->current()->environment,
            $this->providerConfigured(),
            $this->liveDelivery(),
            $requestId ?? Uuid::v4(),
        );
    }

    public function providerName(): string
    {
        // As `sanitize_key()` cleans it, without needing WordPress loaded.
        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $this->config->get('mail.provider', '')));
    }

    public function providerConfigured(): bool
    {
        return $this->studio->supportsProvider($this->providerName());
    }

    public function liveDelivery(): bool
    {
        return filter_var($this->config->get('mail.live_delivery', false), FILTER_VALIDATE_BOOLEAN);
    }
}
