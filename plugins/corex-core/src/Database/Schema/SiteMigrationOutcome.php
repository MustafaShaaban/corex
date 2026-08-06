<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

enum SiteMigrationOutcome: string
{
    case Migrated = 'migrated';
    case AlreadyCurrent = 'already-current';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
