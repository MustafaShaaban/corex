<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Corex\Forms\Success\SuccessStateRegistry;

/**
 * Typed runtime behavior registries projected together by the extensions route.
 */
final readonly class FlowBehaviorRegistries
{
    public function __construct(
        public FlowActionRegistry $actions,
        public EmailVariableRegistry $emailVariables,
        public SuccessStateRegistry $successStates,
    ) {
    }
}
