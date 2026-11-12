<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

/**
 * Where a refusal outside the CoreX screens themselves is recorded (spec 112, FR-031): the Data
 * screen refusing a model that belongs to another screen. Lets the Data layer record one without
 * depending on the Access screen's classes.
 */
interface AccessRefusalRecorder
{
    /**
     * @param string $kind    What was refused, e.g. `data` for a Data screen model.
     * @param string $section Which one: a model's key for `data`.
     */
    public function refused(int $userId, string $kind, string $section): void;
}
