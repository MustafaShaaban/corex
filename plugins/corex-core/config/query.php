<?php

/**
 * QueryBuilder defaults. `max` caps an unbounded "fetch all" so a query is never
 * executed uncapped (spec FR-015). Override via the Config engine (`query.max`).
 *
 * @package Corex
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    'max' => 500,
];
