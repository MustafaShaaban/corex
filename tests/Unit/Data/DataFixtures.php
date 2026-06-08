<?php

/**
 * Shared fixtures for the data-layer unit tests. Required directly (several
 * classes in one file) and ignored by Pest as a non-test file.
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

namespace Corex\Tests\Fixtures\Data;

use Corex\Database\QueryExecutor;
use Corex\Fields\FieldDriver;
use Corex\Models\Model;
use Corex\Repositories\Hydrator;
use Corex\Repositories\PostRepository;

/** In-memory field driver for repository tests (real collaborator, no WP). */
final class FakeFieldDriver implements FieldDriver
{
    /** @var array<int, array<string, mixed>> */
    public array $store = [];

    public function get(int $entityId, string $key, mixed $default = null): mixed
    {
        return $this->store[$entityId][$key] ?? $default;
    }

    public function set(int $entityId, string $key, mixed $value): void
    {
        $this->store[$entityId][$key] = $value;
    }
}

final class JobRepository extends PostRepository
{
    public static function make(FakeFieldDriver $fields): self
    {
        $hydrator = new Hydrator($fields);

        return new self($fields, $hydrator, new QueryExecutor($hydrator), 500);
    }

    protected function model(): string
    {
        return Job::class;
    }
}

final class Company extends Model
{
    public static function postType(): string
    {
        return 'company';
    }

    public static function fields(): array
    {
        return [];
    }
}

final class Job extends Model
{
    public static function postType(): string
    {
        return 'job';
    }

    public static function fields(): array
    {
        return ['salary' => 'job_salary'];
    }

    public static function casts(): array
    {
        return [
            'salary'  => 'int',
            'active'  => 'bool',
            'created' => \DateTimeImmutable::class,
        ];
    }

    public static function relations(): array
    {
        return ['company' => ['type' => 'belongsTo', 'model' => Company::class, 'foreignKey' => 'company_id']];
    }
}
