<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Reset;

defined('ABSPATH') || exit;

/**
 * An ordered list of reset actions. Knows whether it is destructive (contains a DB wipe)
 * and can render a human summary for the dry-run preview and the post-run report. Pure.
 */
final class ResetPlan
{
    /**
     * @param list<ResetAction> $actions
     */
    public function __construct(public readonly array $actions = [])
    {
    }

    public function isEmpty(): bool
    {
        return $this->actions === [];
    }

    public function isDestructive(): bool
    {
        foreach ($this->actions as $action) {
            if ($action->kind === ResetAction::DB_WIPE) {
                return true;
            }
        }

        return false;
    }

    public function summary(): string
    {
        if ($this->actions === []) {
            return 'Nothing to reset.';
        }

        return implode("\n", array_map(
            static fn (ResetAction $action): string => '- ' . $action->label,
            $this->actions,
        ));
    }
}
