<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Repositories;

defined('ABSPATH') || exit;

use Corex\Database\QueryBuilder;
use Corex\Database\QueryExecutor;
use Corex\Fields\FieldDriver;
use Corex\Models\Model;
use RuntimeException;

/**
 * Abstract post-backed repository. The only layer that calls WordPress data
 * functions (spec FR-004): reads via get_post (+ field driver for declared
 * fields), writes via wp_insert_post/wp_update_post/wp_delete_post. Subclasses
 * declare their Model with model().
 */
abstract class PostRepository implements RepositoryInterface
{
    /**
     * Input attribute => WP post field.
     */
    private const CORE_MAP = [
        'title'   => 'post_title',
        'content' => 'post_content',
        'status'  => 'post_status',
        'slug'    => 'post_name',
        'parent'  => 'post_parent',
    ];

    public function __construct(
        protected readonly FieldDriver $fields,
        protected readonly Hydrator $hydrator,
        protected readonly QueryExecutor $executor,
        protected readonly int $cap = 500,
    ) {
    }

    /**
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    public function query(): QueryBuilder
    {
        return new QueryBuilder($this->model(), $this->executor, $this->cap);
    }

    public function find(int $id): ?Model
    {
        $post = get_post($id);
        $model = $this->model();

        if ($post === null || ($post->post_type ?? null) !== $model::postType()) {
            return null;
        }

        return $this->hydrator->fromPost($model, $post);
    }

    public function create(array $attributes): Model
    {
        $postId = (int) wp_insert_post($this->coreArgs($attributes) + ['post_type' => ($this->model())::postType()]);

        $this->writeFields($postId, $attributes);

        return $this->findOrFail($postId);
    }

    public function update(int $id, array $attributes): Model
    {
        wp_update_post(['ID' => $id] + $this->coreArgs($attributes));

        $this->writeFields($id, $attributes);

        return $this->findOrFail($id);
    }

    public function delete(int $id): bool
    {
        return (bool) wp_delete_post($id, true);
    }

    private function findOrFail(int $id): Model
    {
        $model = $this->find($id);

        if ($model === null) {
            throw new RuntimeException(sprintf('%s could not load entity #%d after write.', static::class, $id));
        }

        return $model;
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    private function coreArgs(array $attributes): array
    {
        $args = [];

        foreach (self::CORE_MAP as $input => $postField) {
            if (array_key_exists($input, $attributes)) {
                $args[$postField] = $attributes[$input];
            }
        }

        return $args;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function writeFields(int $postId, array $attributes): void
    {
        foreach (($this->model())::fields() as $name => $key) {
            if (array_key_exists($name, $attributes)) {
                $this->fields->set($postId, $key, $attributes[$name]);
            }
        }
    }
}
