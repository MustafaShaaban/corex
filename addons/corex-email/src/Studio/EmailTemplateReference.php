<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Optional persisted-template provenance attached to a delivery attempt.
 */
final class EmailTemplateReference
{
    public function __construct(
        public readonly string $slug,
        public readonly ?int $templateId = null,
        public readonly ?int $templateVersion = null,
        public readonly ?int $routeId = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $this->slug) !== 1
            || ($this->templateId !== null && $this->templateId < 1)
            || ($this->templateVersion !== null && $this->templateVersion < 1)
            || ($this->routeId !== null && $this->routeId < 1)
        ) {
            throw new InvalidArgumentException(__('Email template delivery reference is invalid.', 'corex'));
        }
    }
}
