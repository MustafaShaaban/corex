<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Reset;

defined('ABSPATH') || exit;

/**
 * The pure decision that makes the destructive reset fail-closed: a soft reset is always
 * permitted, but a full (DB-wipe) reset is permitted ONLY when the operator passed the
 * typed safeguard (`ResetRequest::confirmed`). The command consults this before ever
 * reaching the executor's wipe (spec 025 FR-005, FR-009).
 */
final class ResetGate
{
    public function permits(ResetRequest $request): bool
    {
        if (! $request->isFull()) {
            return true;
        }

        return $request->confirmed;
    }
}
