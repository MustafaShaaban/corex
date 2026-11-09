<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Chain;

defined('ABSPATH') || exit;

/**
 * Asks the model services in the chain's order until one answers (spec 129, User Story 4).
 *
 * A service that fails is skipped for as long as its failure says ({@see Failure::waitSeconds()}), so
 * the next question goes straight to the next one. The whole search stops after {@see BUDGET_SECONDS}:
 * shared hosting ends a request at 30 seconds, and a visitor should not wait that long anyway.
 */
final class ChatRouter
{
    private const CALL_TIMEOUT = 10;
    private const BUDGET_SECONDS = 22;

    /** Below this many seconds left, a call would be cut before a service could answer. */
    private const MIN_CALL_SECONDS = 4;

    /**
     * @param \Closure(): int $clock seconds, for the time budget
     */
    public function __construct(
        private readonly ModelClient $client,
        private readonly Cooldowns $cooldowns,
        private readonly \Closure $clock,
    ) {
    }

    /**
     * @param list<Provider>                              $chain
     * @param list<array{role: string, content: string}> $messages the system message first
     */
    public function answer(array $chain, array $messages): ?string
    {
        $started = ($this->clock)();
        foreach ($chain as $provider) {
            if ($this->cooldowns->isCooling($provider->id)) {
                continue;
            }

            $left = self::BUDGET_SECONDS - (($this->clock)() - $started);
            if ($left < self::MIN_CALL_SECONDS) {
                break;
            }

            $result = $this->client->ask($provider, $messages, min(self::CALL_TIMEOUT, $left));
            if (is_string($result)) {
                // A rest that ran out is still on record until now: answering ends it.
                $this->cooldowns->clear($provider->id);

                return $result;
            }

            $this->cooldowns->cool($provider->id, $result);
        }

        return null;
    }
}
