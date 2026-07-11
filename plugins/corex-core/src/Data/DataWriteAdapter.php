<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Data;

defined('ABSPATH') || exit;

use Corex\Operations\OperationResult;

/**
 * Optional persistence boundary for sources that explicitly declare write support.
 */
interface DataWriteAdapter
{
    /** @param array<string,mixed> $values */
    public function create(array $values): OperationResult;

    /** @param list<int|string> $recordIds @param array<string,mixed> $values */
    public function update(array $recordIds, array $values): OperationResult;

    /** @param list<int|string> $recordIds */
    public function delete(array $recordIds): OperationResult;
}
