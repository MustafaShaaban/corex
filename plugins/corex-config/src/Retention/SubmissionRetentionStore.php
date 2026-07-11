<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Retention;

defined('ABSPATH') || exit;

interface SubmissionRetentionStore
{
    public function trashForRetention(int $id): bool;

    public function archiveForRetention(int $id): bool;

    public function anonymizeForRetention(int $id): bool;
}
