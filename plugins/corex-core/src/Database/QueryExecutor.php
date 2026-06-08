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
    public function __construct(
        private readonly Hydrator $hydrator,
        private readonly int $maxResults = 500,
    ) {
    }

    /**
     * The configured cap a QueryBuilder applies to an unbounded query (FR-015).
     */
    public function maxResults(): int
    {
        return $this->maxResults;
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

        foreach ($relations as $relation) {
            $models = $this->eagerLoad($modelClass, $relation, $models);
        }

        return new Collection($models);
    }

    /**
     * Distinct related post ids referenced by a belongs-to foreign key across the
     * given models (empty/zero keys are skipped). Pure — unit-testable.
     *
     * @param list<Model> $models
     *
     * @return list<int>
     */
    public static function collectRelatedIds(array $models, string $foreignKey): array
    {
        $ids = [];

        foreach ($models as $model) {
            $relatedId = (int) $model->get($foreignKey, 0);

            if ($relatedId > 0) {
                $ids[$relatedId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param class-string<Model> $modelClass
     * @param list<Model>         $models
     *
     * @return list<Model>
     */
    private function eagerLoad(string $modelClass, string $relation, array $models): array
    {
        $definition = $modelClass::relations()[$relation] ?? null;

        if ($definition === null || ($definition['type'] ?? null) !== 'belongsTo') {
            return $models;
        }

        $foreignKey   = $definition['foreignKey'];
        $relatedClass = $definition['model'];
        $relatedById  = $this->fetchRelated($relatedClass, self::collectRelatedIds($models, $foreignKey));

        return array_map(
            fn (Model $model): Model => $model->withAttribute(
                $relation,
                $relatedById[(int) $model->get($foreignKey, 0)] ?? null
            ),
            $models
        );
    }

    /**
     * One batched query for the related entities (no N+1).
     *
     * @param class-string<Model> $relatedClass
     * @param list<int>           $ids
     *
     * @return array<int, Model>
     */
    private function fetchRelated(string $relatedClass, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $query = new WP_Query([
            'post_type'      => $relatedClass::postType(),
            'post__in'       => $ids,
            'posts_per_page' => count($ids),
            'orderby'        => 'post__in',
            'no_found_rows'  => true,
        ]);

        $byId = [];

        foreach ($query->posts as $post) {
            $byId[(int) $post->ID] = $this->hydrator->fromPost($relatedClass, $post);
        }

        return $byId;
    }
}
