<?php

/**
 * Fixtures for the block-render tests. Required directly; ignored by Pest.
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

namespace Corex\Tests\Fixtures\Blocks;

use Corex\Blocks\BlockRenderer;
use Corex\Blocks\Connectors\RepositoryConnector;
use Corex\Database\QueryBuilder;
use Corex\Models\Model;
use Corex\Repositories\RepositoryInterface;
use BadMethodCallException;
use RuntimeException;

final class FakeEntity extends Model
{
    public static function postType(): string
    {
        return 'fake';
    }

    public static function fields(): array
    {
        return [];
    }
}

final class FakeRepository implements RepositoryInterface
{
    public function __construct(private readonly ?Model $model)
    {
    }

    public function find(int $id): ?Model
    {
        return $this->model;
    }

    public function query(): QueryBuilder
    {
        throw new BadMethodCallException();
    }

    public function create(array $attributes): Model
    {
        throw new BadMethodCallException();
    }

    public function update(int $id, array $attributes): Model
    {
        throw new BadMethodCallException();
    }

    public function delete(int $id): bool
    {
        return true;
    }
}

final class TestConnector extends RepositoryConnector
{
    public function name(): string
    {
        return 'corex/test';
    }
}

final class FakeRenderer implements BlockRenderer
{
    public function render(array $attributes, string $content, object $block): string
    {
        return 'rendered: ' . ($attributes['x'] ?? '');
    }
}

final class ThrowingRenderer implements BlockRenderer
{
    public function render(array $attributes, string $content, object $block): string
    {
        throw new RuntimeException('render boom');
    }
}
