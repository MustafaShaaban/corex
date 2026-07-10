<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

interface DataAccessPolicy
{
    public function allows(int $actorId, string $ability): bool;
}
