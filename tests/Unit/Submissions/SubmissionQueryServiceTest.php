<?php

/**
 * Inbox query tests for spec 068 T092 / FR-046, FR-049, FR-057, and FR-058.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Corex\Config\Submissions\SubmissionAccessPolicy;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionInboxQuery;
use Corex\Config\Submissions\SubmissionInboxReader;
use Corex\Config\Submissions\SubmissionQueryService;

function queryReader(array $items = [], int $total = 0): SubmissionInboxReader
{
    return new class($items, $total) implements SubmissionInboxReader {
        public int $queries = 0;

        /** @param list<array<string,mixed>> $items */
        public function __construct(private array $items, private int $total)
        {
        }

        public function queryInbox(SubmissionInboxQuery $query, SubmissionAccessScope $scope): array
        {
            $this->queries++;

            return ['items' => $this->items, 'total' => $this->total];
        }

        public function findInbox(int $id, SubmissionAccessScope $scope): ?array
        {
            foreach ($this->items as $item) {
                if ((int) $item['id'] === $id) {
                    return $item;
                }
            }

            return null;
        }
    };
}

function queryPolicy(?SubmissionAccessScope $scope): SubmissionAccessPolicy
{
    return new class($scope) implements SubmissionAccessPolicy {
        public function __construct(private ?SubmissionAccessScope $scope)
        {
        }

        public function scopeFor(int $actorId): ?SubmissionAccessScope
        {
            return $this->scope;
        }
    };
}

it('normalizes inbox filters and excludes test submissions by default', function () {
    $query = SubmissionInboxQuery::from([
        'search' => '  sam@example.com  ',
        'flow' => '42',
        'status' => 'in_progress',
        'owner' => 'user:7',
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-04',
        'page' => 2,
        'per_page' => 250,
    ]);

    expect($query->search)->toBe('sam@example.com')
        ->and($query->flowId)->toBe(42)
        ->and($query->status)->toBe('in_progress')
        ->and($query->owner)->toBe('user:7')
        ->and($query->dateFrom)->toBe('2026-07-01')
        ->and($query->dateTo)->toBe('2026-07-04')
        ->and($query->page)->toBe(2)
        ->and($query->perPage)->toBe(100)
        ->and($query->includeTest)->toBeFalse();
});

it('passes a record scope to the reader without leaking inaccessible counts', function () {
    $scope = new SubmissionAccessScope(actorId: 7, manageAll: false, teamKeys: ['sales'], roleKeys: ['editor']);
    $reader = queryReader([
        ['id' => 4, 'owner_type' => 'team', 'owner_key' => 'sales', 'is_test' => false],
    ], 1);
    $service = new SubmissionQueryService($reader, queryPolicy($scope));

    $page = $service->query(7, SubmissionInboxQuery::from([]));

    expect($page['total'])->toBe(1)
        ->and($page['items'])->toHaveCount(1)
        ->and($page['items'][0]['id'])->toBe(4)
        ->and($reader->queries)->toBe(1);
});

it('denies query and detail before touching storage when the actor has no scope', function () {
    $reader = queryReader([['id' => 9]], 1);
    $service = new SubmissionQueryService($reader, queryPolicy(null));

    expect(fn () => $service->query(23, SubmissionInboxQuery::from([])))
        ->toThrow(DomainException::class, 'not allowed')
        ->and(fn () => $service->detail(23, 9))
        ->toThrow(DomainException::class, 'not allowed')
        ->and($reader->queries)->toBe(0);
});

it('rejects unsupported status filters instead of widening the result set', function () {
    expect(fn () => SubmissionInboxQuery::from(['status' => 'deleted']))
        ->toThrow(InvalidArgumentException::class, 'status');
});
