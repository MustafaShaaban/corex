<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

interface SubmissionInboxReader
{
    /**
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function queryInbox(SubmissionInboxQuery $query, SubmissionAccessScope $scope): array;

    /** @return array<string,mixed>|null */
    public function findInbox(int $id, SubmissionAccessScope $scope): ?array;

    /**
     * The IDs of the flows whose submissions the scope reaches (spec 122, FR-013).
     *
     * @return list<int>
     */
    public function flowIdsIn(SubmissionAccessScope $scope): array;
}
