<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Delivery;

defined('ABSPATH') || exit;

/**
 * Fails closed unless an environment is explicitly safe for capture or delivery.
 */
final class DeliveryPolicy
{
    private const SETUP_PATH = '/wp-admin/admin.php?page=corex-settings-config&corex_tab=mail';

    public function evaluate(string $environment, bool $providerConfigured, bool $liveDeliveryEnabled): DeliveryDecision
    {
        if (in_array($environment, ['local', 'development'], true)) {
            return new DeliveryDecision(
                action: DeliveryDecision::ACTION_CAPTURE,
                reason: __('Development mail is captured locally and is never sent to real recipients.', 'corex'),
                providerRequired: false,
            );
        }

        if ($environment === 'staging') {
            if ($providerConfigured && $liveDeliveryEnabled) {
                return new DeliveryDecision(
                    action: DeliveryDecision::ACTION_DELIVER,
                    reason: __('Staging delivery was deliberately enabled with a configured provider.', 'corex'),
                    providerRequired: true,
                );
            }

            return new DeliveryDecision(
                action: DeliveryDecision::ACTION_CAPTURE,
                reason: __('Staging mail is captured until provider-backed live delivery is deliberately enabled.', 'corex'),
                providerRequired: false,
                setupPath: self::SETUP_PATH,
            );
        }

        if ($environment !== 'production') {
            return new DeliveryDecision(
                action: DeliveryDecision::ACTION_BLOCK,
                reason: __('Unknown environment; email delivery is blocked for safety.', 'corex'),
                providerRequired: true,
                setupPath: self::SETUP_PATH,
            );
        }

        if (! $providerConfigured) {
            return new DeliveryDecision(
                action: DeliveryDecision::ACTION_BLOCK,
                reason: __('Production delivery requires a configured provider.', 'corex'),
                providerRequired: true,
                setupPath: self::SETUP_PATH,
            );
        }

        if (! $liveDeliveryEnabled) {
            return new DeliveryDecision(
                action: DeliveryDecision::ACTION_BLOCK,
                reason: __('Production live delivery must be deliberately enabled after provider setup.', 'corex'),
                providerRequired: true,
                setupPath: self::SETUP_PATH,
            );
        }

        return new DeliveryDecision(
            action: DeliveryDecision::ACTION_DELIVER,
            reason: __('Production delivery is enabled through the configured provider.', 'corex'),
            providerRequired: true,
        );
    }
}
