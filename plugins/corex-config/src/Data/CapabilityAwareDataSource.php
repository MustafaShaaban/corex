<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Data\DataSourceCapabilities;

interface CapabilityAwareDataSource
{
    public function capabilities(): DataSourceCapabilities;
}
