<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Site;

defined('ABSPATH') || exit;

/**
 * Where make:site learns which framework release it is generating against (spec 102, FR-006).
 * Behind an interface so the scaffolder never runs git itself and stays constructible with no
 * repository around it.
 */
interface FrameworkBaselineSource
{
    public function current(): FrameworkBaseline;
}
