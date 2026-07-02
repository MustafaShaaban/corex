<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

/**
 * Pure view model for the Data Models catalog (spec 063, Phase 3). It presents the REAL registered
 * CoreX data models (the `DataRegistry` sources) — each model's schema (columns) and live record count —
 * so an operator can see what data exists without the raw record explorer. It never invents a model,
 * column, or count; the corex-config boundary passes the real registry data in. Record management +
 * export live on the real Data explorer / export handler; a write-path import and a pending-migration
 * tracker do not exist in the data layer, so those are honestly deferred by the boundary, never faked.
 * WordPress-free, so it is unit-testable.
 */
final class DataModelsCatalog
{
    /**
     * @param list<array{key:string,label:string,columns:list<array{id:string,label:string}>,total:int}> $models
     *
     * @return array{
     *   models:list<array{key:string,label:string,columns:list<array{id:string,label:string}>,columnCount:int,total:int}>,
     *   count:int,
     *   totalRecords:int,
     *   isEmpty:bool
     * }
     */
    public function catalog(array $models): array
    {
        $totalRecords = 0;
        $shaped       = [];

        foreach ($models as $model) {
            $total         = max(0, $model['total']);
            $totalRecords += $total;
            $shaped[]      = [
                'key'         => $model['key'],
                'label'       => $model['label'],
                'columns'     => $model['columns'],
                'columnCount' => count($model['columns']),
                'total'       => $total,
            ];
        }

        return [
            'models'       => $shaped,
            'count'        => count($shaped),
            'totalRecords' => $totalRecords,
            'isEmpty'      => $shaped === [],
        ];
    }
}
