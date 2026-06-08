<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database;

defined('ABSPATH') || exit;

use Corex\Models\Model;
use Corex\Repositories\Hydrator;
use WP_Query;

/**
 * The only place that instantiates `WP_Query`. Runs the args the QueryBuilder
 * built, hydrates each post into a Model, and returns a Collection. Eager loading
 * of belongs-to relations is added by US4.
 */
final class QueryExecutor
{
    public function __construct(private readonly Hydrator $hydrator)
    {
    }

    /**
     * @param array<string, mixed>  $args
     * @param class-string<Model>   $modelClass
     * @param list<string>          $relations
     */
    public function run(array $args, string $modelClass, array $relations = []): Collection
    {
        $query = new WP_Query($args);

        $models = array_map(
            fn (object $post): Model => $this->hydrator->fromPost($modelClass, $post),
            $query->posts
        );

        return new Collection($models);
    }
}
