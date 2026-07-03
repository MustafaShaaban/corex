<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Data\DataField;

interface FieldAwareDataSource
{
    /** @return list<DataField> */
    public function fields(): array;
}
