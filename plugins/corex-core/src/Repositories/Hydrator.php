<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Repositories;

defined('ABSPATH') || exit;

use Corex\Fields\FieldDriver;
use Corex\Models\Model;

/**
 * Builds a Model from a post object plus its declared fields (read through the
 * field driver). Shared by the Repository and the QueryExecutor so hydration is
 * defined once.
 */
final class Hydrator
{
    public function __construct(private readonly FieldDriver $fields)
    {
    }

    /**
     * @param class-string<Model> $modelClass
     * @param object               $post A WP_Post (or any object exposing its public fields).
     */
    public function fromPost(string $modelClass, object $post): Model
    {
        $id = (int) ($post->ID ?? 0);

        $attributes = [
            'id'      => $id,
            'title'   => $post->post_title ?? '',
            'content' => $post->post_content ?? '',
            'status'  => $post->post_status ?? '',
            'slug'    => $post->post_name ?? '',
            'parent'  => (int) ($post->post_parent ?? 0),
            'date'    => $post->post_date ?? '',
        ];

        foreach ($modelClass::fields() as $name => $key) {
            $attributes[$name] = $this->fields->get($id, $key);
        }

        return new $modelClass($attributes);
    }
}
