<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Corex\Forms\Submission\FlowTestService;

/**
 * Typed use cases exposed by the Flow REST boundary.
 */
final readonly class FlowControllerServices
{
    public function __construct(
        public FlowService $flows,
        public FlowTestService $tests,
    ) {
    }
}
