<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Cache\Status;

defined('ABSPATH') || exit;

/**
 * One caching layer, as reported to an operator (spec 078, FR-016).
 *
 * Every field answers a question somebody actually asks when a site is serving something stale:
 * what is this, is it on, who provides it, can CoreX do anything about it, is clearing it safe, and
 * when did you last look.
 *
 * `manageable` and `safeToClear` are separate deliberately. CoreX can flush the object cache and it
 * is not safe to; CoreX cannot purge a CDN and doing so would be perfectly safe. Collapsing them
 * into one flag would produce a button that is either missing when it should be there or dangerous
 * when it looks routine.
 */
final class CacheLayer
{
    /**
     * @param string           $key          Stable identifier, for actions and tests.
     * @param string           $name         What an operator calls it.
     * @param string           $purpose      What it does, in one plain sentence.
     * @param CacheLayerState  $state        What it is actually doing.
     * @param string           $provider     What is behind it, or '' when nothing is.
     * @param bool             $manageable   Whether CoreX can act on it at all.
     * @param bool             $safeToClear  Whether clearing it is a routine act.
     * @param string           $detail       The nuance a state alone cannot carry.
     * @param string           $checkedAt    Canonical timestamp of this check (spec 076 formats it).
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $purpose,
        public readonly CacheLayerState $state,
        public readonly string $provider,
        public readonly bool $manageable,
        public readonly bool $safeToClear,
        public readonly string $detail = '',
        public readonly string $checkedAt = '',
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'purpose' => $this->purpose,
            'state' => $this->state->value,
            'state_label' => $this->state->label(),
            'provider' => $this->provider,
            'manageable' => $this->manageable,
            'safe_to_clear' => $this->safeToClear,
            'detail' => $this->detail,
            'checked_at' => $this->checkedAt,
        ];
    }
}
