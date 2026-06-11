<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Reset;

defined('ABSPATH') || exit;

/**
 * One step in a reset plan: its kind (the executor switches on this), the target it acts
 * on (a plugin file, an option key, a page id, or `database`), and a human label used in
 * the dry-run preview and the post-run report. A pure value object.
 */
final class ResetAction
{
    public const DEACTIVATE_ADDON = 'deactivate-addon';
    public const DELETE_OPTION    = 'delete-option';
    public const REMOVE_DEMO      = 'remove-demo';
    public const DB_WIPE          = 'db-wipe';

    public function __construct(
        public readonly string $kind,
        public readonly string $target,
        public readonly string $label,
    ) {
    }
}
