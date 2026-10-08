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
    /**
     * @param SubmissionQuestions|null  $questions Asked for a form's wording, when there is somebody to ask.
     * @param SubmissionOwnerNames|null $owners    Asked who an owner is, and who can be one.
     */
    public function __construct(
        private SubmissionInboxReader $reader,
        private SubmissionAccessPolicy $access,
        private ?SubmissionQuestions $questions = null,
        private ?SubmissionOwnerNames $owners = null,
    ) {
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,can_delete_permanently:bool} */
    public function query(int $actorId, SubmissionInboxQuery $query): array
    {
        $scope = $this->scope($actorId);
        $page  = $this->reader->queryInbox($query, $scope);

        return [
            'items' => $page['items'],
            'total' => max(0, $page['total']),
            'page' => $query->page,
            'per_page' => $query->perPage,
            // So the inbox can offer a permanent delete, or say why it does not (spec 105, FR-010).
            'can_delete_permanently' => $scope->canDeletePermanently,
        ];
    }

    /** @return array<string,mixed>|null */
    public function detail(int $actorId, int $submissionId): ?array
    {
        $scope = $this->scope($actorId);
        $record = $this->reader->findInbox($submissionId, $scope);

        return $record !== null && $scope->allows($record) ? $this->described($record) : null;
    }

    /**
     * The record, with what a person needs to read it: the questions its answers belong to, its
     * owner by name, and the people it could be given to. The record's own fields are untouched.
     *
     * @param array<string,mixed> $record
     *
     * @return array<string,mixed>
     */
    private function described(array $record): array
    {
        return $record + [
            'questions' => $this->questions?->for((string) ($record['form'] ?? '')) ?? [],
            'owner_name' => $this->owners?->nameOf(
                (string) ($record['owner_type'] ?? 'none'),
                (string) ($record['owner_key'] ?? ''),
            ) ?? '',
            'owners' => $this->owners?->people() ?? [],
        ];
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
