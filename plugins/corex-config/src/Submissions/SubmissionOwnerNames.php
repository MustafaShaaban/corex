<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * Names the owner of a submission the way a person would.
 */
interface SubmissionOwnerNames
{
    /**
     * @return string A display name, or '' when this owner cannot be named.
     */
    public function nameOf(string $ownerType, string $ownerKey): string;
}
