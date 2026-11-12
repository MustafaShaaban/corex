<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * Who Maintenance closes the site to (spec 065, spec 112): everyone but a signed-in administrator,
 * while the mode is `maintenance`, unless the `corex_maintenance_bypass` filter passes the request
 * (DECISIONS #123).
 *
 * It says nothing about the kind of request. The page guard adds its own rule that the admin,
 * cron, AJAX and REST are never sent the maintenance page; the submission guard closes only the
 * REST routes that declare a public submission. Both ask this one question, so they cannot
 * disagree about who passes.
 */
final class MaintenanceDecision
{
    public function __construct(private readonly OperationsModeStore $store)
    {
    }

    public function closedToCurrentVisitor(): bool
    {
        if ($this->store->current() !== OperationsMode::MAINTENANCE) {
            return false;
        }

        if (function_exists('apply_filters') && apply_filters('corex_maintenance_bypass', false) === true) {
            return false;
        }

        // Never close the site to a signed-in administrator: the operator cannot lock themselves out.
        return ! (is_user_logged_in() && current_user_can('manage_options'));
    }
}
