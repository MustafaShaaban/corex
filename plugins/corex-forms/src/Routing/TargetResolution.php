<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

/**
 * One target's answer: who it assigns, or why it assigns nobody.
 */
final readonly class TargetResolution
{
    private function __construct(
        public ?Assignee $assignee,
        public string $reason,
    ) {
    }

    public static function found(Assignee $assignee): self
    {
        return new self($assignee, '');
    }

    public static function failed(string $reason): self
    {
        return new self(null, $reason);
    }
}
