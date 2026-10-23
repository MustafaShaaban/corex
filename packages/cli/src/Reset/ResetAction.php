<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Reset;

defined('ABSPATH') || exit;

/**
 * One step in a reset plan: its kind (the executor switches on this), the target it acts
 * on (a plugin file, an option key, a page id, `reading` or `database`), and a human label used in
 * the dry-run preview and the post-run report. A pure value object.
 *
 * A page a kit touched gets one of four kinds (spec 115): a page the kit created is removed; a page
 * the owner had is restored from what the kit recorded, emptied when the kit adopted it empty and
 * recorded nothing, or kept as it is when it was replaced and nothing was recorded. A page the owner
 * had is never deleted.
 */
final class ResetAction
{
    public const DEACTIVATE_ADDON = 'deactivate-addon';
    public const DELETE_OPTION    = 'delete-option';
    public const REMOVE_DEMO      = 'remove-demo';
    public const RESTORE_PAGE     = 'restore-page';
    public const EMPTY_PAGE       = 'empty-page';
    public const KEEP_PAGE        = 'keep-page';
    public const RESTORE_READING  = 'restore-reading';
    public const DB_WIPE          = 'db-wipe';

    public function __construct(
        public readonly string $kind,
        public readonly string $target,
        public readonly string $label,
    ) {
    }
}
