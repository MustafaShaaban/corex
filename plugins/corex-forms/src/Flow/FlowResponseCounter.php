<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

/**
 * How many responses a flow has stored: marked tests and the trash not counted (spec 122, DECISIONS #477).
 */
interface FlowResponseCounter
{
    public function responses(int $flowId): int;
}
