<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Operations\Confirmation;

final readonly class ProductionLaunchOverride
{
    public function __construct(
        public Confirmation $confirmation,
        public string $phrase,
    ) {
    }
}
