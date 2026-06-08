<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks\Connectors;

defined('ABSPATH') || exit;

use Corex\Repositories\RepositoryInterface;

/**
 * A connector backed by a Corex Repository (the only layer that touches the data
 * source). Resolves a bound field from the entity identified by the binding args,
 * escaped on output and empty-safe — a missing record/field yields '' rather than
 * an error (spec FR-012, FR-013).
 */
abstract class RepositoryConnector implements Connector
{
    public function __construct(protected readonly RepositoryInterface $repository)
    {
    }

    public function value(string $field, array $args): mixed
    {
        $id = (int) ($args['id'] ?? 0);

        if ($id <= 0) {
            return '';
        }

        $model = $this->repository->find($id);

        if ($model === null) {
            return '';
        }

        $value = $model->get($field);

        return $value === null ? '' : esc_html((string) $value);
    }
}
