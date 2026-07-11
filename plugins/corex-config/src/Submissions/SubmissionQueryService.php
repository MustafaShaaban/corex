<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use DomainException;

/**
 * Permission-first query facade for Inbox rows, counts, and detail.
 */
final readonly class SubmissionQueryService
{
    public function __construct(
        private SubmissionInboxReader $reader,
        private SubmissionAccessPolicy $access,
    ) {
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int} */
    public function query(int $actorId, SubmissionInboxQuery $query): array
    {
        $scope = $this->scope($actorId);
        $page  = $this->reader->queryInbox($query, $scope);

        return [
            'items' => $page['items'],
            'total' => max(0, $page['total']),
            'page' => $query->page,
            'per_page' => $query->perPage,
        ];
    }

    /** @return array<string,mixed>|null */
    public function detail(int $actorId, int $submissionId): ?array
    {
        $scope = $this->scope($actorId);
        $record = $this->reader->findInbox($submissionId, $scope);

        return $record !== null && $scope->allows($record) ? $record : null;
    }

    private function scope(int $actorId): SubmissionAccessScope
    {
        $scope = $this->access->scopeFor($actorId);
        if ($scope === null) {
            throw new DomainException('This actor is not allowed to manage submissions.');
        }

        return $scope;
    }
}
