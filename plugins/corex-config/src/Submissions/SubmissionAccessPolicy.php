<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

interface SubmissionAccessPolicy
{
    public function scopeFor(int $actorId): ?SubmissionAccessScope;
}
