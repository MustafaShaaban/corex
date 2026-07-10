<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Delivery;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Truthful delivery action selected by the environment policy.
 */
final class DeliveryDecision
{
    public const ACTION_CAPTURE = 'capture';
    public const ACTION_DELIVER = 'deliver';
    public const ACTION_BLOCK   = 'block';

    public function __construct(
        public readonly string $action,
        public readonly string $reason,
        public readonly bool $providerRequired,
        public readonly ?string $setupPath = null,
    ) {
        if (! in_array($this->action, [self::ACTION_CAPTURE, self::ACTION_DELIVER, self::ACTION_BLOCK], true)) {
            throw new InvalidArgumentException(__('Email delivery action is invalid.', 'corex'));
        }

        if ($this->reason === '') {
            throw new InvalidArgumentException(__('Email delivery decision requires a reason.', 'corex'));
        }
    }
}
