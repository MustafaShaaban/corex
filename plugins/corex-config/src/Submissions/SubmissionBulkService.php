<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use DomainException;
use InvalidArgumentException;

/**
 * Two-step exact bulk mutations with bounded selection and single-use preview tokens.
 */
final readonly class SubmissionBulkService
{
    private const MAX_RECORDS = 100;
    private const ACTIONS = ['mark_read', 'assign', 'mark_spam', 'archive'];

    public function __construct(
        private SubmissionWorkflowService $workflow,
        private SubmissionWorkflowStore $submissions,
        private SubmissionBulkPreviewStore $previews,
    ) {
    }

    /** @param list<int> $submissionIds @param array<string,mixed> $parameters */
    public function preview(
        SubmissionAccessScope $scope,
        string $action,
        array $submissionIds,
        array $parameters = [],
    ): SubmissionBulkPreview {
        $ids = $this->ids($submissionIds);
        $this->assertAction($action, $parameters);
        $records = [];
        foreach ($ids as $id) {
            $record = $this->submissions->findWorkflow($id);
            if ($record === null || ! $scope->allows($record)) {
                throw new DomainException('One or more selected submissions are unavailable.');
            }
            $records[] = ['id' => $id, 'updated_at' => (string) $record['updated_at']];
        }

        return $this->previews->issue($scope->actorId, $action, $records, $parameters);
    }

    /** @return array{matched:int,updated:int,failed:int} */
    public function apply(SubmissionAccessScope $scope, string $token): array
    {
        $preview = $this->previews->consume($token, $scope->actorId);
        if ($preview === null) {
            throw new DomainException('The bulk preview expired or was already used.');
        }
        $this->preflight($scope, $preview);
        foreach ($preview->submissionIds as $id) {
            $this->applyOne($scope, $preview, $id);
        }

        return ['matched' => $preview->count(), 'updated' => $preview->count(), 'failed' => 0];
    }

    /** @param list<int> $submissionIds @return list<int> */
    private function ids(array $submissionIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $submissionIds), static fn (int $id): bool => $id > 0)));
        sort($ids, SORT_NUMERIC);
        if ($ids === []) {
            throw new InvalidArgumentException('A bulk selection is required.');
        }
        if (count($ids) > self::MAX_RECORDS) {
            throw new InvalidArgumentException('Bulk actions are limited to 100 submissions.');
        }

        return $ids;
    }

    /** @param array<string,mixed> $parameters */
    private function assertAction(string $action, array $parameters): void
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException('The bulk action is invalid.');
        }
        if ($action === 'assign') {
            new SubmissionAssignment(
                (string) ($parameters['owner_type'] ?? ''),
                (string) ($parameters['owner_key'] ?? ''),
            );
        }
    }

    private function preflight(SubmissionAccessScope $scope, SubmissionBulkPreview $preview): void
    {
        foreach ($preview->submissionIds as $id) {
            $record = $this->submissions->findWorkflow($id);
            $expected = $preview->expectedVersions[$id] ?? '';
            if ($record === null || ! $scope->allows($record) || ! hash_equals((string) $record['updated_at'], $expected)) {
                throw new DomainException('A selected submission changed or became unavailable.');
            }
        }
    }

    private function applyOne(SubmissionAccessScope $scope, SubmissionBulkPreview $preview, int $id): void
    {
        $version = $preview->expectedVersions[$id];
        match ($preview->action) {
            'mark_read' => $this->workflow->markRead($scope, $id, $version),
            'mark_spam' => $this->workflow->changeStatus($scope, $id, 'spam', $version),
            'archive' => $this->workflow->changeStatus($scope, $id, 'archived', $version),
            'assign' => $this->workflow->assign($scope, $id, new SubmissionAssignment(
                (string) $preview->parameters['owner_type'],
                (string) $preview->parameters['owner_key'],
            ), $version),
        };
    }
}
