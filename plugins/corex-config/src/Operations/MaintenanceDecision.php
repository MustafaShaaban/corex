<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Http\PublicSubmissionPause;

/**
 * Who Maintenance closes the site to (spec 065, spec 112): everyone but a signed-in administrator,
 * while the mode is `maintenance`, unless the `corex_maintenance_bypass` filter passes the request
 * (DECISIONS #123).
 *
 * It says nothing about the kind of request. The page guard adds its own rule that the admin,
 * cron, AJAX and REST are never sent the maintenance page. The answer is corex-core's
 * {@see PublicSubmissionPause}, which also closes public submissions through
 * {@see PublicSubmissionGuard}, so the maintenance page and the closed forms cannot disagree about
 * who passes (spec 124). Forms paused closes the forms only, never the site.
 */
final class MaintenanceDecision
{
    public function __construct(private readonly PublicSubmissionPause $pause)
    {
    }

    public function closedToCurrentVisitor(): bool
    {
        return $this->pause->closedBy() === PublicSubmissionPause::MAINTENANCE;
    }
}
