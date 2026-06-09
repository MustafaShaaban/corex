<?php

/**
 * Integration test: the custom-table repository on real ./wp (spec 011 US2: FR-002, FR-003,
 * FR-004, FR-005, SC-003/4). Creates a real table, exercises typed CRUD + where, drops it.
 *
 * @package Corex\Tests\Integration\Data
 */

declare(strict_types=1);

use Corex\Database\Casts\Caster;
use Corex\Database\Schema\Migrator;
use Corex\Database\Schema\Table;
use Corex\Repositories\TableRepository;

final class TestItemRepository extends TableRepository
{
    protected function table(): string
    {
        return 'test_items';
    }

    /**
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return ['active' => 'bool', 'tags' => 'json', 'score' => 'decimal'];
    }
}

beforeEach(function () {
    $this->migrator = new Migrator();
    $this->repo     = new TestItemRepository(new Caster(), $this->migrator);

    $this->migrator->create(
        (new Table('test_items'))
            ->id()->string('name')->boolean('active')->text('tags')->decimal('score')->timestamps()
    );
});

afterEach(function () {
    $this->migrator->drop('test_items');
});

it('creates the table and round-trips typed values on insert + find', function () {
    expect($this->migrator->exists('test_items'))->toBeTrue();

    $id  = $this->repo->insert(['name' => 'Alpha', 'active' => true, 'tags' => ['a', 'b'], 'score' => 9.5]);
    $row = $this->repo->find($id);

    expect($id)->toBeGreaterThan(0)
        ->and($row['name'])->toBe('Alpha')
        ->and($row['active'])->toBeTrue()
        ->and($row['tags'])->toBe(['a', 'b'])
        ->and($row['score'])->toBe(9.5);
});

it('updates, queries by column, and deletes', function () {
    $id = $this->repo->insert(['name' => 'Beta', 'active' => false, 'tags' => [], 'score' => 1.0]);

    $this->repo->update($id, ['active' => true]);
    expect($this->repo->find($id)['active'])->toBeTrue();

    $found = $this->repo->where('name', 'Beta');
    expect($found)->toHaveCount(1)->and($found[0]['name'])->toBe('Beta');

    expect($this->repo->delete($id))->toBeTrue()
        ->and($this->repo->find($id))->toBeNull();
});

it('is non-fatal for a missing id and an empty query', function () {
    expect($this->repo->find(999999))->toBeNull()
        ->and($this->repo->where('name', 'nothing'))->toBe([]);
});
