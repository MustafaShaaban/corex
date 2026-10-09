<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Retention;

use Corex\Config\Submissions\SubmissionAccessScope;

defined('ABSPATH') || exit;

/**
 * Where the retention panel's "Move to trash" puts what is due (spec 105, US4).
 *
 * The trash is the inbox's, with its access rules, its history and its record of who did what.
 * Retention asks for it here and does not trash anything itself.
 */
interface SubmissionRetentionTrash
{
    /**
     * Move the due submissions this person may act on to the trash, as one retention run.
     *
     * @param list<int> $ids
     *
     * @return int How many were moved. One the person may not see, or that is gone, is left.
     */
    public function trashForRetention(SubmissionAccessScope $scope, array $ids): int;
}
