<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

final class WpDataAccessPolicy implements DataAccessPolicy
{
    public function allows(int $actorId, string $ability): bool
    {
        return $actorId > 0
            && get_current_user_id() === $actorId
            && (current_user_can($ability) || current_user_can('manage_options'));
    }
}
